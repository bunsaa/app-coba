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
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Services\RekomendasiService;

class CapaianIndikatorController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Check user role - support both new role field and legacy email check
        $isAdminMutu = $user->role === 'admin_mutu' || $user->email === 'admin@mutu.rsud.go.id';
        $isKepalaUnit = $user->role === 'kepala_unit';
        $isPjData = str_starts_with($user->role ?? '', 'PJ Data - ');

        // Get all units for dropdown
        $units = Units::with('tim_units')->orderBy('nama_unit', 'asc')->get();

        // Auto-select unit for non-admin users
        $selectedUnitCode = $request->input('unit', null);

        if (!$selectedUnitCode && !$isAdminMutu) {
            // First try to use kode_unit from user record
            if ($user->kode_unit) {
                $selectedUnitCode = $user->kode_unit;
            } else {
                // Fallback: Extract unit code from email (e.g., datin@mutu.rsud.go.id -> Datin)
                $emailPrefix = explode('@', $user->email)[0];

                // Cari unit berdasarkan kode_unit (case insensitive)
                $userUnit = Units::whereRaw('LOWER(kode_unit) = ?', [strtolower($emailPrefix)])->first();

                if ($userUnit) {
                    $selectedUnitCode = $userUnit->kode_unit;
                }
            }
        }

        $selectedUnit = $selectedUnitCode ? Units::with('tim_units')->where('kode_unit', $selectedUnitCode)->first() : null;

        // Determine if user can approve for this unit
        // Admin mutu can approve all, kepala unit can approve their own unit
        $canApprove = $isAdminMutu || ($isKepalaUnit && $user->kode_unit === $selectedUnitCode);

        // Get selected tim unit (if any)
        // PJ Data auto-select their own tim based on their role name
        $selectedTimUnit = $request->input('tim_unit', null);
        if (!$selectedTimUnit && $isPjData) {
            $selectedTimUnit = str_replace('PJ Data - ', '', $user->role);
        }

        // Get quarter and year from request or use current
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $currentQuarter = ceil($currentMonth / 3);
        $currentDay = Carbon::now()->day;

        // Hanya PJ Data dan admin yang boleh input data capaian
        $canInput = ($isPjData || $isAdminMutu) && $currentDay <= 29;

        $tahun = (int) $request->input('tahun', $currentYear);
        $quarter = (int) $request->input('quarter', $currentQuarter);

        // viewMonth: bulan yang sedang dilihat di approval summary (untuk navigasi bulan)
        $viewMonth = $request->input('viewMonth', $currentMonth);
        $viewMonth = max(1, min(12, (int) $viewMonth));
        
        $capaianData = [];
        
        // HANYA AMBIL DATA JIKA UNIT SUDAH DIPILIH
        if ($selectedUnitCode) {
            // Get indikators untuk unit ini
            $query = Indikator::where('kode_unit', $selectedUnitCode)
                ->where('is_active', true)
                ->where(function ($q) use ($quarter) {
                    $q->whereNull('berlaku_tw')
                      ->orWhereJsonContains('berlaku_tw', $quarter);
                })
                ->orderByRaw("FIELD(jenis_indikator, 'INM', 'SPM', 'PRIORITAS', 'IMUT_RS', 'IMUT_UNIT')")
                ->orderBy('indikator');

            // Filter by tim_unit
            $unitHasTim = $selectedUnit && $selectedUnit->tim_units->isNotEmpty();
            if ($selectedTimUnit) {
                $query->where('tim_unit', $selectedTimUnit);
            } elseif ($unitHasTim) {
                // Unit punya tim tapi belum dipilih → tidak tampilkan indikator dulu
                // (akan digantikan oleh panel pilih tim)
                $query->whereRaw('1 = 0');
            }
            // Jika unit tidak punya tim → tidak filter tim_unit, ambil semua indikator unit
            
            // Get indikators with capaian for this specific unit
            $indikators = $query->with(['capaian' => function($q) use ($tahun, $selectedUnitCode) {
                    $q->where('tahun', $tahun)
                      ->where('kode_unit', $selectedUnitCode); // Filter capaian by unit
                }, 'capaian.lampiran'])
                ->get();
            
            // Mapping quarter ke bulan
            $quarterMonths = [
                1 => ['jan', 'feb', 'mar'],
                2 => ['apr', 'may', 'jun'],
                3 => ['jul', 'aug', 'sep'],
                4 => ['oct', 'nov', 'des']
            ];
            
            $monthsToShow = $quarterMonths[$quarter];
            
            // Transform data untuk Vue
            $capaianData = $indikators->map(function($indikator) use ($selectedUnitCode, $tahun, $monthsToShow, $quarter) {
                $capaian = $indikator->capaian->first();
                
                if (!$capaian) {
                    $capaian = new CapaianIndikator([
                        'indikator_id' => $indikator->id,
                        'kode_unit' => $selectedUnitCode,
                        'tahun' => $tahun,
                    ]);
                }
                
                // Build month data only for current quarter
                $monthData = [];
                $validationStatus = [];
                $approvalStatus = [];
                $attachments = [];
                $monthlyKomentar = [];

                foreach($monthsToShow as $month) {
                    $monthData[$month] = [
                        'N' => $capaian->{$month . '_n'},
                        'D' => $capaian->{$month . '_d'},
                    ];
                    $validationStatus[$month] = [
                        'validated' => (bool) $capaian->{$month . '_validated'}
                    ];
                    $approvalStatus[$month] = [
                        'approved' => (bool) $capaian->{$month . '_approved'},
                        'rejected' => (bool) $capaian->{$month . '_rejected'},
                        'reject_reason' => $capaian->{$month . '_reject_reason'},
                        'rejected_n' => $capaian->{$month . '_rejected_n'},
                        'rejected_d' => $capaian->{$month . '_rejected_d'},
                        'rejected_at' => $capaian->{$month . '_rejected_at'} ? $capaian->{$month . '_rejected_at'}->format('d/m/Y H:i') : null,
                    ];

                    $lampiran = $capaian->lampiran ? $capaian->lampiran->where('bulan', $month)->first() : null;
                    $attachments[$month] = $lampiran ? $lampiran->file_name : null;

                    // Get monthly comment
                    $komentarField = "{$month}_komentar";
                    $komentarDibacaField = "{$month}_komentar_dibaca";
                    $monthlyKomentar[$month] = [
                        'komentar' => $capaian->{$komentarField} ?? null,
                        'dibaca' => (bool) ($capaian->{$komentarDibacaField} ?? false),
                        'revised' => (bool) ($capaian->{$month . '_revised'} ?? false),
                    ];
                }

                // Get analisis/RTL per month + history
                $monthlyAnalisis       = [];
                $monthlyAnalisisHistory = [];
                $allHistory            = $capaian->analisis_rtl_history ?? [];
                foreach($monthsToShow as $month) {
                    $monthlyAnalisis[$month] = [
                        'analisis' => $capaian->{$month . '_analisis'} ?? '',
                        'rtl' => $capaian->{$month . '_rtl'} ?? '',
                    ];
                    $monthlyAnalisisHistory[$month] = $allHistory[$month] ?? [];
                }

                return [
                    'id' => $indikator->id,
                    'capaian_id' => $capaian->id ?? null,
                    'jenis_indikator' => $indikator->jenis_indikator,
                    'is_prioritas' => (bool) $indikator->is_prioritas,
                    'indikator' => $indikator->indikator,
                    'standar' => $indikator->standar,
                    'standar_tw1' => $indikator->standar_tw1,
                    'satuan_tw1' => $indikator->satuan_tw1,
                    'satuan_waktu_tw1' => $indikator->satuan_waktu_tw1,
                    'standar_tw2' => $indikator->standar_tw2,
                    'satuan_tw2' => $indikator->satuan_tw2,
                    'satuan_waktu_tw2' => $indikator->satuan_waktu_tw2,
                    'standar_tw3' => $indikator->standar_tw3,
                    'satuan_tw3' => $indikator->satuan_tw3,
                    'satuan_waktu_tw3' => $indikator->satuan_waktu_tw3,
                    'standar_tw4' => $indikator->standar_tw4,
                    'satuan_tw4' => $indikator->satuan_tw4,
                    'satuan_waktu_tw4' => $indikator->satuan_waktu_tw4,
                    'satuan' => $indikator->satuan ?? 'persen',
                    'satuan_waktu' => $indikator->satuan_waktu,
                    'numeratorDesc' => $indikator->numerator,
                    'denominatorDesc' => $indikator->denominator,
                    'm' => $monthData,
                    'v' => $validationStatus,
                    'a' => $approvalStatus,
                    'att' => $attachments,
                    'komentar' => $monthlyKomentar,
                    'analisisRtl' => $monthlyAnalisis,
                    'analisisRtlHistory' => $monthlyAnalisisHistory,
                    'rejectionHistory' => $capaian->rejection_history ?? [],
                ];
            })->toArray();
        }

        // Get komentar data for indicators - check monthly comment fields
$komentarData = [];
if ($selectedUnitCode) {
    $activeIndikatorIds = Indikator::where('kode_unit', $selectedUnitCode)
        ->where('is_active', true)
        ->pluck('id')
        ->toArray();

    // Mapping quarter ke bulan
    $quarterMonths = [
        1 => ['jan', 'feb', 'mar'],
        2 => ['apr', 'may', 'jun'],
        3 => ['jul', 'aug', 'sep'],
        4 => ['oct', 'nov', 'des']
    ];
    $monthsToShow = $quarterMonths[$quarter];

    $capaianWithKomentar = CapaianIndikator::whereIn('indikator_id', $activeIndikatorIds)
        ->where('tahun', $tahun)
        ->get();

    foreach ($capaianWithKomentar as $capaian) {
        // Check if any month in current quarter has comment
        foreach ($monthsToShow as $month) {
            $komentarField = "{$month}_komentar";
            $komentarDibacaField = "{$month}_komentar_dibaca";

            if (!empty($capaian->{$komentarField})) {
                $komentarData[$capaian->indikator_id] = [
                    'komentar' => $capaian->{$komentarField},
                    'dibaca' => (bool) $capaian->{$komentarDibacaField},
                ];
                break; // Stop checking other months once we find a comment
            }
        }
    }
}

        // Get tim approval summary jika unit dipilih dan unit punya tim
        $timApprovalSummary = [];
        if ($selectedUnitCode && $selectedUnit && $selectedUnit->tim_units && count($selectedUnit->tim_units) > 0) {
            $viewMonthKeys = [1=>'jan',2=>'feb',3=>'mar',4=>'apr',5=>'may',6=>'jun',7=>'jul',8=>'aug',9=>'sep',10=>'oct',11=>'nov',12=>'des'];
            $monthKey = $viewMonthKeys[$viewMonth];

            // Pre-fetch PJ Data users untuk setiap tim (name lookup)
            $pjDataUserMap = User::where('kode_unit', $selectedUnitCode)
                ->where('role', 'like', 'PJ Data - %')
                ->get()
                ->mapWithKeys(function($u) {
                    $timName = str_replace('PJ Data - ', '', $u->role);
                    return [$timName => $u->name];
                });

            // Pre-fetch Penilai PJ Data users per tim (untuk menentukan apakah tim perlu validasi dulu)
            $penilaiTimMap = User::where('kode_unit', $selectedUnitCode)
                ->where('role', 'like', 'penilai_pj_data - %')
                ->get()
                ->mapWithKeys(function($u) {
                    $timName = str_replace('penilai_pj_data - ', '', $u->role);
                    return [$timName => true];
                });

            // Pre-fetch penilaian per-tim untuk bulan yang sedang dilihat (non-admin only)
            $timPenilaianMap = collect();
            if (!$isAdminMutu) {
                $timPenilaianMap = PenilaianPjData::where('kode_unit', $selectedUnitCode)
                    ->where('tahun', $tahun)
                    ->where('bulan', $viewMonth)
                    ->whereNotNull('nama_tim')
                    ->get()
                    ->keyBy('nama_tim');
            }

            // Pre-fetch ALL indikators for this unit's tims in ONE query (replaces N pluck queries)
            $allTimNames = $selectedUnit->tim_units->pluck('nama_tim')->toArray();
            $allIndikatorsByTim = Indikator::where('kode_unit', $selectedUnitCode)
                ->where('is_active', true)
                ->whereIn('tim_unit', $allTimNames)
                ->select(['id', 'tim_unit', 'indikator', 'jenis_indikator', 'is_prioritas'])
                ->get()
                ->groupBy('tim_unit');

            // Pre-fetch ALL capaian for those indikator IDs in ONE query (replaces 3N queries)
            $allIndikatorIds = $allIndikatorsByTim->flatten()->pluck('id')->toArray();
            $allCapaian = collect();
            if (!empty($allIndikatorIds)) {
                $allCapaian = CapaianIndikator::whereIn('indikator_id', $allIndikatorIds)
                    ->where('tahun', $tahun)
                    ->with('indikator:id,indikator')
                    ->get();
            }

            // Build indikator_id → jenis_indikator lookup for per-jenis filtering
            $indikatorJenisMap = $allIndikatorsByTim->flatten()->mapWithKeys(function($ind) {
                return [$ind->id => ['jenis' => $ind->jenis_indikator, 'is_prioritas' => (bool)$ind->is_prioritas]];
            });

            foreach ($selectedUnit->tim_units as $tim) {
                $timIndikators = $allIndikatorsByTim->get($tim->nama_tim, collect());
                $timIndikatorIds = $timIndikators->pluck('id')->toArray();
                $totalIndikator = count($timIndikatorIds);

                if ($totalIndikator > 0) {
                    // Filter capaian for this tim in memory
                    $timCapaian = $allCapaian->whereIn('indikator_id', $timIndikatorIds);

                    // Per-jenis total counts
                    $byJenis = [];
                    foreach ($timIndikators->groupBy('jenis_indikator') as $jenis => $inds) {
                        $byJenis[$jenis] = count($inds);
                    }
                    // PRIORITAS = jenis PRIORITAS + INM with is_prioritas=true
                    $byJenis['PRIORITAS'] = $timIndikators->filter(fn($i) =>
                        $i->jenis_indikator === 'PRIORITAS' ||
                        ($i->jenis_indikator === 'INM' && $i->is_prioritas)
                    )->count();
                    // Virtual PRIORITAS: INM with is_prioritas=true counted as extra row (same as virtual display)
                    $virtualPrioritasInds = $timIndikators->filter(fn($i) => $i->jenis_indikator === 'INM' && $i->is_prioritas);
                    $byJenis['PRIORITAS_VIRTUAL'] = $virtualPrioritasInds->count();

                    // Per-jenis approved/notApproved counts
                    $byJenisApproved = [];
                    $byJenisNotApproved = [];
                    foreach (array_keys($byJenis) as $jKey) {
                        if ($jKey === 'PRIORITAS_VIRTUAL') {
                            $jIdsRaw = $virtualPrioritasInds->pluck('id')->toArray();
                        } elseif ($jKey === 'PRIORITAS') {
                            $jIdsRaw = $timIndikators->filter(fn($i) => $i->jenis_indikator === 'PRIORITAS' || ($i->jenis_indikator === 'INM' && $i->is_prioritas))->pluck('id')->toArray();
                        } else {
                            $jIdsRaw = $timIndikators->where('jenis_indikator', $jKey)->pluck('id')->toArray();
                        }
                        $jCapaian = $timCapaian->whereIn('indikator_id', $jIdsRaw);
                        $byJenisApproved[$jKey] = $jCapaian->filter(fn($c) => $c->{$monthKey.'_approved'} && empty($c->{$monthKey.'_komentar'}))->count();
                        $byJenisNotApproved[$jKey] = $jCapaian->filter(fn($c) =>
                            $c->{$monthKey.'_n'} !== null && $c->{$monthKey.'_d'} !== null &&
                            (!$c->{$monthKey.'_approved'} || ($c->{$monthKey.'_approved'} && !empty($c->{$monthKey.'_komentar'})))
                        )->count();
                    }

                    $approvedCount = $timCapaian->filter(function($c) use ($monthKey) {
                        return $c->{$monthKey . '_approved'}
                            && empty($c->{$monthKey . '_komentar'});
                    })->count();

                    $notApprovedCount = $timCapaian->filter(function($c) use ($monthKey) {
                        return $c->{$monthKey . '_n'} !== null
                            && $c->{$monthKey . '_d'} !== null
                            && (
                                !$c->{$monthKey . '_approved'}
                                || ($c->{$monthKey . '_approved'} && !empty($c->{$monthKey . '_komentar'}))
                            );
                    })->count();

                    $rejectedCount = 0;
                    $rejectedIndicators = [];

                    $capaianWithHistory = $timCapaian->filter(function($c) use ($monthKey) {
                        return $c->{$monthKey . '_rejected_n'} !== null
                            || $c->{$monthKey . '_rejected'}
                            || !empty($c->rejection_history);
                    });

                    foreach ($capaianWithHistory as $capaian) {
                        $history = $capaian->rejection_history ?? [];
                        $monthHistory = array_filter($history, fn($entry) => ($entry['bulan'] ?? '') === $monthKey);

                        if (!empty($monthHistory)) {
                            $rejectedCount += count($monthHistory);
                            foreach ($monthHistory as $entry) {
                                $rejectedIndicators[] = [
                                    'nama' => ($capaian->indikator->indikator ?? 'Unknown') . ' (N:' . ($entry['n'] ?? '-') . ', D:' . ($entry['d'] ?? '-') . ')',
                                    'reason' => $entry['reason'] ?? 'Tidak ada alasan',
                                    'rejected_at' => $entry['at'] ?? null,
                                ];
                            }
                        } elseif ($capaian->{$monthKey . '_rejected_n'} !== null || $capaian->{$monthKey . '_rejected'}) {
                            $rejectedCount++;
                            $historyN = $capaian->{$monthKey . '_rejected_n'};
                            $historyD = $capaian->{$monthKey . '_rejected_d'};
                            $rejectedAt = $capaian->{$monthKey . '_rejected_at'};
                            $rejectedIndicators[] = [
                                'nama' => ($capaian->indikator->indikator ?? 'Unknown') . ($historyN !== null ? " (N:{$historyN}, D:{$historyD})" : ''),
                                'reason' => $capaian->{$monthKey . '_reject_reason'} ?? 'Tidak ada alasan',
                                'rejected_at' => $rejectedAt ? $rejectedAt->format('d/m/Y H:i') : null,
                            ];
                        }
                    }

                    $timPenilaian = $timPenilaianMap->get($tim->nama_tim);
                    $timApprovalSummary[] = [
                        'nama_tim' => $tim->nama_tim,
                        'pj_data_nama' => $pjDataUserMap->get($tim->nama_tim) ?? null,
                        'total_indikator' => $totalIndikator,
                        'approved' => $approvedCount,
                        'not_approved' => $notApprovedCount,
                        'rejected' => $rejectedCount,
                        'rejected_list' => $rejectedIndicators,
                        'by_jenis' => $byJenis,
                        'by_jenis_approved' => $byJenisApproved,
                        'by_jenis_not_approved' => $byJenisNotApproved,
                        'has_penilai' => $penilaiTimMap->has($tim->nama_tim),
                        'penilaian_pj' => $timPenilaian
                            ? PenilaianPjDataController::formatPenilaian($timPenilaian)
                            : null,
                    ];
                }
            }
        }
        
        // Fetch penilaian PJ data — hanya untuk non-admin (PJ Data & kepala bagian/bidang melihat)
        $penilaianPjData = [];
        if ($selectedUnitCode && !$isAdminMutu) {
            $quarterBulanMap = [1 => [1,2,3], 2 => [4,5,6], 3 => [7,8,9], 4 => [10,11,12]];
            $bulanList = $quarterBulanMap[$quarter];
            $penilaianQuery = PenilaianPjData::where('kode_unit', $selectedUnitCode)
                ->where('tahun', $tahun)
                ->whereIn('bulan', $bulanList);

            // PJ Data hanya melihat penilaian untuk tim mereka sendiri
            if ($isPjData) {
                $pjDataTimName = str_replace('PJ Data - ', '', $user->role);
                $penilaianQuery->where('nama_tim', $pjDataTimName);
            }

            $penilaianList = $penilaianQuery->get();
            foreach ($penilaianList as $p) {
                $penilaianPjData[$p->bulan] = PenilaianPjDataController::formatPenilaian($p);
            }
        }

        return Inertia::render('Capaian-Indikator', [
            'units' => $units,
            'selectedUnit' => $selectedUnit,
            'selectedTimUnit' => $selectedTimUnit,
            'capaianData' => $capaianData,
            'tahun' => $tahun,
            'quarter' => $quarter,
            'currentMonth' => $currentMonth,
            'currentQuarter' => $currentQuarter,
            'currentYear' => $currentYear,
            'currentDay' => $currentDay,
            'canInput' => $canInput,
            'komentarData' => $komentarData,
            'isAdmin' => $isAdminMutu,
            'canApprove' => $canApprove,
            'userRole' => $user->role ?? 'staf',
            'timApprovalSummary' => $timApprovalSummary,
            'viewMonth' => (int) $viewMonth,
            'penilaianPjData' => $penilaianPjData,
        ]);
    }

    // Save N/D data
    public function saveCapaian(Request $request)
    {
        $userRole = auth()->user()->role ?? '';
        $isAdminMutu = $userRole === 'admin_mutu' || auth()->user()->email === 'admin@mutu.rsud.go.id';
        $isPjData = str_starts_with($userRole, 'PJ Data - ');

        if (!$isPjData && !$isAdminMutu) {
            return response()->json(['error' => 'Hanya PJ Data yang dapat mengisi data capaian indikator'], 403);
        }

        $validated = $request->validate([
            'indikator_id' => 'required|exists:indikators,id',
            'kode_unit' => 'required|exists:units,kode_unit',
            'tahun' => 'required|integer',
            'bulan' => 'required|in:jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,des',
            'field' => 'required|in:N,D',
            'nilai' => 'required|integer|min:0',
        ]);

        // Mapping bulan string ke nomor bulan
        $bulanMap = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
            'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
            'sep' => 9, 'oct' => 10, 'nov' => 11, 'des' => 12
        ];

        $bulanInput = $bulanMap[$validated['bulan']];
        $tahunInput = $validated['tahun'];
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $currentDay = Carbon::now()->day;

        // VALIDASI: Hanya bisa input untuk bulan berjalan di tahun berjalan
        if ($tahunInput != $currentYear || $bulanInput != $currentMonth) {
            return response()->json(['error' => 'Input capaian indikator hanya dapat dilakukan pada bulan berjalan'], 403);
        }

        // VALIDASI: Hanya bisa input tanggal 1-29
        if ($currentDay > 29) {
            return response()->json(['error' => 'Jadwal input capaian indikator sudah ditutup'], 403);
        }

        $bulan = $validated['bulan'];
        $field = $validated['field'] === 'N' ? 'n' : 'd';
        $fieldName = $bulan . '_' . $field;

        $isNewRecord = !CapaianIndikator::where([
            'indikator_id' => $validated['indikator_id'],
            'kode_unit'    => $validated['kode_unit'],
            'tahun'        => $validated['tahun'],
        ])->exists();

        $capaian = CapaianIndikator::firstOrNew([
            'indikator_id' => $validated['indikator_id'],
            'kode_unit' => $validated['kode_unit'],
            'tahun' => $validated['tahun'],
        ]);

        // Isi nama_indikator saat pertama kali dibuat
        if ($isNewRecord && !$capaian->nama_indikator) {
            $indikatorModel = \App\Models\Indikator::find($validated['indikator_id']);
            $capaian->nama_indikator = $indikatorModel?->indikator;
            $capaian->created_by = auth()->user()->name;
        }

        // Selalu update updated_by setiap ada perubahan data
        $capaian->updated_by = auth()->user()->name;

        if ($capaian->{$bulan . '_validated'}) {
            return response()->json(['error' => 'Bulan ini sudah divalidasi, tidak dapat diubah'], 422);
        }

        // Jika ada komentar admin, simpan nilai lama ke prev lalu reset approval
        if ($capaian->{$bulan . '_approved'} && $capaian->{$bulan . '_komentar'}) {
            $capaian->{$bulan . '_' . $field . '_prev'} = $capaian->$fieldName;
            $capaian->{$bulan . '_approved'} = false;
        }
        // Fallback: jika komentar ada tapi prev belum tersimpan (komentar dikirim sebelum fitur ini)
        elseif ($capaian->{$bulan . '_komentar'} && $capaian->{$bulan . '_' . $field . '_prev'} === null) {
            $capaian->{$bulan . '_' . $field . '_prev'} = $capaian->$fieldName;
        }

        $capaian->$fieldName = $validated['nilai'];

        // Clear rejected status when new data is submitted (after rejection)
        if ($capaian->{$bulan . '_rejected'}) {
            $capaian->{$bulan . '_rejected'} = false;
        }

        $capaian->save();

        // Auto-fill: indikator dengan nama sama, unit sama, tim sama, tapi jenis berbeda
        $primaryIndicator = Indikator::find($validated['indikator_id']);
        $autoFilledIds = [];

        if ($primaryIndicator) {
            $relatedQuery = Indikator::where('kode_unit', $validated['kode_unit'])
                ->where('indikator', $primaryIndicator->indikator)
                ->where('id', '!=', $validated['indikator_id'])
                ->where('is_active', true);

            // Match tim_unit: jika null/kosong match keduanya, jika ada match exact
            if ($primaryIndicator->tim_unit) {
                $relatedQuery->where('tim_unit', $primaryIndicator->tim_unit);
            } else {
                $relatedQuery->where(function($q) {
                    $q->whereNull('tim_unit')->orWhere('tim_unit', '');
                });
            }

            foreach ($relatedQuery->get() as $rel) {
                $relCapaian = CapaianIndikator::firstOrNew([
                    'indikator_id' => $rel->id,
                    'kode_unit'    => $validated['kode_unit'],
                    'tahun'        => $validated['tahun'],
                ]);

                // Skip jika sudah divalidasi atau approve
                if ($relCapaian->{$bulan . '_validated'} || $relCapaian->{$bulan . '_approved'}) {
                    continue;
                }

                $relCapaian->$fieldName = $validated['nilai'];
                if ($relCapaian->{$bulan . '_rejected'}) {
                    $relCapaian->{$bulan . '_rejected'} = false;
                }
                if (!$relCapaian->exists) {
                    $relCapaian->nama_indikator = $rel->indikator;
                    $relCapaian->created_by = auth()->user()->name;
                }
                $relCapaian->updated_by = auth()->user()->name;
                $relCapaian->save();
                $autoFilledIds[] = $rel->id;
            }
        }

        return response()->json(['success' => true, 'capaian' => $capaian, 'autoFilledIds' => $autoFilledIds]);
    }

    // Save Analisis & RTL per month (PJ Data, kepala_unit, dan admin_mutu)
    public function saveAnalisis(Request $request)
    {
        $user = auth()->user();
        $userRole = $user->role ?? '';
        $isAdminMutu = $userRole === 'admin_mutu' || $user->email === 'admin@mutu.rsud.go.id';
        $isPjData = str_starts_with($userRole, 'PJ Data - ') || $userRole === 'PJ Data';
        $isKepalaUnit = $userRole === 'kepala_unit';

        if (!$isPjData && !$isAdminMutu && !$isKepalaUnit) {
            return response()->json(['error' => 'Tidak memiliki kewenangan untuk mengisi analisis dan RTL'], 403);
        }

        $validated = $request->validate([
            'indikator_id' => 'required|exists:indikators,id',
            'kode_unit' => 'required|exists:units,kode_unit',
            'tahun' => 'required|integer',
            'bulan' => 'required|in:jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,des',
            'analisis' => 'nullable|string',
            'rtl' => 'nullable|string',
        ]);

        $capaian = CapaianIndikator::firstOrCreate([
            'indikator_id' => $validated['indikator_id'],
            'kode_unit' => $validated['kode_unit'],
            'tahun' => $validated['tahun'],
        ]);

        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $currentDay = Carbon::now()->day;

        // Mapping bulan string ke nomor bulan
        $bulanMap = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
            'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
            'sep' => 9, 'oct' => 10, 'nov' => 11, 'des' => 12
        ];
        $requestedMonth = $bulanMap[$validated['bulan']];

        // VALIDASI: Hanya bisa input untuk tahun dan bulan berjalan
        if ($validated['tahun'] != $currentYear || $requestedMonth != $currentMonth) {
            return response()->json(['error' => 'Analisis hanya bisa diedit pada bulan berjalan'], 422);
        }

        // VALIDASI: Hanya bisa input tanggal 1-29
        if ($currentDay > 29) {
            return response()->json(['error' => 'Jadwal input capaian indikator sudah ditutup'], 403);
        }

        // Check if month is already validated or approved
        $bulan = $validated['bulan'];
        if ($capaian->{$bulan . '_validated'}) {
            return response()->json(['error' => 'Bulan ini sudah divalidasi, tidak dapat diubah'], 422);
        }
        if ($capaian->{$bulan . '_approved'}) {
            // Jika ada komentar admin, izinkan re-edit dan reset approval
            if ($capaian->{$bulan . '_komentar'}) {
                $capaian->{$bulan . '_approved'} = false;
            } else {
                return response()->json(['error' => 'Bulan ini sudah di-approve, analisis/RTL tidak dapat diubah'], 422);
            }
        }

        // Simpan versi lama ke history sebelum overwrite
        $oldAnalisis = $capaian->{$bulan . '_analisis'};
        $oldRtl      = $capaian->{$bulan . '_rtl'};
        if (!empty($oldAnalisis) || !empty($oldRtl)) {
            $history           = $capaian->analisis_rtl_history ?? [];
            $history[$bulan]   = $history[$bulan] ?? [];
            array_unshift($history[$bulan], [
                'analisis'        => $oldAnalisis ?? '',
                'rtl'             => $oldRtl ?? '',
                'changed_at'      => now()->format('Y-m-d H:i:s'),
                'changed_by'      => $user->name,
                'changed_by_role' => $userRole,
            ]);
            $capaian->analisis_rtl_history = $history;
        }

        $capaian->{$bulan . '_analisis'} = $validated['analisis'];
        $capaian->{$bulan . '_rtl'} = $validated['rtl'];
        $capaian->updated_by = $user->name;
        $capaian->save();

        $analisisHistory = $capaian->analisis_rtl_history[$bulan] ?? [];
        return response()->json(['success' => true, 'analisisHistory' => $analisisHistory]);
    }

    // Upload lampiran
    public function uploadLampiran(Request $request)
    {
        $userRole = auth()->user()->role ?? '';
        $isAdminMutu = $userRole === 'admin_mutu' || auth()->user()->email === 'admin@mutu.rsud.go.id';
        $isPjData = str_starts_with($userRole, 'PJ Data - ');

        if (!$isPjData && !$isAdminMutu) {
            return response()->json(['error' => 'Hanya PJ Data yang dapat upload lampiran'], 403);
        }

        $validated = $request->validate([
            'indikator_id' => 'required|exists:indikators,id',
            'kode_unit' => 'required|exists:units,kode_unit',
            'tahun' => 'required|integer',
            'bulan' => 'required|in:jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,des',
            'file' => 'required|file|mimes:pdf,xlsx,xls|max:500', // Max 500KB, only PDF & Excel
        ], [
            'file.mimes' => 'File harus berformat PDF atau Excel (.pdf, .xlsx, .xls)',
            'file.max' => 'Ukuran file maksimal 500KB',
        ]);

        // Mapping bulan string ke nomor bulan
        $bulanMap = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
            'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
            'sep' => 9, 'oct' => 10, 'nov' => 11, 'des' => 12
        ];

        $bulanInput = $bulanMap[$validated['bulan']];
        $tahunInput = $validated['tahun'];
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $currentDay = Carbon::now()->day;

        // VALIDASI: Hanya bisa upload untuk bulan berjalan di tahun berjalan
        if ($tahunInput != $currentYear || $bulanInput != $currentMonth) {
            return response()->json(['error' => 'Upload lampiran hanya dapat dilakukan pada bulan berjalan'], 403);
        }

        // VALIDASI: Hanya bisa upload tanggal 1-29
        if ($currentDay > 29) {
            return response()->json(['error' => 'Jadwal input capaian indikator sudah ditutup'], 403);
        }

        // Get indikator info untuk nama file
        $indikator = Indikator::find($validated['indikator_id']);
        if (!$indikator) {
            return response()->json(['error' => 'Indikator tidak ditemukan'], 404);
        }

        $capaian = CapaianIndikator::firstOrCreate([
            'indikator_id' => $validated['indikator_id'],
            'kode_unit' => $validated['kode_unit'],
            'tahun' => $validated['tahun'],
        ]);

        $bulan = $validated['bulan'];
        if ($capaian->{$bulan . '_validated'}) {
            return response()->json(['error' => 'Bulan ini sudah divalidasi'], 422);
        }
        if ($capaian->{$bulan . '_approved'}) {
            return response()->json(['error' => 'Bulan ini sudah di-approve, tidak bisa upload lampiran'], 422);
        }

        // Cek apakah sudah ada lampiran untuk bulan ini (untuk tahu upload baru atau upload ulang)
        $oldLampiran = CapaianLampiran::where('capaian_id', $capaian->id)
            ->where('bulan', $bulan)
            ->first();

        $isReupload = false;
        if ($oldLampiran) {
            $isReupload = true;
            // Hapus file fisik lama dari storage
            if (Storage::disk('public')->exists($oldLampiran->file_path)) {
                Storage::disk('public')->delete($oldLampiran->file_path);
            }
            // Hapus record lama dari database
            $oldLampiran->delete();
        }

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();

        // Format nama file: kode_indikator_tim_unit_bulan_tahun.ext
        // Contoh: IND001_Diklat_jan_2025.pdf
        $indikatorCode = str_replace([' ', '/', '\\'], '_', substr($indikator->indikator, 0, 20)); // Ambil 20 karakter pertama
        $timUnit = $indikator->tim_unit ? str_replace([' ', ',', '&', '/', '\\'], '_', $indikator->tim_unit) : 'NoTim';
        $fileName = "{$indikatorCode}_{$timUnit}_{$bulan}_{$validated['tahun']}.{$extension}";

        $filePath = $file->storeAs('lampiran_capaian', $fileName, 'public');

        $lampiran = CapaianLampiran::create([
            'capaian_id' => $capaian->id,
            'bulan' => $bulan,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'file_name' => $lampiran->file_name,
            'file_url' => Storage::url($filePath),
            'is_reupload' => $isReupload
        ]);
    }

    public function validateBulan(Request $request)
    {
        $validated = $request->validate([
            'indikator_id' => 'required|exists:indikators,id',
            'kode_unit' => 'required|exists:units,kode_unit',
            'tahun' => 'required|integer',
            'bulan' => 'required|in:jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,des',
        ]);

        $capaian = CapaianIndikator::where([
            'indikator_id' => $validated['indikator_id'],
            'kode_unit' => $validated['kode_unit'],
            'tahun' => $validated['tahun'],
        ])->first();

        if (!$capaian) {
            return response()->json(['error' => 'Data capaian tidak ditemukan'], 404);
        }

        $bulan = $validated['bulan'];
        $capaian->{$bulan . '_validated'} = true;
        $capaian->save();

        return response()->json(['success' => true]);
    }

    public function getKomentarForUnit(Request $request)
    {
        $validated = $request->validate([
            'tahun' => 'required|integer',
            'kode_unit' => 'required|string',
        ]);
        
        $tahun = $validated['tahun'];
        $kodeUnit = $validated['kode_unit'];
        
        // Get all capaian yang punya komentar untuk unit ini
        $komentarList = CapaianIndikator::where('kode_unit', $kodeUnit)
            ->where('tahun', $tahun)
            ->whereNotNull('komentar')
            ->where('komentar', '!=', '')
            ->with('indikator')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function($capaian) {
                return [
                    'id' => $capaian->id,
                    'indikator_id' => $capaian->indikator_id,
                    'indikator_nama' => $capaian->indikator ? $capaian->indikator->indikator : 'Unknown',
                    'komentar' => $capaian->komentar,
                    'dibaca' => (bool) $capaian->komentar_dibaca,
                    'tanggal' => $capaian->updated_at->format('d M Y H:i'),
                ];
            });
        
        return response()->json($komentarList);
    }

    public function markKomentarDibacaCapaian(Request $request)
{
    $validated = $request->validate([
        'capaian_id' => 'required|exists:indikators,id', // Ini indikator_id
        'kode_unit' => 'required',
        'tahun' => 'required|integer',
        'bulan' => 'required|string', // bulan code like 'jan', 'feb', etc
    ]);

    $capaian = CapaianIndikator::where('indikator_id', $validated['capaian_id'])
        ->where('kode_unit', $validated['kode_unit'])
        ->where('tahun', $validated['tahun'])
        ->first();

    if (!$capaian) {
        return response()->json(['error' => 'Data tidak ditemukan'], 404);
    }

    $komentarDibacaField = "{$validated['bulan']}_komentar_dibaca";
    $capaian->{$komentarDibacaField} = true;
    $capaian->save();

    return response()->json(['success' => true]);
}

    public function markKomentarDibaca(Request $request)
    {
        $validated = $request->validate([
            'capaian_id' => 'required|exists:capaian_indikators,id',
        ]);

        $capaian = CapaianIndikator::find($validated['capaian_id']);

        if (!$capaian) {
            return response()->json(['error' => 'Data tidak ditemukan'], 404);
        }

        $capaian->komentar_dibaca = true;
        $capaian->save();

        return response()->json(['success' => true]);
    }

    public function downloadLampiran($filename)
    {
        $lampiran = CapaianLampiran::where('file_name', $filename)->first();

        if (!$lampiran) {
            abort(404, 'File tidak ditemukan');
        }

        $filePath = storage_path('app/public/' . $lampiran->file_path);

        if (!file_exists($filePath)) {
            abort(404, 'File tidak ditemukan di storage');
        }

        return response()->download($filePath, $lampiran->file_name);
    }

    public function viewLampiran($filename)
    {
        $lampiran = CapaianLampiran::where('file_name', $filename)->first();

        if (!$lampiran) {
            abort(404, 'File tidak ditemukan');
        }

        $filePath = storage_path('app/public/' . $lampiran->file_path);

        if (!file_exists($filePath)) {
            abort(404, 'File tidak ditemukan di storage');
        }

        return response()->file($filePath);
    }

    /**
     * Approve capaian for a specific indicator and month
     */
    public function approveCapaian(Request $request)
    {
        $validated = $request->validate([
            'indikator_id' => 'required|exists:indikators,id',
            'kode_unit' => 'required|exists:units,kode_unit',
            'tahun' => 'required|integer',
            'bulan' => 'required|in:jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,des',
        ]);

        $capaian = CapaianIndikator::where([
            'indikator_id' => $validated['indikator_id'],
            'kode_unit' => $validated['kode_unit'],
            'tahun' => $validated['tahun'],
        ])->first();

        if (!$capaian) {
            return response()->json(['error' => 'Data capaian tidak ditemukan'], 404);
        }

        $bulan = $validated['bulan'];

        // Check if data exists (N and D must have value)
        if ($capaian->{$bulan . '_n'} === null || $capaian->{$bulan . '_d'} === null) {
            return response()->json(['error' => 'Data N/D belum diisi'], 422);
        }

        $capaian->{$bulan . '_approved'} = true;
        $capaian->approved_by = auth()->id();
        $capaian->approved_at = now();
        $capaian->save();

        return response()->json(['success' => true]);
    }

    /**
     * Approve all capaian for current month in a unit
     */
    public function approveAllCapaian(Request $request)
    {
        $validated = $request->validate([
            'kode_unit' => 'required|exists:units,kode_unit',
            'tim_unit' => 'nullable|string',
            'tahun' => 'required|integer',
            'bulan' => 'required|in:jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,des',
        ]);

        $bulan = $validated['bulan'];

        // Get indikator IDs for this unit/tim
        $query = Indikator::where('kode_unit', $validated['kode_unit'])
            ->where('is_active', true);

        // Check if approving all tims or specific tim
        if ($validated['tim_unit'] === '__ALL__') {
            // Approve ALL tims — tidak filter tim_unit
        } elseif (!empty($validated['tim_unit'])) {
            $query->where('tim_unit', $validated['tim_unit']);
        }
        // Jika tim_unit kosong/null → unit tanpa tim, ambil semua indikator unit (tidak filter)

        $indikatorIds = $query->pluck('id')->toArray();

        if (empty($indikatorIds)) {
            return response()->json(['error' => 'Tidak ada indikator yang ditemukan'], 404);
        }

        // Update all capaian for these indicators
        $updated = CapaianIndikator::whereIn('indikator_id', $indikatorIds)
            ->where('tahun', $validated['tahun'])
            ->whereNotNull($bulan . '_n')
            ->whereNotNull($bulan . '_d')
            ->where($bulan . '_approved', false) // Hanya yang belum di-approve
            ->update([
                $bulan . '_approved' => true,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'updated' => $updated
        ]);
    }

    /**
     * Get indikator list for a tim with approval status (untuk modal approve)
     */
    public function getTimIndikatorForApproval(Request $request)
    {
        $validated = $request->validate([
            'kode_unit' => 'required|exists:units,kode_unit',
            'tim_unit' => 'required|string',
            'tahun' => 'required|integer',
            'bulan' => 'required|in:jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,des',
        ]);

        $bulan = $validated['bulan'];

        // Hitung TW dari bulan untuk standar/satuan per-TW
        $monthToTw = [
            'jan' => 1, 'feb' => 1, 'mar' => 1,
            'apr' => 2, 'may' => 2, 'jun' => 2,
            'jul' => 3, 'aug' => 3, 'sep' => 3,
            'oct' => 4, 'nov' => 4, 'des' => 4,
        ];
        $tw = $monthToTw[$bulan] ?? 1;

        $rekomendasiService = new RekomendasiService();

        // Get indikators - if tim_unit is 'all', get all indikators for this unit (for units without teams)
        $query = Indikator::where('kode_unit', $validated['kode_unit'])
            ->where('is_active', true)
            ->with(['capaian' => function($q) use ($validated) {
                $q->where('tahun', $validated['tahun'])
                  ->where('kode_unit', $validated['kode_unit']);
            }]);

        // Filter by tim_unit only if it's not 'all'
        if ($validated['tim_unit'] !== 'all') {
            $query->where('tim_unit', $validated['tim_unit']);
        }

        $indikators = $query->get();

        $result = $indikators->map(function($indikator) use ($bulan, $tw, $rekomendasiService) {
            $capaian = $indikator->capaian->first();

            $n = $capaian ? $capaian->{$bulan . '_n'} : null;
            $d = $capaian ? $capaian->{$bulan . '_d'} : null;

            $hasData = $capaian && $n !== null && $d !== null;

            $isApproved = $capaian && (bool) $capaian->{$bulan . '_approved'};
            $isValidated = $capaian && (bool) $capaian->{$bulan . '_validated'};
            $isRejected = $capaian && (bool) $capaian->{$bulan . '_rejected'};
            $rejectReason = $capaian ? $capaian->{$bulan . '_reject_reason'} : null;
            $komentar = $capaian ? ($capaian->{$bulan . '_komentar'} ?? null) : null;
            $hasKomentar = !empty($komentar);
            $isRevised = $capaian ? (bool) $capaian->{$bulan . '_revised'} : false;

            // Resolve standar/satuan per TW (fallback ke nilai lama)
            $standar = $indikator->{"standar_tw{$tw}"} ?? $indikator->standar ?? '';
            $satuan  = $indikator->{"satuan_tw{$tw}"}  ?? $indikator->satuan  ?? 'persen';

            $recommendation = $rekomendasiService->generate(
                $indikator->indikator,
                $standar,
                $n !== null ? (float) $n : null,
                $d !== null ? (float) $d : null,
                $satuan,
                $indikator->jenis_indikator ?? null
            );

            return [
                'id' => $indikator->id,
                'indikator' => $indikator->indikator,
                'standar' => $standar,
                'n' => $n,
                'd' => $d,
                'has_data' => $hasData,
                'approved' => $isApproved,
                'validated' => $isValidated,
                'rejected' => $isRejected,
                'reject_reason' => $rejectReason,
                'rejected_n' => $capaian ? $capaian->{$bulan . '_rejected_n'} : null,
                'rejected_d' => $capaian ? $capaian->{$bulan . '_rejected_d'} : null,
                'rejected_at' => $capaian && $capaian->{$bulan . '_rejected_at'} ? $capaian->{$bulan . '_rejected_at'}->format('d/m/Y H:i') : null,
                'komentar' => $komentar,
                'revised' => $isRevised,
                'can_approve' => $hasData && !$isValidated && (!$isApproved || $hasKomentar) && (!$hasKomentar || $isRevised),
                'can_reject' => $hasData && !$isApproved && !$isValidated,
                'recommendation' => $recommendation,
            ];
        });

        return response()->json($result);
    }

    /**
     * Get full indicator detail for modal view
     */
    public function getIndicatorDetail(Request $request)
    {
        $validated = $request->validate([
            'indikator_id' => 'required|exists:indikators,id',
            'kode_unit' => 'required|exists:units,kode_unit',
            'tahun' => 'required|integer',
            'bulan' => 'nullable|in:jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,des',
        ]);

        $indikator = Indikator::findOrFail($validated['indikator_id']);

        $capaian = CapaianIndikator::where('indikator_id', $validated['indikator_id'])
            ->where('kode_unit', $validated['kode_unit'])
            ->where('tahun', $validated['tahun'])
            ->with('lampiran')
            ->first();

        // Build months data
        $months = [];
        $approvals = [];
        $validations = [];
        $attachments = [];
        $komentars = [];
        $bulanList = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'des'];

        foreach ($bulanList as $bulan) {
            $months[$bulan] = [
                'N' => $capaian ? $capaian->{$bulan . '_n'} : null,
                'D' => $capaian ? $capaian->{$bulan . '_d'} : null,
                'N_prev' => $capaian ? $capaian->{$bulan . '_n_prev'} : null,
                'D_prev' => $capaian ? $capaian->{$bulan . '_d_prev'} : null,
            ];

            $approvals[$bulan] = [
                'approved' => $capaian ? (bool) $capaian->{$bulan . '_approved'} : false,
                'rejected' => $capaian ? (bool) $capaian->{$bulan . '_rejected'} : false,
                'reject_reason' => $capaian ? $capaian->{$bulan . '_reject_reason'} : null,
            ];

            $validations[$bulan] = [
                'validated' => $capaian ? (bool) $capaian->{$bulan . '_validated'} : false,
            ];

            // Lampiran from related table
            $lampiran = $capaian && $capaian->lampiran ? $capaian->lampiran->where('bulan', $bulan)->first() : null;
            $attachments[$bulan] = $lampiran ? $lampiran->file_name : null;

            // Komentar
            $komentars[$bulan] = [
                'komentar' => $capaian ? ($capaian->{$bulan . '_komentar'} ?? null) : null,
                'dibaca' => $capaian ? (bool) ($capaian->{$bulan . '_komentar_dibaca'} ?? false) : false,
            ];
        }

        // Build analisisRtl + history per month
        $analisisRtl        = [];
        $analisisRtlHistory = [];
        $allHistory         = $capaian ? ($capaian->analisis_rtl_history ?? []) : [];
        foreach ($bulanList as $bulan) {
            $analisisRtl[$bulan] = [
                'analisis' => $capaian ? ($capaian->{$bulan . '_analisis'} ?? '') : '',
                'rtl' => $capaian ? ($capaian->{$bulan . '_rtl'} ?? '') : '',
            ];
            $analisisRtlHistory[$bulan] = $allHistory[$bulan] ?? [];
        }

        // Compute recommendation for the requested month
        $recommendation = null;
        $bulanForRec = $validated['bulan'] ?? null;
        if ($bulanForRec) {
            $recN = $capaian ? $capaian->{$bulanForRec . '_n'} : null;
            $recD = $capaian ? $capaian->{$bulanForRec . '_d'} : null;
            $rekomendasiService = new RekomendasiService();
            $recommendation = $rekomendasiService->generate(
                $indikator->indikator,
                $indikator->standar,
                $recN !== null ? (float) $recN : null,
                $recD !== null ? (float) $recD : null,
                $indikator->satuan ?? 'persen',
                $indikator->jenis_indikator ?? null
            );
        }

        return response()->json([
            'id' => $indikator->id,
            'capaian_id' => $capaian ? $capaian->id : null,
            'indikator' => $indikator->indikator,
            'standar' => $indikator->standar,
            'standar_tw1' => $indikator->standar_tw1,
            'satuan_tw1' => $indikator->satuan_tw1,
            'satuan_waktu_tw1' => $indikator->satuan_waktu_tw1,
            'standar_tw2' => $indikator->standar_tw2,
            'satuan_tw2' => $indikator->satuan_tw2,
            'satuan_waktu_tw2' => $indikator->satuan_waktu_tw2,
            'standar_tw3' => $indikator->standar_tw3,
            'satuan_tw3' => $indikator->satuan_tw3,
            'satuan_waktu_tw3' => $indikator->satuan_waktu_tw3,
            'standar_tw4' => $indikator->standar_tw4,
            'satuan_tw4' => $indikator->satuan_tw4,
            'satuan_waktu_tw4' => $indikator->satuan_waktu_tw4,
            'numerator_desc' => $indikator->numerator,
            'denominator_desc' => $indikator->denominator,
            'satuan' => $indikator->satuan ?? 'persen',
            'months' => $months,
            'approvals' => $approvals,
            'validations' => $validations,
            'attachments' => $attachments,
            'komentars' => $komentars,
            'analisisRtl' => $analisisRtl,
            'analisisRtlHistory' => $analisisRtlHistory,
            'rejectionHistory' => $capaian ? ($capaian->rejection_history ?? []) : [],
            'recommendation' => $recommendation,
        ]);
    }

    /**
     * Mark indicator as revised by staf (after receiving admin komentar)
     */
    public function markRevised(Request $request)
    {
        $validated = $request->validate([
            'indikator_id' => 'required|exists:indikators,id',
            'kode_unit' => 'required|exists:units,kode_unit',
            'tahun' => 'required|integer',
            'bulan' => 'required|in:jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,des',
        ]);

        $bulan = $validated['bulan'];

        $capaian = CapaianIndikator::where('indikator_id', $validated['indikator_id'])
            ->where('kode_unit', $validated['kode_unit'])
            ->where('tahun', $validated['tahun'])
            ->first();

        if (!$capaian) {
            return response()->json(['error' => 'Data capaian tidak ditemukan'], 404);
        }

        if (!$capaian->{$bulan . '_komentar'}) {
            return response()->json(['error' => 'Tidak ada komentar admin untuk bulan ini'], 422);
        }

        $capaian->{$bulan . '_revised'} = true;
        $capaian->save();

        return response()->json(['success' => true]);
    }

    /**
     * Approve selected indicators
     */
    public function approveSelectedIndicators(Request $request)
    {
        $validated = $request->validate([
            'kode_unit' => 'required|exists:units,kode_unit',
            'tahun' => 'required|integer',
            'bulan' => 'required|in:jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,des',
            'indikator_ids' => 'required|array',
            'indikator_ids.*' => 'exists:indikators,id',
        ]);

        $bulan = $validated['bulan'];

        // Cek jika ada indikator yang punya komentar tapi belum direvisi
        $belumDirevisi = CapaianIndikator::whereIn('indikator_id', $validated['indikator_ids'])
            ->where('kode_unit', $validated['kode_unit'])
            ->where('tahun', $validated['tahun'])
            ->whereNotNull($bulan . '_komentar')
            ->where($bulan . '_komentar', '!=', '')
            ->where($bulan . '_revised', false)
            ->with('indikator:id,indikator')
            ->get();

        if ($belumDirevisi->isNotEmpty()) {
            $namaList = $belumDirevisi->map(fn($c) => $c->indikator->indikator ?? 'Unknown')->implode(', ');
            return response()->json([
                'error' => 'Beberapa indikator belum ditandai "Sudah Direvisi" oleh staf: ' . $namaList,
            ], 422);
        }

        // Update selected capaian - approve and clear any rejection
        $updated = CapaianIndikator::whereIn('indikator_id', $validated['indikator_ids'])
            ->where('kode_unit', $validated['kode_unit'])
            ->where('tahun', $validated['tahun'])
            ->whereNotNull($bulan . '_n')
            ->whereNotNull($bulan . '_d')
            ->where($bulan . '_approved', false)
            ->update([
                $bulan . '_approved' => true,
                $bulan . '_rejected' => false,
                $bulan . '_reject_reason' => null,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'updated' => $updated
        ]);
    }

    /**
     * Reject selected indicators
     */
    public function rejectSelectedIndicators(Request $request)
    {
        $validated = $request->validate([
            'kode_unit' => 'required|exists:units,kode_unit',
            'tahun' => 'required|integer',
            'bulan' => 'required|in:jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,des',
            'indikator_ids' => 'required|array',
            'indikator_ids.*' => 'exists:indikators,id',
            'reason' => 'nullable|string|max:1000',
        ]);

        $bulan = $validated['bulan'];
        $reason = $validated['reason'] ?? 'Ditolak oleh kepala unit';

        // Get capaian records - include those with data OR already rejected (to update history)
        $capaians = CapaianIndikator::whereIn('indikator_id', $validated['indikator_ids'])
            ->where('kode_unit', $validated['kode_unit'])
            ->where('tahun', $validated['tahun'])
            ->where($bulan . '_approved', false) // Only reject non-approved items
            ->get();

        $updated = 0;
        foreach ($capaians as $capaian) {
            $currentN = $capaian->{$bulan . '_n'};
            $currentD = $capaian->{$bulan . '_d'};

            // Only save history if there's data to save
            if ($currentN !== null || $currentD !== null) {
                // Append to rejection_history JSON array
                $history = $capaian->rejection_history ?? [];

                // Migrate existing single-column data if JSON history is empty
                // (backward compat: data from before JSON feature existed)
                if (empty($history)) {
                    $oldN = $capaian->{$bulan . '_rejected_n'};
                    $oldD = $capaian->{$bulan . '_rejected_d'};
                    $oldAt = $capaian->{$bulan . '_rejected_at'};
                    $oldReason = $capaian->{$bulan . '_reject_reason'};
                    if ($oldN !== null || $oldD !== null) {
                        $history[] = [
                            'bulan' => $bulan,
                            'n' => $oldN,
                            'd' => $oldD,
                            'reason' => $oldReason ?? 'Ditolak oleh kepala unit',
                            'at' => $oldAt ? $oldAt->format('d/m/Y H:i') : 'Waktu tidak tercatat',
                        ];
                    }
                }

                $history[] = [
                    'bulan' => $bulan,
                    'n' => $currentN,
                    'd' => $currentD,
                    'reason' => $reason,
                    'at' => now()->format('d/m/Y H:i'),
                ];
                $capaian->rejection_history = $history;

                // Also keep latest in single columns for backward compat
                $capaian->{$bulan . '_rejected_n'} = $currentN;
                $capaian->{$bulan . '_rejected_d'} = $currentD;
                $capaian->{$bulan . '_rejected_at'} = now();
            }

            // Set rejected status
            $capaian->{$bulan . '_rejected'} = true;
            $capaian->{$bulan . '_reject_reason'} = $reason;

            // Clear the N/D values so user can re-enter
            $capaian->{$bulan . '_n'} = null;
            $capaian->{$bulan . '_d'} = null;

            $capaian->save();
            $updated++;
        }

        return response()->json([
            'success' => true,
            'updated' => $updated
        ]);
    }
}