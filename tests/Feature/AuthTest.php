<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\Agent;
use App\Models\Area;
use App\Models\Household;
use App\Models\User;
use App\Models\WasteCategory;
use Database\Seeders\EcoCycleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The front door.
 *
 * Every other test reaches for `actingAs`, which is the right shortcut once you
 * are past the gate — but it means the sign-in form itself, the throttle and the
 * four-step registration are the one path nothing exercises. This covers them.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EcoCycleSeeder::class);
        RateLimiter::clear('login:' . strtolower('manager@ecocycle.ng') . '|127.0.0.1');
    }

    // ------------------------------------------------------------- signing in

    #[Test]
    public function each_role_signs_in_and_lands_on_its_own_surface(): void
    {
        $cases = [
            [User::whereHas('household')->firstOrFail(), '/app'],
            [User::whereHas('agent')->firstOrFail(), '/agent'],
            [User::where('role', UserRole::Admin->value)->firstOrFail(), '/console'],
            [User::where('role', UserRole::Staff->value)->firstOrFail(), '/console'],
        ];

        foreach ($cases as [$user, $home]) {
            Auth::logout();

            Livewire::test(Login::class)
                ->set('email', $user->email)
                ->set('password', 'password')
                ->call('login')
                ->assertHasNoErrors()
                ->assertRedirect($home);

            $this->assertTrue(Auth::check(), "{$user->email} did not end up authenticated");
            $this->assertSame($user->id, Auth::id());
        }
    }

    #[Test]
    public function a_wrong_password_is_refused_without_saying_which_half_was_wrong(): void
    {
        $user = User::whereHas('household')->firstOrFail();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'not-the-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function an_unknown_address_is_refused_the_same_way(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'nobody@ecocycle.test')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function the_form_validates_before_it_touches_the_database(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'not-an-email')
            ->set('password', '')
            ->call('login')
            ->assertHasErrors(['email', 'password']);
    }

    #[Test]
    public function repeated_failures_are_throttled(): void
    {
        $user = User::whereHas('household')->firstOrFail();
        $key = 'login:' . strtolower($user->email) . '|127.0.0.1';
        RateLimiter::clear($key);

        $component = Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong');

        for ($i = 0; $i < 5; $i++) {
            $component->call('login')->assertHasErrors('email');
        }

        // The sixth attempt is refused by the throttle, not by the credentials —
        // and it stays refused even when the password is finally correct.
        $component->set('password', 'password')->call('login')->assertHasErrors('email');

        $this->assertGuest();
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));

        RateLimiter::clear($key);
    }

    #[Test]
    public function a_successful_sign_in_clears_the_throttle(): void
    {
        $user = User::whereHas('household')->firstOrFail();
        $key = 'login:' . strtolower($user->email) . '|127.0.0.1';
        RateLimiter::clear($key);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertSame(1, RateLimiter::attempts($key));

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertSame(0, RateLimiter::attempts($key));
    }

    // ---------------------------------------------------------- registration

    #[Test]
    public function a_household_can_register_through_all_four_steps(): void
    {
        $before = Household::count();
        $area = Area::firstOrFail();

        $component = Livewire::test(Register::class)
            ->set('role', 'household')
            ->call('next')                              // 1 -> 2
            ->set('name', 'Ngozi Balogun')
            ->set('email', 'ngozi.balogun@example.test')
            ->set('phone', '08031234567')
            ->set('password', 'a-real-password')
            ->call('next')                              // 2 -> 3
            ->set('size', 5)
            ->set('frequency', 'Weekly')
            ->set('category', 'organic')
            ->set('area_id', $area->id)
            ->set('address', '12 Allen Avenue, Ikeja')
            ->call('setCoords', 6.6018, 3.3515)
            ->call('next')                              // 3 -> 4, creates the account
            ->call('finish')
            ->assertHasNoErrors();

        $this->assertSame($before + 1, Household::count());

        $user = User::where('email', 'ngozi.balogun@example.test')->firstOrFail();
        $this->assertSame(UserRole::Household, $user->role);
        $this->assertNotNull($user->household);
        $this->assertSame($area->id, $user->household->area_id);
        $this->assertEqualsWithDelta(6.6018, (float) $user->household->lat, 0.0001);

        // A new household owes nothing and has sent nothing.
        $this->assertSame(0.0, $user->household->balance());
        $this->assertSame(0, $user->household->batches()->count());

        $component->assertSet('createdCode', $user->household->code);
    }

    #[Test]
    public function an_agent_can_register_and_gets_an_agent_record(): void
    {
        $before = Agent::count();
        $area = Area::firstOrFail();

        Livewire::test(Register::class)
            ->set('role', 'agent')
            ->call('next')
            ->set('name', 'Musa Danjuma')
            ->set('email', 'musa.danjuma@example.test')
            ->set('phone', '08037654321')
            ->set('password', 'a-real-password')
            // Vehicle, plate and the compliance acknowledgement all live on the
            // details step, so they are validated on the way off it.
            ->set('vehicle', 'Motorcycle')
            ->set('capacity', 90)
            ->set('plate', 'lag-771-ab')
            ->set('compliance', true)
            ->call('next')
            ->set('area_id', $area->id)
            ->set('address', 'Agege')
            ->call('setCoords', 6.6152, 3.3250)
            ->call('next')
            ->call('finish')
            ->assertHasNoErrors();

        $this->assertSame($before + 1, Agent::count());

        $user = User::where('email', 'musa.danjuma@example.test')->firstOrFail();
        $this->assertSame(UserRole::Agent, $user->role);
        $this->assertSame('Motorcycle', $user->agent->vehicle_type);
        $this->assertSame(90, $user->agent->capacity_kg);

        // The weighbridge identifies a vehicle by its plate, so it is captured
        // at sign-up and normalised rather than left for someone to fill in.
        $this->assertSame('LAG-771-AB', $user->agent->plate);

        // KYC is a human step, so a fresh agent is unverified by default.
        $this->assertNull($user->agent->kyc_verified_at);

        // But the four papers read as awaiting review rather than missing —
        // the applicant was shown the list and said they could provide them.
        $statuses = array_column($user->agent->docs(), 'status');
        $this->assertSame(array_keys(Agent::DOCUMENT_TYPES), array_keys($user->agent->docs()));
        $this->assertSame(['pending', 'pending', 'pending', 'pending'], $statuses);
    }

    #[Test]
    public function an_agent_cannot_register_without_a_plate_or_the_compliance_acknowledgement(): void
    {
        Livewire::test(Register::class)
            ->set('role', 'agent')
            ->call('next')
            ->set('name', 'Musa Danjuma')
            ->set('email', 'no.plate@example.test')
            ->set('phone', '08037654321')
            ->set('password', 'a-real-password')
            ->call('next')
            ->assertHasErrors(['plate', 'compliance'])
            ->assertSet('step', 2);

        $this->assertDatabaseMissing('users', ['email' => 'no.plate@example.test']);
    }

    #[Test]
    public function a_household_registration_is_unaffected_by_the_agent_compliance_step(): void
    {
        $area = Area::firstOrFail();

        Livewire::test(Register::class)
            ->set('role', 'household')
            ->call('next')
            ->set('name', 'Adaeze Nwosu')
            ->set('email', 'adaeze.nwosu@example.test')
            ->set('phone', '08031112222')
            ->set('password', 'a-real-password')
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('step', 3);
    }

    #[Test]
    public function registration_refuses_an_address_already_in_use(): void
    {
        $taken = User::whereHas('household')->firstOrFail();

        Livewire::test(Register::class)
            ->set('role', 'household')
            ->call('next')
            ->set('name', 'Someone Else')
            ->set('email', $taken->email)
            ->set('phone', '08030000000')
            ->set('password', 'a-real-password')
            ->call('next')
            ->assertHasErrors('email');
    }

    #[Test]
    public function registration_will_not_advance_past_an_incomplete_step(): void
    {
        Livewire::test(Register::class)
            ->set('role', 'household')
            ->call('next')
            ->set('name', '')
            ->set('email', 'not-an-email')
            ->call('next')
            ->assertHasErrors(['name', 'email'])
            ->assertSet('step', 2);
    }

    #[Test]
    public function no_account_is_created_until_the_address_step_validates(): void
    {
        $before = Household::count();

        Livewire::test(Register::class)
            ->set('role', 'household')
            ->call('next')
            ->set('name', 'Halfway Through')
            ->set('email', 'halfway@example.test')
            ->set('phone', '08039999999')
            ->set('password', 'a-real-password')
            ->call('next')
            ->set('address', '')
            ->call('next')
            ->assertHasErrors('address')
            ->assertSet('step', 3);

        $this->assertSame($before, Household::count());
        $this->assertDatabaseMissing('users', ['email' => 'halfway@example.test']);
        $this->assertGuest();
    }

    #[Test]
    public function finishing_out_of_order_returns_to_the_address_step(): void
    {
        // A stale tab or a back button should not fatal on a null user.
        Livewire::test(Register::class)
            ->call('finish')
            ->assertSet('step', 3)
            ->assertOk();
    }

    // ------------------------------------------------------- password reset

    #[Test]
    public function the_reset_screen_accepts_an_address_without_confirming_it_exists(): void
    {
        // Confirming would turn the form into an account-enumeration oracle.
        Livewire::test(ForgotPassword::class)
            ->set('email', 'nobody@ecocycle.test')
            ->call('send')
            ->assertHasNoErrors();
    }

    // -------------------------------------------------------------- sessions

    #[Test]
    public function signing_out_ends_the_session(): void
    {
        $user = User::whereHas('household')->firstOrFail();

        $this->actingAs($user)->get('/app')->assertOk();
        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->get('/app')->assertRedirect('/login');
    }

    #[Test]
    public function a_signed_in_user_is_kept_off_the_auth_screens(): void
    {
        $user = User::whereHas('household')->firstOrFail();

        foreach (['/login', '/register', '/forgot-password'] as $path) {
            $this->actingAs($user)->get($path)->assertRedirect();
        }
    }
}
