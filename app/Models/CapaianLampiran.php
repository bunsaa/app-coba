<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapaianLampiran extends Model
{
    protected $table = 'capaian_lampiran';
    
    protected $fillable = [
        'capaian_id',
        'bulan',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
    ];

    public function capaian()
    {
        return $this->belongsTo(CapaianIndikator::class, 'capaian_id');
    }
}