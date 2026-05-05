<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenilaianPerilaku extends Model
{
    use HasFactory;

    protected $table = 'penilaian_perilaku';

    protected $fillable = [
        'user_id',
        'penilai_id',
        'kode_unit',
        'periode',
        'berorientasi_pelayanan',
        'akuntabel',
        'kompeten',
        'harmonis',
        'loyal',
        'adaptif',
        'kolaboratif',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function penilai()
    {
        return $this->belongsTo(User::class, 'penilai_id');
    }
}
