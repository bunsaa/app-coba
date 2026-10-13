<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\PengajuanIndikator;
use App\Models\Indikator;
use App\Models\Units;
use App\Models\User;
use Carbon\Carbon;

class PengajuanIndikatorController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'admin_mutu';
        $isKepalaUnit = $user->role === 'kepala_unit';
        $isPenilaiPjData = str_starts_with($user->role ?? '', 'penilai_pj_data');

        // Extract tim name from role for penilai
        $penilaiTimName = str_starts_with($user->role ?? '', 'penilai_pj_data - ')
            ? str_replace('penilai_pj_data - ', '', $user->role)
            : null;

        // Find kepala unit of this user's kode_unit
        $kepalaUnitName = User::where('role', 'kepala_unit')
            ->where('kode_unit', $user->kode_unit ?? '')
            ->value('name');

        // Base query
        $query = PengajuanIndikator::with('user')->orderBy('created_at', 'desc');

        if ($isAdmin) {
            // Admin only sees submissions that have passed kepala review
            // (disetujui_kepala = waiting for admin, disetujui = done, ditolak = rejected by admin)
            $query->whereNotIn('status', ['pending_kepala', 'ditolak_kepala']);
        } elseif ($isKepalaUnit) {
            $query->where('kode_unit', $user->kode_unit);
        } elseif ($isPenilaiPjData) {
            $query->where('user_id', $user->id);
        } else {
            $query->whereRaw('1=0'); // No access
        }

        $pengajuanList = $query->get()->map(function ($p) {
            return [
                'id'                    => $p->id,
                'user_id'               => $p->user_id,
                'user_nama'             => $p->user?->name ?? '-',
                'kode_unit'             => $p->kode_unit,
                'nama_tim'              => $p->nama_tim,
                'judul_indikator'       => $p->judul_indikator,
                'dasar_pemikiran'       => $p->dasar_pemikiran,
                'dimensi_mutu'          => $p->dimensi_mutu,
                'tujuan'                => $p->tujuan,
                'definisi_operasional'  => $p->definisi_operasional,
                'jenis_indikator'       => $p->jenis_indikator,
                'numerator'             => $p->numerator,
                'denominator'           => $p->denominator,
                'target_tw1'            => $p->target_tw1,
                'target_tw2'            => $p->target_tw2,
                'target_tw3'            => $p->target_tw3,
                'target_tw4'            => $p->target_tw4,
                'satuan'                => $p->satuan,
                'satuan_waktu'          => $p->satuan_waktu,
                'kriteria_inklusi_eksklusi' => $p->kriteria_inklusi_eksklusi,
                'metode_pengumpulan_data'   => $p->metode_pengumpulan_data,
                'sumber_data'           => $p->sumber_data,
                'instrumen_pengambilan_data' => $p->instrumen_pengambilan_data,
                'populasi_sampel'       => $p->populasi_sampel,
                'penanggung_jawab'      => $p->penanggung_jawab,
                'skor_a'                => $p->skor_a,
                'skor_b'                => $p->skor_b,
                'skor_c'                => $p->skor_c,
                'skor_d'                => $p->skor_d,
                'skor_e'                => $p->skor_e,
                'total_skor'            => $p->total_skor,
                'status'                => $p->status,
                'catatan_kepala'        => $p->catatan_kepala,
                'catatan_admin'         => $p->catatan_admin,
                'reviewed_by_kepala'    => $p->reviewed_by_kepala,
                'reviewed_by_admin'     => $p->reviewed_by_admin,
                'reviewed_at_kepala'    => $p->reviewed_at_kepala?->format('d/m/Y H:i'),
                'reviewed_at_admin'     => $p->reviewed_at_admin?->format('d/m/Y H:i'),
                'indikator_id'          => $p->indikator_id,
                'original_indikator_id' => $p->original_indikator_id,
                'request_type'          => $p->request_type,
                'alasan_nonaktif'       => $p->alasan_nonaktif,
                'created_at'            => $p->created_at?->format('d/m/Y'),
                'created_at_ts'         => $p->created_at?->timestamp ?? 0,
            ];
        });

        // Cards data
        $cardQuery = PengajuanIndikator::query();
        if ($isAdmin) {
            // Admin: global stats
        } elseif ($isKepalaUnit) {
            $cardQuery->where('kode_unit', $user->kode_unit);
        } elseif ($isPenilaiPjData) {
            $cardQuery->where('user_id', $user->id);
        }

        $cardAll = (clone $cardQuery)->get();
        if ($isAdmin) {
            // Admin card: sedang = waiting for admin review (disetujui_kepala)
            $sedangPengajuan = $cardAll->where('status', 'disetujui_kepala')->count();
            $disetujui       = $cardAll->where('status', 'disetujui')->count();
            $ditolak         = $cardAll->where('status', 'ditolak')->count();
        } elseif ($isKepalaUnit) {
            // Kepala card: sedang = pending their review, disetujui = forwarded+approved, ditolak = rejected by kepala
            $sedangPengajuan = $cardAll->where('status', 'pending_kepala')->count();
            $disetujui       = $cardAll->whereIn('status', ['disetujui_kepala', 'disetujui'])->count();
            $ditolak         = $cardAll->where('status', 'ditolak_kepala')->count();
        } else {
            $sedangPengajuan = $cardAll->whereIn('status', ['pending_kepala', 'disetujui_kepala'])->count();
            $disetujui       = $cardAll->where('status', 'disetujui')->count();
            $ditolak         = $cardAll->whereIn('status', ['ditolak_kepala', 'ditolak'])->count();
        }

        // Indikator (berdasarkan role) — aktif maupun nonaktif, untuk card dan tabel
        $indikatorBase = Indikator::query();
        if ($isAdmin) {
            // Admin sees all
        } elseif ($isKepalaUnit) {
            // Kepala unit sees indikators in their unit
            $indikatorBase->where('kode_unit', $user->kode_unit ?? '');
        } elseif ($isPenilaiPjData) {
            // Penilai only sees indikators in their specific team (tim_unit)
            if ($penilaiTimName) {
                $indikatorBase->where('tim_unit', $penilaiTimName);
            } else {
                $indikatorBase->where('kode_unit', $user->kode_unit ?? '');
            }
        } else {
            $indikatorBase->whereRaw('1=0');
        }
        // Indikator list untuk tabel — expanded fields for detail view & revisi pre-fill
        $indikatorRaw = (clone $indikatorBase)->orderBy('indikator')->get();

        // Deduplicate: if same name+unit+tim exists (old data), keep the most recently created one
        $indikatorRaw = $indikatorRaw
            ->groupBy(fn($i) => strtolower(trim($i->indikator)) . '|' . $i->kode_unit . '|' . ($i->tim_unit ?? ''))
            ->map(fn($group) => $group->sortByDesc('id')->first())
            ->values();

        // Indikator aktif count = hanya yang is_active = true (untuk card)
        $indikatorAktif = $indikatorRaw->where('is_active', true)->count();
        $kodeUnitList = $indikatorRaw->pluck('kode_unit')->unique()->filter()->values()->toArray();
        $timUnitList  = $indikatorRaw->pluck('tim_unit')->unique()->filter()->values()->toArray();

        // Pre-fetch linked pengajuan (for revisi pre-fill of fields not on indikators table)
        $indikatorIds = $indikatorRaw->pluck('id')->toArray();
        $pengajuanForIndikator = PengajuanIndikator::whereIn('indikator_id', $indikatorIds)
            ->where('status', 'disetujui')
            ->get()
            ->keyBy('indikator_id');

        // Kepala unit per kode_unit
        $kepalaMap = User::where('role', 'kepala_unit')
            ->whereIn('kode_unit', $kodeUnitList)
            ->pluck('name', 'kode_unit');

        // PJ Data per tim_unit (role format: "PJ Data - {tim}")
        $pjMap = User::whereIn('kode_unit', $kodeUnitList)
            ->where(function ($q) use ($timUnitList) {
                foreach ($timUnitList as $tim) {
                    $q->orWhere('role', 'PJ Data - ' . $tim);
                }
                $q->orWhere('role', 'PJ Data');
            })
            ->get()
            ->keyBy(fn($u) => str_replace('PJ Data - ', '', $u->role) . '|' . $u->kode_unit);

        $indikatorList = $indikatorRaw->map(function ($i) use ($kepalaMap, $pjMap, $pengajuanForIndikator) {
            $timKey = ($i->tim_unit ?? '') . '|' . $i->kode_unit;
            $pjNama = $pjMap->get($timKey)?->name
                ?? $pjMap->get('|' . $i->kode_unit)?->name
                ?? null;
            $p = $pengajuanForIndikator->get($i->id); // linked pengajuan (may be null for old records)
            return [
                'id'              => $i->id,
                'indikator'       => $i->indikator,
                'tim_unit'        => $i->tim_unit,
                'kode_unit'       => $i->kode_unit,
                'jenis_indikator' => $i->jenis_indikator,
                'numerator'       => $i->numerator,
                'denominator'     => $i->denominator,
                'standar'         => $i->standar,
                'standar_tw1'     => $i->standar_tw1,
                'standar_tw2'     => $i->standar_tw2,
                'standar_tw3'     => $i->standar_tw3,
                'standar_tw4'     => $i->standar_tw4,
                'satuan'          => $i->satuan,
                'satuan_waktu'    => $i->satuan_waktu,
                'satuan_tw1'      => $i->satuan_tw1,
                'satuan_tw2'      => $i->satuan_tw2,
                'satuan_tw3'      => $i->satuan_tw3,
                'satuan_tw4'      => $i->satuan_tw4,
                'pic'             => $i->pic ?: $i->tim_unit,
                'pj_nama'         => $pjNama,
                'kepala_nama'     => $kepalaMap->get($i->kode_unit),
                'is_active'       => (bool) $i->is_active,
                'created_at_ts'   => $i->created_at?->timestamp ?? 0,
                // Fields from original pengajuan (for Ajukan Revisi pre-fill)
                'dasar_pemikiran'            => $p?->dasar_pemikiran,
                'dimensi_mutu'               => $p?->dimensi_mutu,
                'tujuan'                     => $p?->tujuan,
                'definisi_operasional'       => $p?->definisi_operasional,
                'kriteria_inklusi_eksklusi'  => $p?->kriteria_inklusi_eksklusi,
                'metode_pengumpulan_data'    => $p?->metode_pengumpulan_data,
                'sumber_data'                => $p?->sumber_data,
                'instrumen_pengambilan_data' => $p?->instrumen_pengambilan_data,
                'populasi_sampel'            => $p?->populasi_sampel,
            ];
        });

        return Inertia::render('PengajuanIndikator', [
            'pengajuanList'  => $pengajuanList,
            'indikatorList'  => $indikatorList,
            'cards'          => [
                'indikator_aktif'  => $indikatorAktif,
                'sedang_pengajuan' => $sedangPengajuan,
                'disetujui'        => $disetujui,
                'ditolak'          => $ditolak,
            ],
            'userRole'       => $user->role,
            'userId'         => $user->id,
            'kodeUnit'       => $user->kode_unit,
            'namaTim'        => $penilaiTimName,
            'userName'       => $user->name,
            'kepalaUnitName' => $kepalaUnitName,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'judul_indikator'            => 'required|string|max:500',
            'dasar_pemikiran'            => 'required|string',
            'dimensi_mutu'               => 'required|string',
            'tujuan'                     => 'required|string',
            'definisi_operasional'       => 'required|string',
            'jenis_indikator'            => 'required|string',
            'numerator'                  => 'nullable|string',
            'denominator'                => 'nullable|string',
            'target_tw1'                 => 'nullable|string',
            'target_tw2'                 => 'nullable|string',
            'target_tw3'                 => 'nullable|string',
            'target_tw4'                 => 'nullable|string',
            'satuan'                     => 'nullable|string',
            'satuan_waktu'               => 'nullable|string',
            'kriteria_inklusi_eksklusi'  => 'nullable|string',
            'metode_pengumpulan_data'    => 'required|string',
            'sumber_data'                => 'required|string',
            'instrumen_pengambilan_data' => 'required|string',
            'populasi_sampel'            => 'nullable|string',
            'penanggung_jawab'           => 'required|string',
            'skor_a'                     => 'nullable|integer|min:1|max:5',
            'skor_b'                     => 'nullable|integer|min:1|max:5',
            'skor_c'                     => 'nullable|integer|min:1|max:5',
            'skor_d'                     => 'nullable|integer|min:1|max:5',
            'skor_e'                     => 'nullable|integer|min:1|max:5',
            'total_skor'                 => 'nullable|integer',
            'original_indikator_id'      => 'nullable|integer',
        ]);

        // Extract tim name from role
        $namaTim = str_starts_with($user->role ?? '', 'penilai_pj_data - ')
            ? str_replace('penilai_pj_data - ', '', $user->role)
            : null;

        PengajuanIndikator::create(array_merge($validated, [
            'user_id'   => $user->id,
            'kode_unit' => $user->kode_unit ?? '',
            'nama_tim'  => $namaTim,
            'status'    => 'pending_kepala',
        ]));

        return back()->with('success', 'Pengajuan indikator berhasil dikirim.');
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $pengajuan = PengajuanIndikator::findOrFail($id);

        $isKepalaUnit = $user->role === 'kepala_unit';
        $isPenilaiOwner = str_starts_with($user->role ?? '', 'penilai_pj_data')
            && $pengajuan->user_id === $user->id;

        $canEdit = ($isPenilaiOwner || ($isKepalaUnit && $pengajuan->kode_unit === $user->kode_unit))
            && $pengajuan->status === 'pending_kepala';

        if (!$canEdit) {
            return back()->with('error', 'Pengajuan tidak dapat diubah.');
        }

        $validated = $request->validate([
            'judul_indikator'            => 'required|string|max:500',
            'dasar_pemikiran'            => 'required|string',
            'dimensi_mutu'               => 'required|string',
            'tujuan'                     => 'required|string',
            'definisi_operasional'       => 'required|string',
            'jenis_indikator'            => 'required|string',
            'numerator'                  => 'nullable|string',
            'denominator'                => 'nullable|string',
            'target_tw1'                 => 'nullable|string',
            'target_tw2'                 => 'nullable|string',
            'target_tw3'                 => 'nullable|string',
            'target_tw4'                 => 'nullable|string',
            'satuan'                     => 'nullable|string',
            'satuan_waktu'               => 'nullable|string',
            'kriteria_inklusi_eksklusi'  => 'nullable|string',
            'metode_pengumpulan_data'    => 'required|string',
            'sumber_data'                => 'required|string',
            'instrumen_pengambilan_data' => 'required|string',
            'populasi_sampel'            => 'nullable|string',
            'penanggung_jawab'           => 'required|string',
            'skor_a'                     => 'nullable|integer|min:1|max:5',
            'skor_b'                     => 'nullable|integer|min:1|max:5',
            'skor_c'                     => 'nullable|integer|min:1|max:5',
            'skor_d'                     => 'nullable|integer|min:1|max:5',
            'skor_e'                     => 'nullable|integer|min:1|max:5',
            'total_skor'                 => 'nullable|integer',
        ]);

        $pengajuan->update($validated);

        return back()->with('success', 'Pengajuan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $pengajuan = PengajuanIndikator::findOrFail($id);

        if ($pengajuan->user_id !== $user->id || $pengajuan->status !== 'pending_kepala') {
            return back()->with('error', 'Pengajuan tidak dapat dihapus.');
        }

        $pengajuan->delete();

        return back()->with('success', 'Pengajuan berhasil dihapus.');
    }

    public function approveKepala(Request $request, $id)
    {
        $user = auth()->user();
        $pengajuan = PengajuanIndikator::findOrFail($id);

        if ($pengajuan->status !== 'pending_kepala') {
            return response()->json(['success' => false, 'message' => 'Status tidak valid.'], 422);
        }

        $pengajuan->update([
            'status'              => 'disetujui_kepala',
            'reviewed_by_kepala'  => $user->name,
            'reviewed_at_kepala'  => Carbon::now(),
            'catatan_kepala'      => $request->catatan ?? null,
        ]);

        return back()->with('success', 'Pengajuan disetujui, diteruskan ke Admin Mutu.');
    }

    public function tolakKepala(Request $request, $id)
    {
        $user = auth()->user();
        $pengajuan = PengajuanIndikator::findOrFail($id);

        if ($pengajuan->status !== 'pending_kepala') {
            return response()->json(['success' => false, 'message' => 'Status tidak valid.'], 422);
        }

        $pengajuan->update([
            'status'             => 'ditolak_kepala',
            'reviewed_by_kepala' => $user->name,
            'reviewed_at_kepala' => Carbon::now(),
            'catatan_kepala'     => $request->catatan ?? null,
        ]);

        return back()->with('success', 'Pengajuan ditolak.');
    }

    public function approveAdmin(Request $request, $id)
    {
        $user = auth()->user();
        $pengajuan = PengajuanIndikator::findOrFail($id);

        if ($pengajuan->status !== 'disetujui_kepala') {
            return response()->json(['success' => false, 'message' => 'Status tidak valid.'], 422);
        }

        // === NONAKTIF: deactivate the original indikator + any old duplicates ===
        if ($pengajuan->request_type === 'nonaktif') {
            $indikator = Indikator::findOrFail($pengajuan->original_indikator_id);

            // Deactivate ALL records with the same name+unit+tim (handles legacy duplicates)
            Indikator::where('kode_unit', $indikator->kode_unit)
                ->where('indikator', $indikator->indikator)
                ->where(function ($q) use ($indikator) {
                    if ($indikator->tim_unit) {
                        $q->where('tim_unit', $indikator->tim_unit);
                    } else {
                        $q->whereNull('tim_unit');
                    }
                })
                ->update(['is_active' => false]);

            $pengajuan->update([
                'status'            => 'disetujui',
                'reviewed_by_admin' => $user->name,
                'reviewed_at_admin' => Carbon::now(),
                'catatan_admin'     => $request->catatan ?? null,
                'indikator_id'      => $indikator->id,
            ]);

            return back()->with('success', 'Pengajuan nonaktif disetujui, indikator telah dinonaktifkan.');
        }

        // === REVISION or NEW INDIKATOR ===
        $satuan = $pengajuan->satuan ?: 'persen';
        $standarDefault = $pengajuan->target_tw1
            ?? $pengajuan->target_tw2
            ?? $pengajuan->target_tw3
            ?? $pengajuan->target_tw4
            ?? '';

        $indikatorData = [
            'kode_unit'         => $pengajuan->kode_unit,
            'tim_unit'          => $pengajuan->nama_tim,
            'indikator'         => $pengajuan->judul_indikator,
            'jenis_indikator'   => $pengajuan->jenis_indikator,
            'numerator'         => $pengajuan->numerator ?? '',
            'denominator'       => $pengajuan->denominator ?? '',
            'pic'               => $pengajuan->nama_tim ?: $pengajuan->kode_unit,
            'standar'           => $standarDefault,
            'satuan'            => $satuan,
            'satuan_waktu'      => $pengajuan->satuan_waktu,
            'standar_tw1'       => $pengajuan->target_tw1,
            'satuan_tw1'        => $satuan,
            'satuan_waktu_tw1'  => $pengajuan->satuan_waktu,
            'standar_tw2'       => $pengajuan->target_tw2,
            'satuan_tw2'        => $satuan,
            'satuan_waktu_tw2'  => $pengajuan->satuan_waktu,
            'standar_tw3'       => $pengajuan->target_tw3,
            'satuan_tw3'        => $satuan,
            'satuan_waktu_tw3'  => $pengajuan->satuan_waktu,
            'standar_tw4'       => $pengajuan->target_tw4,
            'satuan_tw4'        => $satuan,
            'satuan_waktu_tw4'  => $pengajuan->satuan_waktu,
            'is_active'         => true,
        ];

        // Revision: UPDATE the original indikator instead of creating a duplicate
        if ($pengajuan->original_indikator_id) {
            $indikator = Indikator::findOrFail($pengajuan->original_indikator_id);
            $indikator->update($indikatorData);
        } else {
            // New indikator submission: CREATE
            $indikator = Indikator::create(array_merge($indikatorData, [
                'is_prioritas' => false,
                'pic_units'    => [],
                'berlaku_tw'   => [1, 2, 3, 4],
            ]));
        }

        $pengajuan->update([
            'status'             => 'disetujui',
            'reviewed_by_admin'  => $user->name,
            'reviewed_at_admin'  => Carbon::now(),
            'catatan_admin'      => $request->catatan ?? null,
            'indikator_id'       => $indikator->id,
        ]);

        return back()->with('success', 'Pengajuan disetujui dan indikator berhasil diperbarui.');
    }

    public function tolakAdmin(Request $request, $id)
    {
        $user = auth()->user();
        $pengajuan = PengajuanIndikator::findOrFail($id);

        if ($pengajuan->status !== 'disetujui_kepala') {
            return response()->json(['success' => false, 'message' => 'Status tidak valid.'], 422);
        }

        $pengajuan->update([
            'status'            => 'ditolak',
            'reviewed_by_admin' => $user->name,
            'reviewed_at_admin' => Carbon::now(),
            'catatan_admin'     => $request->catatan ?? null,
        ]);

        return back()->with('success', 'Pengajuan ditolak.');
    }

    public function requestNonaktif(Request $request, $id)
    {
        $user      = auth()->user();
        $indikator = Indikator::findOrFail($id);

        // Only penilai PJ Data
        if (!str_starts_with($user->role ?? '', 'penilai_pj_data')) {
            return back()->with('error', 'Tidak diizinkan.');
        }

        $validated = $request->validate([
            'alasan_nonaktif' => 'required|string|max:1000',
        ]);

        // Guard: no duplicate pending nonaktif
        $existing = PengajuanIndikator::where('original_indikator_id', $id)
            ->where('request_type', 'nonaktif')
            ->whereIn('status', ['pending_kepala', 'disetujui_kepala'])
            ->exists();
        if ($existing) {
            return back()->with('error', 'Sudah ada pengajuan nonaktif yang sedang diproses.');
        }

        $namaTim = str_starts_with($user->role ?? '', 'penilai_pj_data - ')
            ? str_replace('penilai_pj_data - ', '', $user->role)
            : null;

        PengajuanIndikator::create([
            'user_id'                    => $user->id,
            'kode_unit'                  => $user->kode_unit ?? '',
            'nama_tim'                   => $namaTim,
            'judul_indikator'            => $indikator->indikator,
            'request_type'               => 'nonaktif',
            'alasan_nonaktif'            => $validated['alasan_nonaktif'],
            'original_indikator_id'      => $id,
            'status'                     => 'pending_kepala',
            // Required fields — not relevant for nonaktif but schema expects them
            'dasar_pemikiran'            => '-',
            'dimensi_mutu'               => '-',
            'tujuan'                     => '-',
            'definisi_operasional'       => '-',
            'jenis_indikator'            => $indikator->jenis_indikator ?? '-',
            'metode_pengumpulan_data'    => '-',
            'sumber_data'                => '-',
            'instrumen_pengambilan_data' => '-',
            'penanggung_jawab'           => $indikator->pic ?? ($indikator->tim_unit ?? '-'),
        ]);

        return back()->with('success', 'Pengajuan nonaktif berhasil dikirim, menunggu persetujuan Kepala Unit.');
    }
}
