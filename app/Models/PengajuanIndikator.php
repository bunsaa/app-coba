<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengajuanIndikator extends Model
{
    protected $table = 'pengajuan_indikators';

    protected $fillable = [
        'user_id',
        'kode_unit',
        'nama_tim',
        'judul_indikator',
        'dasar_pemikiran',
        'dimensi_mutu',
        'tujuan',
        'definisi_operasional',
        'jenis_indikator',
        'numerator',
        'denominator',
        'target_tw1',
        'target_tw2',
        'target_tw3',
        'target_tw4',
        'satuan',
        'satuan_waktu',
        'kriteria_inklusi_eksklusi',
        'metode_pengumpulan_data',
        'sumber_data',
        'instrumen_pengambilan_data',
        'populasi_sampel',
        'penanggung_jawab',
        'skor_a',
        'skor_b',
        'skor_c',
        'skor_d',
        'skor_e',
        'total_skor',
        'status',
        'catatan_kepala',
        'catatan_admin',
        'reviewed_by_kepala',
        'reviewed_by_admin',
        'reviewed_at_kepala',
        'reviewed_at_admin',
        'indikator_id',
        'original_indikator_id',
        'request_type',
        'alasan_nonaktif',
    ];

    protected $casts = [
        'reviewed_at_kepala' => 'datetime',
        'reviewed_at_admin'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function unit()
    {
        return $this->belongsTo(Units::class, 'kode_unit', 'kode_unit');
    }

    public function indikator()
    {
        return $this->belongsTo(Indikator::class);
    }
}
