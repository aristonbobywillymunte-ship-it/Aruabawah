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
        'parent_user_id',
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
     * Project yang di-assign ke user ini melalui relasi project-user.
     * Admin tidak perlu relasi ini — mereka lihat semua.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user')
                    ->withTimestamps();
    }

    // ─── Client Management ───────────────────────────────────────────────

    /** Apakah user ini client? (selain admin dan user biasa) */
    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    /** Siapa yang membuat client ini (bisa admin atau user biasa) */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    /** Client apa saja yang dibuat oleh user ini */
    public function clients(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'parent_user_id');
    }

    /** Settings spesifik untuk client (limits, permissions) */
    public function clientSettings(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\ClientSetting::class);
    }

    /** Paket apa saja yang diizinkan untuk client ini */
    public function allowedPackages(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Package::class, 'client_package_permissions')
                    ->withTimestamps();
    }

    /** 
     * Menghitung batas maksimal proyek dari sebuah Collection package.
     * Aturan:
     * - Kosong -> 0
     * - Semua NULL -> NULL (unlimited)
     * - Mix NULL dan Numeric -> MAX(numerics)
     */
    public static function calculateMaxProjectEntitlement($packages): ?int
    {
        if ($packages->isEmpty()) {
            return 0; // Tidak punya izin ke paket manapun
        }

        $hasNull = false;
        $numerics = [];

        foreach ($packages as $package) {
            if (is_null($package->max_projects)) {
                $hasNull = true;
            } else {
                $numerics[] = $package->max_projects;
            }
        }

        // Jika ada nilai numerik, ambil yang terbesar
        if (!empty($numerics)) {
            return max($numerics);
        }

        // Jika tidak ada nilai numerik sama sekali tapi loop jalan, berarti semuanya NULL
        return null;
    }

    /** 
     * Dapatkan batas maksimal proyek dari paket yang diizinkan saat ini.
     */
    public function getMaxProjectEntitlement(): ?int
    {
        return self::calculateMaxProjectEntitlement($this->allowedPackages);
    }

    /**
     * Dapatkan batas efektif untuk proyek (hanya dari client_settings atau package default).
     */
    public function getEffectiveMaxProjects(): ?int
    {
        // Prioritaskan setting klien (override individu)
        $clientMax = optional($this->clientSettings)->max_projects;
        if (!is_null($clientMax)) {
            return $clientMax;
        }

        // Jika klien tidak punya override, gunakan batas maksimal dari paket-paketnya
        return $this->getMaxProjectEntitlement();
    }
}
