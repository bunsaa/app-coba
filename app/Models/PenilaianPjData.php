<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenilaianPjData extends Model
{
    protected $table = 'penilaian_pj_data';

    protected $fillable = [
        'kode_unit',
        'nama_tim',
        'tahun',
        'bulan',
        'nilai',
        'catatan',
        'penilai_id',
        'is_submitted',
        'aspek',
        'temuan_kesesuaian',
        'temuan_kelengkapan',
        'temuan_ketepatan',
        'temuan_validitas',
        'rekomendasi',
    ];

    protected $casts = [
        'aspek'       => 'array',
        'rekomendasi' => 'array',
    ];

    public function penilai()
    {
        return $this->belongsTo(User::class, 'penilai_id');
    }
}
