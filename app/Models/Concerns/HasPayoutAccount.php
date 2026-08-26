<?php

namespace App\Models\Concerns;

/**
 * Payout arrangements, shared by Household and Agent.
 *
 * Stored inside `preferences` rather than as columns because none of it is ever
 * queried across parties — the settlements screen reads the payouts table, not
 * these. The account number is stored already masked: the pilot pays by hand
 * from a bank file, so the full number has no business being in this database.
 */
trait HasPayoutAccount
{
    /** The banks a Lagos pilot actually pays into. */
    public const PAYOUT_BANKS = [
        'GTBank', 'Access Bank', 'Zenith Bank', 'First Bank', 'UBA',
        'Kuda', 'Opay', 'Moniepoint', 'PalmPay', 'Sterling Bank',
    ];

    public const PAYOUT_CYCLES = [
        'On request',
        'Weekly, every Monday',
        'Fortnightly',
        'Monthly, first working day',
    ];

    /**
     * @return array{bank: ?string, account: ?string, cycle: string, auto: bool, threshold: float}
     */
    public function payoutAccount(): array
    {
        $p = (array) ($this->preferences ?? []);

        return [
            'bank'      => $p['payout_bank'] ?? null,
            'account'   => $p['payout_account'] ?? null,
            'cycle'     => $p['payout_cycle'] ?? 'Weekly, every Monday',
            'auto'      => (bool) ($p['payout_auto'] ?? false),
            'threshold' => (float) ($p['payout_threshold'] ?? 10000),
        ];
    }

    public function hasPayoutAccount(): bool
    {
        return filled($this->payoutAccount()['bank']);
    }

    /**
     * Write a payout arrangement, merging over whatever is stored.
     *
     * The merge reads `preferences` back from the database rather than trusting
     * an array handed in from a Livewire component: the same blob carries the
     * notification switches, and a screen that round-trips the whole thing
     * through the browser is a screen that can be made to overwrite a bank
     * account by editing a payload.
     *
     * `$rawAccount` is masked on the way in and never stored in full.
     */
    public function savePayoutAccount(
        ?string $bank,
        ?string $rawAccount,
        string $cycle,
        bool $auto,
        float $threshold,
    ): void {
        $existing = $this->payoutAccount();

        // An untouched field keeps what is already on file, so re-saving the
        // form to flip `auto` does not blank the account number.
        $account = filled($rawAccount)
            ? self::maskAccount($rawAccount)
            : $existing['account'];

        $this->mergePreferences([
            'payout_bank'      => $bank ?: null,
            'payout_account'   => $bank ? $account : null,
            'payout_cycle'     => $cycle,
            'payout_auto'      => $auto,
            'payout_threshold' => round($threshold, 2),
        ]);
    }

    public function clearPayoutAccount(): void
    {
        $this->mergePreferences([
            'payout_bank' => null, 'payout_account' => null, 'payout_auto' => false,
        ]);
    }

    /**
     * Patch `preferences` from the stored value outwards.
     *
     * Every write to this column goes through here so that two screens touching
     * different keys cannot erase each other's work.
     *
     * @param  array<string, mixed>  $patch
     */
    public function mergePreferences(array $patch): void
    {
        $stored = (array) ($this->newQuery()->whereKey($this->getKey())->value('preferences') ?? []);

        if (is_string($stored)) {
            $stored = (array) json_decode($stored, true);
        }

        $this->update(['preferences' => array_replace($stored, $patch)]);
    }

    /**
     * Keep the last four digits, mask the rest.
     *
     * Nigerian NUBAN account numbers are ten digits; the last four are what a
     * person recognises as theirs and are useless on their own, which is the
     * whole point of storing only those.
     */
    public static function maskAccount(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (strlen($digits) < 4) {
            return str_repeat('•', max(4, strlen($digits)));
        }

        return '•••• •••• ' . substr($digits, -4);
    }
}
