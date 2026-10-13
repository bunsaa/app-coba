<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiDataIndikator extends Model
{
    protected $table = 'validasi_data_indikators';

    protected $fillable = [
        'kode_unit',
        'nama_tim',
        'tahun',
        'triwulan',
        'detail',
        'jumlah_kesesuaian',
        'persentase_kesesuaian',
        'is_valid',
        'is_required',
        'pj_data_1_id',
        'pj_data_2_id',
        'pj1_submitted_at',
        'pj2_submitted_at',
        'is_submitted',
    ];

    protected $casts = [
        'detail'       => 'array',
        'is_valid'     => 'boolean',
        'is_required'  => 'boolean',
        'is_submitted' => 'boolean',
        'pj1_submitted_at' => 'datetime',
        'pj2_submitted_at' => 'datetime',
    ];

    public function pjData1()
    {
        return $this->belongsTo(User::class, 'pj_data_1_id');
    }

    public function pjData2()
    {
        return $this->belongsTo(User::class, 'pj_data_2_id');
    }
}
