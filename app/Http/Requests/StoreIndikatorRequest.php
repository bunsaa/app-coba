<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIndikatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jenis_indikator' => 'required|in:SPM,INM,IMUT_RS,IMUT_UNIT,PRIORITAS',
            'kode_unit' => 'nullable|string|exists:units,kode_unit', // Nullable karena bisa multi-unit
            'tim_unit' => 'nullable|string',
            'indikator' => 'required|string', // Allow duplicate (1 indikator bisa untuk banyak unit)
            'standar' => 'required|string',
            'satuan' => 'required|in:rata-rata,persen,permil,kejadian,peserta,dokumen',
            'satuan_waktu' => 'nullable|in:hari,jam,menit|required_if:satuan,rata-rata',
            'pic' => 'nullable|string',
            'pic_units' => 'nullable|array',
            'pic_units.*' => 'string',
            'numerator' => 'required|string',
            'denominator' => 'required|string',
            'berlaku_tw' => 'nullable|array',
            'berlaku_tw.*' => 'integer|in:1,2,3,4',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('pic_units') && is_array($this->pic_units)) {
                foreach ($this->pic_units as $index => $picUnit) {
                    // Jika format "UNIT-01|Tim A", extract kode_unit
                    $kodeUnit = $picUnit;
                    if (strpos($picUnit, '|') !== false) {
                        $kodeUnit = explode('|', $picUnit)[0];
                    }

                    // Validasi kode_unit exists
                    if (!\App\Models\Units::where('kode_unit', $kodeUnit)->exists()) {
                        $validator->errors()->add(
                            "pic_units.{$index}",
                            "The selected pic_units.{$index} is invalid."
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'jenis_indikator.required' => 'Jenis indikator harus dipilih',
            'jenis_indikator.in' => 'Jenis indikator tidak valid',
            'kode_unit.required' => 'Kode unit harus diisi',
            'kode_unit.exists' => 'Unit tidak ditemukan',
            'indikator.required' => 'Indikator harus diisi',
            'standar.required' => 'Standar harus diisi',
            'satuan.required' => 'Satuan harus diisi',
            'satuan.in' => 'Satuan harus salah satu dari: rata-rata, persen, permil',
            'satuan_waktu.required_if' => 'Satuan waktu harus diisi untuk satuan rata-rata',
            'satuan_waktu.in' => 'Satuan waktu harus salah satu dari: hari, jam, menit',
            'pic.required' => 'PIC harus diisi',
            'numerator.required' => 'Numerator harus diisi',
            'denominator.required' => 'Denominator harus diisi',
        ];
    }
}