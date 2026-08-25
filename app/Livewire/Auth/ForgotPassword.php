<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.auth')]
class ForgotPassword extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    public ?string $sent = null;

    public function send(): void
    {
        $this->validate();

        // Always the same response, so this cannot be used to enumerate accounts.
        Password::sendResetLink(['email' => $this->email]);

        $this->sent = 'If that address is registered, a reset link is on its way.';
        $this->reset('email');
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
