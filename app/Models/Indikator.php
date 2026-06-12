<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Indikator extends Model
{
    protected $table = 'indikators';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'jenis_indikator',
        'is_prioritas',
        'kode_unit',
        'tim_unit',
        'indikator',
        'standar',
        'satuan',
        'satuan_waktu',
        'standar_tw1','satuan_tw1','satuan_waktu_tw1',
        'standar_tw2','satuan_tw2','satuan_waktu_tw2',
        'standar_tw3','satuan_tw3','satuan_waktu_tw3',
        'standar_tw4','satuan_tw4','satuan_waktu_tw4',
        'pic',
        'pic_units',
        'numerator',
        'denominator',
        'is_active',
        'berlaku_tw',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'is_prioritas' => 'boolean',
        'pic_units'    => 'array',
        'berlaku_tw'   => 'array',
    ];

    protected $attributes = [
        'is_active' => true,
    ];
    
    public function unit()
    {
        return $this->belongsTo(Units::class, 'kode_unit', 'kode_unit');
    }
    
    public function capaian()
    {
        return $this->hasMany(CapaianIndikator::class);
    }
}