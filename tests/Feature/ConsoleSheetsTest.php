<?php

namespace Tests\Feature;

use App\Livewire\Console\Agents;
use App\Livewire\Console\Batches;
use App\Livewire\Console\Households;
use App\Livewire\Console\Pipeline;
use App\Models\Agent;
use App\Models\Household;
use App\Models\User;
use App\Models\WasteBatch;
use Database\Seeders\EcoCycleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The console's record sheets.
 *
 * A page-render test never opens these — they are hidden until a row is clicked,
 * so a fatal inside one (a method that does not exist, a null relation) sails
 * past every other check and only shows up in front of the operator.
 */
class ConsoleSheetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EcoCycleSeeder::class);
    }

    private function staff(): User
    {
        return User::whereIn('role', ['admin', 'staff'])->firstOrFail();
    }

    #[Test]
    public function the_household_sheet_opens_and_closes(): void
    {
        $household = Household::whereHas('batches')->firstOrFail();

        Livewire::actingAs($this->staff())
            ->test(Households::class)
            ->call('show', $household->id)
            ->assertOk()
            ->assertSet('openId', $household->id)
            ->assertSee($household->code)
            ->assertSee('Recent batches')
            ->call('close')
            ->assertSet('openId', null)
            ->assertDontSee('Recent batches');
    }

    #[Test]
    public function the_household_sheet_survives_a_household_with_no_history(): void
    {
        $bare = Household::doesntHave('batches')->first();

        if (! $bare) {
            $this->markTestSkipped('every seeded household has history');
        }

        Livewire::actingAs($this->staff())
            ->test(Households::class)
            ->call('show', $bare->id)
            ->assertOk();
    }

    #[Test]
    public function the_agent_sheet_opens(): void
    {
        $agent = Agent::whereHas('batches')->firstOrFail();

        Livewire::actingAs($this->staff())
            ->test(Agents::class)
            ->call('show', $agent->id)
            ->assertOk()
            ->assertSet('openId', $agent->id)
            ->assertSee($agent->code)
            ->call('close')
            ->assertSet('openId', null);
    }

    #[Test]
    public function the_batch_sheet_opens_from_the_registry(): void
    {
        $batch = WasteBatch::whereNotNull('verified_kg')->firstOrFail();

        Livewire::actingAs($this->staff())
            ->test(Batches::class)
            ->call('show', $batch->id)
            ->assertOk()
            ->assertSet('openId', $batch->id)
            ->assertSee($batch->code)
            ->call('close')
            ->assertSet('openId', null);
    }

    #[Test]
    public function the_batch_sheet_opens_from_the_pipeline_board(): void
    {
        $batch = WasteBatch::firstOrFail();

        Livewire::actingAs($this->staff())
            ->test(Pipeline::class)
            ->call('show', $batch->id)
            ->assertOk()
            ->assertSet('openId', $batch->id)
            ->assertSee($batch->code);
    }

    #[Test]
    public function the_tag_lookup_finds_a_batch_and_refuses_an_unknown_code(): void
    {
        $batch = WasteBatch::firstOrFail();

        Livewire::actingAs($this->staff())
            ->test(Batches::class)
            ->set('scanCode', $batch->code)
            ->call('lookup')
            ->assertRedirect('/b/' . $batch->code);

        Livewire::actingAs($this->staff())
            ->test(Batches::class)
            ->set('scanCode', 'EC-W-9999-0000000')
            ->call('lookup')
            ->assertNoRedirect();
    }
}
