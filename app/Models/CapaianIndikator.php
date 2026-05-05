<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Request;
class CapaianIndikator extends Model
{
    protected $table = 'capaian_indikators';
    
    protected $fillable = [
        'indikator_id',
        'kode_unit',
        'tahun',
        'jan_n', 'jan_d', 'feb_n', 'feb_d', 'mar_n', 'mar_d',
        'apr_n', 'apr_d', 'may_n', 'may_d', 'jun_n', 'jun_d',
        'jul_n', 'jul_d', 'aug_n', 'aug_d', 'sep_n', 'sep_d',
        'oct_n', 'oct_d', 'nov_n', 'nov_d', 'des_n', 'des_d',
        'jan_validated', 'feb_validated', 'mar_validated',
        'apr_validated', 'may_validated', 'jun_validated',
        'jul_validated', 'aug_validated', 'sep_validated',
        'oct_validated', 'nov_validated', 'des_validated',
        'jan_approved', 'feb_approved', 'mar_approved',
        'apr_approved', 'may_approved', 'jun_approved',
        'jul_approved', 'aug_approved', 'sep_approved',
        'oct_approved', 'nov_approved', 'des_approved',
        'jan_rejected', 'feb_rejected', 'mar_rejected',
        'apr_rejected', 'may_rejected', 'jun_rejected',
        'jul_rejected', 'aug_rejected', 'sep_rejected',
        'oct_rejected', 'nov_rejected', 'des_rejected',
        'jan_reject_reason', 'feb_reject_reason', 'mar_reject_reason',
        'apr_reject_reason', 'may_reject_reason', 'jun_reject_reason',
        'jul_reject_reason', 'aug_reject_reason', 'sep_reject_reason',
        'oct_reject_reason', 'nov_reject_reason', 'des_reject_reason',
        'jan_rejected_n', 'jan_rejected_d', 'jan_rejected_at',
        'feb_rejected_n', 'feb_rejected_d', 'feb_rejected_at',
        'mar_rejected_n', 'mar_rejected_d', 'mar_rejected_at',
        'apr_rejected_n', 'apr_rejected_d', 'apr_rejected_at',
        'may_rejected_n', 'may_rejected_d', 'may_rejected_at',
        'jun_rejected_n', 'jun_rejected_d', 'jun_rejected_at',
        'jul_rejected_n', 'jul_rejected_d', 'jul_rejected_at',
        'aug_rejected_n', 'aug_rejected_d', 'aug_rejected_at',
        'sep_rejected_n', 'sep_rejected_d', 'sep_rejected_at',
        'oct_rejected_n', 'oct_rejected_d', 'oct_rejected_at',
        'nov_rejected_n', 'nov_rejected_d', 'nov_rejected_at',
        'des_rejected_n', 'des_rejected_d', 'des_rejected_at',
        'approved_by', 'approved_at',
        'analisis_q1', 'rtl_q1',
        'analisis_q2', 'rtl_q2',
        'analisis_q3', 'rtl_q3',
        'analisis_q4', 'rtl_q4',
        'jan_analisis', 'jan_rtl', 'jan_rekomendasi',
        'feb_analisis', 'feb_rtl', 'feb_rekomendasi',
        'mar_analisis', 'mar_rtl', 'mar_rekomendasi',
        'apr_analisis', 'apr_rtl', 'apr_rekomendasi',
        'may_analisis', 'may_rtl', 'may_rekomendasi',
        'jun_analisis', 'jun_rtl', 'jun_rekomendasi',
        'jul_analisis', 'jul_rtl', 'jul_rekomendasi',
        'aug_analisis', 'aug_rtl', 'aug_rekomendasi',
        'sep_analisis', 'sep_rtl', 'sep_rekomendasi',
        'oct_analisis', 'oct_rtl', 'oct_rekomendasi',
        'nov_analisis', 'nov_rtl', 'nov_rekomendasi',
        'des_analisis', 'des_rtl', 'des_rekomendasi',
        'rejection_history',
        'komentar',
        'komentar_dibaca',
    ];

    protected $casts = [
        'jan_validated' => 'boolean',
        'feb_validated' => 'boolean',
        'mar_validated' => 'boolean',
        'apr_validated' => 'boolean',
        'may_validated' => 'boolean',
        'jun_validated' => 'boolean',
        'jul_validated' => 'boolean',
        'aug_validated' => 'boolean',
        'sep_validated' => 'boolean',
        'oct_validated' => 'boolean',
        'nov_validated' => 'boolean',
        'des_validated' => 'boolean',
        'jan_approved' => 'boolean',
        'feb_approved' => 'boolean',
        'mar_approved' => 'boolean',
        'apr_approved' => 'boolean',
        'may_approved' => 'boolean',
        'jun_approved' => 'boolean',
        'jul_approved' => 'boolean',
        'aug_approved' => 'boolean',
        'sep_approved' => 'boolean',
        'oct_approved' => 'boolean',
        'nov_approved' => 'boolean',
        'des_approved' => 'boolean',
        'jan_rejected' => 'boolean',
        'feb_rejected' => 'boolean',
        'mar_rejected' => 'boolean',
        'apr_rejected' => 'boolean',
        'may_rejected' => 'boolean',
        'jun_rejected' => 'boolean',
        'jul_rejected' => 'boolean',
        'aug_rejected' => 'boolean',
        'sep_rejected' => 'boolean',
        'oct_rejected' => 'boolean',
        'nov_rejected' => 'boolean',
        'des_rejected' => 'boolean',
        'approved_at' => 'datetime',
        'jan_rejected_at' => 'datetime',
        'feb_rejected_at' => 'datetime',
        'mar_rejected_at' => 'datetime',
        'apr_rejected_at' => 'datetime',
        'may_rejected_at' => 'datetime',
        'jun_rejected_at' => 'datetime',
        'jul_rejected_at' => 'datetime',
        'aug_rejected_at' => 'datetime',
        'sep_rejected_at' => 'datetime',
        'oct_rejected_at' => 'datetime',
        'nov_rejected_at' => 'datetime',
        'des_rejected_at' => 'datetime',
        'komentar_dibaca' => 'boolean',
        'rejection_history' => 'array',
    ];
    
    public function indikator()
    {
        return $this->belongsTo(Indikator::class);
    }
    
    public function unit()
    {
        return $this->belongsTo(Units::class, 'kode_unit', 'kode_unit');
    }
    
    public function lampiran()
    {
        return $this->hasMany(CapaianLampiran::class, 'capaian_id');
    }
   
}