{{--
    Payout account, shared by the household and agent profiles.

    Rendered inside a Livewire component, so the wire: bindings below resolve
    against whichever profile is hosting it — both expose the same property
    names and the same savePayout() action.
--}}
@props([
    'account', 'banks', 'cycles',
    // Blade components do not inherit the host component's scope, so the two
    // pieces of Livewire state this markup branches on are passed in.
    'showPayout' => false,
    'payoutAuto' => false,
    'anchor' => 'payout',
])

<section class="card" id="{{ $anchor }}">
    <div class="card-head">
        <h3>Payout account</h3>
        @if ($account['bank'])
            <span class="badge badge-green"><i data-icon="check" class="i i-sm"></i> On file</span>
        @else
            <span class="badge badge-amber"><i data-icon="alert" class="i i-sm"></i> Not set</span>
        @endif
    </div>

    <div class="card-body col g-14">
        @if ($account['bank'])
            <div class="row g-10">
                <span class="qa-ic v2"><i data-icon="wallet" class="i i-sm"></i></span>
                <span class="grow" style="min-width:0">
                    <div class="small strong truncate">{{ $account['bank'] }}</div>
                    <div class="tiny dim mono">{{ $account['account'] }}</div>
                </span>
                <button class="btn btn-sm btn-quiet" type="button" wire:click="openPayout">
                    <i data-icon="edit" class="i i-sm"></i> Change
                </button>
            </div>

            <dl>
                <div class="kv"><dt>Cycle</dt><dd>{{ $account['cycle'] }}</dd></div>
                <div class="kv"><dt>Automatic payout</dt>
                    <dd>{{ $account['auto'] ? 'On' : 'Off' }}</dd></div>
                @if ($account['auto'])
                    <div class="kv"><dt>Threshold</dt>
                        <dd class="figure-num">₦{{ number_format($account['threshold'], 2) }}</dd></div>
                @endif
            </dl>
        @else
            <div class="callout callout-warn">
                <i data-icon="alert" class="i i-sm"></i>
                <div>
                    <strong>No payout account on file.</strong>
                    Add one and your balance can be paid out.
                </div>
            </div>
            <button class="btn btn-primary btn-block" type="button" wire:click="openPayout">
                <i data-icon="plus" class="i i-sm"></i> Add a payout account
            </button>
        @endif

        <p class="tiny dim">
            Only the last four digits of the account number are stored. The pilot pays by hand
            from a bank file, so the full number is never kept here.
        </p>
    </div>
</section>

{{-- Editor --}}
@if ($showPayout)
    <div class="scrim" wire:click.self="$set('showPayout', false)">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="payout-title">
            <div class="card-head">
                <h3 id="payout-title">{{ $account['bank'] ? 'Change payout account' : 'Add payout account' }}</h3>
                <button class="btn btn-icon btn-quiet" type="button"
                        wire:click="$set('showPayout', false)" aria-label="Close">
                    <i data-icon="close" class="i i-sm"></i>
                </button>
            </div>

            <form wire:submit="savePayout">
                <div class="card-body col g-14">
                    <div class="field">
                        <label class="label" for="payout-bank">Bank</label>
                        <select class="select" id="payout-bank" wire:model="payoutBank">
                            <option value="">Choose a bank…</option>
                            @foreach ($banks as $b)
                                <option value="{{ $b }}">{{ $b }}</option>
                            @endforeach
                        </select>
                        @error('payoutBank') <span class="hint t-neg">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="label" for="payout-account">Account number</label>
                        <input class="input figure-num" id="payout-account" type="text"
                               inputmode="numeric" autocomplete="off" maxlength="32"
                               wire:model="payoutAccount"
                               placeholder="{{ $account['account'] ? 'Leave blank to keep ' . $account['account'] : '0123456789' }}">
                        <span class="hint">
                            {{ $account['account']
                                ? 'Currently ' . $account['account'] . '. Leave blank to keep it.'
                                : 'Ten digits. Only the last four are stored.' }}
                        </span>
                        @error('payoutAccount') <span class="hint t-neg">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="label" for="payout-cycle">Payout cycle</label>
                        <select class="select" id="payout-cycle" wire:model="payoutCycle">
                            @foreach ($cycles as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>
                        @error('payoutCycle') <span class="hint t-neg">{{ $message }}</span> @enderror
                    </div>

                    <label class="switch">
                        <input type="checkbox" wire:model.live="payoutAuto">
                        <span class="track"></span>
                        <span class="small muted">Pay out automatically once the balance passes a threshold</span>
                    </label>

                    @if ($payoutAuto)
                        <div class="field">
                            <label class="label" for="payout-threshold">Threshold (₦)</label>
                            <input class="input figure-num" id="payout-threshold" type="number"
                                   min="0" step="500" wire:model="payoutThreshold">
                            @error('payoutThreshold') <span class="hint t-neg">{{ $message }}</span> @enderror
                        </div>
                    @endif
                </div>

                <div class="card-foot row" style="justify-content:flex-end">
                    <button class="btn btn-ghost" type="button" wire:click="$set('showPayout', false)">Cancel</button>
                    <button class="btn btn-primary" type="submit" wire:loading.attr="disabled" wire:target="savePayout">
                        <span wire:loading.remove wire:target="savePayout">Save account</span>
                        <span wire:loading wire:target="savePayout">Saving…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
