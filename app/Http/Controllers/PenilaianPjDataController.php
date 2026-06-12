<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PenilaianPjData;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PenilaianPjDataController extends Controller
{
    // 12 aspek penilaian (urutan tetap)
    public static array $aspekItems = [
        'Petugas memahami definisi operasional indikator',
        'Terdapat dokumen definisi operasional di unit',
        'Data dikumpulkan sesuai metode yang ditetapkan',
        'Sumber data jelas (rekam medis/register/dll)',
        'Numerator sesuai dengan kriteria indikator',
        'Denominator sesuai dengan kriteria indikator',
        'Data lengkap (tidak ada yang kosong)',
        'Data akurat (sesuai dengan sumber data)',
        'Pengumpulan data dilakukan tepat waktu',
        'Data sudah direkap dan dihitung dengan benar',
        'Terdapat bukti verifikasi oleh Kepala Unit',
        'Terdapat analisis sederhana (trend/capaian)',
    ];

    /**
     * Hitung nilai otomatis dari array aspek.
     * Return: 'Baik' (>=80%), 'Cukup' (60-79%), 'Kurang' (<60%), null jika kosong
     */
    public static function hitungNilai(array $aspek): ?string
    {
        $total = count($aspek);
        if ($total === 0) return null;

        $ya = collect($aspek)->filter(fn($a) => ($a['nilai'] ?? null) === 'ya')->count();
        $pct = ($ya / $total) * 100;

        if ($pct >= 80) return 'Baik';
        if ($pct >= 60) return 'Cukup';
        return 'Kurang';
    }

    public function get(Request $request)
    {
        $validated = $request->validate([
            'kode_unit' => 'required|string',
            'nama_tim'  => 'nullable|string',
            'tahun'     => 'required|integer',
            'bulan'     => 'required|integer',
        ]);

        $namaTim = $validated['nama_tim'] ?? null;

        $query = PenilaianPjData::where('kode_unit', $validated['kode_unit'])
            ->where('tahun', $validated['tahun'])
            ->where('bulan', $validated['bulan']);

        if ($namaTim !== null) {
            $query->where('nama_tim', $namaTim);
        } else {
            $query->whereNull('nama_tim');
        }

        $penilaian = $query->first();

        return response()->json([
            'penilaian' => $penilaian ? self::formatPenilaian($penilaian) : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_unit'                      => 'required|string',
            'nama_tim'                       => 'nullable|string|max:100',
            'tahun'                          => 'required|integer',
            'bulan'                          => 'required|integer|min:1|max:12',
            'catatan'                        => 'nullable|string',
            'aspek'                          => 'nullable|array',
            'aspek.*.item'                   => 'required_with:aspek|string',
            'aspek.*.nilai'                  => 'nullable|in:ya,tidak',
            'temuan_kesesuaian'              => 'nullable|string|max:50',
            'temuan_kelengkapan'             => 'nullable|string|max:50',
            'temuan_ketepatan'               => 'nullable|string|max:50',
            'temuan_validitas'               => 'nullable|string|max:50',
            'rekomendasi'                    => 'nullable|array',
            'rekomendasi.*.rencana'          => 'nullable|string',
            'rekomendasi.*.penanggung_jawab' => 'nullable|string',
            'rekomendasi.*.target_waktu'     => 'nullable|string',
            'rekomendasi.*.status'           => 'nullable|string',
            'is_submitted'                   => 'nullable|boolean',
        ]);

        // Auto-hitung nilai dari aspek
        $nilai = null;
        if (!empty($validated['aspek'])) {
            $nilai = self::hitungNilai($validated['aspek']);
        }

        // updateOrCreate won't work for nullable columns (MySQL NULL != NULL in WHERE).
        // Build the query manually to handle nama_tim = NULL correctly.
        $namaTim = $validated['nama_tim'] ?? null;

        $query = PenilaianPjData::where('kode_unit', $validated['kode_unit'])
            ->where('tahun', $validated['tahun'])
            ->where('bulan', $validated['bulan']);

        if ($namaTim !== null) {
            $query->where('nama_tim', $namaTim);
        } else {
            $query->whereNull('nama_tim');
        }

        $penilaian = $query->first() ?? new PenilaianPjData([
            'kode_unit' => $validated['kode_unit'],
            'nama_tim'  => $namaTim,
            'tahun'     => $validated['tahun'],
            'bulan'     => $validated['bulan'],
        ]);

        // is_submitted hanya bisa true jika request mengizinkan dan belum pernah true sebelumnya
        // (sekali submitted tidak bisa di-unsubmit)
        $isSubmitted = $penilaian->is_submitted
            ? true
            : (bool) ($validated['is_submitted'] ?? false);

        // Partial update: only overwrite fields that were explicitly sent in the request.
        // This prevents a "status-only" update (e.g., validateFromDetail) from wiping
        // existing aspek / nilai / temuan data.
        $fillData = [
            'is_submitted' => $isSubmitted,
        ];

        if ($request->has('aspek')) {
            $fillData['aspek']      = $validated['aspek'] ?? null;
            $fillData['nilai']      = $nilai; // recalculated from aspek
            $fillData['penilai_id'] = Auth::id(); // only track who actually filled the form
        }
        foreach (['catatan', 'temuan_kesesuaian', 'temuan_kelengkapan', 'temuan_ketepatan', 'temuan_validitas', 'rekomendasi'] as $field) {
            if ($request->has($field)) {
                $fillData[$field] = $validated[$field] ?? null;
            }
        }

        $penilaian->fill($fillData);
        $penilaian->save();

        return response()->json([
            'success'   => true,
            'penilaian' => self::formatPenilaian($penilaian),
        ]);
    }

    public static function formatPenilaian(PenilaianPjData $p): array
    {
        // Kepala asli: kepala_unit dengan NIP format standar (bukan placeholder 4000000x)
        $kepala = User::where('kode_unit', $p->kode_unit)
            ->where('role', 'kepala_unit')
            ->whereNotNull('nip')
            ->where('nip', 'not like', '4%')
            ->first();

        // PJ Data: cari berdasarkan nama_tim jika ada, fallback ke PJ Data pertama unit
        $rolePjData = $p->nama_tim ? "PJ Data - {$p->nama_tim}" : null;
        $pjDataUser = $rolePjData
            ? User::where('kode_unit', $p->kode_unit)->where('role', $rolePjData)->first()
            : User::where('kode_unit', $p->kode_unit)->where('role', 'like', 'PJ Data - %')->first();

        // Penilai PJ Data resmi untuk tim ini (berdasarkan role, bukan penilai_id yang bisa ditimpa kepala)
        $penilaiPjRole = 'penilai_pj_data' . ($p->nama_tim ? ' - ' . $p->nama_tim : '');
        $penilaiPjUser = User::where('kode_unit', $p->kode_unit)
            ->where('role', $penilaiPjRole)
            ->first();

        // Fallback: siapa yang terakhir mengisi (dari penilai_id)
        $penilai = (!$penilaiPjUser && $p->penilai_id) ? User::find($p->penilai_id) : null;

        // Gunakan penilai_pj_data user jika ada, fallback ke penilai_id
        $resolvedPenilai = $penilaiPjUser ?? $penilai;

        return [
            'id'                  => $p->id,
            'nilai'               => $p->nilai,
            'catatan'             => $p->catatan,
            'aspek'               => $p->aspek,
            'temuan_kesesuaian'   => $p->temuan_kesesuaian,
            'temuan_kelengkapan'  => $p->temuan_kelengkapan,
            'temuan_ketepatan'    => $p->temuan_ketepatan,
            'temuan_validitas'    => $p->temuan_validitas,
            'rekomendasi'         => $p->rekomendasi,
            'nama_tim'            => $p->nama_tim,
            'kepala_nama'         => $kepala?->name ?? null,
            'kepala_nip'          => $kepala?->nip  ?? null,
            'pj_data_nama'        => $pjDataUser?->name ?? null,
            'pj_data_nip'         => $pjDataUser?->nip  ?? null,
            'penilai_nama'        => $resolvedPenilai?->name ?? null,
            'penilai_nip'         => $resolvedPenilai?->nip  ?? null,
            'penilai_role'        => $resolvedPenilai?->role ?? null,
            'is_submitted'        => (bool) $p->is_submitted,
            'updated_at'          => $p->updated_at ? $p->updated_at->toISOString() : null,
        ];
    }
}
