<?php

namespace App\Http\Controllers;

use App\Models\TimUnit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Indikator;
use App\Models\CapaianIndikator;
use App\Models\Units;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Jika mode perilaku, redirect ke halaman yang sesuai (bukan dashboard mutu)
        if (session('mode_akses') === 'perilaku') {
            if ($user->role === 'staf') {
                return redirect('/penilaian-perilaku-saya');
            }
            if ($user->role === 'kepala_unit') {
                return redirect('/penilaian-perilaku-pegawai');
            }
            return redirect('/manajemen-pegawai');
        }

        // Tentukan apakah user adalah admin
        $isAdmin = $user->role === 'admin_mutu' || $user->email === 'admin@mutu.rsud.go.id';

        // Untuk non-admin, dapatkan kode unit
        $userUnitCode = null;
        if (!$isAdmin) {
            if ($user->kode_unit) {
                $userUnitCode = $user->kode_unit;
            } else {
                $emailPrefix = explode('@', $user->email)[0];
                $userUnit = Units::whereRaw('LOWER(kode_unit) = ?', [strtolower($emailPrefix)])->first();
                if ($userUnit) {
                    $userUnitCode = $userUnit->kode_unit;
                }
            }
        }

        // Total indikator AKTIF — sama dengan logika rekap:
        // INM & PRIORITAS: dihitung distinct per nama (1 indikator shared ke banyak unit = 1)
        // SPM, IMUT_RS, IMUT_UNIT: setiap record dihitung 1
        $baseQuery = fn() => $isAdmin
            ? Indikator::where('is_active', true)
            : Indikator::where('is_active', true)->where('kode_unit', $userUnitCode);

        $totalIndikator =
            $baseQuery()->whereIn('jenis_indikator', ['INM', 'PRIORITAS'])->distinct('indikator')->count('indikator') +
            $baseQuery()->whereNotIn('jenis_indikator', ['INM', 'PRIORITAS'])->count();

        // Awal hari ini (00:00:00)
        $startOfToday = Carbon::today();

        // Total indikator AKTIF sampai akhir kemarin (logika sama)
        $baseQueryKemarin = fn() => $isAdmin
            ? Indikator::where('is_active', true)->where('created_at', '<', $startOfToday)
            : Indikator::where('is_active', true)->where('created_at', '<', $startOfToday)->where('kode_unit', $userUnitCode);

        $totalIndikatorKemarin =
            $baseQueryKemarin()->whereIn('jenis_indikator', ['INM', 'PRIORITAS'])->distinct('indikator')->count('indikator') +
            $baseQueryKemarin()->whereNotIn('jenis_indikator', ['INM', 'PRIORITAS'])->count();

        // Indikator baru AKTIF yang ditambahkan hari ini (filter berdasarkan unit untuk non-admin)
        $indikatorBaruQuery = Indikator::where('is_active', true)
            ->whereDate('created_at', $startOfToday)
            ->with('unit');
        if (!$isAdmin && $userUnitCode) {
            $indikatorBaruQuery->where('kode_unit', $userUnitCode);
        }
        $indikatorBaruData = $indikatorBaruQuery->get();

        // Hitung indikator baru yang UNIK (berdasarkan nama indikator)
        $indikatorBaru = $indikatorBaruData->pluck('indikator')->unique()->count();

        // Dapatkan daftar nama unit yang mendapat indikator baru (unik)
        $daftarUnitIndikatorBaru = $indikatorBaruData
            ->pluck('unit.nama_unit')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Hitung selisih
        $perubahanIndikator = $totalIndikator - $totalIndikatorKemarin;

        // ========== CAPAIAN BULANAN BERJALAN ==========
        $capaianBulanan = $this->hitungCapaianBulanan($isAdmin, $userUnitCode);

        // ========== CAPAIAN TRIWULAN (TW) ==========
        $capaianTriwulan = $this->hitungCapaianTriwulan($isAdmin, $userUnitCode);

        // ========== CAPAIAN TAHUNAN ==========
        $capaianTahunan = $this->hitungCapaianTahunan($isAdmin, $userUnitCode);

        // ========== AKTIVITAS TERBARU ==========
        $aktivitasTerbaru = $this->getAktivitasTerbaru($user);

        // ========== DATA GRAFIK ==========
        $grafikBulanan = $this->getGrafikCapaianBulanan($isAdmin, $userUnitCode);
        $dataCapaianBulananDetail = $this->getDataCapaianBulananDetail($isAdmin, $userUnitCode);

        // Get data for all 4 quarters
        $semuaGrafikTriwulanan = [];
        $semuaDataCapaianTriwulanDetail = [];
        for ($tw = 1; $tw <= 4; $tw++) {
            $semuaGrafikTriwulanan[$tw] = $this->getGrafikCapaianTriwulanan($isAdmin, $userUnitCode, $tw);
            $semuaDataCapaianTriwulanDetail[$tw] = $this->getDataCapaianTriwulanDetail($isAdmin, $userUnitCode, $tw);
        }

        // Current triwulan
        $triwulanSekarang = ceil(Carbon::now()->month / 3);

        // Get previous year annual data for download option
        $tahunSebelumnya = Carbon::now()->year - 1;
        $dataCapaianTahunanSebelumnya = $this->getDataCapaianTahunan($isAdmin, $userUnitCode, $tahunSebelumnya);
        $dataCapaianTahunanDetail = $this->getDataCapaianTahunanDetail($isAdmin, $userUnitCode, $tahunSebelumnya);

        // ========== RANKING DATA ==========
        $rankingData = $this->getRankingData($isAdmin, $userUnitCode);

        return Inertia::render('Dashboard', [
            'totalIndikator' => $totalIndikator,
            'indikatorBaru' => $indikatorBaru,
            'perubahanIndikator' => $perubahanIndikator,
            'totalIndikatorKemarin' => $totalIndikatorKemarin,
            'daftarUnitIndikatorBaru' => $daftarUnitIndikatorBaru,
            'capaianBulanan' => $capaianBulanan,
            'capaianTriwulan' => $capaianTriwulan,
            'capaianTahunan' => $capaianTahunan,
            'aktivitasTerbaru' => $aktivitasTerbaru,
            'grafikBulanan' => $grafikBulanan,
            'dataCapaianBulananDetail' => $dataCapaianBulananDetail,
            'semuaGrafikTriwulanan' => $semuaGrafikTriwulanan,
            'semuaDataCapaianTriwulanDetail' => $semuaDataCapaianTriwulanDetail,
            'triwulanSekarang' => $triwulanSekarang,
            'dataCapaianTahunanSebelumnya' => $dataCapaianTahunanSebelumnya,
            'dataCapaianTahunanDetail' => $dataCapaianTahunanDetail,
            'rankingData' => $rankingData,
        ]);
    }

    /**
     * Hitung capaian bulanan = rata-rata hasil (N/D × 100) semua indikator AKTIF
     * Untuk admin: semua unit
     * Untuk non-admin: hanya unit yang login
     */
    private function hitungCapaianBulanan($isAdmin = true, $userUnitCode = null)
    {
        $bulanSekarang = Carbon::now()->month;
        $tahunSekarang = Carbon::now()->year;

        $bulanField = [
            1 => 'jan',
            2 => 'feb',
            3 => 'mar',
            4 => 'apr',
            5 => 'may',
            6 => 'jun',
            7 => 'jul',
            8 => 'aug',
            9 => 'sep',
            10 => 'oct',
            11 => 'nov',
            12 => 'des'
        ];

        $fieldBulan = $bulanField[$bulanSekarang];
        $numeratorField = $fieldBulan . '_n';
        $denominatorField = $fieldBulan . '_d';

        // Total indikator AKTIF (filter berdasarkan unit untuk non-admin)
        $totalIndikatorAktifQuery = Indikator::where('is_active', true);
        if (!$isAdmin && $userUnitCode) {
            $totalIndikatorAktifQuery->where('kode_unit', $userUnitCode);
        }
        $totalIndikatorAktif = $totalIndikatorAktifQuery->count();

        // Total unit (untuk admin semua unit, untuk non-admin = 1)
        $totalUnits = $isAdmin ? Units::count() : 1;

        if ($totalIndikatorAktif == 0 || $totalUnits == 0) {
            return [
                'persentase' => 0,
                'indikatorTerlaporkan' => 0,
                'totalIndikator' => 0,
                'timUnitBelumMelaporkan' => 0,
                'daftarUnitBelumMelaporkan' => [],
                'daftarBelumMengisi' => [],
                'daftarBelumApprove' => [],
                'daftarBelumMengisiPerJenis' => [],
                'daftarBelumApprovePerJenis' => [],
                'belumAdaIndikator' => true,
                'bulan' => Carbon::now()->locale('id')->translatedFormat('F Y'),
            ];
        }

        // Get all ACTIVE indikator IDs (filter berdasarkan unit untuk non-admin)
        $activeIndikatorQuery = Indikator::where('is_active', true);
        if (!$isAdmin && $userUnitCode) {
            $activeIndikatorQuery->where('kode_unit', $userUnitCode);
        }
        $activeIndikatorIds = $activeIndikatorQuery->pluck('id')->toArray();

        // Field approve untuk bulan ini
        $approvedField = $fieldBulan . '_approved';

        // Hitung total hasil (N/D × 100) untuk bulan berjalan - SEMUA YANG SUDAH TERISI
        $capaianData = CapaianIndikator::where('tahun', $tahunSekarang)
            ->whereIn('indikator_id', $activeIndikatorIds)
            ->whereNotNull($numeratorField)
            ->whereNotNull($denominatorField)
            ->where($denominatorField, '>', 0)
            ->get();

        $totalHasil = 0;
        $jumlahCapaian = 0;

        foreach ($capaianData as $capaian) {
            $n = $capaian->{$numeratorField};
            $d = $capaian->{$denominatorField};

            if ($d > 0) {
                $hasil = ($n / $d) * 100;
                $totalHasil += $hasil;
                $jumlahCapaian++;
            }
        }

        // Persentase = total hasil / TOTAL SEMUA indikator aktif (terisi maupun belum)
        $persentaseCapaianBulanan = $totalIndikatorAktif > 0
            ? round($totalHasil / $totalIndikatorAktif, 2)
            : 0;

        // Total capaian yang harus masuk
        $totalLaporanYangHarusMasuk = $totalIndikatorAktif;

        // Field validasi untuk bulan ini
        $validatedField = $fieldBulan . '_validated';

        // Hitung jumlah yang sudah divalidasi
        $jumlahTervalidasi = CapaianIndikator::where('tahun', $tahunSekarang)
            ->whereIn('indikator_id', $activeIndikatorIds)
            ->where($validatedField, 1)
            ->count();

        // Cek apakah semua indikator sudah tervalidasi
        $semuaTervalidasi = $jumlahTervalidasi >= $totalIndikatorAktif;

        // Daftar tim/unit yang belum mengisi dan belum approve
        $daftarBelumMelaporkan = [];
        $daftarBelumMengisi = [];
        $daftarBelumApprove = [];

        // Per jenis indikator
        $daftarBelumMengisiPerJenis = [];
        $daftarBelumApprovePerJenis = [];

        $jenisLabels = [
            'SPM' => 'Indikator SPM',
            'INM' => 'Indikator Nasional Mutu',
            'IMUT_RS' => 'Indikator Mutu Prioritas RS',
            'IMUT_UNIT' => 'Indikator Mutu Unit',
        ];

        // BATCH: Ambil semua indikator aktif sekaligus (id, kode_unit, jenis_indikator, tim_unit)
        $allIndQ = Indikator::where('is_active', true)->select('id', 'kode_unit', 'jenis_indikator', 'tim_unit');
        if (!$isAdmin && $userUnitCode) {
            $allIndQ->where('kode_unit', $userUnitCode);
        }
        $allInds = $allIndQ->get();

        // Dapatkan semua jenis indikator yang aktif dari data yang sudah diambil
        $jenisIndikatorAktif = $allInds->pluck('jenis_indikator')->unique()->values()->toArray();

        // Semua ID indikator aktif (reuse untuk query capaian batch)
        $allIndIdsBatch = $allInds->pluck('id')->toArray();

        // BATCH: Semua indicator ID yang sudah terisi (N & D tidak null) untuk bulan ini
        $filledIndIds = !empty($allIndIdsBatch)
            ? DB::table('capaian_indikators')
                ->where('tahun', $tahunSekarang)
                ->whereIn('indikator_id', $allIndIdsBatch)
                ->whereNotNull($numeratorField)
                ->whereNotNull($denominatorField)
                ->pluck('indikator_id')
                ->flip()
                ->toArray()
            : [];

        // BATCH: Semua indicator ID yang sudah di-approve untuk bulan ini
        $approvedBatchIds = !empty($allIndIdsBatch)
            ? DB::table('capaian_indikators')
                ->where('tahun', $tahunSekarang)
                ->whereIn('indikator_id', $allIndIdsBatch)
                ->where($approvedField, true)
                ->pluck('indikator_id')
                ->flip()
                ->toArray()
            : [];

        // Bangun lookup map: [kode_unit][jenis] => [id, ...] dan [kode_unit][jenis][tim] => [id, ...]
        $indsByUnitJenis    = [];
        $indsByUnitJenisTim = [];
        foreach ($allInds as $ind) {
            $indsByUnitJenis[$ind->kode_unit][$ind->jenis_indikator][] = $ind->id;
            if ($ind->tim_unit) {
                $indsByUnitJenisTim[$ind->kode_unit][$ind->jenis_indikator][$ind->tim_unit][] = $ind->id;
            }
        }

        // Helper: hitung berapa ID dari daftar $ids ada di lookup set
        $countFilled   = fn(array $ids) => count(array_filter($ids, fn($id) => isset($filledIndIds[$id])));
        $countApproved = fn(array $ids) => count(array_filter($ids, fn($id) => isset($approvedBatchIds[$id])));

        if ($isAdmin) {
            // LOGIKA ADMIN: Cek per UNIT per JENIS INDIKATOR (tanpa N+1)
            $allUnits = Units::orderBy('nama_unit')->get();

            foreach ($jenisIndikatorAktif as $jenis) {
                $belumIsiJenis = [];
                $belumApproveJenis = [];

                foreach ($allUnits as $unit) {
                    $indIds = $indsByUnitJenis[$unit->kode_unit][$jenis] ?? [];
                    if (empty($indIds)) continue;

                    $totalUnit    = count($indIds);
                    $laporanMasuk = $countFilled($indIds);
                    $approved     = $countApproved($indIds);

                    if ($laporanMasuk < $totalUnit) {
                        $belumIsiJenis[] = $unit->nama_unit;
                        if (!in_array($unit->nama_unit, $daftarBelumMengisi)) {
                            $daftarBelumMengisi[] = $unit->nama_unit;
                        }
                        if (!in_array($unit->nama_unit, $daftarBelumMelaporkan)) {
                            $daftarBelumMelaporkan[] = $unit->nama_unit;
                        }
                    } elseif ($approved < $totalUnit) {
                        $belumApproveJenis[] = $unit->nama_unit;
                        if (!in_array($unit->nama_unit, $daftarBelumApprove)) {
                            $daftarBelumApprove[] = $unit->nama_unit;
                        }
                        if (!in_array($unit->nama_unit, $daftarBelumMelaporkan)) {
                            $daftarBelumMelaporkan[] = $unit->nama_unit;
                        }
                    }
                }

                $label = $jenisLabels[$jenis] ?? $jenis;
                if (count($belumIsiJenis) > 0) {
                    $daftarBelumMengisiPerJenis[] = [
                        'jenis' => $label,
                        'units' => $belumIsiJenis,
                    ];
                }
                if (count($belumApproveJenis) > 0) {
                    $daftarBelumApprovePerJenis[] = [
                        'jenis' => $label,
                        'units' => $belumApproveJenis,
                    ];
                }
            }
        } else {
            // LOGIKA NON-ADMIN (staf / kepala_unit)
            if ($userUnitCode) {
                $unit     = Units::where('kode_unit', $userUnitCode)->first();
                $timUnits = TimUnit::where('kode_unit', $userUnitCode)->get();

                foreach ($jenisIndikatorAktif as $jenis) {
                    $belumIsiJenis    = [];
                    $belumApproveJenis = [];

                    if ($timUnits->count() > 0) {
                        foreach ($timUnits as $tim) {
                            $indIds = $indsByUnitJenisTim[$userUnitCode][$jenis][$tim->nama_tim] ?? [];
                            if (empty($indIds)) continue;

                            $totalTim        = count($indIds);
                            $laporanMasukTim = $countFilled($indIds);
                            $approvedTim     = $countApproved($indIds);

                            if ($laporanMasukTim < $totalTim) {
                                $belumIsiJenis[] = $tim->nama_tim;
                                if (!in_array($tim->nama_tim, $daftarBelumMengisi)) {
                                    $daftarBelumMengisi[] = $tim->nama_tim;
                                }
                                if (!in_array($tim->nama_tim, $daftarBelumMelaporkan)) {
                                    $daftarBelumMelaporkan[] = $tim->nama_tim;
                                }
                            } elseif ($approvedTim < $totalTim) {
                                $belumApproveJenis[] = $tim->nama_tim;
                                if (!in_array($tim->nama_tim, $daftarBelumApprove)) {
                                    $daftarBelumApprove[] = $tim->nama_tim;
                                }
                                if (!in_array($tim->nama_tim, $daftarBelumMelaporkan)) {
                                    $daftarBelumMelaporkan[] = $tim->nama_tim;
                                }
                            }
                        }
                    } else {
                        $indIds = $indsByUnitJenis[$userUnitCode][$jenis] ?? [];
                        if (!empty($indIds)) {
                            $totalUnitCount   = count($indIds);
                            $laporanMasukUnit = $countFilled($indIds);
                            $approvedUnit     = $countApproved($indIds);
                            $namaUnit         = $unit->nama_unit ?? 'Unit';

                            if ($laporanMasukUnit < $totalUnitCount) {
                                $belumIsiJenis[] = $namaUnit;
                                if (!in_array($namaUnit, $daftarBelumMengisi)) {
                                    $daftarBelumMengisi[] = $namaUnit;
                                }
                                if (!in_array($namaUnit, $daftarBelumMelaporkan)) {
                                    $daftarBelumMelaporkan[] = $namaUnit;
                                }
                            } elseif ($approvedUnit < $totalUnitCount) {
                                $belumApproveJenis[] = $namaUnit;
                                if (!in_array($namaUnit, $daftarBelumApprove)) {
                                    $daftarBelumApprove[] = $namaUnit;
                                }
                                if (!in_array($namaUnit, $daftarBelumMelaporkan)) {
                                    $daftarBelumMelaporkan[] = $namaUnit;
                                }
                            }
                        }
                    }

                    $label = $jenisLabels[$jenis] ?? $jenis;
                    if (count($belumIsiJenis) > 0) {
                        $daftarBelumMengisiPerJenis[] = [
                            'jenis' => $label,
                            'units' => $belumIsiJenis,
                        ];
                    }
                    if (count($belumApproveJenis) > 0) {
                        $daftarBelumApprovePerJenis[] = [
                            'jenis' => $label,
                            'units' => $belumApproveJenis,
                        ];
                    }
                }
            }
        }

        $namaBulan = Carbon::now()->locale('id')->translatedFormat('F Y');

        return [
            'persentase' => $persentaseCapaianBulanan,
            'indikatorTerlaporkan' => $jumlahCapaian,
            'totalIndikator' => $totalLaporanYangHarusMasuk,
            'timUnitBelumMelaporkan' => count($daftarBelumMelaporkan),
            'daftarUnitBelumMelaporkan' => $daftarBelumMelaporkan,
            'daftarBelumMengisi' => $daftarBelumMengisi,
            'daftarBelumApprove' => $daftarBelumApprove,
            'daftarBelumMengisiPerJenis' => $daftarBelumMengisiPerJenis,
            'daftarBelumApprovePerJenis' => $daftarBelumApprovePerJenis,
            'belumAdaIndikator' => false,
            'bulan' => $namaBulan,
            'jumlahTervalidasi' => $jumlahTervalidasi,
            'semuaTervalidasi' => $semuaTervalidasi,
        ];
    }

    /**
     * Hitung capaian triwulan = rata-rata hasil 3 bulan dalam TW
     */
    private function hitungCapaianTriwulan($isAdmin = true, $userUnitCode = null)
    {
        $bulanSekarang = Carbon::now()->month;
        $tahunSekarang = Carbon::now()->year;

        // Tentukan triwulan berdasarkan bulan
        $triwulan = ceil($bulanSekarang / 3);

        // Mapping bulan ke triwulan
        $bulanPerTriwulan = [
            1 => ['jan', 'feb', 'mar'],
            2 => ['apr', 'may', 'jun'],
            3 => ['jul', 'aug', 'sep'],
            4 => ['oct', 'nov', 'des']
        ];

        $bulanDalamTW = $bulanPerTriwulan[$triwulan];

        // Total indikator AKTIF (filter berdasarkan unit untuk non-admin)
        $totalIndikatorAktifQuery = Indikator::where('is_active', true);
        if (!$isAdmin && $userUnitCode) {
            $totalIndikatorAktifQuery->where('kode_unit', $userUnitCode);
        }
        $totalIndikatorAktif = $totalIndikatorAktifQuery->count();
        $totalUnits = $isAdmin ? Units::count() : 1;

        if ($totalIndikatorAktif == 0 || $totalUnits == 0) {
            return [
                'triwulan' => $triwulan,
                'persentase' => 0,
                'totalIndikator' => 0,
                'detailPerBulan' => [],
                'status' => 'tidak ada data',
            ];
        }

        // Get all ACTIVE indikator IDs (filter berdasarkan unit untuk non-admin)
        $activeIndikatorQuery = Indikator::where('is_active', true);
        if (!$isAdmin && $userUnitCode) {
            $activeIndikatorQuery->where('kode_unit', $userUnitCode);
        }
        $activeIndikatorIds = $activeIndikatorQuery->pluck('id')->toArray();

        // Nama bulan dalam Bahasa Indonesia
        $namaBulanIndo = [
            'jan' => 'Januari',
            'feb' => 'Februari',
            'mar' => 'Maret',
            'apr' => 'April',
            'may' => 'Mei',
            'jun' => 'Juni',
            'jul' => 'Juli',
            'aug' => 'Agustus',
            'sep' => 'September',
            'oct' => 'Oktober',
            'nov' => 'November',
            'des' => 'Desember'
        ];

        $jenisLabels = [
            'SPM' => 'Indikator SPM',
            'INM' => 'Indikator Nasional Mutu',
            'IMUT_RS' => 'Indikator Mutu Prioritas RS',
            'IMUT_UNIT' => 'Indikator Mutu Unit',
        ];

        // Ambil semua indikator aktif dengan jenis_indikator
        $allActiveIndikators = Indikator::where('is_active', true);
        if (!$isAdmin && $userUnitCode) {
            $allActiveIndikators->where('kode_unit', $userUnitCode);
        }
        $allActiveIndikators = $allActiveIndikators->get();

        // Group indikator IDs per jenis
        $indikatorPerJenis = [];
        foreach ($allActiveIndikators as $ind) {
            $jenis = $ind->jenis_indikator;
            if (!isset($indikatorPerJenis[$jenis])) {
                $indikatorPerJenis[$jenis] = [];
            }
            $indikatorPerJenis[$jenis][] = $ind->id;
        }

        // Hitung persentase untuk setiap bulan dalam TW
        $detailTooltip = [];
        $totalPersentaseTW = 0;

        foreach ($bulanDalamTW as $bulan) {
            $numeratorField = $bulan . '_n';
            $denominatorField = $bulan . '_d';
            $approvedFieldBulan = $bulan . '_approved';

            // Hitung total keseluruhan untuk bulan ini - SEMUA YANG SUDAH TERISI
            $capaianData = CapaianIndikator::where('tahun', $tahunSekarang)
                ->whereIn('indikator_id', $activeIndikatorIds)
                ->whereNotNull($numeratorField)
                ->whereNotNull($denominatorField)
                ->where($denominatorField, '>', 0)
                ->get();

            $totalHasil = 0;
            $jumlahCapaian = 0;

            foreach ($capaianData as $capaian) {
                $n = $capaian->{$numeratorField};
                $d = $capaian->{$denominatorField};

                if ($d > 0) {
                    $hasil = ($n / $d) * 100;
                    $totalHasil += $hasil;
                    $jumlahCapaian++;
                }
            }

            // Bagi total semua indikator aktif (bukan hanya yang terisi)
            $persentaseBulan = $totalIndikatorAktif > 0
                ? round($totalHasil / $totalIndikatorAktif, 2)
                : 0;

            // Selalu tambahkan (termasuk 0 jika belum ada data), dibagi 3 di akhir
            $totalPersentaseTW += $persentaseBulan;

            // Hitung per jenis indikator untuk bulan ini - SEMUA YANG SUDAH TERISI
            $detailJenis = [];
            foreach ($indikatorPerJenis as $jenis => $jenisIds) {
                $capaianJenis = CapaianIndikator::where('tahun', $tahunSekarang)
                    ->whereIn('indikator_id', $jenisIds)
                    ->whereNotNull($numeratorField)
                    ->whereNotNull($denominatorField)
                    ->where($denominatorField, '>', 0)
                    ->get();

                $totalHasilJenis = 0;
                $totalJenis = count($jenisIds);

                foreach ($capaianJenis as $cap) {
                    $n = $cap->{$numeratorField};
                    $d = $cap->{$denominatorField};
                    if ($d > 0) {
                        $totalHasilJenis += ($n / $d) * 100;
                    }
                }

                $persentaseJenis = $totalJenis > 0
                    ? round($totalHasilJenis / $totalJenis, 2)
                    : 0;

                $detailJenis[] = [
                    'jenis' => $jenisLabels[$jenis] ?? $jenis,
                    'persentase' => $persentaseJenis,
                ];
            }

            $detailTooltip[] = [
                'bulan' => $namaBulanIndo[$bulan] ?? ucfirst($bulan),
                'persentase' => $persentaseBulan,
                'detailJenis' => $detailJenis,
            ];
        }

        // Rata-rata TW = total persentase 3 bulan / 3
        $persentaseTW = round($totalPersentaseTW / 3, 2);

        // Status
        $bulanKeNomor = [
            'jan' => 1,
            'feb' => 2,
            'mar' => 3,
            'apr' => 4,
            'may' => 5,
            'jun' => 6,
            'jul' => 7,
            'aug' => 8,
            'sep' => 9,
            'oct' => 10,
            'nov' => 11,
            'des' => 12
        ];

        $bulanTerakhirTW = end($bulanDalamTW);
        $nomorBulanTerakhirTW = $bulanKeNomor[$bulanTerakhirTW];

        $status = $bulanSekarang > $nomorBulanTerakhirTW ? 'selesai' : 'masih dalam proses';

        return [
            'triwulan' => $triwulan,
            'persentase' => $persentaseTW,
            'totalIndikator' => $totalIndikatorAktif * $totalUnits,
            'detailPerBulan' => $detailTooltip,
            'status' => $status,
        ];
    }

    /**
     * Hitung capaian tahunan = rata-rata hasil semua bulan yang sudah lewat
     */
    private function hitungCapaianTahunan($isAdmin = true, $userUnitCode = null)
    {
        $bulanSekarang = Carbon::now()->month;
        $tahunSekarang = Carbon::now()->year;

        // Total indikator AKTIF (filter berdasarkan unit untuk non-admin)
        $totalIndikatorAktifQuery = Indikator::where('is_active', true);
        if (!$isAdmin && $userUnitCode) {
            $totalIndikatorAktifQuery->where('kode_unit', $userUnitCode);
        }
        $totalIndikatorAktif = $totalIndikatorAktifQuery->count();
        $totalUnits = $isAdmin ? Units::count() : 1;

        if ($totalIndikatorAktif == 0 || $totalUnits == 0) {
            return [
                'persentase' => 0,
                'totalIndikator' => 0,
                'totalValidasi' => 0,
                'jumlahBulan' => $bulanSekarang,
                'status' => 'tidak ada data',
            ];
        }

        // Get all ACTIVE indikator IDs (filter berdasarkan unit untuk non-admin)
        $activeIndikatorQuery = Indikator::where('is_active', true);
        if (!$isAdmin && $userUnitCode) {
            $activeIndikatorQuery->where('kode_unit', $userUnitCode);
        }
        $activeIndikatorIds = $activeIndikatorQuery->pluck('id')->toArray();

        // TW berjalan
        $twSekarang = (int) ceil($bulanSekarang / 3);

        $bulanPerTriwulanFull = [
            1 => ['jan', 'feb', 'mar'],
            2 => ['apr', 'may', 'jun'],
            3 => ['jul', 'aug', 'sep'],
            4 => ['oct', 'nov', 'des'],
        ];

        $totalPersentaseTahunan = 0;

        // Hitung capaian per TW (masing-masing 3 bulan / 3), lalu rata-ratakan per jumlah TW berjalan
        for ($tw = 1; $tw <= $twSekarang; $tw++) {
            $bulanTW = $bulanPerTriwulanFull[$tw];
            $totalPersentaseTW = 0;
            foreach ($bulanTW as $bulan) {
                $nField = $bulan . '_n';
                $dField = $bulan . '_d';
                $caps = CapaianIndikator::where('tahun', $tahunSekarang)
                    ->whereIn('indikator_id', $activeIndikatorIds)
                    ->whereNotNull($nField)
                    ->whereNotNull($dField)
                    ->where($dField, '>', 0)
                    ->get();
                $sum = 0;
                foreach ($caps as $c) {
                    if ($c->{$dField} > 0) { $sum += ($c->{$nField} / $c->{$dField}) * 100; }
                }
                // Bagi total semua indikator aktif (bukan hanya yang terisi)
                $totalPersentaseTW += $totalIndikatorAktif > 0 ? round($sum / $totalIndikatorAktif, 2) : 0;
            }
            $totalPersentaseTahunan += round($totalPersentaseTW / 3, 2);
        }

        // Rata-rata tahunan = total capaian TW / jumlah TW berjalan
        $persentaseTahunan = round($totalPersentaseTahunan / $twSekarang, 2);

        // Status message
        $bulanPerTriwulanAkhir = [1 => 3, 2 => 6, 3 => 9, 4 => 12];
        $bulanAkhirTW = $bulanPerTriwulanAkhir[$twSekarang];

        if ($bulanSekarang <= $bulanAkhirTW) {
            $status = "menunggu data TW " . $twSekarang;
        } else {
            $twBerikutnya = $twSekarang + 1;
            $status = $twBerikutnya <= 4 ? "menunggu data TW " . $twBerikutnya : "data lengkap";
        }

        // Hitung capaian TW sebelumnya (untuk ditampilkan saat hover)
        $twSebelumnyaData = null;
        if ($twSekarang > 1) {
            $twPrev = $twSekarang - 1;
            $bulanTW = $bulanPerTriwulanFull[$twPrev];
            $totalPct = 0;
            foreach ($bulanTW as $bl) {
                $nField = $bl . '_n';
                $dField = $bl . '_d';
                $caps = CapaianIndikator::where('tahun', $tahunSekarang)
                    ->whereIn('indikator_id', $activeIndikatorIds)
                    ->whereNotNull($nField)
                    ->whereNotNull($dField)
                    ->where($dField, '>', 0)
                    ->get();
                $sum = 0;
                foreach ($caps as $c) {
                    if ($c->{$dField} > 0) { $sum += ($c->{$nField} / $c->{$dField}) * 100; }
                }
                $totalPct += $totalIndikatorAktif > 0 ? round($sum / $totalIndikatorAktif, 2) : 0;
            }
            $twSebelumnyaData = [
                'triwulan' => $twPrev,
                'persentase' => round($totalPct / 3, 2),
            ];
        }

        return [
            'persentase' => $persentaseTahunan,
            'totalIndikator' => $totalIndikatorAktif * $totalUnits,
            'totalValidasi' => 0,
            'jumlahBulan' => $twSekarang,
            'status' => $status,
            'twSebelumnya' => $twSebelumnyaData,
        ];
    }

    /**
     * Ambil aktivitas terbaru (12 jam terakhir)
     * Filter berdasarkan unit user yang login
     */
    private function getAktivitasTerbaru($user)
    {
        $aktivitas = collect();
        $waktuBatas = Carbon::now()->subHours(12);

        // Tentukan apakah user adalah admin
        $isAdmin = $user->email === 'admin@mutu.rsud.go.id';

        // Untuk non-admin, dapatkan kode unit dari email
        $userUnitCode = null;
        if (!$isAdmin) {
            $emailPrefix = explode('@', $user->email)[0];
            $userUnit = Units::whereRaw('LOWER(kode_unit) = ?', [strtolower($emailPrefix)])->first();
            if ($userUnit) {
                $userUnitCode = $userUnit->kode_unit;
            }
        }

        // 1. Aktivitas INPUT/UPDATE CAPAIAN
        $capaianQuery = CapaianIndikator::where('updated_at', '>=', $waktuBatas)
            ->with(['indikator.unit'])
            ->whereHas('indikator', function ($q) use ($isAdmin, $userUnitCode) {
                $q->where('is_active', true); // Hanya indikator aktif
                // Filter berdasarkan unit jika non-admin
                if (!$isAdmin && $userUnitCode) {
                    $q->where('kode_unit', $userUnitCode);
                }
            });

        $capaianActivity = $capaianQuery
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($item) {
                // Gunakan nama tim unit jika ada, jika tidak gunakan nama unit
                $displayName = $item->indikator->tim_unit ?? ($item->indikator->unit->nama_unit ?? 'Unknown Unit');
                $isNew = $item->created_at->eq($item->updated_at);
                $action = $isNew ? 'melakukan input capaian indikator' : 'melakukan update capaian indikator';

                return [
                    'type' => 'capaian',
                    'icon' => '📊',
                    'color' => 'bg-green-50',
                    'text_color' => 'text-green-700',
                    'message' => $displayName . ' - ' . $action,
                    'timestamp' => $item->updated_at,
                    'time_display' => $item->updated_at->locale('id')->diffForHumans(),
                ];
            });

        // 2. Aktivitas ADMIN MENAMBAHKAN INDIKATOR BARU (AKTIF)
        $indikatorBaruQuery = Indikator::where('created_at', '>=', $waktuBatas)
            ->where('is_active', true)
            ->with(['unit']);

        // Filter berdasarkan unit jika non-admin
        if (!$isAdmin && $userUnitCode) {
            $indikatorBaruQuery->where('kode_unit', $userUnitCode);
        }

        $indikatorBaru = $indikatorBaruQuery
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                // Gunakan nama tim unit jika ada, jika tidak gunakan nama unit
                $displayName = $item->tim_unit ?? ($item->unit->nama_unit ?? 'Unknown Unit');

                return [
                    'type' => 'indikator_baru',
                    'icon' => '✨',
                    'color' => 'bg-blue-50',
                    'text_color' => 'text-blue-700',
                    'message' => 'Admin menambahkan indikator baru untuk ' . $displayName,
                    'timestamp' => $item->created_at,
                    'time_display' => $item->created_at->locale('id')->diffForHumans(),
                ];
            });

        // 3. Aktivitas PENONAKTIFAN INDIKATOR
        $indikatorNonaktifQuery = Indikator::where('updated_at', '>=', $waktuBatas)
            ->where('is_active', false)
            ->where('updated_at', '>', DB::raw('created_at')) // Hanya yang di-update, bukan baru dibuat
            ->with(['unit']);

        // Filter berdasarkan unit jika non-admin
        if (!$isAdmin && $userUnitCode) {
            $indikatorNonaktifQuery->where('kode_unit', $userUnitCode);
        }

        $indikatorNonaktif = $indikatorNonaktifQuery
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($item) {
                // Gunakan nama tim unit jika ada, jika tidak gunakan nama unit
                $displayName = $item->tim_unit ?? ($item->unit->nama_unit ?? 'Unknown Unit');

                return [
                    'type' => 'indikator_nonaktif',
                    'icon' => '🔴',
                    'color' => 'bg-red-50',
                    'text_color' => 'text-red-700',
                    'message' => 'Admin menonaktifkan indikator dari ' . $displayName,
                    'timestamp' => $item->updated_at,
                    'time_display' => $item->updated_at->locale('id')->diffForHumans(),
                ];
            });

        // 4. Aktivitas KOMENTAR BARU dari Admin
        $komentarBaruQuery = CapaianIndikator::where('updated_at', '>=', $waktuBatas)
            ->whereNotNull('komentar')
            ->where('komentar', '!=', '')
            ->with(['indikator.unit'])
            ->whereHas('indikator', function ($q) use ($isAdmin, $userUnitCode) {
                $q->where('is_active', true);
                // Filter berdasarkan unit jika non-admin
                if (!$isAdmin && $userUnitCode) {
                    $q->where('kode_unit', $userUnitCode);
                }
            });

        $komentarBaru = $komentarBaruQuery
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($item) {
                // Gunakan nama tim unit jika ada, jika tidak gunakan nama unit
                $displayName = $item->indikator->tim_unit ?? ($item->indikator->unit->nama_unit ?? 'Unknown Unit');

                return [
                    'type' => 'komentar',
                    'icon' => '💬',
                    'color' => 'bg-yellow-50',
                    'text_color' => 'text-yellow-700',
                    'message' => 'Admin memberikan catatan revisi untuk ' . $displayName,
                    'timestamp' => $item->updated_at,
                    'time_display' => $item->updated_at->locale('id')->diffForHumans(),
                ];
            });

        // 5. Aktivitas VALIDASI
        $bulanSekarang = Carbon::now()->month;
        $bulanField = [
            1 => 'jan',
            2 => 'feb',
            3 => 'mar',
            4 => 'apr',
            5 => 'may',
            6 => 'jun',
            7 => 'jul',
            8 => 'aug',
            9 => 'sep',
            10 => 'oct',
            11 => 'nov',
            12 => 'des'
        ];
        $namaBulan = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];

        $validasiActivity = collect();

        foreach ($bulanField as $month => $fieldPrefix) {
            $validatedField = $fieldPrefix . '_validated';

            $validatedQuery = CapaianIndikator::where('updated_at', '>=', $waktuBatas)
                ->where($validatedField, 1)
                ->with(['indikator.unit'])
                ->whereHas('indikator', function ($q) use ($isAdmin, $userUnitCode) {
                    $q->where('is_active', true);
                    // Filter berdasarkan unit jika non-admin
                    if (!$isAdmin && $userUnitCode) {
                        $q->where('kode_unit', $userUnitCode);
                    }
                });

            $validated = $validatedQuery
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($item) use ($namaBulan, $month) {
                    // Gunakan nama tim unit jika ada, jika tidak gunakan nama unit
                    $displayName = $item->indikator->tim_unit ?? ($item->indikator->unit->nama_unit ?? 'Unknown Unit');

                    return [
                        'type' => 'verifikasi',
                        'icon' => '✅',
                        'color' => 'bg-purple-50',
                        'text_color' => 'text-purple-700',
                        'message' => 'Admin melakukan verifikasi capaian indikator ' . $displayName . ' bulan ' . $namaBulan[$month],
                        'timestamp' => $item->updated_at,
                        'time_display' => $item->updated_at->locale('id')->diffForHumans(),
                    ];
                });

            $validasiActivity = $validasiActivity->merge($validated);
        }

        // Merge semua aktivitas
        $aktivitas = $aktivitas
            ->merge($capaianActivity)
            ->merge($indikatorBaru)
            ->merge($indikatorNonaktif)
            ->merge($komentarBaru)
            ->merge($validasiActivity)
            ->sortByDesc('timestamp')
            ->unique(function ($item) {
                return $item['type'] . '-' . $item['message'] . '-' . $item['timestamp'];
            })
            ->take(15) // Ambil 15 aktivitas terbaru
            ->values()
            ->toArray();

        return $aktivitas;
    }

    /**
     * Get data for bar chart: Capaian Bulanan per Unit
     * Sumbu X: Unit kerja
     * Sumbu Y: Nilai capaian bulan ini
     */
    private function getGrafikCapaianBulanan($isAdmin = true, $userUnitCode = null)
    {
        $bulanSekarang = Carbon::now()->month;
        $tahunSekarang = Carbon::now()->year;

        $bulanField = [
            1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr',
            5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug',
            9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'des'
        ];

        $fieldBulan = $bulanField[$bulanSekarang];
        $numeratorField = $fieldBulan . '_n';
        $denominatorField = $fieldBulan . '_d';
        $approvedField = $fieldBulan . '_approved';

        // Get all active indikators based on user role
        $indikatorQuery = Indikator::where('is_active', true)->with('unit');
        if (!$isAdmin && $userUnitCode) {
            $indikatorQuery->where('kode_unit', $userUnitCode);
        }
        $allIndikators = $indikatorQuery->get();

        // First, identify units that have teams
        $unitsWithTim = [];
        foreach ($allIndikators as $indikator) {
            if ($indikator->tim_unit) {
                $unitsWithTim[$indikator->kode_unit] = true;
            }
        }

        // Group indikators by tim (tim_unit) with kode unit prefix
        $timGroups = [];
        foreach ($allIndikators as $indikator) {
            $kodeUnit = $indikator->kode_unit ?? 'Unknown';

            // Skip indicators without tim if the unit has other indicators with tim
            if (!$indikator->tim_unit && isset($unitsWithTim[$indikator->kode_unit])) {
                continue;
            }

            // Format: "Kode Unit - Nama Tim" or just "Kode Unit" if no tim
            if ($indikator->tim_unit) {
                $timName = strtoupper($kodeUnit) . ' - ' . $indikator->tim_unit;
            } else {
                $timName = strtoupper($kodeUnit);
            }

            if (!isset($timGroups[$timName])) {
                $timGroups[$timName] = [];
            }
            $timGroups[$timName][] = $indikator->id;
        }

        // Sort by tim name
        ksort($timGroups);

        $labels = [];
        $data = [];

        foreach ($timGroups as $timName => $indikatorIds) {
            // Calculate average capaian for this tim - HANYA YANG SUDAH APPROVED
            $capaianData = CapaianIndikator::where('tahun', $tahunSekarang)
                ->whereIn('indikator_id', $indikatorIds)
                ->where($approvedField, true)
                ->whereNotNull($numeratorField)
                ->whereNotNull($denominatorField)
                ->where($denominatorField, '>', 0)
                ->get();

            $totalHasil = 0;
            $jumlahCapaian = 0;

            foreach ($capaianData as $capaian) {
                $n = $capaian->{$numeratorField};
                $d = $capaian->{$denominatorField};

                if ($d > 0) {
                    $hasil = ($n / $d) * 100;
                    $totalHasil += $hasil;
                    $jumlahCapaian++;
                }
            }

            $persentase = $jumlahCapaian > 0 ? round($totalHasil / $jumlahCapaian, 2) : 0;

            $labels[] = $timName;
            $data[] = $persentase;
        }

        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return [
            'labels' => $labels,
            'data' => $data,
            'bulan' => $namaBulan[$bulanSekarang],
            'tahun' => $tahunSekarang,
        ];
    }

    /**
     * Get detailed monthly data with individual indicators for Excel download
     */
    private function getDataCapaianBulananDetail($isAdmin = true, $userUnitCode = null)
    {
        $bulanSekarang = Carbon::now()->month;
        $tahunSekarang = Carbon::now()->year;

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

        $fieldBulan = $bulanField[$bulanSekarang];
        $numeratorField = $fieldBulan . '_n';
        $denominatorField = $fieldBulan . '_d';
        $approvedField = $fieldBulan . '_approved';

        // Get all active indikators based on user role
        $indikatorQuery = Indikator::where('is_active', true)->with('unit');
        if (!$isAdmin && $userUnitCode) {
            $indikatorQuery->where('kode_unit', $userUnitCode);
        }
        $allIndikators = $indikatorQuery
            ->orderByRaw("FIELD(jenis_indikator, 'INM', 'SPM', 'PRIORITAS', 'IMUT_RS', 'IMUT_UNIT')")
            ->orderBy('kode_unit')->orderBy('tim_unit')->orderBy('indikator')
            ->get();

        // First, identify units that have teams
        $unitsWithTim = [];
        foreach ($allIndikators as $indikator) {
            if ($indikator->tim_unit) {
                $unitsWithTim[$indikator->kode_unit] = true;
            }
        }

        $detailData = [];

        foreach ($allIndikators as $indikator) {
            $kodeUnit = $indikator->kode_unit ?? 'Unknown';

            // Skip indicators without tim if the unit has other indicators with tim
            if (!$indikator->tim_unit && isset($unitsWithTim[$indikator->kode_unit])) {
                continue;
            }

            // Format tim name: "Kode Unit - Nama Tim" or just "Kode Unit" if no tim
            if ($indikator->tim_unit) {
                $timName = strtoupper($kodeUnit) . ' - ' . $indikator->tim_unit;
            } else {
                $timName = strtoupper($kodeUnit);
            }

            // Get capaian for this specific indicator
            $capaian = CapaianIndikator::where('tahun', $tahunSekarang)
                ->where('indikator_id', $indikator->id)
                ->first();

            $capaianValue = 0;
            if ($capaian && $capaian->{$approvedField}) {
                $n = $capaian->{$numeratorField};
                $d = $capaian->{$denominatorField};

                if ($d && $d > 0) {
                    $capaianValue = round(($n / $d) * 100, 2);
                }
            }

            $detailData[] = [
                'jenis_indikator' => $indikator->jenis_indikator,
                'tim' => $timName,
                'indikator' => $indikator->indikator,
                'target' => $indikator->standar ?? 0,
                'capaian' => $capaianValue,
            ];
        }

        // Merge shared indicators (same indicator name across multiple units)
        $grouped = [];
        foreach ($detailData as $row) {
            $grouped[$row['indikator']][] = $row;
        }

        $mergedData = [];
        $perUnitDetail = [];
        foreach ($grouped as $indName => $rows) {
            if (count($rows) === 1) {
                $mergedData[] = $rows[0];
            } else {
                $avgCapaian = round(array_sum(array_column($rows, 'capaian')) / count($rows), 2);
                $mergedData[] = [
                    'jenis_indikator' => $rows[0]['jenis_indikator'],
                    'tim' => 'Multi Unit',
                    'indikator' => $indName,
                    'target' => $rows[0]['target'],
                    'capaian' => $avgCapaian,
                ];
                $perUnitDetail[] = [
                    'indikator' => $indName,
                    'jenis_indikator' => $rows[0]['jenis_indikator'],
                    'target' => $rows[0]['target'],
                    'units' => array_map(fn($r) => ['unit' => $r['tim'], 'capaian' => $r['capaian']], $rows),
                ];
            }
        }

        return [
            'data' => $mergedData,
            'perUnitDetail' => $perUnitDetail,
            'bulan' => $namaBulan[$bulanSekarang],
            'tahun' => $tahunSekarang,
        ];
    }

    /**
     * Get data for grouped bar chart: Capaian Triwulanan per Tim
     * Sumbu X: 3 bulan masing-masing tim (grouped)
     * Sumbu Y: Nilai capaian
     */
    private function getGrafikCapaianTriwulanan($isAdmin = true, $userUnitCode = null, $triwulan = null)
    {
        $tahunSekarang = Carbon::now()->year;

        // Tentukan triwulan - gunakan parameter atau hitung dari bulan sekarang
        if ($triwulan === null) {
            $triwulan = ceil(Carbon::now()->month / 3);
        }

        $bulanPerTriwulan = [
            1 => ['jan', 'feb', 'mar'],
            2 => ['apr', 'may', 'jun'],
            3 => ['jul', 'aug', 'sep'],
            4 => ['oct', 'nov', 'des']
        ];

        $namaBulanIndo = [
            'jan' => 'Januari', 'feb' => 'Februari', 'mar' => 'Maret',
            'apr' => 'April', 'may' => 'Mei', 'jun' => 'Juni',
            'jul' => 'Juli', 'aug' => 'Agustus', 'sep' => 'September',
            'oct' => 'Oktober', 'nov' => 'November', 'des' => 'Desember'
        ];

        $bulanDalamTW = $bulanPerTriwulan[$triwulan];

        // Get all active indikators based on user role
        $indikatorQuery = Indikator::where('is_active', true)->with('unit');
        if (!$isAdmin && $userUnitCode) {
            $indikatorQuery->where('kode_unit', $userUnitCode);
        }
        $allIndikators = $indikatorQuery->get();

        // First, identify units that have teams
        $unitsWithTim = [];
        foreach ($allIndikators as $indikator) {
            if ($indikator->tim_unit) {
                $unitsWithTim[$indikator->kode_unit] = true;
            }
        }

        // Group indikators by tim (tim_unit) with kode unit prefix
        $timGroups = [];
        foreach ($allIndikators as $indikator) {
            $kodeUnit = $indikator->kode_unit ?? 'Unknown';

            // Skip indicators without tim if the unit has other indicators with tim
            if (!$indikator->tim_unit && isset($unitsWithTim[$indikator->kode_unit])) {
                continue;
            }

            // Format: "Kode Unit - Nama Tim" or just "Kode Unit" if no tim
            if ($indikator->tim_unit) {
                $timName = strtoupper($kodeUnit) . ' - ' . $indikator->tim_unit;
            } else {
                $timName = strtoupper($kodeUnit);
            }

            if (!isset($timGroups[$timName])) {
                $timGroups[$timName] = [];
            }
            $timGroups[$timName][] = $indikator->id;
        }

        // Sort by tim name
        ksort($timGroups);

        $labels = []; // Tim names
        $datasets = []; // One dataset per bulan

        // Initialize datasets for each month in the quarter
        foreach ($bulanDalamTW as $bulan) {
            $datasets[$bulan] = [
                'label' => $namaBulanIndo[$bulan],
                'data' => [],
            ];
        }

        foreach ($timGroups as $timName => $indikatorIds) {
            $labels[] = $timName;

            // Calculate capaian for each month
            foreach ($bulanDalamTW as $bulan) {
                $numeratorField = $bulan . '_n';
                $denominatorField = $bulan . '_d';
                $approvedFieldBulan = $bulan . '_approved';

                $capaianData = CapaianIndikator::where('tahun', $tahunSekarang)
                    ->whereIn('indikator_id', $indikatorIds)
                    ->where($approvedFieldBulan, true)
                    ->whereNotNull($numeratorField)
                    ->whereNotNull($denominatorField)
                    ->where($denominatorField, '>', 0)
                    ->get();

                $totalHasil = 0;
                $jumlahCapaian = 0;

                foreach ($capaianData as $capaian) {
                    $n = $capaian->{$numeratorField};
                    $d = $capaian->{$denominatorField};

                    if ($d > 0) {
                        $hasil = ($n / $d) * 100;
                        $totalHasil += $hasil;
                        $jumlahCapaian++;
                    }
                }

                $persentase = $jumlahCapaian > 0 ? round($totalHasil / $jumlahCapaian, 2) : 0;
                $datasets[$bulan]['data'][] = $persentase;
            }
        }

        return [
            'labels' => $labels,
            'datasets' => array_values($datasets),
            'triwulan' => $triwulan,
            'tahun' => $tahunSekarang,
        ];
    }

    /**
     * Get annual capaian data for Excel download
     * Returns data per tim/unit for all 12 months
     */
    private function getDataCapaianTahunan($isAdmin = true, $userUnitCode = null, $tahun = null)
    {
        if ($tahun === null) {
            $tahun = Carbon::now()->year;
        }

        $semuaBulan = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'des'];

        $namaBulanIndo = [
            'jan' => 'Januari', 'feb' => 'Februari', 'mar' => 'Maret',
            'apr' => 'April', 'may' => 'Mei', 'jun' => 'Juni',
            'jul' => 'Juli', 'aug' => 'Agustus', 'sep' => 'September',
            'oct' => 'Oktober', 'nov' => 'November', 'des' => 'Desember'
        ];

        // Get all active indikators based on user role
        $indikatorQuery = Indikator::where('is_active', true)->with('unit');
        if (!$isAdmin && $userUnitCode) {
            $indikatorQuery->where('kode_unit', $userUnitCode);
        }
        $allIndikators = $indikatorQuery->get();

        // First, identify units that have teams
        $unitsWithTim = [];
        foreach ($allIndikators as $indikator) {
            if ($indikator->tim_unit) {
                $unitsWithTim[$indikator->kode_unit] = true;
            }
        }

        // Group indikators by tim (tim_unit) with kode unit prefix
        $timGroups = [];
        foreach ($allIndikators as $indikator) {
            $kodeUnit = $indikator->kode_unit ?? 'Unknown';

            // Skip indicators without tim if the unit has other indicators with tim
            if (!$indikator->tim_unit && isset($unitsWithTim[$indikator->kode_unit])) {
                continue;
            }

            // Format: "Kode Unit - Nama Tim" or just "Kode Unit" if no tim
            if ($indikator->tim_unit) {
                $timName = strtoupper($kodeUnit) . ' - ' . $indikator->tim_unit;
            } else {
                $timName = strtoupper($kodeUnit);
            }

            if (!isset($timGroups[$timName])) {
                $timGroups[$timName] = [];
            }
            $timGroups[$timName][] = $indikator->id;
        }

        // Sort by tim name
        ksort($timGroups);

        $labels = []; // Tim names
        $data = []; // Data per tim per bulan

        foreach ($timGroups as $timName => $indikatorIds) {
            $labels[] = $timName;
            $timData = [];

            // Get average target (standar) for this tim
            $avgTarget = Indikator::whereIn('id', $indikatorIds)->avg('standar') ?? 0;
            $timData['target'] = round($avgTarget, 2);

            // Calculate capaian for each month
            foreach ($semuaBulan as $bulan) {
                $numeratorField = $bulan . '_n';
                $denominatorField = $bulan . '_d';
                $approvedFieldBulan = $bulan . '_approved';

                $capaianData = CapaianIndikator::where('tahun', $tahun)
                    ->whereIn('indikator_id', $indikatorIds)
                    ->where($approvedFieldBulan, true)
                    ->whereNotNull($numeratorField)
                    ->whereNotNull($denominatorField)
                    ->where($denominatorField, '>', 0)
                    ->get();

                $totalHasil = 0;
                $jumlahCapaian = 0;

                foreach ($capaianData as $capaian) {
                    $n = $capaian->{$numeratorField};
                    $d = $capaian->{$denominatorField};

                    if ($d > 0) {
                        $hasil = ($n / $d) * 100;
                        $totalHasil += $hasil;
                        $jumlahCapaian++;
                    }
                }

                $persentase = $jumlahCapaian > 0 ? round($totalHasil / $jumlahCapaian, 2) : 0;
                $timData[$bulan] = $persentase;
            }

            $data[] = $timData;
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'bulanHeaders' => array_map(fn($b) => $namaBulanIndo[$b], $semuaBulan),
            'tahun' => $tahun,
        ];
    }

    /**
     * Get ranking data: top 5 fastest, top 5 most commented, bottom 5 slowest units
     */
    private function getRankingData($isAdmin = true, $userUnitCode = null)
    {
        $tahun = Carbon::now()->year;
        $bulanSekarang = Carbon::now()->month;
        $allMonths = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'des'];
        $months = array_slice($allMonths, 0, $bulanSekarang);
        $currentMonthKey = $allMonths[$bulanSekarang - 1];

        // Earliest input timestamp per unit for current month (MIN updated_at where _n is filled)
        $inputTimesQuery = DB::table('capaian_indikators as ci')
            ->join('indikators as i', 'ci.indikator_id', '=', 'i.id')
            ->where('i.is_active', true)
            ->where('ci.tahun', $tahun)
            ->whereNotNull("ci.{$currentMonthKey}_n")
            ->groupBy('i.kode_unit')
            ->selectRaw('i.kode_unit, MIN(ci.updated_at) as waktu_input');
        if (!$isAdmin && $userUnitCode) {
            $inputTimesQuery->where('i.kode_unit', $userUnitCode);
        }
        $inputTimesRaw = $inputTimesQuery->pluck('waktu_input', 'kode_unit')->toArray();

        // Build single-query conditional aggregation
        // _app = approved by kepala unit (for speed/slowness ranking)
        // _kom = monthly komentar from admin
        $selectRaw = "u.kode_unit, u.nama_unit";
        foreach ($months as $m) {
            $selectRaw .= ", SUM(CASE WHEN ci.{$m}_approved = 1 THEN 1 ELSE 0 END) as {$m}_app";
            $selectRaw .= ", SUM(CASE WHEN ci.{$m}_komentar IS NOT NULL AND ci.{$m}_komentar != '' THEN 1 ELSE 0 END) as {$m}_kom";
            $selectRaw .= ", SUM(CASE WHEN ci.{$m}_n IS NOT NULL THEN 1 ELSE 0 END) as {$m}_fil";
        }

        $query = DB::table('capaian_indikators as ci')
            ->join('indikators as i', 'ci.indikator_id', '=', 'i.id')
            ->join('units as u', 'i.kode_unit', '=', 'u.kode_unit')
            ->where('i.is_active', true)
            ->where('ci.tahun', $tahun)
            ->groupBy('u.kode_unit', 'u.nama_unit')
            ->selectRaw($selectRaw);

        if (!$isAdmin && $userUnitCode) {
            $query->where('i.kode_unit', $userUnitCode);
        }

        $rows = $query->get();

        // Count active indicators per unit per month (respecting berlaku_tw)
        $monthToQuarter = ['jan'=>1,'feb'=>1,'mar'=>1,'apr'=>2,'may'=>2,'jun'=>2,
                           'jul'=>3,'aug'=>3,'sep'=>3,'oct'=>4,'nov'=>4,'des'=>4];
        $allActiveIndikators = Indikator::where('is_active', true)
            ->select('kode_unit', 'berlaku_tw')
            ->get();
        $indikatorPerUnitMonth = [];
        foreach ($allActiveIndikators as $ind) {
            $berlakuTw = $ind->berlaku_tw ?? [1,2,3,4];
            foreach ($months as $m) {
                if (in_array($monthToQuarter[$m], $berlakuTw)) {
                    $indikatorPerUnitMonth[$ind->kode_unit][$m] = ($indikatorPerUnitMonth[$ind->kode_unit][$m] ?? 0) + 1;
                }
            }
        }

        $bulanLabels = ['jan'=>'Jan','feb'=>'Feb','mar'=>'Mar','apr'=>'Apr','may'=>'Mei',
                        'jun'=>'Jun','jul'=>'Jul','aug'=>'Agu','sep'=>'Sep','oct'=>'Okt',
                        'nov'=>'Nov','des'=>'Des'];

        $unitStats = $rows->map(function ($row) use ($months, $indikatorPerUnitMonth, $bulanLabels, $inputTimesRaw) {
            $totalApproved    = 0;
            $totalKomentar    = 0;
            $totalFilled      = 0;
            $bulanBelumTerisi = [];
            $totalPossible    = 0;

            foreach ($months as $m) {
                $totalApproved  += (int) ($row->{$m . '_app'} ?? 0);
                $totalKomentar  += (int) ($row->{$m . '_kom'} ?? 0);
                $filled          = (int) ($row->{$m . '_fil'} ?? 0);
                $totalFilled    += $filled;
                $indForMonth     = $indikatorPerUnitMonth[$row->kode_unit][$m] ?? 0;
                $totalPossible  += $indForMonth;
                if ($indForMonth > 0 && $filled < $indForMonth) {
                    $bulanBelumTerisi[] = $bulanLabels[$m];
                }
            }
            $pct          = $totalPossible > 0 ? round($totalApproved / $totalPossible * 100, 1) : 0;
            $belumTerisi  = max(0, $totalPossible - $totalFilled);

            $rawTs      = $inputTimesRaw[$row->kode_unit] ?? null;
            $waktuInput = $rawTs
                ? Carbon::parse($rawTs)->locale('id')->isoFormat('D MMM YYYY, HH:mm')
                : null;

            return [
                'unit'               => $row->nama_unit,
                'kode_unit'          => $row->kode_unit,
                'total_approved'     => $totalApproved,
                'total_komentar'     => $totalKomentar,
                'pct'                => $pct,
                'belum_terisi'       => $belumTerisi,
                'bulan_belum_terisi' => $bulanBelumTerisi,
                'jumlah_bulan_belum' => count($bulanBelumTerisi),
                'waktu_input'        => $waktuInput,
                'waktu_input_ts'     => $rawTs,
            ];
        });

        $topTercepat      = $unitStats
            ->filter(fn($u) => $u['waktu_input_ts'] !== null)
            ->sortBy('waktu_input_ts')
            ->values()
            ->take(5)
            ->toArray();
        $bottomTerlambat  = $unitStats->sortByDesc('jumlah_bulan_belum')->values()->take(5)->toArray();
        $topKomentar = $unitStats
            ->filter(fn($u) => $u['total_komentar'] > 0)
            ->sortByDesc('total_komentar')
            ->values()
            ->take(5)
            ->toArray();

        // Top 5 units with most indicators above standar (capaian >= standar target)
        $indikatorQuery = Indikator::where('is_active', true)->with('unit');
        if (!$isAdmin && $userUnitCode) {
            $indikatorQuery->where('kode_unit', $userUnitCode);
        }
        $allIndikators = $indikatorQuery->get();
        $indikatorIds = $allIndikators->pluck('id')->toArray();

        $capaianRecords = !empty($indikatorIds)
            ? CapaianIndikator::where('tahun', $tahun)
                ->whereIn('indikator_id', $indikatorIds)
                ->get()
                ->keyBy('indikator_id')
            : collect();

        $unitAboveStats = [];
        foreach ($allIndikators as $indikator) {
            $kodeUnit = $indikator->kode_unit;
            if (!isset($unitAboveStats[$kodeUnit])) {
                $unitAboveStats[$kodeUnit] = [
                    'unit' => $indikator->unit->nama_unit ?? 'Unknown',
                    'kode_unit' => $kodeUnit,
                    'above_standar' => 0,
                    'total_validated' => 0,
                ];
            }

            $standarRaw = str_replace(',', '.', $indikator->standar ?? '');
            preg_match('/(\d+(?:\.\d+)?)/', $standarRaw, $m2);
            $standarValue = isset($m2[1]) ? (float) $m2[1] : null;
            if ($standarValue === null) continue;

            $capaian = $capaianRecords[$indikator->id] ?? null;
            if (!$capaian) continue;

            foreach ($months as $m) {
                if ($capaian->{$m . '_validated'} == 1) {
                    $unitAboveStats[$kodeUnit]['total_validated']++;
                    $n = $capaian->{$m . '_n'};
                    $d = $capaian->{$m . '_d'};
                    if ($d && $d > 0 && ($n / $d * 100) >= $standarValue) {
                        $unitAboveStats[$kodeUnit]['above_standar']++;
                    }
                }
            }
        }

        $topDiAtasStandar = collect($unitAboveStats)
            ->filter(fn($u) => $u['above_standar'] > 0)
            ->sortByDesc('above_standar')
            ->values()
            ->take(5)
            ->map(fn($u) => [
                'unit' => $u['unit'],
                'kode_unit' => $u['kode_unit'],
                'above_standar' => $u['above_standar'],
                'total_validated' => $u['total_validated'],
            ])
            ->values()
            ->toArray();

        return [
            'topTercepat' => array_values($topTercepat),
            'topKomentar' => array_values($topKomentar),
            'bottomTerlambat' => array_values($bottomTerlambat),
            'topDiAtasStandar' => array_values($topDiAtasStandar),
        ];
    }

    /**
     * Get detailed annual data with individual indicators for Excel download
     */
    private function getDataCapaianTriwulanDetail($isAdmin = true, $userUnitCode = null, $triwulan = null)
    {
        $tahun = Carbon::now()->year;
        if ($triwulan === null) {
            $triwulan = ceil(Carbon::now()->month / 3);
        }

        $bulanPerTriwulan = [
            1 => ['jan', 'feb', 'mar'],
            2 => ['apr', 'may', 'jun'],
            3 => ['jul', 'aug', 'sep'],
            4 => ['oct', 'nov', 'des'],
        ];
        $namaBulanIndo = [
            'jan' => 'Januari', 'feb' => 'Februari', 'mar' => 'Maret',
            'apr' => 'April',   'may' => 'Mei',       'jun' => 'Juni',
            'jul' => 'Juli',    'aug' => 'Agustus',   'sep' => 'September',
            'oct' => 'Oktober', 'nov' => 'November',  'des' => 'Desember',
        ];
        $bulanDalamTW = $bulanPerTriwulan[$triwulan];

        $indikatorQuery = Indikator::where('is_active', true)->with('unit');
        if (!$isAdmin && $userUnitCode) {
            $indikatorQuery->where('kode_unit', $userUnitCode);
        }
        $allIndikators = $indikatorQuery
            ->orderByRaw("FIELD(jenis_indikator, 'INM', 'SPM', 'PRIORITAS', 'IMUT_RS', 'IMUT_UNIT')")
            ->orderBy('kode_unit')->orderBy('tim_unit')->orderBy('indikator')
            ->get();

        $unitsWithTim = [];
        foreach ($allIndikators as $ind) {
            if ($ind->tim_unit) $unitsWithTim[$ind->kode_unit] = true;
        }

        $detailData = [];
        foreach ($allIndikators as $indikator) {
            if (!$indikator->tim_unit && isset($unitsWithTim[$indikator->kode_unit])) continue;

            $kodeUnit = $indikator->kode_unit ?? 'Unknown';
            $timName = $indikator->tim_unit
                ? strtoupper($kodeUnit) . ' - ' . $indikator->tim_unit
                : strtoupper($kodeUnit);

            $capaian = CapaianIndikator::where('tahun', $tahun)
                ->where('indikator_id', $indikator->id)
                ->first();

            $row = [
                'jenis_indikator' => $indikator->jenis_indikator,
                'tim' => $timName,
                'indikator' => $indikator->indikator,
                'target' => $indikator->standar ?? 0,
            ];

            foreach ($bulanDalamTW as $bulan) {
                $pct = 0;
                if ($capaian && $capaian->{$bulan . '_approved'}) {
                    $n = $capaian->{$bulan . '_n'};
                    $d = $capaian->{$bulan . '_d'};
                    if ($d && $d > 0) $pct = round(($n / $d) * 100, 2);
                }
                $row[$bulan] = $pct;
            }
            $detailData[] = $row;
        }

        // Merge shared indicators
        $grouped = [];
        foreach ($detailData as $row) {
            $grouped[$row['indikator']][] = $row;
        }

        $mergedData = [];
        $perUnitDetail = [];
        foreach ($grouped as $indName => $rows) {
            if (count($rows) === 1) {
                $mergedData[] = $rows[0];
            } else {
                $mergedRow = [
                    'jenis_indikator' => $rows[0]['jenis_indikator'],
                    'tim' => 'Multi Unit',
                    'indikator' => $indName,
                    'target' => $rows[0]['target'],
                ];
                foreach ($bulanDalamTW as $bulan) {
                    $vals = array_column($rows, $bulan);
                    $mergedRow[$bulan] = count($vals) > 0 ? round(array_sum($vals) / count($vals), 2) : 0;
                }
                $mergedData[] = $mergedRow;

                $unitRows = [];
                foreach ($rows as $r) {
                    $unitRow = ['unit' => $r['tim']];
                    foreach ($bulanDalamTW as $bulan) {
                        $unitRow[$bulan] = $r[$bulan];
                    }
                    $unitRows[] = $unitRow;
                }
                $perUnitDetail[] = [
                    'indikator' => $indName,
                    'jenis_indikator' => $rows[0]['jenis_indikator'],
                    'target' => $rows[0]['target'],
                    'units' => $unitRows,
                ];
            }
        }

        return [
            'data' => $mergedData,
            'perUnitDetail' => $perUnitDetail,
            'bulanHeaders' => array_map(fn($b) => $namaBulanIndo[$b], $bulanDalamTW),
            'bulanKeys' => $bulanDalamTW,
            'triwulan' => $triwulan,
            'tahun' => $tahun,
        ];
    }

    private function getDataCapaianTahunanDetail($isAdmin = true, $userUnitCode = null, $tahun = null)
    {
        if ($tahun === null) {
            $tahun = Carbon::now()->year;
        }

        $semuaBulan = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'des'];

        $namaBulanIndo = [
            'jan' => 'Januari', 'feb' => 'Februari', 'mar' => 'Maret',
            'apr' => 'April', 'may' => 'Mei', 'jun' => 'Juni',
            'jul' => 'Juli', 'aug' => 'Agustus', 'sep' => 'September',
            'oct' => 'Oktober', 'nov' => 'November', 'des' => 'Desember'
        ];

        // Get all active indikators based on user role
        $indikatorQuery = Indikator::where('is_active', true)->with('unit');
        if (!$isAdmin && $userUnitCode) {
            $indikatorQuery->where('kode_unit', $userUnitCode);
        }
        $allIndikators = $indikatorQuery
            ->orderByRaw("FIELD(jenis_indikator, 'INM', 'SPM', 'PRIORITAS', 'IMUT_RS', 'IMUT_UNIT')")
            ->orderBy('kode_unit')->orderBy('tim_unit')->orderBy('indikator')
            ->get();

        // First, identify units that have teams
        $unitsWithTim = [];
        foreach ($allIndikators as $indikator) {
            if ($indikator->tim_unit) {
                $unitsWithTim[$indikator->kode_unit] = true;
            }
        }

        $detailData = [];

        foreach ($allIndikators as $indikator) {
            $kodeUnit = $indikator->kode_unit ?? 'Unknown';

            // Skip indicators without tim if the unit has other indicators with tim
            if (!$indikator->tim_unit && isset($unitsWithTim[$indikator->kode_unit])) {
                continue;
            }

            // Format tim name: "Kode Unit - Nama Tim" or just "Kode Unit" if no tim
            if ($indikator->tim_unit) {
                $timName = strtoupper($kodeUnit) . ' - ' . $indikator->tim_unit;
            } else {
                $timName = strtoupper($kodeUnit);
            }

            $indikatorData = [
                'jenis_indikator' => $indikator->jenis_indikator,
                'tim' => $timName,
                'indikator' => $indikator->indikator,
                'target' => $indikator->standar ?? 0,
            ];

            // Get capaian for this specific indicator
            $capaian = CapaianIndikator::where('tahun', $tahun)
                ->where('indikator_id', $indikator->id)
                ->first();

            // Calculate capaian for each month
            foreach ($semuaBulan as $bulan) {
                $numeratorField = $bulan . '_n';
                $denominatorField = $bulan . '_d';
                $approvedFieldBulan = $bulan . '_approved';

                $persentase = 0;
                if ($capaian && $capaian->{$approvedFieldBulan}) {
                    $n = $capaian->{$numeratorField};
                    $d = $capaian->{$denominatorField};

                    if ($d && $d > 0) {
                        $persentase = round(($n / $d) * 100, 2);
                    }
                }
                $indikatorData[$bulan] = $persentase;
            }

            $detailData[] = $indikatorData;
        }

        return [
            'data' => $detailData,
            'bulanHeaders' => array_map(fn($b) => $namaBulanIndo[$b], $semuaBulan),
            'tahun' => $tahun,
        ];
    }
}
