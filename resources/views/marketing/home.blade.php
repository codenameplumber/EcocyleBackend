<x-layouts.site :title="'EcoCycle — Waste-to-value infrastructure'" :page="'home'" :description="'EcoCycle connects the households that generate waste, the agents who move it and the facilities that process it, with a verified record at every hand-off.'">

  {{-- ── Hero ───────────────────────────────────────────────────────────
       The photograph carries the section rather than sitting beside it, but it
       stays a real <img> with its alt text: a CSS background would drop the
       caption out of the document for anyone not looking at it. --}}
  <section class="cine">
    <div class="cine-bg">
      <img class="cine-img" src="{{ asset('assets/img/hero-collection.jpg') }}"
           alt="A loaded hand cart on a residential street during a collection round"
           width="1600" height="1100" fetchpriority="high">
    </div>
    <div class="cine-mesh" aria-hidden="true"></div>

    <div class="cine-inner">
      <div class="container">
        <div style="max-width:60ch">
          <div class="row wrap g-12">
            <span class="eyebrow">Waste-to-value infrastructure</span>
            <span class="live-pill">
              <span class="pulse-dot"></span>
              {{ $stats["agents_online"] }} agents online
            </span>
          </div>

          <h1 class="display cine-title mt-16">Waste is not disposal.<br>It is a supply chain.</h1>

          <p class="lede mt-20 measure">
            Households generate it. Independent agents move it. Facilities turn it into
            biogas, fertiliser and recovered material. EcoCycle is the record that runs
            underneath all three — so every kilogram is traced, and everybody is paid on
            the weight the scale actually recorded.
          </p>

          <div class="row wrap g-12 mt-24">
            <a class="btn btn-primary btn-lg" href="/app">Open the Household app</a>
            <a class="btn btn-lg" href="/console"
               style="background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.24);color:#fff;backdrop-filter:blur(14px)">
              Open the Facility console
            </a>
          </div>

          <div class="cine-note mt-24">
            <div class="tiny">On a collection round</div>
            <div class="strong" style="font-size:15px;color:#fff">
              {{ number_format($stats["in_transit_kg"], 1) }} kg in transit right now
            </div>
            <p class="small mt-8">
              Independent collectors already move most of this material. EcoCycle gives the
              movement a record, and the record a value.
            </p>
          </div>
        </div>

        <div class="cine-figures">
          <div>
            <div class="n"><span data-countup="{{ $stats['households'] }}">{{ number_format($stats['households']) }}</span></div>
            <div class="k">Registered households</div>
          </div>
          <div>
            <div class="n"><span data-countup="{{ $stats['agents'] }}">{{ number_format($stats['agents']) }}</span></div>
            <div class="k">Collection agents</div>
          </div>
          <div>
            <div class="n"><span data-countup="{{ $stats['month_kg'] / 1000 }}" data-decimals="1">{{ number_format($stats['month_kg'] / 1000, 1) }}</span><span style="font-size:.5em"> t</span></div>
            <div class="k">Collected this month</div>
          </div>
          <div>
            <div class="n"><span data-countup="{{ $stats['co2e_kg'] / 1000 }}" data-decimals="1">{{ number_format($stats['co2e_kg'] / 1000, 1) }}</span><span style="font-size:.5em"> t</span></div>
            <div class="k">CO₂e avoided</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ── About, in brief ──────────────────────────────────────────────── -->
  <section class="section band-soft">
    <div class="container">
      <div class="ed-split" style="align-items:center">
        <div data-reveal>
          <span class="eyebrow">About us</span>
          <h2 class="mt-10">We did not start a waste company. We started a ledger.</h2>
          <p class="lede mt-12 measure">
            The material was always moving. Collectors were already working, households
            were already separating, processors were already buying. What nobody had was
            a shared, trustworthy record of what moved, how much it weighed, and who was
            owed for it.
          </p>
          <a class="btn btn-ghost mt-20" href="{{ route('about') }}">Read about us <i data-icon="chevR" class="i i-sm"></i></a>
        </div>
        <figure class="fig" data-reveal="120">
          <div class="media media-16x9 media-round">
            <img src="{{ asset('assets/img/flow-collected.jpg') }}" alt="A collector sorting recovered material by hand" loading="lazy">
          </div>
          <figcaption class="fig-cap">
            Most of this work already happens. It just happens without a receipt.
          </figcaption>
        </figure>
      </div>
    </div>
  </section>

  <!-- ── Pipeline ─────────────────────────────────────────────────────── -->
  <section class="section home-flow" id="pipeline">
    <div class="container">
      <div class="ed-head" data-reveal>
        <div>
          <span class="eyebrow">How it works</span>
          <p class="tiny dim mt-8">Ten states, one record</p>
        </div>
        <div>
          <h2>Every kilogram has a state, and every transition is recorded</h2>
          <p class="lede mt-12 measure">
            A collection is not a row that gets edited. It is a batch that moves through a
            state machine and leaves an audit trail behind it.
          </p>
        </div>
      </div>

      <div data-reveal="80"><x-flow-steps :stages="$stages" /></div>

      <div class="ed-split mt-40" style="align-items:start" data-reveal>
        <p class="pull">
          Expected weight drives planning. Collected weight is what the agent declares.
          Verified weight is what the facility scale says — and money only ever settles
          on the verified figure.
        </p>
        <div class="foot-note">
          <strong style="color:var(--text)">Why three weights?</strong><br>
          Because the gap between them is the signal. A household that consistently
          under-estimates needs a better prediction; an agent whose declarations run
          12% above the scale needs investigating. Collapsing them into one number
          throws that away.
        </div>
      </div>
    </div>
  </section>

  <!-- ── Apps ─────────────────────────────────────────────────────────── -->
  <section class="section band-soft" id="apps">
    <div class="container">
      <div class="ed-head" data-reveal>
        <div>
          <span class="eyebrow">Three surfaces</span>
          <p class="tiny dim mt-8">One record, three jobs</p>
        </div>
        <div>
          <h2>Everyone in the chain gets the view they need</h2>
          <p class="lede mt-12 measure">
            The household wants to know when someone is coming and what they earned. The
            agent wants the next job worth taking. The facility wants throughput and
            variance. Same batch, three different questions.
          </p>
        </div>
      </div>

      <div class="bento mt-32">
        <a class="bento-card" href="/app" data-reveal>
          <div class="media media-16x9">
            <img src="{{ asset('assets/img/story-household.jpg') }}" alt="Kerbside bins outside a house awaiting collection" loading="lazy" width="1200" height="900">
          </div>
          <div class="card-body">
            <span class="bento-num">01 — Household</span>
            <h3 class="mt-8" style="font-size:19px">Request, watch, get paid</h3>
            <p class="small muted mt-8">
              Put in a pickup, follow the agent in, see the verified weight and take 60%
              of what the batch was worth.
            </p>
            <div class="bento-go mt-16">Open the app <i data-icon="chevR" class="i i-sm"></i></div>
          </div>
        </a>

        <a class="bento-card" href="/agent" data-reveal="110">
          <div class="media media-16x9">
            <img src="{{ asset('assets/img/story-agent.jpg') }}" alt="A loaded three-wheeler carrying collection bins on a road" loading="lazy" width="1200" height="900">
          </div>
          <div class="card-body">
            <span class="bento-num">02 — Agent</span>
            <h3 class="mt-8" style="font-size:19px">The next job worth taking</h3>
            <p class="small muted mt-8">
              Ranked jobs nearby, an optimised route, a QR tag and a weight at every stop,
              and 40% of every batch delivered.
            </p>
            <div class="bento-go mt-16">Open the app <i data-icon="chevR" class="i i-sm"></i></div>
          </div>
        </a>

        <a class="bento-card bento-wide" href="/console" data-reveal="200">
          <div class="media media-fill">
            <img src="{{ asset('assets/img/facility-exterior.jpg') }}" alt="A waste processing plant lit up at night" loading="lazy" width="1200" height="800">
          </div>
          <div class="card-body">
            <span class="bento-num">03 — Facility</span>
            <h3 class="mt-8" style="font-size:23px">Intake to settlement</h3>
            <p class="lede mt-12 measure" style="font-size:15px">
              The gate, the inspection bay, the processing lines and the payment queue on
              one console.
            </p>
            <div class="bento-go mt-16">Open the console <i data-icon="chevR" class="i i-sm"></i></div>
          </div>
        </a>
      </div>
    </div>
  </section>

  <!-- ── Facilities, in brief ─────────────────────────────────────────── -->
  <section class="section">
    <div class="container">
      <div class="ed-head" data-reveal>
        <div>
          <span class="eyebrow">Facilities</span>
          <p class="tiny dim mt-8">Where the material lands</p>
        </div>
        <div>
          <h2>One plant, four lines, 9,500 kg a day</h2>
          <p class="lede mt-12 measure">
            Everything collected on the network arrives at the Lagos Facility in Ojota, is
            weighed at the gate, graded in the inspection bay and routed to whichever line
            can take it.
          </p>
        </div>
      </div>

      <div class="fig-grid mt-32">
        <figure class="fig" data-reveal>
          <div class="media media-3x2 media-round">
            <img src="{{ asset('assets/img/facility-scale.jpg') }}" alt="The inbound weighbridge at the facility gate" loading="lazy">
          </div>
          <figcaption class="fig-cap">Gate 1. Nothing is valued before it has crossed the weighbridge.</figcaption>
        </figure>
        <figure class="fig" data-reveal="110">
          <div class="media media-3x2 media-round">
            <img src="{{ asset('assets/img/facility-sorting.jpg') }}" alt="Workers at a sorting line" loading="lazy">
          </div>
          <figcaption class="fig-cap">Sorting line B takes mixed loads at 2,800 kg a day.</figcaption>
        </figure>
        <figure class="fig" data-reveal="220">
          <div class="media media-3x2 media-round">
            <img src="{{ asset('assets/img/facility-digester.jpg') }}" alt="The anaerobic digester dome" loading="lazy">
          </div>
          <figcaption class="fig-cap">Digester A turns organics into biogas and digestate.</figcaption>
        </figure>
      </div>

      <div class="row wrap g-12 mt-24" data-reveal>
        <a class="btn btn-ghost" href="{{ route('facilities') }}">See the facility <i data-icon="chevR" class="i i-sm"></i></a>
        <a class="btn btn-ghost" href="{{ route('location') }}">Where we operate <i data-icon="chevR" class="i i-sm"></i></a>
      </div>
    </div>
  </section>

  <!-- ── Intelligence ─────────────────────────────────────────────────── -->
  <section class="section band-soft" id="intelligence">
    <div class="container">
      <div class="ed-split-rev" style="align-items:start">
        <div class="col g-12" data-reveal>
          @forelse ($insights as $i)
            <x-insight :insight="$i" />
          @empty
            <div class="empty"><span class="empty-icon"><i data-icon="sparkles" class="i i-lg"></i></span>
              <div class="strong">Not enough history yet</div>
              <p class="small dim" style="max-width:280px">Insights appear once a couple of weeks of verified batches are on record.</p></div>
          @endforelse
        </div>
        <div data-reveal="120">
          <span class="eyebrow">Intelligence layer</span>
          <h2 class="mt-10">The value is in the graph, not the pickup</h2>
          <p class="lede mt-12">
            Once thousands of households, hundreds of agents and every inspection sit in one
            graph, the platform can answer questions no single actor can.
          </p>
          <ul class="cap-list mt-20">
            <li><i data-icon="trend" class="i i-sm"></i>
              <span class="small"><strong>Volume prediction</strong> — expected kilograms per household, per area, per week.</span></li>
            <li><i data-icon="target" class="i i-sm"></i>
              <span class="small"><strong>Agent matching</strong> — the five jobs that suit this agent, not all fifty.</span></li>
            <li><i data-icon="route" class="i i-sm"></i>
              <span class="small"><strong>Route optimisation</strong> — maximum kilograms per kilometre driven.</span></li>
            <li><i data-icon="camera" class="i i-sm"></i>
              <span class="small"><strong>Classification</strong> — composition from a photograph, as an assistant to the inspector.</span></li>
            <li><i data-icon="shield" class="i i-sm"></i>
              <span class="small"><strong>Integrity</strong> — weight variance, impossible travel, duplicate collections, GPS drift.</span></li>
            <li><i data-icon="factory" class="i i-sm"></i>
              <span class="small"><strong>Capacity forecasting</strong> — what arrives next week against what the plant can absorb.</span></li>
          </ul>
          <p class="tiny dim mt-20 measure">
            Everything the model produces is labelled as a prediction and carries a
            confidence figure. Nothing inferred is ever presented as a recorded fact.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Materials ────────────────────────────────────────────────────── -->
  <section class="section-tight">
    <div class="container">
      <div class="between wrap g-16" data-reveal>
        <div>
          <span class="eyebrow">What moves through it</span>
          <h3 class="mt-6" style="font-size:17px">Seven streams, each with its own rate per kilogram</h3>
        </div>
        <a class="small strong" style="color:var(--primary)" href="{{ route('facilities') }}">See processing lines →</a>
      </div>
      <div class="mt-20" data-reveal="80"><x-materials :categories="$categories" /></div>
    </div>
  </section>

  <!-- ── Location, in brief ───────────────────────────────────────────── -->
  <section class="section band-soft">
    <div class="container">
      <div class="ed-split" style="align-items:center">
        <div data-reveal>
          <span class="eyebrow">Location</span>
          <h2 class="mt-10">Lagos, Lagos State</h2>
          <p class="lede mt-12 measure">
            Twelve areas, all of them within collection range of a single processing
            facility in Ojota. Coverage grows area by area, because route economics only
            work when households are dense enough to cluster.
          </p>
          <address class="small muted mt-16" style="font-style:normal;line-height:1.6">
            Plot 14, Ojota Industrial Layout · Lagos State<br>
            Intake 06:00 – 18:00, Mon–Sat
          </address>
          <a class="btn btn-ghost mt-20" href="{{ route('location') }}">See where we operate <i data-icon="chevR" class="i i-sm"></i></a>
        </div>
        <div class="card card-flat" style="overflow:hidden" data-reveal="120">
          <x-map :pins="$pins" :height="330" :legend="false" />
        </div>
      </div>
    </div>
  </section>

  <!-- ── Data model ───────────────────────────────────────────────────── -->
  <section class="section" id="model">
    <div class="container">
      <div class="ed-head" data-reveal>
        <div>
          <span class="eyebrow">Foundation</span>
          <p class="tiny dim mt-8">One object, one truth</p>
        </div>
        <div>
          <h2>WasteBatch is the centre of gravity</h2>
          <p class="lede mt-12 measure">
            Everything traceable hangs off a single record. This is close to the shape the
            database migration takes.
          </p>
        </div>
      </div>

      <div class="grid mt-32" style="grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);gap:28px;align-items:start">
        <div data-reveal>
          <div class="block-head"><h3>The batch record</h3><span class="badge">EC-W-2026-0091827</span></div>
          <dl class="card card-flat card-pad">
            <div class="kv"><dt>household_id</dt><dd class="mono">EC-H-0183</dd></div>
            <div class="kv"><dt>agent_id</dt><dd class="mono">EC-A-0042</dd></div>
            <div class="kv"><dt>qr_code</dt><dd class="mono">EC-W-2026-0091827</dd></div>
            <div class="kv"><dt>expected_weight</dt><dd>19.8 kg</dd></div>
            <div class="kv"><dt>collected_weight</dt><dd>21.4 kg</dd></div>
            <div class="kv"><dt>verified_weight</dt><dd class="t-pos strong">18.7 kg</dd></div>
            <div class="kv"><dt>status</dt><dd>PROCESSED</dd></div>
          </dl>
        </div>

        <div data-reveal="120">
          <div class="block-head"><h3>Settlement follows verification</h3><span class="badge badge-green">Paid</span></div>
          <div class="card card-flat card-pad">
            <div class="callout"><i data-icon="scale" class="i i-sm"></i>
              <div><div class="tiny dim">Verified weight × category rate</div>
              <div class="strong figure-num" style="font-size:16px">18.7 kg × ₦42/kg = ₦785</div></div></div>

            <div class="grid grid-keep-2 mt-14" style="gap:12px">
              <div class="split-tile is-hh">
                <div class="tiny" style="color:var(--primary)">Household · 60%</div>
                <div class="figure-num" style="font-size:26px;color:var(--primary);margin-top:2px">₦471</div>
              </div>
              <div class="split-tile is-ag">
                <div class="tiny" style="color:var(--info)">Agent · 40%</div>
                <div class="figure-num" style="font-size:26px;color:var(--info);margin-top:2px">₦314</div>
              </div>
            </div>

            <div class="rule mt-16 mb-14"></div>
            <p class="tiny dim">
              Declared 21.4 kg, verified 18.7 kg — a 12.6% variance. Settlement uses the
              verified figure and the gap is logged against the agent's integrity score.
            </p>
          </div>

          <figure class="fig mt-16">
            <div class="media media-21x9 media-round">
              <img src="{{ asset('assets/img/story-value.jpg') }}" alt="Sorted cardboard being handled for onward sale" loading="lazy" width="1200" height="900">
            </div>
            <figcaption class="fig-cap">
              The verified figure is also what the facility sells on, so the household,
              the agent and the plant are all reading the same number.
            </figcaption>
          </figure>
        </div>
      </div>
    </div>
  </section>

  {{-- The hero's two calls to action, following the reader down once the hero
       itself has scrolled away. Same destinations, same labels. --}}
  <div class="cta-dock" aria-hidden="true">
    <span class="small strong">Open EcoCycle</span>
    <a class="btn btn-primary btn-sm" href="/app" tabindex="-1">Household app</a>
    <a class="btn btn-ghost btn-sm" href="/console" tabindex="-1">Facility console</a>
  </div>
</x-layouts.site>
