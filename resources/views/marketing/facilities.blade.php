<x-layouts.site :title="'Facilities — EcoCycle'" :page="'facilities'" :description="'The EcoCycle Lagos Facility: gate, inspection bay, four processing lines and 9,500 kg a day of intake capacity.'">
<!-- ── Page hero ────────────────────────────────────────────────────── -->
  <section class="page-hero">
    <div class="container">
      <div class="ed-split">
        <div>
          <span class="eyebrow">Facilities</span>
          <h1 class="display mt-14">One plant, four lines,<br>9,500 kg a day.</h1>
          <p class="lede mt-20 measure">
            Everything collected on the network arrives at the EcoCycle Lagos Facility in
            Ojota, is weighed at the gate, graded in the inspection bay and routed to
            whichever line can take it. Nothing is paid for before it has been on the scale.
          </p>
        </div>
        <figure class="fig">
          <div class="media media-4x3 media-round">
            <img src="{{ asset('assets/img/facility-exterior.jpg') }}" alt="The processing plant lit up at night" width="1200" height="800">
          </div>
          <figcaption class="fig-cap">
            The plant runs two shifts, six days a week.
          </figcaption>
        </figure>
      </div>

      <div class="ed-figures mt-40">
        <div><div class="n">9,500<span style="font-size:.45em"> kg</span></div><div class="tiny dim mt-4">Daily intake ceiling</div></div>
        <div><div class="n">4</div><div class="tiny dim mt-4">Processing lines</div></div>
        <div><div class="n">89.4<span style="font-size:.45em"> %</span></div><div class="tiny dim mt-4">Average yield</div></div>
        <div><div class="n">52<span style="font-size:.45em"> min</span></div><div class="tiny dim mt-4">Median gate-to-verified</div></div>
      </div>
    </div>
  </section>

  <!-- ── The gate ─────────────────────────────────────────────────────── -->
  <section class="section" style="background:var(--surface-2);border-block:1px solid var(--border)">
    <div class="container">
      <div class="ed-head">
        <div>
          <span class="eyebrow">The gate</span>
          <p class="tiny dim mt-8">Step one</p>
        </div>
        <div>
          <h2>The weighbridge is the source of truth</h2>
          <p class="lede mt-12 measure">
            A load arrives, the agent scans each batch tag, and the weighbridge reading is
            split across the batches on board. From that moment three separate things are
            decided by one number neither party entered.
          </p>
        </div>
      </div>

      <div class="grid mt-32" style="grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr);gap:32px;align-items:center">
        <figure class="fig">
          <div class="media media-16x9 media-round">
            <img src="{{ asset('assets/img/facility-scale.jpg') }}" alt="The inbound weighbridge at the facility gate" loading="lazy" width="1000" height="700">
          </div>
          <figcaption class="fig-cap">
            Gate 1. Every inbound load crosses this weighbridge before anything is recorded
            against a batch.
          </figcaption>
        </figure>
        <div>
          <dl>
            <div class="kv"><dt>Intake hours</dt><dd>06:00 – 18:00, Mon–Sat</dd></div>
            <div class="kv"><dt>Weighbridges</dt><dd>2 inbound, 1 outbound</dd></div>
            <div class="kv"><dt>Inspection bays</dt><dd>3</dd></div>
            <div class="kv"><dt>Median wait at gate</dt><dd>52 min</dd></div>
            <div class="kv"><dt>Tolerance before flagging</dt><dd class="strong">±5% against declaration</dd></div>
          </dl>
          <p class="small muted mt-16 measure">
            Anything outside that tolerance is logged against the agent's integrity score
            and surfaces in the integrity queue with its evidence attached. The household
            is still paid in full on the verified weight.
          </p>
          <a class="btn btn-ghost mt-16" href="/console/inspection">Open the inspection bay <i data-icon="chevR" class="i i-sm"></i></a>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Lines ────────────────────────────────────────────────────────── -->
  <section class="section">
    <div class="container">
      <div class="ed-head">
        <div>
          <span class="eyebrow">Processing lines</span>
          <p class="tiny dim mt-8">Four, one offline</p>
        </div>
        <div>
          <h2>Where each stream ends up</h2>
          <p class="lede mt-12 measure">
            Organic material goes to anaerobic digestion and comes out as biogas and
            digestate. Mixed loads go to the sorting line. Clean plastic is baled for
            onward sale.
          </p>
        </div>
      </div>

      <div class="grid mt-32" style="grid-template-columns:repeat(4,minmax(0,1fr));gap:16px">
        @foreach ($lines as $l)
          <div class="card card-flat" style="overflow:hidden{{ $l->status === 'offline' ? ';opacity:.74' : '' }}">
            <div class="media media-16x9 media-flat">
              <img src="{{ asset('assets/img/'.($l->category ? 'cat-'.$l->category->key : 'facility-digester').'.jpg') }}" alt="" loading="lazy">
            </div>
            <div class="card-body" style="padding:14px">
              <div class="between">
                <h3 style="font-size:14.5px">{{ $l->name }}</h3>
                <span class="badge {{ $l->badgeClass() }}">{{ ucfirst($l->status) }}</span>
              </div>
              <div class="figure-num mt-8" style="font-size:16px">{{ number_format($l->capacity_kg_day) }} kg/day</div>
              <div class="tiny dim mt-2 mb-10">{{ $l->output_desc ?? 'Recovered material' }}</div>
              <div class="bar @if($l->utilisation > 85) amber @endif"><span style="width:{{ $l->utilisation }}%"></span></div>
              <div class="tiny dim mt-6">
                {{ $l->utilisation ? $l->utilisation.'% of capacity today' : ($l->note ?? 'Offline for scheduled maintenance') }}
              </div>
            </div>
          </div>
        @endforeach
      </div>

      <!-- Output -->
      <div class="grid mt-32" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:32px;align-items:start">
        <div>
          <div class="block-head"><h3>Output on an average day</h3><span class="badge">by stream</span></div>
          <div class="card card-flat card-pad">
            <div class="col g-14">
              <div>
                <div class="between mb-6"><span class="row g-8 small"><i data-icon="flame" class="i i-sm" style="color:var(--warn)"></i>Biogas</span>
                  <span class="small strong figure-num">{{ number_format($output["biogas_m3"], 0) }} m³</span></div>
                <div class="bar amber"><span style="width:{{ min(100, $output["total_kg"] ? $output["biogas_m3"] / max(1, $output["total_kg"] / 1000 * 120) * 100 : 0) }}%"></span></div>
              </div>
              <div>
                <div class="between mb-6"><span class="row g-8 small"><i data-icon="recycle" class="i i-sm" style="color:var(--primary)"></i>Recovered recyclables</span>
                  <span class="small strong figure-num">{{ number_format($output["recovered_kg"] / 1000, 1) }} t</span></div>
                <div class="bar"><span style="width:{{ min(100, $output["total_kg"] ? $output["recovered_kg"] / $output["total_kg"] * 100 : 0) }}%"></span></div>
              </div>
              <div>
                <div class="between mb-6"><span class="row g-8 small"><i data-icon="droplet" class="i i-sm" style="color:var(--cyan)"></i>Digestate fertiliser</span>
                  <span class="small strong figure-num">{{ number_format($output["digestate_kg"] / 1000, 1) }} t</span></div>
                <div class="bar blue"><span style="width:{{ min(100, $output["total_kg"] ? $output["digestate_kg"] / $output["total_kg"] * 100 : 0) }}%"></span></div>
              </div>
              <div>
                <div class="between mb-6"><span class="row g-8 small"><i data-icon="ban" class="i i-sm" style="color:var(--danger)"></i>Residual to landfill</span>
                  <span class="small strong figure-num">{{ number_format($output["residual_kg"] / 1000, 1) }} t</span></div>
                <div class="bar red"><span style="width:{{ min(100, $output["total_kg"] ? $output["residual_kg"] / $output["total_kg"] * 100 : 0) }}%"></span></div>
              </div>
            </div>
            <div class="rule mt-16 mb-14"></div>
            <div class="grid grid-keep-2" style="gap:12px">
              <div><div class="tiny dim">Diversion rate</div><div class="strong figure-num" style="font-size:19px">{{ number_format($output["diversion_pct"], 1) }}%</div></div>
              <div><div class="tiny dim">CO₂e avoided</div><div class="strong figure-num" style="font-size:19px">{{ number_format($output["co2e_kg"] / 1000, 1) }} t</div></div>
            </div>
          </div>
        </div>

        <div>
          <div class="block-head"><h3>What we accept</h3><span class="badge">{{ $categories->count() }} streams</span></div>
          <x-materials :categories="$categories" />
          <p class="tiny dim mt-14">
            Rates are per kilogram of verified weight. Contaminated loads are graded down
            or rejected outright — a clean stream is worth materially more than a mixed one,
            which is why the household app pushes separation at source.
          </p>
          <a class="btn btn-ghost mt-16" href="/console/processing">Open the processing console <i data-icon="chevR" class="i i-sm"></i></a>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Capacity ─────────────────────────────────────────────────────── -->
  <section class="section" style="background:var(--surface-2);border-block:1px solid var(--border)">
    <div class="container">
      <div class="ed-split" style="align-items:start">
        <div>
          <span class="eyebrow">Capacity</span>
          <h2 class="mt-10">Growth is limited by the plant, not the network</h2>
          <p class="lede mt-12 measure">
            Signing up households is the easy part. The constraint is the digester: organic
            intake is already running close to its ceiling on peak days, and a second
            digester is the next thing that has to come online.
          </p>
          <p class="small muted mt-14 measure">
            The forecast below is the same one the facility console runs on, and it is the
            reason coverage opens area by area rather than all at once.
          </p>
          <a class="btn btn-ghost mt-16" href="{{ route('location') }}">See where we operate <i data-icon="chevR" class="i i-sm"></i></a>
        </div>
        <div class="col g-14">
          @forelse ($forecast as $i)
            <x-insight :insight="$i" />
          @empty
            <div class="empty"><span class="empty-icon"><i data-icon="chart" class="i i-lg"></i></span>
              <div class="strong">Forecast needs more history</div>
              <p class="small dim" style="max-width:280px">Two weeks of verified intake and the capacity projection appears here.</p></div>
          @endforelse
        </div>
      </div>
    </div>
  </section>
</x-layouts.site>
