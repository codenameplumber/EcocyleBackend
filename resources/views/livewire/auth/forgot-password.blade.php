<div>
    <h1 class="mt-20">Reset your password</h1>
    <p class="muted small mt-4">We'll email you a link to set a new one.</p>

    @if ($sent)
        <div class="callout callout-ok mt-20"><i data-icon="ok" class="i i-sm"></i><div>{{ $sent }}</div></div>
    @endif

    <form wire:submit="send" class="mt-20 col g-14">
        <div class="field">
            <label class="label">Email address</label>
            <input class="input" type="email" wire:model="email" autocomplete="email">
            @error('email') <span class="hint t-neg">{{ $message }}</span> @enderror
        </div>
        <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
    </form>

    <p class="tiny dim center mt-20">
        <a href="{{ route('login') }}" wire:navigate style="color:var(--primary);font-weight:600">Back to sign in</a>
    </p>
</div>
