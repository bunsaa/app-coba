<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimUnit extends Model
{
    protected $primaryKey = 'id';
    protected $table = 'tim_units';
    protected $fillable = [
        'kode_unit',
        'nama_tim'
    ];

    // Relasi ke Unit
    public function unit()
    {
        return $this->belongsTo(Units::class, 'kode_unit', 'kode_unit');
    }
    
    // ❌ HAPUS relasi indikator() - tidak ada foreign key
    
    // ❌ HAPUS relasi capaian_indikators() - tidak ada foreign key
}