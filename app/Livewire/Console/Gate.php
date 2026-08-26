<?php

namespace App\Livewire\Console;

use App\Enums\BatchStatus;
use App\Models\Agent;
use App\Models\Delivery;
use App\Models\Facility;
use App\Models\WasteBatch;
use App\Services\BatchCodeGenerator;
use App\Services\BatchStateMachine;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The weighbridge. One vehicle, one crossing, one net weight.
 *
 * This is the hand-off the rest of the system was already built around but had
 * no screen for: an agent leaves the last doorstep IN_TRANSIT and nothing moved
 * the load to RECEIVED, so the inspection queue never saw it.
 *
 * The rule enforced here is the same one the schema states: an agent arrives
 * with several batches on board and crosses the scale ONCE. The gate records a
 * single gross and tare; SettlementService::apportionDelivery() splits the net
 * across the batches in proportion to what was declared per batch. There is
 * deliberately one apportionment rule, because two would be a dispute.
 *
 * Nothing here values anything. The gate produces a weight; the inspection bay
 * decides whether that weight is worth paying for.
 */
#[Layout('layouts.console')]
class Gate extends Component
{
    /** The agent whose vehicle is on the plate. */
    public ?int $agentId = null;

    public string $grossKg = '';
    public string $tareKg = '';
    public string $weighbridge = 'Gate 1';

    public array $weighbridges = ['Gate 1', 'Gate 2'];

    public function mount(): void
    {
        $this->selectFirst();
    }

    private function selectFirst(): void
    {
        $this->select((int) $this->arrivals()->keys()->first());
    }

    public function select(?int $agentId): void
    {
        $this->agentId = $agentId ?: null;
        $this->reset(['grossKg', 'tareKg']);

        // Pre-fill the tare from the last crossing this vehicle made. The plate
        // does not change weight between trips, and re-typing it every time is
        // how a wrong tare gets entered.
        if ($this->agentId) {
            $this->tareKg = (string) (Delivery::where('agent_id', $this->agentId)
                ->whereNotNull('tare_kg')
                ->latest('weighed_at')
                ->value('tare_kg') ?? '');
        }
    }

    /**
     * Every agent with a load on the road, and what is on board.
     *
     * Keyed by agent id so the selected vehicle survives a re-render.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function arrivals(): \Illuminate\Support\Collection
    {
        return WasteBatch::where('status', BatchStatus::InTransit->value)
            ->with(['agent.user', 'household.user', 'category'])
            ->orderBy('in_transit_at')
            ->get()
            ->groupBy('agent_id')
            ->map(fn ($batches, $agentId) => [
                'agent'    => $batches->first()->agent,
                'batches'  => $batches,
                'count'    => $batches->count(),
                'declared' => round((float) $batches->sum('collected_kg'), 2),
                'since'    => $batches->min('in_transit_at'),
            ]);
    }

    /** @return \Illuminate\Support\Collection<int, WasteBatch> */
    public function getOnBoardProperty(): \Illuminate\Support\Collection
    {
        return $this->agentId
            ? collect($this->arrivals()[$this->agentId]['batches'] ?? [])
            : collect();
    }

    public function getAgentProperty(): ?Agent
    {
        return $this->agentId ? Agent::with('user')->find($this->agentId) : null;
    }

    public function getDeclaredKgProperty(): float
    {
        return round((float) $this->onBoard->sum('collected_kg'), 2);
    }

    public function getNetKgProperty(): ?float
    {
        if (! is_numeric($this->grossKg)) {
            return null;
        }

        return round((float) $this->grossKg - (float) ($this->tareKg ?: 0), 2);
    }

    /**
     * How the net would land on each batch, before anything is written.
     *
     * Runs the same proportional maths apportionDelivery() will, so the
     * weighbridge operator sees the split they are about to commit rather than
     * discovering it on the inspection screen.
     *
     * Sorted by id, because that is the order apportionDelivery() walks and the
     * last batch in that order absorbs the rounding remainder. Previewing in
     * arrival order would put the odd kilogram on a different batch than the one
     * that actually receives it -- a preview that lies by a hundredth is still a
     * preview that lies.
     *
     * @return array<int, array{batch: WasteBatch, kg: float, variance: ?float}>
     */
    public function getSplitProperty(): array
    {
        $net = $this->netKg;
        $batches = $this->onBoard->sortBy('id')->values();

        if ($net === null || $net <= 0 || $batches->isEmpty()) {
            return [];
        }

        $declaredTotal = (float) $batches->sum('collected_kg');
        $settlement = app(SettlementService::class);

        $running = 0.0;
        $last = $batches->count() - 1;
        $rows = [];

        foreach ($batches as $i => $batch) {
            if ($i === $last) {
                $kg = round($net - $running, 2);          // the last one absorbs rounding
            } else {
                $ratio = $declaredTotal > 0
                    ? ((float) $batch->collected_kg / $declaredTotal)
                    : (1 / $batches->count());
                $kg = round($net * $ratio, 2);
                $running += $kg;
            }

            $rows[] = [
                'batch'    => $batch,
                'kg'       => $kg,
                'variance' => $settlement->variance($batch->collected_kg, $kg),
            ];
        }

        return $rows;
    }

    /** Net against what the agent declared across the whole vehicle. */
    public function getVehicleVarianceProperty(): ?float
    {
        return app(SettlementService::class)->variance($this->declaredKg, $this->netKg);
    }

    /**
     * Stand-in for a serial feed off the weighbridge, matching the inspection
     * bay's own takeReading(). The control is not the cable -- it is that the
     * agent is not the one typing.
     */
    public function takeReading(): void
    {
        if (! $this->agentId || $this->declaredKg <= 0) {
            $this->dispatch('toast', message: 'No vehicle selected', kind: 'warn');

            return;
        }

        $tare = (float) ($this->tareKg ?: round(($this->agent?->capacity_kg ?? 200) * 0.35, 1));

        $this->tareKg = (string) $tare;
        $this->grossKg = (string) round(
            $tare + $this->declaredKg * (0.9 + (random_int(0, 120) / 1000)), 1
        );
    }

    /**
     * Commit the crossing.
     *
     * Order matters: the batches must belong to the delivery before the net can
     * be apportioned across them, so each one is moved to RECEIVED (carrying its
     * delivery_id and facility_id) and only then is the delivery apportioned.
     */
    public function weighIn(
        BatchCodeGenerator $codes,
        BatchStateMachine $machine,
        SettlementService $settlement,
    ): void {
        $this->validate([
            'grossKg' => 'required|numeric|min:0.1|max:20000',
            'tareKg'  => 'nullable|numeric|min:0|max:20000',
        ], [], ['grossKg' => 'gross weight', 'tareKg' => 'tare weight']);

        $batches = $this->onBoard;

        if ($batches->isEmpty()) {
            $this->dispatch('toast', message: 'Nothing on board for that vehicle', kind: 'warn');

            return;
        }

        if ($this->netKg === null || $this->netKg <= 0) {
            $this->dispatch('toast', message: 'Net weight must be above zero — check the tare', kind: 'warn');

            return;
        }

        $facility = Facility::first();

        $delivery = DB::transaction(function () use ($codes, $machine, $batches, $facility) {
            $delivery = Delivery::create([
                'code'        => $codes->deliveryCode(),
                'agent_id'    => $this->agentId,
                'facility_id' => $facility?->id,
                'gross_kg'    => (float) $this->grossKg,
                'tare_kg'     => (float) ($this->tareKg ?: 0),
                'net_kg'      => $this->netKg,
                'weighbridge' => $this->weighbridge,
                'weighed_at'  => now(),
                'weighed_by'  => auth()->id(),
                'status'      => 'weighed',
            ]);

            foreach ($batches as $batch) {
                $machine->transition($batch, BatchStatus::Received, auth()->user(), [
                    'delivery_id' => $delivery->id,
                    'facility_id' => $facility?->id,
                ], [
                    'delivery'    => $delivery->code,
                    'weighbridge' => $this->weighbridge,
                ]);
            }

            return $delivery;
        });

        // Apportion outside the write loop, once every batch is attached.
        $settlement->apportionDelivery($delivery->refresh());

        $this->dispatch('toast',
            message: sprintf('%s weighed · %d batches · %s kg net → inspection',
                $delivery->code, $batches->count(), number_format($this->netKg, 1)),
            kind: 'ok');

        $this->reset(['grossKg', 'tareKg']);
        $this->selectFirst();
    }

    public function render()
    {
        $arrivals = $this->arrivals();

        // A vehicle that was on the plate can be weighed in by another operator
        // between renders, so a stale selection falls back to the next one.
        if ($this->agentId && ! $arrivals->has($this->agentId)) {
            $this->selectFirst();
            $arrivals = $this->arrivals();
        }

        return view('livewire.console.gate', [
            'arrivals'    => $arrivals,
            'waitingKg'   => round((float) $arrivals->sum('declared'), 1),
            'waitingCount'=> (int) $arrivals->sum('count'),
            'today'       => Delivery::with('agent.user')->withCount('batches')
                                ->whereDate('weighed_at', today())
                                ->latest('weighed_at')->limit(12)->get(),
            'todayNetKg'  => round((float) Delivery::whereDate('weighed_at', today())->sum('net_kg'), 1),
            'awaiting'    => WasteBatch::awaitingInspection()->count(),
        ])->layoutData(['page' => 'gate', 'heading' => 'Weighbridge']);
    }
}
