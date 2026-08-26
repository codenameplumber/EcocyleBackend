<x-layouts.site :title="'About — EcoCycle'" :page="'about'" :description="'Why EcoCycle exists, what we built, and how the 60/40 split works.'">
<!-- ── Page hero ────────────────────────────────────────────────────── -->
  <section class="page-hero">
    <div class="container">
      <div class="ed-split">
        <div>
          <span class="eyebrow">About us</span>
          <h1 class="display mt-14">We did not start a waste company.<br>We started a ledger.</h1>
          <p class="lede mt-20 measure">
            The material was always moving. Collectors were already working, households
            were already separating, processors were already buying. What nobody had was
            a shared, trustworthy record of what moved, how much it weighed, and who was
            owed for it.
          </p>
        </div>
        <figure class="fig">
          <div class="media media-4x3 media-round">
            <img src="{{ asset('assets/img/flow-collected.jpg') }}" alt="A collector sorting recovered material by hand" width="640" height="640">
          </div>
          <figcaption class="fig-cap">
            Most of this work already happens. It just happens without a receipt.
          </figcaption>
        </figure>
      </div>
    </div>
  </section>

  <!-- ── The problem ──────────────────────────────────────────────────── -->
  <section class="section" style="background:var(--surface-2);border-block:1px solid var(--border)">
    <div class="container">
      <div class="ed-head">
        <div>
          <span class="eyebrow">The problem</span>
          <p class="tiny dim mt-8">Why this is hard</p>
        </div>
        <div>
          <h2>Everyone in the chain is guessing, and the guessing is expensive</h2>
          <p class="lede mt-12 measure">
            It is the single reason the informal recovery system stays informal: without a
            number everybody trusts, nobody can be paid properly, and nobody separates
            their waste twice.
          </p>
        </div>
      </div>

      <div class="grid mt-32" style="grid-template-columns:repeat(3,minmax(0,1fr));gap:24px">
        <div>
          <span class="marker">01 — Households</span>
          <h3 class="mt-8" style="font-size:17px">No idea what it was worth</h3>
          <p class="small muted mt-6">
            A household hands over a bag and never learns what was in it, what it weighed
            or what it earned. Separating waste becomes a favour rather than a transaction,
            so most people stop doing it.
          </p>
        </div>
        <div>
          <span class="marker">02 — Agents</span>
          <h3 class="mt-8" style="font-size:17px">No way to prove they were honest</h3>
          <p class="small muted mt-6">
            A collector declares a weight nobody can check. The honest ones are treated
            exactly like the dishonest ones, which means the reputation they have built
            over hundreds of trips is worth nothing at the gate.
          </p>
        </div>
        <div>
          <span class="marker">03 — Processors</span>
          <h3 class="mt-8" style="font-size:17px">Paying on trust, eating the difference</h3>
          <p class="small muted mt-6">
            A facility settles against declarations, absorbs the shortfall, and cannot
            forecast next week's feedstock because it has no reliable history of what
            actually arrived.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- ── What we built ────────────────────────────────────────────────── -->
  <section class="section">
    <div class="container">
      <div class="ed-head">
        <div>
          <span class="eyebrow">What we built</span>
          <p class="tiny dim mt-8">One record, three weights</p>
        </div>
        <div>
          <h2>A batch record that nobody in the chain can quietly edit</h2>
          <p class="lede mt-12 measure">
            Every collection becomes a tagged batch that moves through a state machine and
            leaves an audit trail. Money settles on the facility scale, never on a
            declaration.
          </p>
        </div>
      </div>

      <div class="grid mt-32" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:32px;align-items:start">
        <div class="col g-20">
          <div>
            <h3 style="font-size:17px">Three weights, kept apart on purpose</h3>
            <p class="small muted mt-6 measure">
              Expected weight drives planning. Collected weight is what the agent declares.
              Verified weight is what the facility scale says. Most systems would collapse
              these into one field. Keeping them separate is what makes settlement,
              forecasting and fraud detection possible at all — the gap between them is the
              signal, not noise to be tidied away.
            </p>
          </div>
          <div>
            <h3 style="font-size:17px">The scale is the only authority</h3>
            <p class="small muted mt-6 measure">
              A household's payment, an agent's payment and an agent's integrity score are
              all determined by one number that neither of them entered. Nothing is paid
              for before it has been on the weighbridge.
            </p>
          </div>
          <div>
            <h3 style="font-size:17px">Predictions are labelled as predictions</h3>
            <p class="small muted mt-6 measure">
              The platform forecasts volumes, ranks jobs, optimises routes and classifies
              material from photographs. Every one of those outputs carries a confidence
              figure and is visually distinct from a recorded fact. An inspector should
              never have to ask which is which.
            </p>
          </div>
        </div>

        <div class="col g-16">
          <div class="card card-flat card-pad">
            <div class="block-head"><h3>How a batch settles</h3><span class="badge badge-green">Worked example</span></div>
            <div class="callout"><i data-icon="scale" class="i i-sm"></i>
              <div><div class="tiny dim">Verified weight × category rate</div>
              <div class="strong figure-num" style="font-size:16px">18.7 kg × ₦42/kg = ₦785</div></div></div>
            <div class="grid grid-keep-2 mt-14" style="gap:12px">
              <div style="padding:13px 14px;background:var(--primary-soft);border-radius:var(--r-sm)">
                <div class="tiny" style="color:var(--primary)">Household · 60%</div>
                <div class="figure-num" style="font-size:24px;color:var(--primary);margin-top:2px">₦471</div>
              </div>
              <div style="padding:13px 14px;background:var(--info-soft);border-radius:var(--r-sm)">
                <div class="tiny" style="color:var(--info)">Agent · 40%</div>
                <div class="figure-num" style="font-size:24px;color:var(--info);margin-top:2px">₦314</div>
              </div>
            </div>
            <div class="rule mt-16 mb-14"></div>
            <p class="tiny dim">
              The agent declared 21.4 kg; the scale read 18.7 kg. That 12.6% variance is
              logged against the agent's integrity score, and the household is still paid
              in full on the verified figure.
            </p>
          </div>

          <p class="pull">
            If you cannot prove what a kilogram weighed, you cannot pay for it fairly —
            and if you cannot pay for it fairly, nobody separates their waste twice.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Principles ───────────────────────────────────────────────────── -->
  <section class="section" style="background:var(--surface-2);border-block:1px solid var(--border)">
    <div class="container">
      <div class="ed-head">
        <div>
          <span class="eyebrow">How we work</span>
          <p class="tiny dim mt-8">Four commitments</p>
        </div>
        <div>
          <h2>The rules we will not trade away</h2>
        </div>
      </div>

      <div class="grid mt-32" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:28px">
        <div class="card-ruled">
          <div class="block-head"><h3>Settlement follows verification</h3><span class="marker">01</span></div>
          <p class="small muted">
            No payment is ever released against a declared weight, an estimate or a
            photograph. If the batch has not crossed a scale, it has not been valued.
          </p>
        </div>
        <div class="card-ruled">
          <div class="block-head"><h3>Variance is recorded, not hidden</h3><span class="marker">02</span></div>
          <p class="small muted">
            The difference between declared and verified is kept on the batch forever. It
            is what protects honest agents as much as it exposes dishonest ones.
          </p>
        </div>
        <div class="card-ruled">
          <div class="block-head"><h3>The inspector overrides the model</h3><span class="marker">03</span></div>
          <p class="small muted">
            Classification and grading suggestions are advisory. A human decision always
            wins, and disagreements are recorded and used to retrain rather than discarded.
          </p>
        </div>
        <div class="card-ruled">
          <div class="block-head"><h3>Rejections are explained</h3><span class="marker">04</span></div>
          <p class="small muted">
            A rejected batch pays neither the household nor the agent, so it always carries
            a reason, an inspector note and evidence, and it can be appealed.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- ── By the numbers ───────────────────────────────────────────────── -->
  <section class="section">
    <div class="container">
      <div class="between wrap g-16 mb-8">
        <div>
          <span class="eyebrow">Where we are now</span>
          <h3 class="mt-6" style="font-size:17px">The network as it stands today</h3>
        </div>
        <a class="small strong" style="color:var(--primary)" href="{{ route('location') }}">See coverage →</a>
      </div>

      <div class="ed-figures mt-20">
        <div><div class="n">12,842</div><div class="tiny dim mt-4">Registered households</div></div>
        <div><div class="n">436</div><div class="tiny dim mt-4">Collection agents</div></div>
        <div><div class="n">94.6<span style="font-size:.5em"> %</span></div><div class="tiny dim mt-4">Diverted from landfill</div></div>
        <div><div class="n">218<span style="font-size:.5em"> t</span></div><div class="tiny dim mt-4">CO₂e avoided</div></div>
      </div>

      <div class="grid mt-32" style="grid-template-columns:repeat(3,minmax(0,1fr));gap:20px">
        <figure class="fig">
          <div class="media media-3x2 media-round">
            <img src="{{ asset('assets/img/story-household.jpg') }}" alt="Kerbside bins outside a house awaiting collection" loading="lazy">
          </div>
          <figcaption class="fig-cap">Households separate at source and request a pickup when they are ready.</figcaption>
        </figure>
        <figure class="fig">
          <div class="media media-3x2 media-round">
            <img src="{{ asset('assets/img/story-agent.jpg') }}" alt="A loaded three-wheeler carrying collection bins" loading="lazy">
          </div>
          <figcaption class="fig-cap">Independent agents keep 40% of every batch they deliver verified.</figcaption>
        </figure>
        <figure class="fig">
          <div class="media media-3x2 media-round">
            <img src="{{ asset('assets/img/facility-sorting.jpg') }}" alt="Workers at a sorting line inside a recovery facility" loading="lazy">
          </div>
          <figcaption class="fig-cap">The facility weighs, grades and routes every batch to a processing line.</figcaption>
        </figure>
      </div>

      <div class="row wrap g-12 mt-32">
        <a class="btn btn-primary btn-lg" href="{{ route('register') }}">Register a household</a>
        <a class="btn btn-ghost btn-lg" href="{{ route('register') }}">Become a collection agent</a>
        <a class="btn btn-ghost btn-lg" href="{{ route('facilities') }}">See the facility</a>
      </div>
    </div>
  </section>
</x-layouts.site>
