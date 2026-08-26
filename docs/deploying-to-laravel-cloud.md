# Deploying EcoCycle to Laravel Cloud

What this app needs in production, and how the queue works once you are off your
laptop.

---

## Why there is a queue at all

Notifications go out on `mail` **and** `database`. The database half must land
immediately — it is the in-app feed. The mail half must not block the request:
an inspector pressing **Verify** should not wait on an SMTP handshake before the
screen updates.

So `BatchVerified`, `BatchRejected`, `JobAccepted` and `BatchCollected` all
implement `ShouldQueue`. Locally that means:

```bash
php artisan queue:work        # in a second terminal
```

Without a worker running, verified batches still settle and the in-app feed
still updates — the emails simply sit in the `jobs` table until something
processes them. That is the correct failure: money is never blocked on mail.

---

## Setting the queue up on Laravel Cloud

Laravel Cloud does not use Supervisor or a Procfile. A worker is a **separate
compute resource** you add to the environment, alongside the web resource.

### 1 · Point the queue at a real driver

`database` works and needs nothing extra — the `jobs` table is already in your
migrations. It is the right choice at pilot volume (a few hundred emails a day).

```
QUEUE_CONNECTION=database
```

If you later add Redis from the Cloud dashboard, switch to `QUEUE_CONNECTION=redis`
and nothing else changes.

### 2 · Add the worker

In your environment on Laravel Cloud:

1. **Compute → Add resource → Worker**
2. Command:
   ```
   php artisan queue:work --queue=default --tries=3 --backoff=10 --max-time=3600
   ```
3. Size: the smallest tier. This app's jobs are "render an email and hand it to
   an SMTP server" — they are network-bound, not CPU-bound.
4. Set **Number of processes** to 1 to start. Watch the `jobs` table depth
   before adding more; one worker clears a pilot day's mail in seconds.

The flags matter:

| Flag | Why |
| --- | --- |
| `--tries=3` | A transient SMTP failure retries rather than landing in `failed_jobs` on the first blip |
| `--backoff=10` | Ten seconds between attempts, so a brief outage is not hammered |
| `--max-time=3600` | The process exits hourly and Cloud restarts it. Long-lived PHP workers accumulate memory and hold stale config; a scheduled restart is cheaper than debugging that |

### 3 · Deploy hook

Add this to your deploy commands so a worker never runs code older than the
release that just shipped:

```bash
php artisan migrate --force
php artisan queue:restart
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`queue:restart` tells running workers to finish their current job and exit;
Cloud brings them back on the new release. Without it, workers keep executing
the previous deploy's code until they happen to restart.

### 4 · Scheduler

There is nothing on a schedule in this app yet. When you add one — a nightly
integrity sweep, a weekly payout run — Laravel Cloud has a **Scheduler**
resource that runs `schedule:run` every minute. You do not add a cron entry
yourself.

---

## Environment variables to set on Cloud

Everything below is read from `.env` locally and from the environment's
variables panel on Cloud.

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app.laravel.cloud
APP_TIMEZONE=Africa/Lagos

# Cloud injects the database credentials when you attach a MySQL resource.
DB_CONNECTION=mysql

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database

# Mail — Resend in production
MAIL_MAILER=resend
RESEND_KEY=re_xxxxxxxxxxxx
# RESEND_API_KEY works too — config/services.php reads either name.
MAIL_FROM_ADDRESS=ops@your-verified-domain.com
MAIL_FROM_NAME=EcoCycle

# AI — both features degrade gracefully without this
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
OPENAI_VISION_MODEL=gpt-4o-mini

# The split. Must sum to 1.0 — SettlementService asserts it.
ECOCYCLE_HOUSEHOLD_SHARE=0.60
ECOCYCLE_AGENT_SHARE=0.40
ECOCYCLE_VARIANCE_TOLERANCE=5.0
```

**`APP_DEBUG=false` matters more than usual here.** The error pages otherwise
print the full stack trace, which on this app includes SQL containing household
addresses and coordinates.

---

## Storage

Pickup photographs are written to the `public` disk. On Cloud's ephemeral
filesystem those disappear on every deploy, so before going live:

1. Attach an S3-compatible bucket (Cloud offers one, or bring your own).
2. Set `FILESYSTEM_DISK=s3` plus the bucket credentials.

Nothing in the code changes — `Storage::disk('public')` in `Inspection` and
`Pickup` becomes `Storage::disk(config('filesystems.default'))` in one place if
you want it configurable, but the simplest move is to make `s3` the public disk.

Until you do that, photo classification still works in a single deploy and the
photos vanish on the next one. Worth knowing before a demo.

---

## Do not seed production

`migrate:fresh --seed` drops every table. The seeders also create 200 accounts
that all share the password `password`.

For a real deployment:

```bash
php artisan migrate --force        # never migrate:fresh
php artisan db:seed --class=EcoCycleSeeder --force   # reference data only
```

`EcoCycleSeeder` creates the areas, waste categories, facility and processing
lines — reference data a real deployment genuinely needs. It also creates the
demo accounts, so change those passwords immediately or strip the `households()`,
`agents()` and `batches()` calls from its `run()` before deploying.

`NetworkSeeder`, `ProcessingRunSeeder`, `PayoutAccountSeeder` and
`NotificationSeeder` are demo-only. Never run them anywhere real.

---

## After the first deploy

```bash
php artisan ecocycle:audit        # read-only, safe against production
php artisan ecocycle:mail-check   # confirms which transport is actually live
```

The audit checks that the ledger's net equals the sum of every party balance.
Run it after any deploy that touched settlement — if those two figures ever
disagree, stop and investigate before more money moves.
