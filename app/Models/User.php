<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'nip',
        'email',
        'password',
        'role',
        'status_pegawai',
        'kode_unit',
        'penilaian_aktif',
    ];

    /**
     * Relationship to unit
     */
    public function unit()
    {
        return $this->belongsTo(Units::class, 'kode_unit', 'kode_unit');
    }

    /**
     * Check if user is admin mutu
     */
    public function isAdminMutu(): bool
    {
        return $this->role === 'admin_mutu';
    }

    /**
     * Check if user is kepala unit (kepala bagian/bidang)
     */
    public function isKepalaUnit(): bool
    {
        return $this->role === 'kepala_unit';
    }

    /**
     * Check if user can approve for a specific unit
     */
    public function canApproveForUnit(string $kodeUnit): bool
    {
        // Admin mutu can approve all units
        if ($this->isAdminMutu()) {
            return true;
        }

        // Kepala unit can only approve their own unit
        if ($this->isKepalaUnit() && $this->kode_unit === $kodeUnit) {
            return true;
        }

        return false;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'penilaian_aktif' => 'boolean',
        ];
    }
}
