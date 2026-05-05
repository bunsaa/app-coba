<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Units extends Model
{
    protected $primaryKey = 'id';
    protected $table = 'units';
    
    protected $fillable = [
        'kode_unit',
        'nama_unit',
        'alias',
    ];

    public function tim_units()
    {
        return $this->hasMany(TimUnit::class, 'kode_unit', 'kode_unit');
    }

    // Tambahkan relationship ke indikators
    public function indikators()
    {
        return $this->hasMany(Indikator::class, 'kode_unit', 'kode_unit');
    }
}