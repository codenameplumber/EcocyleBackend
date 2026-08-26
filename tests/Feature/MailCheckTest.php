<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WasteBatch;
use App\Notifications\BatchVerified;
use Database\Seeders\EcoCycleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The tool that proves mail works has to work itself.
 *
 * `--to` builds an unsaved stand-in User so a check can be sent anywhere without
 * touching a row. That stand-in was created with only a name and an email, so
 * its role was null and the mail template's url($notifiable->role->home()) threw
 * — the command reported "Send failed" for every address it was ever given.
 */
class MailCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EcoCycleSeeder::class);
        $this->settleABatch();
    }

    /** The command needs one settled batch to have something to report on. */
    private function settleABatch(): WasteBatch
    {
        $batch = WasteBatch::whereNotNull('value_naira')->first();

        if (! $batch) {
            $batch = WasteBatch::whereHas('household.user')->firstOrFail();
            $batch->forceFill([
                'verified_kg'     => 18.7,
                'rate_applied'    => 42,
                'value_naira'     => 785.40,
                'household_share' => 471.24,
                'agent_share'     => 314.16,
            ])->save();
        }

        return $batch;
    }

    #[Test]
    public function the_mail_check_can_send_to_an_arbitrary_address(): void
    {
        Notification::fake();

        $this->artisan('ecocycle:mail-check', ['--to' => 'delivered@resend.dev', '--now' => true])
            ->assertSuccessful();
    }

    #[Test]
    public function the_stand_in_recipient_carries_a_role_so_the_mail_template_renders(): void
    {
        $real = User::whereHas('household')->firstOrFail();

        $standIn = new User([
            'name' => 'Mail check', 'email' => 'delivered@resend.dev', 'role' => $real->role,
        ]);
        $standIn->exists = false;

        // Rendering is exactly where the null role blew up, so render it for real.
        $mail = (new BatchVerified($this->settleABatch()))->toMail($standIn);

        $this->assertNotNull($standIn->role);
        $this->assertStringContainsString($standIn->role->home(), $mail->actionUrl);
    }

    #[Test]
    public function the_mail_check_reports_a_transport_failure_rather_than_claiming_success(): void
    {
        // Stands in for the real failure: a from-domain Resend has not verified.
        config(['mail.default' => 'a-transport-that-does-not-exist']);

        $this->artisan('ecocycle:mail-check', ['--to' => 'delivered@resend.dev', '--now' => true])
            ->assertFailed();
    }
}
