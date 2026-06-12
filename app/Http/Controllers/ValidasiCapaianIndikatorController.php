<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Units;
use App\Models\TimUnits;
use App\Models\Indikator;
use App\Models\CapaianIndikator;
use App\Models\CapaianLampiran;
use App\Models\PenilaianPjData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ValidasiCapaianIndikatorController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isAdminMutu = ($user->role === 'admin_mutu' || $user->email === 'admin@mutu.rsud.go.id');

        // Get selected month/year from request or use current
        $bulanDipilih = $request->input('bulan', Carbon::now()->month);
        $tahunDipilih = $request->input('tahun', Carbon::now()->year);
        
        // Current month for validation check
        $bulanSekarang = Carbon::now()->month;
        $tahunSekarang = Carbon::now()->year;
        
        // Check if selected month is current month
        $isBulanBerjalan = ($bulanDipilih == $bulanSekarang && $tahunDipilih == $tahunSekarang);

        // Check if validation window is open (hanya sampai akhir bulan berjalan)
        $today = Carbon::now();
        $tanggalBatasValidasi = Carbon::createFromDate($tahunSekarang, $bulanSekarang, 1)
            ->endOfMonth()
            ->endOfDay();

        $validasiTerbuka = $today->lte($tanggalBatasValidasi) && $isBulanBerjalan;

        // Mapping bulan
        $bulanField = [
            1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr',
            5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug',
            9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'des'
        ];
        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $bulan = $bulanField[$bulanDipilih];
        
        // Get ALL units (including those without indicators)
        $units = Units::with(['tim_units'])->orderBy('nama_unit', 'asc')->get();

        // Transform data - GROUP BY UNIT ONLY
        $dataValidasi = [];
        
        foreach ($units as $unit) {
            // Get ALL ACTIVE indikators for this unit
            // Sekarang menggunakan multiple records (kode_unit terpisah per record)
            $indikators = Indikator::where('kode_unit', $unit->kode_unit)
                ->where('is_active', true)
                ->with(['capaian' => function($q) use ($tahunDipilih, $unit) {
                    $q->where('tahun', $tahunDipilih)
                      ->where('kode_unit', $unit->kode_unit);
                }])
                ->get();
            
            // Count approved and validated indicators for selected month
            $approvedCount = 0;
            $validatedCount = 0;
            foreach ($indikators as $indikator) {
                $capaian = $indikator->capaian->first();
                if ($capaian && $capaian->{$bulan . '_approved'}) {
                    $approvedCount++;
                    if ($capaian->{$bulan . '_validated'}) {
                        $validatedCount++;
                    }
                }
            }

            // ADD UNIT TO LIST
            $dataValidasi[] = [
                'unit_id' => $unit->id,
                'unit_kode' => $unit->kode_unit,
                'unit_nama' => $unit->nama_unit,
                'indikator_count' => $indikators->count(),
                'approved_count' => $approvedCount,
                'validated_count' => $validatedCount,
                'has_tim_unit' => $unit->tim_units && $unit->tim_units->count() > 0,
                'tim_units' => $unit->tim_units ? $unit->tim_units->pluck('nama_tim')->toArray() : [],
            ];
        }

        // Fetch penilaian PJ data — hanya untuk non-admin (kepala bagian/bidang)
        if (!$isAdminMutu) {
            $penilaianPjList = PenilaianPjData::where('tahun', $tahunDipilih)
                ->where('bulan', $bulanDipilih)
                ->get()
                ->keyBy('kode_unit');

            foreach ($dataValidasi as &$item) {
                $penilaian = $penilaianPjList->get($item['unit_kode']);
                $item['penilaian_pj'] = $penilaian
                    ? PenilaianPjDataController::formatPenilaian($penilaian)
                    : null;
            }
            unset($item);
        } else {
            // Admin tidak melihat penilaian PJ
            foreach ($dataValidasi as &$item) {
                $item['penilaian_pj'] = null;
            }
            unset($item);
        }

        // Generate month options (12 months back from current)
        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $date = Carbon::now()->subMonths($i);
            $monthOptions[] = [
                'value' => $date->month,
                'label' => $namaBulan[$date->month] . ' ' . $date->year,
                'year' => $date->year,
            ];
        }

        return Inertia::render('Verifikasi-Capaian-Indikator', [
            'dataVerifikasi' => $dataValidasi,
            'bulanDipilih' => $bulanDipilih,
            'tahunDipilih' => $tahunDipilih,
            'namaBulanDipilih' => $namaBulan[$bulanDipilih],
            'bulanSekarang' => $bulanSekarang,
            'tahunSekarang' => $tahunSekarang,
            'isBulanBerjalan' => $isBulanBerjalan,
            'verifikasiTerbuka' => $validasiTerbuka,
            'tanggalBatas' => $tanggalBatasValidasi->format('d F Y'),
            'monthOptions' => $monthOptions,
            'isAdmin' => $isAdminMutu,
        ]);
    }

    /**
     * Get detail capaian for validation - HANYA YANG AKTIF
     */
    public function getDetailCapaian(Request $request)
    {
        $validated = $request->validate([
            'kode_unit' => 'required',
            'tim_unit' => 'nullable|string',
            'tahun' => 'required|integer',
            'bulan' => 'required|integer',
        ]);

        $bulanField = [
            1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr',
            5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug',
            9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'des'
        ];
        $bulan = $bulanField[$validated['bulan']];

        // Get indikators untuk unit ini
        // Sekarang menggunakan multiple records (kode_unit + tim_unit terpisah per record)
        $query = Indikator::where('kode_unit', $validated['kode_unit'])
            ->where('is_active', true);

        // Filter by tim_unit if selected
        // Jika tim_unit tidak dikirim (null/empty), ambil SEMUA indikator untuk unit ini
        if (!empty($validated['tim_unit'])) {
            $query->where('tim_unit', $validated['tim_unit']);
        }
        // ELSE: Tidak ada filter tim_unit, ambil semua (baik NULL maupun ada isinya)

        $indikators = $query->with(['capaian' => function($q) use ($validated) {
            $q->where('tahun', $validated['tahun'])
              ->where('kode_unit', $validated['kode_unit']); // Filter capaian by unit
        }, 'capaian.lampiran'])->get();

        // Hitung TW dari bulan (1-3 → TW1, 4-6 → TW2, 7-9 → TW3, 10-12 → TW4)
        $tw = (int) ceil($validated['bulan'] / 3);

        // Transform data - ONLY APPROVED indicators
        $capaianData = $indikators->filter(function($indikator) use ($bulan, $tw) {
            $capaian = $indikator->capaian->first();
            if (!$capaian || !$capaian->{$bulan . '_approved'}) return false;
            // Sembunyikan jika standar TW ini = '0'
            $standarTw = $indikator->{"standar_tw{$tw}"};
            if ($standarTw !== null && $standarTw !== '' && $standarTw === '0') return false;
            return true;
        })->map(function($indikator) use ($bulan, $validated, $tw) {
            $capaian = $indikator->capaian->first();

            $numerator = $capaian->{$bulan . '_n'};
            $denominator = $capaian->{$bulan . '_d'};
            $validated_status = (bool) $capaian->{$bulan . '_validated'};

            // Get lampiran for this month
            $lampiran = $capaian->lampiran ? $capaian->lampiran->where('bulan', $bulan)->first() : null;

            // Gunakan standar/satuan per TW (fallback ke nilai lama)
            $standar = $indikator->{"standar_tw{$tw}"} ?? $indikator->standar ?? '';
            $satuan = $indikator->{"satuan_tw{$tw}"} ?? $indikator->satuan ?? 'persen';
            $satuan_waktu = $indikator->{"satuan_waktu_tw{$tw}"} ?? $indikator->satuan_waktu;

            // Calculate hasil based on satuan TW
            $hasil = null;
            if ($numerator !== null && $denominator !== null && $denominator > 0) {
                if ($satuan === 'rata-rata') {
                    $hasil = $numerator / $denominator;
                } elseif ($satuan === 'permil') {
                    $hasil = ($numerator / $denominator) * 1000;
                } else {
                    $hasil = ($numerator / $denominator) * 100;
                }
            }

            // Get monthly comment fields
            $komentarField = "{$bulan}_komentar";
            $komentarDibacaField = "{$bulan}_komentar_dibaca";

            return [
    'id' => $indikator->id,
    'capaian_id' => $capaian->id ?? null,
    'indikator' => $indikator->indikator,
    'standar' => $standar,
    'satuan' => $satuan,
    'satuan_waktu' => $satuan_waktu,
    'tim_unit' => $indikator->tim_unit,
    'numerator' => $numerator,
    'denominator' => $denominator,
    'hasil' => $hasil,
    'validated' => $validated_status,
    'lampiran' => $lampiran ? [
        'file_name' => $lampiran->file_name,
        'file_url' => asset('storage/' . $lampiran->file_path),
        'download_url' => route('capaian-indikator.lampiran.download', ['filename' => $lampiran->file_name]),
    ] : null,
    'komentar' => $capaian->{$komentarField} ?? '',
    'komentar_dibaca' => (bool) ($capaian->{$komentarDibacaField} ?? false),
    'komentar_history' => $capaian->komentar_history ? json_decode($capaian->komentar_history, true) : [],
    'analisis' => $capaian->{$bulan . '_analisis'} ?? '',
    'rtl' => $capaian->{$bulan . '_rtl'} ?? '',
    'rekomendasi' => $capaian->{$bulan . '_rekomendasi'} ?? '',
    'analisis_rtl_history' => $capaian->analisis_rtl_history[$bulan] ?? [],
];
        });

        return response()->json($capaianData->values());
    }

    public function validateSingle(Request $request)
    {
        $validated = $request->validate([
            'indikator_id' => 'required|exists:indikators,id',
            'kode_unit' => 'required',
            'tahun' => 'required|integer',
            'bulan' => 'required|integer',
        ]);

        // Check if validation is still allowed (only until end of current month)
        $bulanSekarang = Carbon::now()->month;
        $tahunSekarang = Carbon::now()->year;
        $today = Carbon::now();
        $tanggalBatasValidasi = Carbon::createFromDate($tahunSekarang, $bulanSekarang, 1)
            ->endOfMonth()
            ->endOfDay();

        $isBulanBerjalan = ($validated['bulan'] == $bulanSekarang && $validated['tahun'] == $tahunSekarang);
        $validasiTerbuka = $today->lte($tanggalBatasValidasi) && $isBulanBerjalan;

        if (!$validasiTerbuka) {
            return response()->json([
                'error' => 'Validasi hanya dapat dilakukan sampai akhir bulan berjalan'
            ], 403);
        }

        $bulanField = [
            1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr',
            5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug',
            9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'des'
        ];
        $bulan = $bulanField[$validated['bulan']];

        $capaian = CapaianIndikator::where('indikator_id', $validated['indikator_id'])
            ->where('kode_unit', $validated['kode_unit'])
            ->where('tahun', $validated['tahun'])
            ->first();

        if (!$capaian) {
            return response()->json(['error' => 'Data capaian tidak ditemukan'], 404);
        }

        // Check if approved first
        if (!$capaian->{$bulan . '_approved'}) {
            return response()->json(['error' => 'Data belum di-approve oleh kepala unit'], 403);
        }

        $capaian->{$bulan . '_validated'} = true;
        $capaian->save();

        return response()->json(['success' => true, 'message' => 'Indikator berhasil divalidasi']);
    }

    public function sendKomentar(Request $request)
    {
        $validated = $request->validate([
            'indikator_id' => 'required|exists:indikators,id',
            'kode_unit' => 'required',
            'tahun' => 'required|integer',
            'bulan' => 'required|integer|min:1|max:12',
            'komentar' => 'required|string',
        ]);

        $bulanField = [
            1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr',
            5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug',
            9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'des'
        ];
        $bulan = $bulanField[$validated['bulan']];

        $capaian = CapaianIndikator::firstOrCreate([
            'indikator_id' => $validated['indikator_id'],
            'kode_unit' => $validated['kode_unit'],
            'tahun' => $validated['tahun'],
        ]);

        // Save comment to monthly field
        $komentarField = "{$bulan}_komentar";
        $komentarDibacaField = "{$bulan}_komentar_dibaca";

        // Simpan komentar lama ke history jika ada
        if ($capaian->{$komentarField}) {
            $history = $capaian->komentar_history ? json_decode($capaian->komentar_history, true) : [];
            $history[] = [
                'komentar' => $capaian->{$komentarField},
                'tanggal' => $capaian->updated_at->format('Y-m-d H:i:s'),
                'dibaca' => $capaian->{$komentarDibacaField} ?? false,
            ];
            $capaian->komentar_history = json_encode($history);
        }

        $capaian->{$komentarField} = $validated['komentar'];
        $capaian->{$komentarDibacaField} = false;

        // Reset approval agar unit bisa revisi dan approve ulang ke atasan
        // Simpan snapshot N/D sebelum revisi agar pimpinan bisa lihat perbandingan
        if ($capaian->{$bulan . '_approved'}) {
            $capaian->{$bulan . '_n_prev'} = $capaian->{$bulan . '_n'};
            $capaian->{$bulan . '_d_prev'} = $capaian->{$bulan . '_d'};
            $capaian->{$bulan . '_approved'} = false;
        }

        $capaian->save();

        return response()->json(['success' => true, 'message' => 'Komentar berhasil dikirim. Status approval di-reset, unit dapat melakukan revisi.']);
    }

    public function clearKomentar(Request $request)
    {
        $validated = $request->validate([
            'indikator_id' => 'required|exists:indikators,id',
            'kode_unit' => 'required',
            'tahun' => 'required|integer',
            'bulan' => 'required|integer|min:1|max:12',
        ]);

        $bulanField = [
            1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr',
            5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug',
            9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'des'
        ];
        $bulan = $bulanField[$validated['bulan']];

        $capaian = CapaianIndikator::where('indikator_id', $validated['indikator_id'])
            ->where('kode_unit', $validated['kode_unit'])
            ->where('tahun', $validated['tahun'])
            ->first();

        if (!$capaian) {
            return response()->json(['error' => 'Data tidak ditemukan'], 404);
        }

        // Clear monthly comment
        $komentarField = "{$bulan}_komentar";
        $komentarDibacaField = "{$bulan}_komentar_dibaca";

        $capaian->{$komentarField} = null;
        $capaian->{$komentarDibacaField} = false;
        $capaian->save();

        return response()->json(['success' => true]);
    }

    public function markKomentarDibaca(Request $request)
    {
        $validated = $request->validate([
            'indikator_id' => 'required|exists:indikators,id',
            'kode_unit' => 'required',
            'tahun' => 'required|integer',
        ]);

        $capaian = CapaianIndikator::where('indikator_id', $validated['indikator_id'])
            ->where('kode_unit', $validated['kode_unit'])
            ->where('tahun', $validated['tahun'])
            ->first();

        if (!$capaian) {
            return response()->json(['error' => 'Data tidak ditemukan'], 404);
        }

        $capaian->komentar_dibaca = true;
        $capaian->save();

        return response()->json(['success' => true]);
    }

    public function validateUnit(Request $request)
    {
        $validated = $request->validate([
            'kode_unit' => 'required',
            'tim_unit' => 'nullable|string',
            'tahun' => 'required|integer',
            'bulan' => 'required|integer',
        ]);

        // Check if validation is still allowed (only until end of current month)
        $bulanSekarang = Carbon::now()->month;
        $tahunSekarang = Carbon::now()->year;
        $today = Carbon::now();
        $tanggalBatasValidasi = Carbon::createFromDate($tahunSekarang, $bulanSekarang, 1)
            ->endOfMonth()
            ->endOfDay();

        $isBulanBerjalan = $validated['bulan'] == $bulanSekarang && $validated['tahun'] == $tahunSekarang;
        $validasiTerbuka = $today->lte($tanggalBatasValidasi) && $isBulanBerjalan;

        if (!$validasiTerbuka) {
            return response()->json([
                'error' => 'Validasi hanya dapat dilakukan sampai akhir bulan berjalan'
            ], 403);
        }

        $bulanField = [
            1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr',
            5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug',
            9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'des'
        ];
        $bulan = $bulanField[$validated['bulan']];

        // Get all ACTIVE indikators untuk unit ini
        // Sekarang menggunakan multiple records (kode_unit + tim_unit terpisah per record)
        $query = Indikator::where('kode_unit', $validated['kode_unit'])
            ->where('is_active', true);

        // Filter by tim_unit if selected
        // Jika tim_unit tidak dikirim (null/empty), validasi SEMUA tim_unit di unit ini
        if (!empty($validated['tim_unit'])) {
            $query->where('tim_unit', $validated['tim_unit']);
        }
        // ELSE: Tidak ada filter tim_unit, validasi semua indikator unit ini

        $indikators = $query->get();

        $successCount = 0;
        foreach ($indikators as $indikator) {
            $capaian = CapaianIndikator::where('indikator_id', $indikator->id)
                ->where('kode_unit', $validated['kode_unit'])
                ->where('tahun', $validated['tahun'])
                ->first();

            if ($capaian) {
                $capaian->{$bulan . '_validated'} = true;
                $capaian->save();
                $successCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$successCount} indikator berhasil divalidasi"
        ]);
    }

    public function validateAll(Request $request)
    {
        $validated = $request->validate([
            'tahun' => 'required|integer',
            'bulan' => 'required|integer',
        ]);

        // Check if validation is still allowed (only until end of current month)
        $bulanSekarang = Carbon::now()->month;
        $tahunSekarang = Carbon::now()->year;
        $today = Carbon::now();
        $tanggalBatasValidasi = Carbon::createFromDate($tahunSekarang, $bulanSekarang, 1)
            ->endOfMonth()
            ->endOfDay();

        $isBulanBerjalan = $validated['bulan'] == $bulanSekarang && $validated['tahun'] == $tahunSekarang;
        $validasiTerbuka = $today->lte($tanggalBatasValidasi) && $isBulanBerjalan;

        if (!$validasiTerbuka) {
            return response()->json([
                'error' => 'Validasi hanya dapat dilakukan sampai akhir bulan berjalan'
            ], 403);
        }

        $bulanField = [
            1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr',
            5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug',
            9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'des'
        ];
        $bulan = $bulanField[$validated['bulan']];
        $validatedField = $bulan . '_validated';

        $approvedField = $bulan . '_approved';

        // Get all ACTIVE indikator IDs
        $activeIndikatorIds = Indikator::where('is_active', true)->pluck('id')->toArray();

        // Update only ACTIVE and APPROVED indikators
        $updated = CapaianIndikator::where('tahun', $validated['tahun'])
            ->whereIn('indikator_id', $activeIndikatorIds)
            ->where($approvedField, true)
            ->update([$validatedField => true]);

        return response()->json([
            'success' => true,
            'message' => "Semua indikator yang sudah di-approve berhasil divalidasi ({$updated} records)"
        ]);
    }

    /**
     * Reject all approved indicators (remove approval)
     */
    public function rejectAll(Request $request)
    {
        $validated = $request->validate([
            'tahun' => 'required|integer',
            'bulan' => 'required|integer',
        ]);

        // Check if validation is still allowed (only until end of current month)
        $bulanSekarang = Carbon::now()->month;
        $tahunSekarang = Carbon::now()->year;
        $today = Carbon::now();
        $tanggalBatasValidasi = Carbon::createFromDate($tahunSekarang, $bulanSekarang, 1)
            ->endOfMonth()
            ->endOfDay();

        $isBulanBerjalan = $validated['bulan'] == $bulanSekarang && $validated['tahun'] == $tahunSekarang;
        $validasiTerbuka = $today->lte($tanggalBatasValidasi) && $isBulanBerjalan;

        if (!$validasiTerbuka) {
            return response()->json([
                'error' => 'Reject hanya dapat dilakukan sampai akhir bulan berjalan'
            ], 403);
        }

        $bulanField = [
            1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr',
            5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug',
            9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'des'
        ];
        $bulan = $bulanField[$validated['bulan']];
        $approvedField = $bulan . '_approved';
        $validatedField = $bulan . '_validated';
        $rejectedField = $bulan . '_rejected';

        // Get all ACTIVE indikator IDs
        $activeIndikatorIds = Indikator::where('is_active', true)->pluck('id')->toArray();

        // Reject only ACTIVE and APPROVED indikators (set approved=false, validated=false, rejected=true)
        $updated = CapaianIndikator::where('tahun', $validated['tahun'])
            ->whereIn('indikator_id', $activeIndikatorIds)
            ->where($approvedField, true)
            ->update([
                $approvedField => false,
                $validatedField => false,
                $rejectedField => true,
            ]);

        return response()->json([
            'success' => true,
            'message' => "Semua indikator yang sudah di-approve berhasil di-reject ({$updated} records)"
        ]);
    }

    public function saveAnalisisAdmin(Request $request)
    {
        $validated = $request->validate([
            'indikator_id' => 'required|exists:indikators,id',
            'kode_unit' => 'required',
            'tahun' => 'required|integer',
            'bulan' => 'required|integer|min:1|max:12',
            'analisis' => 'nullable|string',
            'rtl' => 'nullable|string',
        ]);

        // Map month number to month key
        $bulanMap = [
            1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr',
            5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug',
            9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'des',
        ];
        $bulanKey = $bulanMap[$validated['bulan']];

        $capaian = CapaianIndikator::firstOrCreate([
            'indikator_id' => $validated['indikator_id'],
            'kode_unit' => $validated['kode_unit'],
            'tahun' => $validated['tahun'],
        ]);

        // Simpan versi lama ke history sebelum overwrite
        $oldAnalisis = $capaian->{$bulanKey . '_analisis'};
        $oldRtl = $capaian->{$bulanKey . '_rtl'};
        if (!empty($oldAnalisis) || !empty($oldRtl)) {
            $user = Auth::user();
            $history = $capaian->analisis_rtl_history ?? [];
            if (!isset($history[$bulanKey])) {
                $history[$bulanKey] = [];
            }
            array_unshift($history[$bulanKey], [
                'analisis'        => $oldAnalisis ?? '',
                'rtl'             => $oldRtl ?? '',
                'changed_at'      => now()->format('Y-m-d H:i:s'),
                'changed_by'      => $user->name,
                'changed_by_role' => $user->role,
            ]);
            $capaian->analisis_rtl_history = $history;
        }

        $capaian->{$bulanKey . '_analisis'} = $validated['analisis'];
        $capaian->{$bulanKey . '_rtl'} = $validated['rtl'];
        $capaian->save();

        return response()->json([
            'success' => true,
            'message' => 'Analisis/RTL berhasil disimpan',
            'analisisHistory' => $capaian->analisis_rtl_history[$bulanKey] ?? [],
        ]);
    }

    public function generateRekomendasi(Request $request)
    {
        $validated = $request->validate([
            'indikator_id' => 'required|exists:indikators,id',
            'kode_unit'    => 'required',
            'tahun'        => 'required|integer',
            'bulan'        => 'required|integer|min:1|max:12',
        ]);

        $bulanMap = [
            1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr',
            5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug',
            9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'des',
        ];
        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $bulanKey = $bulanMap[$validated['bulan']];

        $indikator = Indikator::findOrFail($validated['indikator_id']);
        $capaian = CapaianIndikator::where('indikator_id', $validated['indikator_id'])
            ->where('kode_unit', $validated['kode_unit'])
            ->where('tahun', $validated['tahun'])
            ->first();

        if (!$capaian) {
            return response()->json(['error' => 'Data capaian tidak ditemukan'], 404);
        }

        $numerator   = $capaian->{$bulanKey . '_n'};
        $denominator = $capaian->{$bulanKey . '_d'};
        $tw          = (int) ceil($validated['bulan'] / 3);
        $satuan      = $indikator->{"satuan_tw{$tw}"} ?? $indikator->satuan ?? 'persen';
        $satuan_waktu = $indikator->{"satuan_waktu_tw{$tw}"} ?? $indikator->satuan_waktu;
        $standar      = $indikator->{"standar_tw{$tw}"} ?? $indikator->standar ?? '';

        $hasil = null;
        if ($numerator !== null && $denominator !== null && $denominator > 0) {
            if ($satuan === 'rata-rata') {
                $hasil = $numerator / $denominator;
            } elseif ($satuan === 'permil') {
                $hasil = ($numerator / $denominator) * 1000;
            } else {
                $hasil = ($numerator / $denominator) * 100;
            }
        }

        $hasilFormatted = $hasil !== null
            ? round($hasil, 2) . ($satuan === 'persen' ? '%' : ($satuan === 'permil' ? '‰' : ' ' . ($satuan_waktu ?? '')))
            : 'belum ada data';

        $prompt = <<<PROMPT
Kamu adalah sistem pendukung keputusan mutu layanan rumah sakit yang bertugas memberikan rekomendasi berdasarkan Permenkes Nomor 30 Tahun 2022 tentang Indikator Nasional Mutu Pelayanan Kesehatan serta standar akreditasi rumah sakit.

Berikan rekomendasi singkat dan spesifik (maksimal 4 kalimat) dalam Bahasa Indonesia untuk indikator mutu berikut:

- **Nama Indikator**: {$indikator->indikator}
- **Standar/Target**: {$standar}
- **Satuan**: {$satuan}
- **Numerator (definisi)**: {$indikator->numerator}
- **Denominator (definisi)**: {$indikator->denominator}
- **Nilai Numerator Bulan {$namaBulan[$validated['bulan']]}**: {$numerator}
- **Nilai Denominator Bulan {$namaBulan[$validated['bulan']]}**: {$denominator}
- **Capaian (Hasil)**: {$hasilFormatted}

Tentukan apakah capaian sudah memenuhi standar atau belum, lalu berikan:
- Jika **belum memenuhi standar**: rekomendasi konkret dan spesifik untuk meningkatkan capaian agar mencapai target standar yang ditetapkan.
- Jika **sudah memenuhi standar**: rekomendasi untuk mempertahankan dan meningkatkan capaian lebih baik lagi.

Fokus pada tindakan operasional yang dapat dilakukan oleh unit terkait. Referensikan regulasi yang relevan jika memungkinkan.
PROMPT;

        $apiKey = config('services.anthropic.api_key');
        if (!$apiKey) {
            return response()->json(['error' => 'API key Anthropic belum dikonfigurasi'], 500);
        }

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 512,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Gagal menghubungi Claude API: ' . $response->body()], 500);
            }

            $rekomendasi = $response->json('content.0.text') ?? '';

            // Simpan ke database
            $capaian->{$bulanKey . '_rekomendasi'} = $rekomendasi;
            $capaian->save();

            return response()->json(['success' => true, 'rekomendasi' => $rekomendasi]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}