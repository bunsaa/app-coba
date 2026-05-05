<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\PenilaianPerilaku;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Exports\PenilaianPerilakuExport;
use Maatwebsite\Excel\Facades\Excel;

class PenilaianPerilakuController extends Controller
{
    public function home()
    {
        $user = Auth::user();
        return Inertia::render('PenilaianPerilakuHome', [
            'user' => [
                'name' => $user->name,
                'role' => $user->role,
                'penilaian_aktif' => $user->penilaian_aktif ?? false,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        // Hanya kepala_unit dan admin_mutu yang bisa akses
        $isAdmin = $user->role === 'admin_mutu' || $user->email === 'admin@mutu.rsud.go.id';
        $isKepala = $user->role === 'kepala_unit';

        if (!$isAdmin && !$isKepala) {
            abort(403);
        }

        // Periode otomatis aktif tgl 15–31 dan 1–5 bulan berikutnya
        $inAutoPeriod = self::isPenilaianPeriod();
        $penilaianBelumAktif = $isKepala && !$user->penilaian_aktif && !$inAutoPeriod;

        $periode = $request->get('periode', Carbon::now()->format('Y-m'));
        $statusFilter = $request->get('status', 'semua');

        // Admin bisa lihat semua staf, kepala hanya staf di unitnya
        if ($isAdmin) {
            $pegawaiQuery = User::with('unit')
                ->where('role', 'staf')
                ->orderBy('name', 'asc');
        } else {
            // Kepala unit hanya menilai pegawai di unitnya
            // yang statusnya selain PNS, CPNS, PPPK, PPPK Paruh Waktu
            $pegawaiQuery = User::with('unit')
                ->where('kode_unit', $user->kode_unit)
                ->where('role', 'staf')
                ->where(function ($q) {
                    $q->whereNull('status_pegawai')
                       ->orWhereNotIn('status_pegawai', ['PNS', 'CPNS', 'PPPK', 'PPPK Paruh Waktu']);
                })
                ->orderBy('name', 'asc');
        }

        $pegawaiList = $pegawaiQuery->get();

        // Ambil penilaian untuk periode ini
        $penilaianMap = PenilaianPerilaku::where('periode', $periode)
            ->whereIn('user_id', $pegawaiList->pluck('id'))
            ->get()
            ->keyBy('user_id');

        // Gabungkan data pegawai + status penilaian
        $data = $pegawaiList->map(function ($pegawai) use ($penilaianMap) {
            $penilaian = $penilaianMap->get($pegawai->id);
            return [
                'id' => $pegawai->id,
                'name' => $pegawai->name,
                'email' => $pegawai->email,
                'role' => $pegawai->role,
                'status_pegawai' => $pegawai->status_pegawai,
                'kode_unit' => $pegawai->kode_unit,
                'unit_nama' => $pegawai->unit ? $pegawai->unit->nama_unit : '-',
                'penilaian_id' => $penilaian ? $penilaian->id : null,
                'status_penilaian' => $penilaian ? $penilaian->status : 'belum_dinilai',
            ];
        });

        // Jika penilaian belum aktif, hanya tampilkan pegawai yang sudah dinilai
        if ($penilaianBelumAktif) {
            $data = $data->filter(fn($d) => $d['status_penilaian'] === 'selesai')->values();
        } elseif ($statusFilter === 'selesai') {
            $data = $data->filter(fn($d) => $d['status_penilaian'] === 'selesai')->values();
        } elseif ($statusFilter === 'belum_dinilai') {
            $data = $data->filter(fn($d) => $d['status_penilaian'] === 'belum_dinilai')->values();
        }

        return Inertia::render('PenilaianPerilakuPegawai', [
            'pegawaiList' => $data,
            'periode' => $periode,
            'statusFilter' => $statusFilter,
            'penilaianBelumAktif' => $penilaianBelumAktif,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->role === 'admin_mutu' || $user->email === 'admin@mutu.rsud.go.id';
        $isKepala = $user->role === 'kepala_unit';

        if (!$isAdmin && !$isKepala) {
            abort(403);
        }

        $validValues = 'nullable|in:di_atas_ekspektasi,sesuai_ekspektasi,di_bawah_ekspektasi';

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'periode' => 'required|string',
            'berorientasi_pelayanan' => $validValues,
            'akuntabel' => $validValues,
            'kompeten' => $validValues,
            'harmonis' => $validValues,
            'loyal' => $validValues,
            'adaptif' => $validValues,
            'kolaboratif' => $validValues,
        ]);

        $pegawai = User::findOrFail($request->user_id);

        // Kepala hanya bisa menilai pegawai di unitnya
        if ($isKepala && $pegawai->kode_unit !== $user->kode_unit) {
            abort(403);
        }

        // Kepala tidak bisa menilai pegawai berstatus PNS, CPNS, PPPK, PPPK Paruh Waktu
        if ($isKepala && in_array($pegawai->status_pegawai, ['PNS', 'CPNS', 'PPPK', 'PPPK Paruh Waktu'])) {
            abort(403);
        }

        $penilaianData = [
            'user_id' => $request->user_id,
            'penilai_id' => $user->id,
            'kode_unit' => $pegawai->kode_unit,
            'periode' => $request->periode,
            'berorientasi_pelayanan' => $request->berorientasi_pelayanan,
            'akuntabel' => $request->akuntabel,
            'kompeten' => $request->kompeten,
            'harmonis' => $request->harmonis,
            'loyal' => $request->loyal,
            'adaptif' => $request->adaptif,
            'kolaboratif' => $request->kolaboratif,
            'status' => 'selesai',
        ];

        PenilaianPerilaku::updateOrCreate(
            ['user_id' => $request->user_id, 'periode' => $request->periode],
            $penilaianData
        );

        return redirect()->back()->with('success', 'Penilaian berhasil disimpan!');
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $isAdmin = $user->role === 'admin_mutu' || $user->email === 'admin@mutu.rsud.go.id';
        $isKepala = $user->role === 'kepala_unit';

        if (!$isAdmin && !$isKepala) {
            abort(403);
        }

        $penilaian = PenilaianPerilaku::findOrFail($id);

        // Kepala hanya bisa edit penilaian di unitnya
        if ($isKepala && $penilaian->kode_unit !== $user->kode_unit) {
            abort(403);
        }

        $validValues = 'nullable|in:di_atas_ekspektasi,sesuai_ekspektasi,di_bawah_ekspektasi';

        $request->validate([
            'berorientasi_pelayanan' => $validValues,
            'akuntabel' => $validValues,
            'kompeten' => $validValues,
            'harmonis' => $validValues,
            'loyal' => $validValues,
            'adaptif' => $validValues,
            'kolaboratif' => $validValues,
        ]);

        $penilaian->update([
            'penilai_id' => $user->id,
            'berorientasi_pelayanan' => $request->berorientasi_pelayanan,
            'akuntabel' => $request->akuntabel,
            'kompeten' => $request->kompeten,
            'harmonis' => $request->harmonis,
            'loyal' => $request->loyal,
            'adaptif' => $request->adaptif,
            'kolaboratif' => $request->kolaboratif,
            'status' => 'selesai',
        ]);

        return redirect()->back()->with('success', 'Penilaian berhasil diupdate!');
    }

    public function show(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'periode' => 'required|string',
        ]);

        $penilaian = PenilaianPerilaku::where('user_id', $request->user_id)
            ->where('periode', $request->periode)
            ->with('penilai')
            ->first();

        return response()->json([
            'penilaian' => $penilaian,
        ]);
    }

    public function pengaturan()
    {
        $user = Auth::user();
        $isAdmin = $user->role === 'admin_mutu' || $user->email === 'admin@mutu.rsud.go.id';

        if (!$isAdmin) {
            abort(403);
        }

        // Auto-sync semua kepala_unit sesuai periode berjalan
        $shouldBeAktif = self::isPenilaianPeriod();
        User::where('role', 'kepala_unit')->update(['penilaian_aktif' => $shouldBeAktif]);

        $kepalaList = User::with('unit')
            ->where('role', 'kepala_unit')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($k) {
                return [
                    'id' => $k->id,
                    'name' => $k->name,
                    'unit_nama' => $k->unit ? $k->unit->nama_unit : '-',
                    'penilaian_aktif' => (bool) $k->penilaian_aktif,
                ];
            });

        return Inertia::render('PengaturanPenilaian', [
            'kepalaList' => $kepalaList,
            'isPeriod'   => $shouldBeAktif,
        ]);
    }

    public function togglePenilaian($id)
    {
        $user = Auth::user();
        $isAdmin = $user->role === 'admin_mutu' || $user->email === 'admin@mutu.rsud.go.id';

        if (!$isAdmin) {
            abort(403);
        }

        $kepala = User::where('role', 'kepala_unit')->findOrFail($id);
        $kepala->penilaian_aktif = !$kepala->penilaian_aktif;
        $kepala->save();

        return response()->json([
            'success' => true,
            'penilaian_aktif' => $kepala->penilaian_aktif,
        ]);
    }

    public function toggleAllPenilaian(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->role === 'admin_mutu' || $user->email === 'admin@mutu.rsud.go.id';

        if (!$isAdmin) {
            abort(403);
        }

        $aktif = $request->boolean('aktif');

        User::where('role', 'kepala_unit')->update(['penilaian_aktif' => $aktif]);

        return response()->json([
            'success' => true,
            'aktif' => $aktif,
        ]);
    }

    /**
     * Cek apakah hari ini termasuk periode penilaian perilaku.
     * Aktif: tanggal 15 s/d akhir bulan, dan tanggal 1–5 bulan berikutnya.
     * Nonaktif: tanggal 6–14.
     */
    public static function isPenilaianPeriod(): bool
    {
        $day = now()->day;
        return $day >= 15 || $day <= 5;
    }

    public function indexSaya(Request $request)
    {
        $user = Auth::user();
        $tahun = $request->get('tahun', Carbon::now()->year);

        $namaBulan = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
        ];

        // Konversi nilai
        $konversi = [
            'di_atas_ekspektasi' => 3,
            'sesuai_ekspektasi' => 2,
            'di_bawah_ekspektasi' => 1,
        ];

        $unsurKeys = ['berorientasi_pelayanan', 'akuntabel', 'kompeten', 'harmonis', 'loyal', 'adaptif', 'kolaboratif'];

        // Ambil penilaian untuk tahun ini
        $penilaianList = PenilaianPerilaku::where('user_id', $user->id)
            ->where('periode', 'like', $tahun . '-%')
            ->where('status', 'selesai')
            ->orderBy('periode', 'asc')
            ->get();

        $data = $penilaianList->map(function ($p) use ($namaBulan, $konversi, $unsurKeys) {
            $bulan = substr($p->periode, 5, 2); // "2026-02" -> "02"

            $nilaiUnsur = [];
            $totalNilai = 0;
            foreach ($unsurKeys as $key) {
                $nilai = $konversi[$p->{$key}] ?? 0;
                $nilaiUnsur[$key] = $nilai;
                $totalNilai += $nilai;
            }

            $rataRata = round($totalNilai / 7, 2);

            // Rating berdasarkan rata-rata
            if ($rataRata >= 2.51) {
                $keterangan = 'Di Atas Ekspektasi';
            } elseif ($rataRata >= 1.5) {
                $keterangan = 'Sesuai Ekspektasi';
            } else {
                $keterangan = 'Di Bawah Ekspektasi';
            }

            return array_merge([
                'id' => $p->id,
                'bulan' => $namaBulan[$bulan] ?? $bulan,
                'periode' => $p->periode,
            ], $nilaiUnsur, [
                'rata_rata' => $rataRata,
                'keterangan' => $keterangan,
            ]);
        });

        return Inertia::render('PenilaianPerilakuSaya', [
            'penilaianList' => $data,
            'tahun' => (int) $tahun,
            'userName' => $user->name,
        ]);
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->role === 'admin_mutu' || $user->email === 'admin@mutu.rsud.go.id';

        if (!$isAdmin) {
            abort(403);
        }

        $periode = $request->get('periode', Carbon::now()->format('Y-m'));
        $fileName = 'penilaian-perilaku-' . $periode . '.xlsx';

        return Excel::download(new PenilaianPerilakuExport($periode), $fileName);
    }
}
