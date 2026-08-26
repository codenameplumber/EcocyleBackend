<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WasteBatch;
use Database\Seeders\EcoCycleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every route, for every role, plus the gates between them.
 *
 * `ecocycle:test` proves the domain rules hold. This proves the HTTP layer in
 * front of them does: that each page renders against seeded data, that a
 * household cannot open the console, and that a guest cannot open anything.
 *
 * A page that 500s because a view references a column that moved is exactly the
 * failure this catches and a service-level test cannot.
 */
class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EcoCycleSeeder::class);
    }

    // ------------------------------------------------------------ public site

    public static function publicRoutes(): array
    {
        return [
            'home' => ['/'],
            'about' => ['/about'],
            'facilities' => ['/facilities'],
            'location' => ['/location'],
            'login' => ['/login'],
            'register' => ['/register'],
            'forgot password' => ['/forgot-password'],
        ];
    }

    #[Test]
    #[DataProvider('publicRoutes')]
    public function public_pages_render(string $path): void
    {
        $this->get($path)->assertOk();
    }

    #[Test]
    public function the_public_batch_trace_resolves_by_code(): void
    {
        $batch = WasteBatch::firstOrFail();

        $this->get('/b/' . $batch->code)
            ->assertOk()
            ->assertSee($batch->code);
    }

    #[Test]
    public function an_unknown_batch_code_is_not_found(): void
    {
        $this->get('/b/EC-W-9999-0000000')->assertNotFound();
    }

    // ----------------------------------------------------------- household

    public static function householdRoutes(): array
    {
        return [
            'home' => ['/app'],
            'request' => ['/app/request'],
            'track' => ['/app/track'],
            'history' => ['/app/history'],
            'wallet' => ['/app/wallet'],
            'notifications' => ['/app/notifications'],
            'profile' => ['/app/profile'],
        ];
    }

    #[Test]
    #[DataProvider('householdRoutes')]
    public function household_pages_render(string $path): void
    {
        $this->actingAs($this->household())->get($path)->assertOk();
    }

    // --------------------------------------------------------------- agent

    public static function agentRoutes(): array
    {
        return [
            'today' => ['/agent'],
            'jobs' => ['/agent/jobs'],
            'route' => ['/agent/route'],
            'earnings' => ['/agent/earnings'],
            'notifications' => ['/agent/notifications'],
            'profile' => ['/agent/profile'],
        ];
    }

    #[Test]
    #[DataProvider('agentRoutes')]
    public function agent_pages_render(string $path): void
    {
        $this->actingAs($this->agent())->get($path)->assertOk();
    }

    #[Test]
    public function the_agent_pickup_screen_renders_for_a_batch_they_hold(): void
    {
        $agent = $this->agent();
        $batch = WasteBatch::where('agent_id', $agent->agent->id)->firstOrFail();

        $this->actingAs($agent)->get('/agent/pickup/' . $batch->id)->assertOk();
    }

    // ------------------------------------------------------------- console

    public static function consoleRoutes(): array
    {
        return [
            'overview' => ['/console'],
            'pipeline' => ['/console/pipeline'],
            'batches' => ['/console/batches'],
            'inspection' => ['/console/inspection'],
            'processing' => ['/console/processing'],
            'households' => ['/console/households'],
            'agents' => ['/console/agents'],
            'payments' => ['/console/payments'],
            'analytics' => ['/console/analytics'],
            'integrity' => ['/console/integrity'],
        ];
    }

    #[Test]
    #[DataProvider('consoleRoutes')]
    public function console_pages_render(string $path): void
    {
        $this->actingAs($this->staff())->get($path)->assertOk();
    }

    // ---------------------------------------------------------------- gates

    #[Test]
    public function guests_are_sent_to_login(): void
    {
        foreach (['/app', '/agent', '/console'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    #[Test]
    public function a_household_cannot_open_the_agent_app_or_the_console(): void
    {
        $me = $this->household();

        $this->actingAs($me)->get('/agent')->assertRedirect('/app');
        $this->actingAs($me)->get('/console')->assertRedirect('/app');
    }

    #[Test]
    public function an_agent_cannot_open_the_household_app_or_the_console(): void
    {
        $me = $this->agent();

        $this->actingAs($me)->get('/app')->assertRedirect('/agent');
        $this->actingAs($me)->get('/console')->assertRedirect('/agent');
    }

    #[Test]
    public function staff_cannot_open_either_phone_app(): void
    {
        $me = $this->staff();

        $this->actingAs($me)->get('/app')->assertRedirect('/console');
        $this->actingAs($me)->get('/agent')->assertRedirect('/console');
    }

    #[Test]
    public function a_signed_in_user_is_kept_off_the_auth_screens(): void
    {
        $this->actingAs($this->household())->get('/login')->assertRedirect();
    }

    #[Test]
    public function logging_out_returns_to_the_sign_in_form(): void
    {
        $this->actingAs($this->household())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    // -------------------------------------------------------------- helpers

    private function household(): User
    {
        return User::whereHas('household')->firstOrFail();
    }

    private function agent(): User
    {
        return User::whereHas('agent')->whereHas('agent.batches')->firstOrFail();
    }

    private function staff(): User
    {
        return User::where('role', 'staff')->firstOrFail();
    }
}
