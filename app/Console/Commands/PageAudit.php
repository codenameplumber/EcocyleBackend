<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WasteBatch;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Renders every page in the application against the live database and reports
 * what came back.
 *
 * `ecocycle:test` proves the domain rules; the feature suite proves the routes
 * respond. This proves the pages are actually *full* — a view that renders a
 * 400-byte shell because a section quietly fell out returns 200 and passes both
 * of those, and only shows up here.
 */
class PageAudit extends Command
{
    protected $signature = 'ecocycle:pages {--slow=800 : flag any page slower than this, in ms}';

    protected $description = 'Render every page against real data and report size, timing and errors';

    /** @var list<array{0: string, 1: ?string, 2: int}> path, role, minimum plausible bytes */
    private const PAGES = [
        ['/', null, 20000],
        ['/about', null, 12000],
        ['/facilities', null, 12000],
        ['/location', null, 12000],
        ['/login', null, 4000],
        ['/register', null, 6000],
        ['/forgot-password', null, 2500],

        ['/app', 'household', 6000],
        ['/app/request', 'household', 6000],
        ['/app/track', 'household', 3000],
        ['/app/history', 'household', 5000],
        ['/app/wallet', 'household', 5000],
        ['/app/notifications', 'household', 3000],
        ['/app/profile', 'household', 4000],

        ['/agent', 'agent', 5000],
        ['/agent/jobs', 'agent', 3000],
        ['/agent/route', 'agent', 3000],
        ['/agent/earnings', 'agent', 4000],
        ['/agent/notifications', 'agent', 3000],
        ['/agent/profile', 'agent', 4000],

        ['/console', 'staff', 12000],
        ['/console/pipeline', 'staff', 5000],
        ['/console/batches', 'staff', 7000],
        ['/console/gate', 'staff', 5000],
        ['/console/inspection', 'staff', 8000],
        ['/console/processing', 'staff', 7000],
        ['/console/households', 'staff', 7000],
        ['/console/agents', 'staff', 7000],
        ['/console/payments', 'staff', 6000],
        ['/console/analytics', 'staff', 10000],
        ['/console/integrity', 'staff', 9000],
    ];

    private int $failures = 0;

    public function handle(): int
    {
        $users = [
            'household' => User::whereHas('household')->first(),
            'agent' => User::whereHas('agent')->first(),
            'staff' => User::where('role', 'admin')->orWhere('role', 'staff')->first(),
        ];

        $pages = self::PAGES;

        // The two parameterised pages, resolved against real records.
        if ($batch = WasteBatch::whereNotNull('agent_id')->first()) {
            $pages[] = ['/b/' . $batch->code, null, 3000];
            $owner = User::whereHas('agent', fn ($q) => $q->whereKey($batch->agent_id))->first();
            if ($owner) {
                $users['batch_agent'] = $owner;
                $pages[] = ['/agent/pickup/' . $batch->id, 'batch_agent', 6000];
            }
        }

        $this->newLine();
        $this->line(sprintf('  %-30s %-10s %8s %8s  %s', 'PAGE', 'AS', 'STATUS', 'BYTES', ''));
        $this->line('  ' . str_repeat('-', 76));

        foreach ($pages as [$path, $role, $floor]) {
            $this->render($path, $role ? $users[$role] ?? null : null, $role, $floor);
        }

        $this->newLine();

        if ($this->failures === 0) {
            $this->info('  ' . count($pages) . ' pages, all rendered in full.');

            return self::SUCCESS;
        }

        $this->error('  ' . $this->failures . ' of ' . count($pages) . ' pages need attention.');

        return self::FAILURE;
    }

    private function render(string $path, ?User $user, ?string $role, int $floor): void
    {
        $user ? Auth::login($user) : Auth::logout();

        $started = microtime(true);

        try {
            $request = Request::create($path, 'GET');
            $response = app()->handle($request);
            $status = $response->getStatusCode();
            $body = (string) $response->getContent();
        } catch (\Throwable $e) {
            $this->failures++;
            $this->line(sprintf('  %-30s %-10s %8s %8s  <fg=red>%s</>',
                $path, $role ?? 'guest', 'THROW', '—', mb_substr($e->getMessage(), 0, 60)));

            return;
        }

        $ms = (int) round((microtime(true) - $started) * 1000);
        $bytes = strlen($body);
        $notes = [];

        if ($status !== 200) {
            $notes[] = 'status ' . $status;
        } elseif ($bytes < $floor) {
            // A 200 that is far too small means a section stopped rendering.
            $notes[] = 'thin: ' . number_format($bytes) . ' < ' . number_format($floor);
        }

        if ($ms > (int) $this->option('slow')) {
            $notes[] = $ms . 'ms';
        }

        // Blade swallows nothing, but an unresolved directive left in the output
        // is a silent template bug.
        if (str_contains($body, '@if') || str_contains($body, '{{ $')) {
            $notes[] = 'unrendered directive';
        }

        $bad = (bool) array_filter($notes, fn ($n) => ! str_ends_with($n, 'ms'));
        if ($bad) {
            $this->failures++;
        }

        $this->line(sprintf('  %-30s %-10s %8s %8s  <fg=%s>%s</>',
            $path,
            $role ?? 'guest',
            $status,
            number_format($bytes),
            $bad ? 'red' : ($notes ? 'yellow' : 'green'),
            $notes ? implode(' · ', $notes) : 'ok'));
    }
}
