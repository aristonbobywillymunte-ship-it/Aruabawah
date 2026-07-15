<?php

namespace App\Models;

use Database\Factories\UserFactory;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role', // 'admin' | 'user'
        'status', // 'active' | 'inactive'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'status'            => 'string',
        ];
    }

    // ─── Role Helpers ────────────────────────────────────────────────────

    /** Apakah user ini admin? */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Apakah user ini user biasa? */
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    // ─── Relations ───────────────────────────────────────────────────────

    /**
     * Project yang di-assign ke user ini (pivot: project_user).
     * Admin tidak perlu relasi ini — mereka lihat semua.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user')
                    ->withTimestamps();
    }
}
