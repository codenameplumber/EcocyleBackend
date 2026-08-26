{{--
    A password input with a reveal toggle.

    The button sits inside the input's box rather than beside it, so the field
    keeps the same footprint as every other input on the form. Toggling is
    handled by the delegated [data-ec-reveal] listener in partials/scripts, which
    also keeps aria-pressed and the icon in step — a reveal control that only
    changes glyph tells a screen-reader user nothing.
--}}
@props([
    'model',
    'label' => 'Password',
    'id' => null,
    'autocomplete' => 'current-password',
    'hint' => null,
    'autofocus' => false,
])

@php($fieldId = $id ?? 'pw-' . \Illuminate\Support\Str::slug($model))

<div class="field">
    <div class="between">
        <label class="label" for="{{ $fieldId }}">{{ $label }}</label>
        {{ $action ?? '' }}
    </div>

    <div class="pw-wrap">
        <input class="input pw-input"
               id="{{ $fieldId }}"
               type="password"
               wire:model="{{ $model }}"
               autocomplete="{{ $autocomplete }}"
               @if ($autofocus) autofocus @endif
               {{ $attributes->except(['class']) }}>

        <button class="pw-eye" type="button" tabindex="-1"
                data-ec-reveal aria-pressed="false" aria-label="Show password"
                aria-controls="{{ $fieldId }}">
            <i data-icon="eye" class="i i-sm"></i>
        </button>
    </div>

    @isset($hint)
        <span class="hint">{{ $hint }}</span>
    @endisset

    @error($model) <span class="hint t-neg">{{ $message }}</span> @enderror
</div>
