@props(['categories'])
{{-- The accepted-streams strip. Rates come from the same table settlement reads,
     so a rate shown to the public cannot drift from the rate that pays out. --}}
<div class="strip">
  @foreach ($categories as $c)
    <div>
      <div class="media media-1x1">
        <img src="{{ asset('assets/img/cat-'.$c->key.'.jpg') }}" alt="{{ $c->label }}" loading="lazy">
      </div>
      <div class="small strong mt-8">{{ $c->label }}</div>
      <div class="tiny dim figure-num">₦{{ number_format($c->rate_per_kg, 0) }}/kg</div>
    </div>
  @endforeach
</div>
