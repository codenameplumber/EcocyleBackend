<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'phone', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => UserRole::class,
            'is_active'         => 'boolean',
        ];
    }

    public function household(): HasOne
    {
        return $this->hasOne(Household::class);
    }

    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }

    /** The profile row for whichever role this user holds. */
    public function profile(): Household|Agent|null
    {
        return match ($this->role) {
            UserRole::Household => $this->household,
            UserRole::Agent     => $this->agent,
            default             => null,
        };
    }

    public function isHousehold(): bool { return $this->role === UserRole::Household; }
    public function isAgent(): bool     { return $this->role === UserRole::Agent; }
    public function isFacility(): bool  { return $this->role->isFacility(); }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');
    }

    /**
     * Deterministic avatar tint, so the same person keeps the same colour
     * across every screen. Mirrors EC.variant() from the prototype.
     */
    public function avatarVariant(): string
    {
        $h = 0;
        foreach (str_split($this->name ?: 'EC') as $c) {
            $h = ($h * 31 + ord($c)) % 4294967296;
        }
        $v = $h % 5;

        return $v === 0 ? '' : 'v' . ($v + 1);
    }
}
