# EcoCycle — platform

Laravel 13 · Livewire 4 · MySQL. The working backend behind the prototype in the
parent folder.

The design system, icons, charts and photography are carried over from the
prototype unchanged — `public/assets/` is a copy of it — so what you see here is
the same interface with a real database, real money and real state transitions
underneath.

---

## Run it

WAMP's MySQL and PHP are already what this was built against.

```bash
cd platform
cp .env.example .env          # already done; edit if your DB differs
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Then in a second terminal, so queued email actually sends:

```bash
php artisan queue:work
```

### Demo accounts

| Role | Email | Password |
| --- | --- | --- |
| Household | `nkechi.obiora@ecocycle.test` | `password` |
| Agent | `sunday.amadi@ecocycle.test` | `password` |
| Facility | `manager@ecocycle.ng` | `password` |

Every seeded household and agent follows the same pattern:
`firstname.lastname@ecocycle.test`.

---

## The four rules the code enforces

These are not conventions. They are enforced in one place each, so no screen can
work around them.

**1 · Three weights, kept apart.** `expected_kg` (predicted, plans only),
`collected_kg` (agent declares), `verified_kg` (facility scale). Separate
columns, separate meanings.

**2 · Money settles only on `verified_kg`.** `SettlementService` is the only
thing that values a batch, it refuses any batch that is not settleable, and it
computes from the server's own rate table — never from a figure the browser
sent.

**3 · The ledger is append-only.** There is no `wallet_balance` column anywhere.
A balance is `SUM(credit) - SUM(debit)` over `ledger_entries`, every write
carries an `idempotency_key`, and a mistake is corrected with a reversing entry
rather than an update.

**4 · State changes go through the machine.** `BatchStateMachine` validates every
transition against `BatchStatus::allowedNext()`, stamps its timestamp and writes
a row to `batch_events`. Nothing else touches `status`.

Verify all four at once:

```bash
php artisan ecocycle:verify
```

It moves a real batch through inspection, settles it, tries to settle it twice,
attempts an illegal transition, and has two agents race for the same job. Every
guarantee prints pass or fail.

---

## Layout

```
app/
├── Enums/            BatchStatus (the state machine), UserRole, LedgerDirection
├── Services/
│   ├── BatchStateMachine   guarded transitions + audit trail + atomic claim
│   ├── SettlementService   the 60/40, and delivery apportionment
│   ├── LedgerService       append-only money, idempotent payouts
│   ├── JobMatcher          ranking and route ordering (arithmetic, not ML)
│   ├── IntegrityService    variance flags and agent scoring
│   ├── OpenAiService       analytics + photo classification, degrades gracefully
│   └── QrService           real QR codes as inline SVG
├── Livewire/
│   ├── Auth/         Login, Register (4 steps, real geolocation), ForgotPassword
│   ├── Household/    Home, RequestPickup, Track, Wallet, Profile
│   ├── Agent/        Today, Jobs, Pickup (5 stages), RoutePlan, Earnings, Profile
│   └── Console/      Overview, Pipeline, Batches, Inspection, Processing,
│                     Households, Agents, Payments, Analytics, Integrity
└── Notifications/    BatchVerified, BatchRejected, JobAccepted, BatchCollected
```

### One decision worth reading the schema for

`deliveries` is a separate table from `waste_batches`. An agent arrives with six
batches on one vehicle and crosses the weighbridge **once**, so the net weight is
apportioned across the batches on that delivery in proportion to what was
declared per batch — `SettlementService::apportionDelivery()`. The prototype
assumed one batch, one weight; that assumption is corrected here.

---

## OpenAI

Add a key to `.env`:

```
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
```

Two features use it, and **both work without it**:

- **Analytics** (`/console/analytics`) — aggregates are computed in SQL first and
  handed to the model as a fixed set of figures, with an instruction to use only
  those numbers. Without a key you get the same figures phrased locally.
- **Photo classification** (agent pickup, stage 4) — estimates composition from
  the load photograph. Advisory only; the inspector's decision always wins and
  disagreements are stored on the batch.

The model never touches the database and is never asked to compute anything,
which is why an answer cannot invent a figure.

---

## Email

`MAIL_MAILER=log` by default, so mail lands in `storage/logs/laravel.log` and
nothing needs configuring to try it. Point it at Mailtrap in development or
Resend/Brevo in the pilot.

Notifications use `mail` **and** `database`, so the same event drives both the
email and the in-app feed.

```bash
php artisan ecocycle:mail-check   # queue one, then run queue:work
```

---

## What is deliberately simple

At pilot scale — 200 households, ~29 batches a day — these were the right calls:

| Not built | Why |
| --- | --- |
| Weighbridge serial integration | The inspector reads the scale and types it. Integrity comes from the agent *not* typing it, not from a cable. |
| Offline-first agent PWA | Ten collectors. A dropped form is recoverable; five weeks of sync architecture is not warranted yet. |
| Payment gateway | Ledger + CSV export + mark-paid. Add Paystack when volume justifies the reconciliation work. |
| WebSockets | `wire:poll` on the one screen that needs it. |
| ML models | Volume prediction is the mean of the last eight verified weights. One query, and explainable to a household. |

Each is a line item with a known cost, not an oversight.
