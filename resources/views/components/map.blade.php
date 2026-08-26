@props([
    'pins' => [],
    'height' => 320,
    'legend' => true,
    'note' => null,
])
{{--
  The schematic coverage map. Deliberately not a tile map: at pilot scale what an
  operator needs is where the load sits relative to the facility, and a real map
  would cost an API key and a network round trip to say the same thing.
--}}
<div class="map" @style(['min-height:'.((int) $height).'px' => $height])>
  <svg class="map-roads" viewBox="0 0 100 100" preserveAspectRatio="none">
    <path d="M0 34 H100" stroke="var(--border-strong)" stroke-width=".8" fill="none"/>
    <path d="M0 68 H100" stroke="var(--border-strong)" stroke-width=".8" fill="none"/>
    <path d="M28 0 V100" stroke="var(--border-strong)" stroke-width=".8" fill="none"/>
    <path d="M64 0 V100" stroke="var(--border-strong)" stroke-width=".6" fill="none"/>
    <path d="M0 12 C30 18 44 44 100 52" stroke="var(--border-strong)" stroke-width=".5" fill="none"/>
  </svg>

  @foreach ($pins as $p)
    @php
      $kind = $p['kind'] ?? 'house';
      $glyph = $p['icon'] ?? match ($kind) { 'agent' => 'truck', 'plant' => 'factory', default => 'house' };
    @endphp
    <span class="pin {{ $kind }}@if(! empty($p['live'])) pin-live @endif"
          style="left:{{ $p['x'] }}%;top:{{ $p['y'] }}%">
      <span class="pin-mark"><i data-icon="{{ $glyph }}" class="i i-sm"></i></span>
      @if (! empty($p['label']))<span class="pin-label">{{ $p['label'] }}</span>@endif
    </span>
  @endforeach

  @if ($legend)
    <div class="map-legend">
      <span><i style="background:var(--primary)"></i>Household</span>
      <span><i style="background:var(--info)"></i>Agent</span>
      <span><i style="background:var(--violet)"></i>Facility</span>
    </div>
  @endif

  @if ($note)
    <div class="tiny dim" style="position:absolute;right:10px;top:10px;background:var(--surface);padding:4px 9px;border-radius:var(--r-pill);border:1px solid var(--border)">{{ $note }}</div>
  @endif
</div>
