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