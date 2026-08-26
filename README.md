# EcoCycle

Laravel 13 · Livewire 4 · MySQL. The working platform behind the design prototype
in `../Ecocycle`.

The design system, icons, charts and photography are carried over from the
prototype unchanged — `public/assets/` is a copy of it — so this is the same
interface with a real database, real money and real state transitions
underneath. Every figure on every screen is a query; nothing is illustrated.

---

## Run it

```bash
composer install
cp .env.example .env          # already done; edit if your DB differs
php artisan key:generate
php artisan migrate:fresh --seed      # ~2 minutes: 90 days of history
php artisan serve
```

Then in a second terminal, so queued email actually sends:

```bash
php artisan queue:work
```

### Demo accounts

**Password is `password` for every account below.** Open <http://127.0.0.1:8000>.

#### Facility — `/console`

| Role | Email | What it shows |
| --- | --- | --- |
| Admin | `manager@ecocycle.ng` | Full console. Verify batches, action settlements, resolve integrity cases. **Demo from this one.** |
| Staff | `inspector@ecocycle.ng` | Same console without settlement actions — proves the role gate is real |
| Staff | `gate@ecocycle.ng` | Weighbridge and intake |
| Staff | `lines@ecocycle.ng` | Processing lines and runs |

#### Household — `/app`

| Email | State it demonstrates |
| --- | --- |
| `grace.oladele.103@ecocycle.test` | Heaviest earner — monthly earnings curve, payout method, working Withdraw |
| `chidi.okonkwo@ecocycle.test` | A collection in flight — live map, stage rail, three weights filling in |
| `grace.oyelaran@ecocycle.test` | A rejected batch — the reason, and the batch earning nothing |
| `uzo.adeleke.129@ecocycle.test` | A paused account that still has history and a balance |

#### Collection agent — `/agent`

| Email | State it demonstrates |
| --- | --- |
| `sunday.amadi@ecocycle.test` | Mid-round with a load on board — pickup resumes where it was left |
| `grace.adebayo.523@ecocycle.test` | Largest outstanding balance — payout schedule with a real amount waiting |
| `chiamaka.ogunleye.515@ecocycle.test` | Busiest collector, carrying open integrity cases |
| `gbenga.musa.516@ecocycle.test` | Lowest integrity score — declared weights running apart from the gate scale |

Every seeded household and agent follows `firstname.lastname@ecocycle.test`, so
any of the other ~190 accounts works the same way. Facility staff are on
`@ecocycle.ng`.

> Names and amounts are randomised on each `migrate:fresh --seed`. The four
> `@ecocycle.ng` accounts plus `chidi.okonkwo@` and `sunday.amadi@` are fixtures
> and never change. `docs/demo-accounts.md` has the same list with live figures.

Signing in as the wrong role does not 403 — you are redirected to the surface
that role owns. A household opening `/console` lands back on `/app`.

---

## The four rules the code enforces

These are not conventions. Each is enforced in one place, so no screen can work
around it.

**1 · Three weights, kept apart.** `expected_kg` (household predicts),
`collected_kg` (agent declares), `verified_kg` (facility scale). Separate
columns, separate meanings.

**2 · Money settles only on `verified_kg`.** `SettlementService` is the only
thing that values a batch, it refuses any batch that is not settleable, and it
computes from the server's own rate table — never from a figure the browser
sent. The household takes 60%; the agent takes the *remainder*, so rounding can
never create or lose a naira.

**3 · The ledger is append-only.** There is no `wallet_balance` column anywhere.
A balance is `SUM(credit) - SUM(debit)`, every write carries an
`idempotency_key`, and a mistake is corrected with a reversing entry rather than
an update.

**4 · State changes go through the machine.** `BatchStateMachine` validates every
transition against `BatchStatus::allowedNext()`, stamps its timestamp and writes
a row to `batch_events`. Nothing else touches `status`.

---

## Testing

Four layers, because each catches what the others cannot.

```bash
php artisan ecocycle:test     # 67 domain invariants, driven end to end
php artisan ecocycle:audit    # 20 invariants across every row already stored
php artisan ecocycle:pages    # renders all 32 pages, flags thin or broken ones
php artisan test              # 64 feature tests: auth, routes, gates, lifecycle
```

`ecocycle:test` moves a real batch through inspection, settles it, tries to
settle it twice, attempts an illegal transition, and has two agents race for the
same job. It runs in a rolled-back transaction, so it is safe against real data.

`ecocycle:audit` asks the harder question: do the rules hold across every row
*already* in the database, including rows written by code that has since
changed? The one that matters most is that the ledger's net equals the sum of
every party balance — if those ever disagree, the append-only guarantee has been
broken somewhere. It is read-only and safe against production.

`ecocycle:pages` exists because a render test is not enough: a view that returns
400 bytes because a section quietly stopped rendering still returns 200. It
asserts a plausible floor on every page and catches Blade directives that reached
the browser uncompiled.

The feature suite covers the sign-in form, the throttle and the four-step
registration directly — everything else reaches for `actingAs`, which would
otherwise leave the front door as the one path nothing exercises.

---

## Layout

```
app/
├── Enums/            BatchStatus (the state machine), UserRole, LedgerDirection
├── Services/
│   ├── BatchStateMachine   guarded transitions + audit trail + atomic claim
│   ├── SettlementService   the 60/40, and delivery apportionment
│   ├── LedgerService       append-only money, idempotent payouts
│   ├── ProcessingService   runs, yields, what came off the line
│   ├── JobMatcher          ranking and route ordering (arithmetic, not ML)
│   ├── IntegrityService    four detectors, variance flags, agent scoring
│   ├── InsightService      the standing insight cards, computed in SQL
│   ├── ForecastService     weekday-mean prediction, and its measured error
│   ├── ClaudeService       analytics phrasing + photo classification
│   └── QrService           real QR codes as inline SVG
├── Livewire/
│   ├── Auth/         Login, Register (4 steps, real geolocation), ForgotPassword
│   ├── Household/    Home, RequestPickup, Track, History, Wallet, Profile
│   ├── Agent/        Today, Jobs, Pickup (5 stages), RoutePlan, Earnings, Profile
│   ├── Console/      Overview, Pipeline, Batches, Inspection, Processing,
│   │                 Households, Agents, Payments, Analytics, Integrity
│   └── Notifications shared feed for both phone apps
├── Notifications/    mail + database, so one event drives both
└── Support/Geo       lat/lng projected onto the schematic map canvas
```

### Two decisions worth reading the schema for

**`deliveries` is a separate table from `waste_batches`.** An agent arrives with
six batches on one vehicle and crosses the weighbridge **once**, so the net
weight is apportioned across the batches on that delivery in proportion to what
was declared per batch — `SettlementService::apportionDelivery()`. The prototype
assumed one batch, one weight; that assumption is corrected here.

**`processing_runs` consumes batches.** A run is one line, one day, many
batches, and `waste_batches.processing_run_id` is what keeps the output
attributable back to the households that contributed it. Yield is applied once
against the run's whole input rather than per batch, which is how a plant
actually reports.

---

## Claude

Add a key to `.env`:

```
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-opus-5
```

Two features use it, and **both work without it**:

- **Analytics** (`/console/analytics`) — aggregates are computed in SQL first and
  handed to the model as a fixed set of figures, with an instruction to use only
  those numbers. Without a key you get the same figures phrased locally.
- **Photo classification** (inspection bay, and agent pickup stage 4) — estimates
  composition from the load photograph, on demand rather than on page load.
  Advisory only; the inspector's decision always wins and disagreements are
  stored on the batch.

The model never touches the database and is never asked to compute anything,
which is why an answer cannot invent a figure.

The other six "intelligence modules" on the analytics screen are arithmetic and
say so: volume prediction is a weekday mean that reports its *measured* error,
job matching ranks on distance and capacity, route ordering is nearest-next.

---

## Email

Point it at a Mailtrap inbox:

```
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=<from your inbox>
MAIL_PASSWORD=<from your inbox>
```

If the credentials are blank the app falls back to the log driver rather than
throwing, so mail lands in `storage/logs/laravel.log` and nothing needs
configuring to try it.

```bash
php artisan ecocycle:mail-check          # queue one and report where it went
php artisan ecocycle:mail-check --now    # send synchronously
php artisan ecocycle:mail-check --to you@example.com
```

It prints the resolved driver first, so the quiet failure — `.env` names
Mailtrap, credentials are blank, everything silently logs — is visible rather
than assumed.

Notifications use `mail` **and** `database`, so the same event drives both the
email and the in-app feed.

---

## What is deliberately simple

At pilot scale — 162 households, 38 agents, roughly eight batches a day — these
were the right calls. Each is a line item with a known cost, not an oversight.

| Not built | Why |
| --- | --- |
| Weighbridge serial integration | The inspector reads the scale and types it. Integrity comes from the agent *not* typing it, not from a cable. |
| Offline-first agent PWA | Thirty collectors. A dropped form is recoverable; five weeks of sync architecture is not warranted yet. |
| Payment gateway | Ledger + CSV export + mark-paid. Add Paystack when volume justifies the reconciliation work. |
| WebSockets | `wire:poll` on the one screen that needs it. |
| Real map tiles | The schematic answers "where is the load relative to the plant". A tile map costs an API key to say the same thing. |
| Agent document upload | Compliance papers are verified by hand during the pilot and the outcome recorded. |
| Four of the eight integrity signals | Declared on the console and greyed out. Shipping detectors that never fire would look better and tell the operator less. |
