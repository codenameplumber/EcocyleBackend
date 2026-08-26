# Demo accounts

Password is `password` for every seeded account. Start the app with
`php artisan serve` and open <http://127.0.0.1:8000>.

Figures below were read from the database after
`php artisan migrate:fresh --seed`. The network seeder randomises within
realistic bounds, so **names and amounts differ on each reseed** — but every
state described here will be present, and the four fixture accounts
(`manager@`, `inspector@`, `chidi.okonkwo@`, `sunday.amadi@`) are stable.

To regenerate this list against your own database, the queries are in
`database/seeders/` — or just look at `/console/households` and
`/console/agents`, which sort on exactly these figures.

---

## Facility — `/console`

Ten screens, desktop layout.

| Name | Email | What it shows |
| --- | --- | --- |
| Adaeze Okafor | `manager@ecocycle.ng` | **Admin.** The account to demo from — verifies batches at the gate, actions settlements, resolves integrity cases. |
| Chukwuemeka Nwosu | `inspector@ecocycle.ng` | **Staff.** Same console without settlement actions — useful for showing the role gate is real rather than a label. |
| Halima Yusuf | `gate@ecocycle.ng` | **Staff.** Weighbridge and intake. |
| Tobi Adegboye | `lines@ecocycle.ng` | **Staff.** Processing lines and runs. |

---

## Household — `/app`

Seven screens, phone layout.

| Name | Email | State it demonstrates |
| --- | --- | --- |
| Grace Oladele | `grace.oladele.103@ecocycle.test` | Heaviest earner. Wallet shows the monthly earnings curve, payout method and a working Withdraw. |
| Chidi Okonkwo | `chidi.okonkwo@ecocycle.test` | A collection in flight. Track shows the stage rail, the assigned agent and the three weights filling in. |
| Grace Oyelaran | `grace.oyelaran@ecocycle.test` | A batch rejected at inspection — the reason, and the batch earning nothing. |
| Uzo Adeleke | `uzo.adeleke.129@ecocycle.test` | A paused account that still has history and a balance. |

---

## Collection agent — `/agent`

Seven screens, phone layout.

| Name | Email | State it demonstrates |
| --- | --- | --- |
| Sunday Amadi | `sunday.amadi@ecocycle.test` | Mid-round with a load on board. Pickup resumes at the stage it was left on. |
| Grace Adebayo | `grace.adebayo.523@ecocycle.test` | Largest outstanding balance — payout schedule with a real amount waiting. |
| Chiamaka Ogunleye | `chiamaka.ogunleye.515@ecocycle.test` | Busiest collector, carrying open integrity cases. Cross-reference against `/console/integrity`. |
| Gbenga Musa | `gbenga.musa.516@ecocycle.test` | Lowest integrity score on the network — declared weights running apart from the gate scale. |

---

## Notes

Every seeded household and agent follows `firstname.lastname@ecocycle.test`, so
any of the ~190 other accounts works the same way. Facility staff are on
`@ecocycle.ng`.

Signing in as the wrong role does not 403. You are redirected to the surface that
role owns — a household opening `/console` lands back on `/app`.

**These are demonstration accounts on a seeded database.** A single shared
password is deliberate: it makes the demo easy and makes the database unsuitable
for anything but a demo. Do not seed this data anywhere reachable from the
internet, and do not reuse these addresses for real people.
