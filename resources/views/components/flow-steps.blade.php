@props(['stages'])
<div class="ed-steps mt-32">
  @foreach ($stages as $i => $s)
    <div class="ed-step">
      <div class="marker">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</div>
      <div>
        <h3 style="font-size:17px">{{ $s['t'] }}</h3>
        <p class="small muted mt-6 measure">{{ $s['s'] }}</p>
      </div>
      <div class="media media-3x2"><img src="{{ asset('assets/img/'.$s['img'].'.jpg') }}" alt="" loading="lazy"></div>
    </div>
  @endforeach
</div>
