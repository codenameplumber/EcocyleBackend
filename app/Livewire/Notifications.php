<?php

namespace App\Livewire;

use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The notification feed, shared by the household and agent apps.
 *
 * One component, because the two feeds differ only in which filter chips are
 * offered — the rows themselves are whatever `notifications` holds for the
 * signed-in user. Every row was written by a real event: a state transition, a
 * settlement or a payout. Nothing is generated for display.
 */
#[Layout('layouts.phone')]
class Notifications extends Component
{
    use WithPagination;

    public string $filter = 'all';

    /**
     * Which notification kinds belong to which chip. A kind absent from every
     * group still shows under "All", so a new notification type can never go
     * invisible just because this map was not updated.
     *
     * @var array<string, list<string>>
     */
    private const GROUPS = [
        'collections' => ['batch_collected', 'batch_verified', 'batch_rejected', 'batch_received'],
        'jobs'        => ['job_accepted', 'job_offered', 'batch_collected', 'batch_received'],
        'payments'    => ['payout_paid', 'payout_opened', 'batch_verified', 'referral_bonus'],
    ];

    public function setFilter(string $f): void
    {
        $this->filter = $f;
        $this->resetPage();
    }

    public function markAllRead(): void
    {
        $n = auth()->user()->unreadNotifications()->count();
        auth()->user()->unreadNotifications->markAsRead();

        $this->dispatch('toast', message: $n ? "{$n} marked as read" : 'Nothing unread', kind: 'ok');
    }

    public function open(string $id): void
    {
        $note = auth()->user()->notifications()->whereKey($id)->first();
        $note?->markAsRead();

        $code = $note?->data['batch'] ?? null;
        if ($code) {
            $this->redirect('/b/' . $code, navigate: true);
        }
    }

    public function render()
    {
        $user = auth()->user();
        $agent = $user->isAgent();

        $query = $user->notifications()->latest();

        if ($this->filter !== 'all' && isset(self::GROUPS[$this->filter])) {
            // JSON containment would need a generated column to index; at feed
            // depth a LIKE on the kind key is the cheaper honest answer.
            $query->where(function ($q) {
                foreach (self::GROUPS[$this->filter] as $kind) {
                    $q->orWhere('data', 'like', '%"kind":"' . $kind . '"%');
                }
            });
        }

        return view('livewire.notifications', [
            'notes'  => $query->paginate(20),
            'unread' => $user->unreadNotifications()->count(),
            'chips'  => $agent
                ? ['all' => 'All', 'jobs' => 'Jobs', 'payments' => 'Payments']
                : ['all' => 'All', 'collections' => 'Collections', 'payments' => 'Payments'],
        ])->layoutData([
            'app'     => $agent ? 'agent' : 'household',
            'page'    => $agent ? 'today' : 'home',
            'heading' => 'Notifications',
            'back'    => $agent ? route('agent') : route('app'),
        ]);
    }

    /** The accent used on a row's icon chip. Purely presentational. */
    public function toneFor(?string $kind): string
    {
        return match ($kind) {
            'batch_collected', 'job_offered' => 'v2',
            'batch_rejected', 'payout_paid', 'payout_opened' => 'v3',
            'volume_prediction', 'match_available' => 'v4',
            default => '',
        };
    }
}
