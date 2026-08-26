<x-layouts.site :title="'Location — EcoCycle'" :page="'location'" :description="'Where EcoCycle operates: twelve areas of Lagos served from a single processing facility in Ojota.'">
<!-- ── Page hero ────────────────────────────────────────────────────── -->
  <section class="page-hero">
    <div class="container">
      <div class="ed-split">
        <div>
          <span class="eyebrow">Location</span>
          <h1 class="display mt-14">Lagos, Lagos State.</h1>
          <p class="lede mt-20 measure">
            The network runs across twelve areas of Lagos, all of them within collection
            range of a single processing facility in Ojota. Coverage grows area by area,
            because route economics only work when households are dense enough to cluster.
          </p>
          <div class="row wrap g-12 mt-24">
            <a class="btn btn-primary" href="{{ route('register') }}">Register a household</a>
            <a class="btn btn-ghost" href="{{ route('facilities') }}">See the facility</a>
          </div>
        </div>
        <figure class="fig">
          <div class="media media-4x3 media-round media-scrim">
            <img src="{{ asset('assets/img/city-aerial.jpg') }}" alt="A residential street in Lagos" width="1400" height="800">
            <div class="media-cap"><div class="tiny">Agege, one of twelve areas served</div></div>
          </div>
        </figure>
      </div>
    </div>
  </section>

  <!-- ── Coverage ─────────────────────────────────────────────────────── -->
  <section class="section" style="background:var(--surface-2);border-block:1px solid var(--border)">
    <div class="container">
      <div class="ed-head">
        <div>
          <span class="eyebrow">Coverage</span>
          <p class="tiny dim mt-8">Twelve areas</p>
        </div>
        <div>
          <h2>Everything inside one collection radius</h2>
          <p class="lede mt-12 measure">
            Every household on the network is close enough to Ojota that a tricycle can
            reach the gate and return within a shift. That constraint is what keeps
            settlement fast and organic material from spoiling in transit.
          </p>
        </div>
      </div>

      <div class="grid mt-32" style="grid-template-columns:minmax(0,1.25fr) minmax(0,.75fr);gap:28px;align-items:start">
        <div class="card card-flat" style="overflow:hidden">
          <div class="card-head">
            <h3>Collection network</h3>
            <span class="badge badge-green"><span class="dot"></span>Live</span>
          </div>
          <x-map :pins="$pins" :height="420" :legend="false" />
          <div class="card-foot">
            <div class="row g-24 wrap tiny dim">
              <span class="row g-6"><span style="width:8px;height:8px;border-radius:50%;background:var(--primary);display:inline-block"></span>Households</span>
              <span class="row g-6"><span style="width:8px;height:8px;border-radius:50%;background:var(--info);display:inline-block"></span>Agents on shift</span>
              <span class="row g-6"><span style="width:8px;height:8px;border-radius:50%;background:var(--violet);display:inline-block"></span>Facility</span>
              <span class="grow"></span>
              <span>Lagos cluster · {{ $stats["areas"] }} areas</span>
            </div>
          </div>
        </div>

        <div class="col g-16">
          <div class="card card-flat card-pad">
            <span class="eyebrow">Facility</span>
            <h3 class="mt-8" style="font-size:17px">{{ $facility?->name ?? "EcoCycle Lagos Facility" }}</h3>
            <address class="small muted mt-8" style="font-style:normal;line-height:1.6">
              Plot 14, Ojota Industrial Layout<br>
              Ojota, Lagos<br>
              Lagos State, Nigeria
            </address>
            <div class="rule mt-14 mb-14"></div>
            <dl>
              <div class="kv"><dt>Intake</dt><dd>06:00 – 18:00, Mon–Sat</dd></div>
              <div class="kv"><dt>Office</dt><dd>08:00 – 17:00, Mon–Fri</dd></div>
              <div class="kv"><dt>Phone</dt><dd><a href="tel:+2348001234567">0800 123 4567</a></dd></div>
              <div class="kv"><dt>Email</dt><dd><a href="mailto:ops@ecocycle.ng">ops@ecocycle.ng</a></dd></div>
              <div class="kv"><dt>Coordinates</dt><dd class="mono">{{ number_format($facility?->lat ?? 6.5794, 5) }}, {{ number_format($facility?->lng ?? 3.3792, 5) }}</dd></div>
            </dl>
          </div>

          <div class="card card-flat card-pad">
            <div class="block-head"><h3>Getting here</h3></div>
            <p class="small muted">
              The gate is on the industrial layout side, not the main road. Agents should
              approach from the service road and queue at Gate 1 for inbound weighing.
              Outbound loads leave through Gate 3.
            </p>
            <button class="btn btn-ghost btn-block mt-14" data-toast="Directions open in a maps app in production">
              <i data-icon="nav" class="i i-sm"></i> Get directions
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Areas ────────────────────────────────────────────────────────── -->
  <section class="section">
    <div class="container">
      <div class="between wrap g-16">
        <div>
          <span class="eyebrow">Areas served</span>
          <h3 class="mt-6" style="font-size:17px">Collection volume by area, last 30 days</h3>
        </div>
        <a class="small strong" style="color:var(--primary)" href="/console/households">Open the household console →</a>
      </div>

      <div class="table-wrap mt-20">
        <table class="table">
          <thead><tr>
            <th>Area</th><th class="num">Households</th><th class="num">Volume, 30 days</th>
            <th class="num">Growth</th><th>Share of network</th><th>Status</th>
          </tr></thead>
          <tbody>
          @forelse ($areas as $a)
            <tr>
              <td class="strong">{{ $a['name'] }}</td>
              <td class="num">{{ number_format($a['households']) }}</td>
              <td class="num strong figure-num">{{ number_format($a['kg'], 1) }} kg</td>
              <td class="num">
                @if (abs($a['growth']) < 0.05)
                  <span class="tiny dim">—</span>
                @else
                  <span class="delta {{ $a['growth'] >= 0 ? 'up' : 'down' }}">
                    <i data-icon="{{ $a['growth'] >= 0 ? 'arrowUp' : 'arrowDn' }}" class="i i-sm"></i>{{ number_format(abs($a['growth']), 1) }}%
                  </span>
                @endif
              </td>
              <td style="width:180px">
                <div class="bar"><span style="width:{{ number_format($a['bar'], 1) }}%"></span></div>
                <div class="tiny dim mt-4">{{ number_format($a['share'], 1) }}% of network</div>
              </td>
              <td>
                @if ($a['kg'] <= 0)
                  <span class="badge"><span class="dot"></span>Opening</span>
                @elseif ($a['growth'] < 0)
                  <span class="badge badge-amber"><span class="dot"></span>Watch</span>
                @else
                  <span class="badge badge-green"><span class="dot"></span>Active</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6"><div class="empty"><span class="empty-icon"><i data-icon="mapPin" class="i i-lg"></i></span>
              <div class="strong">No areas on record</div></div></td></tr>
          @endforelse
        </tbody>
        </table>
      </div>

      <div class="foot-note mt-20">
        <strong style="color:var(--text-2)">Not yet serving your area?</strong>
        <a href="{{ route('register') }}" style="color:var(--primary)">Register anyway.</a>
        A new area opens once roughly 200 households have signed up within 3 km of each
        other — below that density a collection round costs more in fuel than the material
        is worth, and the 60/40 split stops working for everybody.
      </div>
    </div>
  </section>

  <!-- ── Expansion ────────────────────────────────────────────────────── -->
  <section class="section" style="background:var(--surface-2);border-block:1px solid var(--border)">
    <div class="container">
      <div class="ed-split" style="align-items:center">
        <div>
          <span class="eyebrow">What comes next</span>
          <h2 class="mt-10">Lagos first, and properly</h2>
          <p class="lede mt-12 measure">
            There is no second city on the roadmap until the digester constraint at Ojota is
            solved and every one of the twelve current areas is running above 70% household
            participation. Spreading thin is how networks like this fail.
          </p>
          <p class="small muted mt-14 measure">
            Density is the whole business. A route that collects 40 households in three
            kilometres pays everybody; the same route spread over fifteen kilometres pays
            nobody.
          </p>
        </div>
        <figure class="fig">
          <div class="media media-16x9 media-round">
            <img src="{{ asset('assets/img/city-street.jpg') }}" alt="A busy market street in Lagos" loading="lazy" width="1200" height="800">
          </div>
          <figcaption class="fig-cap">
            Dense, mixed-use streets are where this model works best — and where the
            existing informal collection network is already strongest.
          </figcaption>
        </figure>
      </div>
    </div>
  </section>
</x-layouts.site>
