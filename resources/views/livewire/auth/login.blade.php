<div>
    <h1 class="mt-20">Sign in</h1>
    <p class="muted small mt-4">One account for households, agents and facility staff.</p>

    <form wire:submit="login" class="mt-24 col g-14">
        <div class="field">
            <label class="label" for="email">Email address</label>
            <input class="input" id="email" type="email" wire:model="email"
                   autocomplete="email" placeholder="you@example.com" autofocus>
            @error('email') <span class="hint t-neg">{{ $message }}</span> @enderror
        </div>

        <x-password-field model="password" id="password" autocomplete="current-password">
            <x-slot:action>
                <a class="tiny" style="color:var(--primary);font-weight:600"
                   href="{{ route('password.request') }}" wire:navigate>Forgot?</a>
            </x-slot:action>
        </x-password-field>

        <label class="switch">
            <input type="checkbox" wire:model="remember">
            <span class="track"></span>
            <span class="small muted">Keep me signed in on this device</span>
        </label>

        <button class="btn btn-primary btn-lg btn-block mt-4" type="submit">
            <span wire:loading.remove wire:target="login">Continue</span>
            <span wire:loading wire:target="login">Signing in…</span>
        </button>
    </form>

    <p class="tiny dim center mt-20">
        New to EcoCycle?
        <a href="{{ route('register') }}" wire:navigate style="color:var(--primary);font-weight:600">Create an account</a>
    </p>

    <div class="foot-note mt-24">
        <strong style="color:var(--text-2)">Demo accounts</strong><br>
        Household <span class="mono">nkechi.obiora@ecocycle.test</span><br>
        Agent <span class="mono">sunday.amadi@ecocycle.test</span><br>
        Facility <span class="mono">manager@ecocycle.ng</span><br>
        Password for all three: <span class="mono">password</span>
    </div>
</div>
