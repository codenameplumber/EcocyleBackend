@props(['insight'])
{{-- One computed insight. The confidence bar reports how much evidence stood
     behind the figure, not how sure the sentence sounds. --}}
<div class="ai-card">
  <div class="between mb-8">
    <span class="ai-tag"><i data-icon="sparkles" class="i i-sm"></i> {{ $insight['tag'] }}</span>
    <span class="confidence">{{ round($insight['conf'] * 100) }}%<span class="bar bar-sm" style="width:52px"><span style="width:{{ $insight['conf'] * 100 }}%;background:var(--violet)"></span></span></span>
  </div>
  <div class="strong" style="font-size:14.5px;color:var(--text)">{{ $insight['title'] }}</div>
  <p class="small muted mt-4">{{ $insight['body'] }}</p>
</div>
