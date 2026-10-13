<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ValidasiDataIndikator;
use App\Models\Indikator;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ValidasiDataIndikatorController extends Controller
{
    /**
     * Ambil record validasi untuk unit/tim/tahun/triwulan tertentu.
     */
    public function get(Request $request)
    {
        $validated = $request->validate([
            'kode_unit' => 'required|string',
            'nama_tim'  => 'nullable|string',
            'tahun'     => 'required|integer',
            'triwulan'  => 'required|integer|min:1|max:4',
        ]);

        $namaTim = $validated['nama_tim'] ?? null;

        $query = ValidasiDataIndikator::where('kode_unit', $validated['kode_unit'])
            ->where('tahun', $validated['tahun'])
            ->where('triwulan', $validated['triwulan']);

        if ($namaTim !== null) {
            $query->where('nama_tim', $namaTim);
        } else {
            $query->whereNull('nama_tim');
        }

        $record = $query->first();

        // Jika belum ada record, generate detail dari indikator aktif
        if (!$record) {
            $indikators = $this->getActiveIndikators($validated['kode_unit'], $namaTim);
            $detail = $indikators->map(fn($ind) => [
                'indikator_id'   => $ind->id,
                'nama_indikator' => $ind->indikator,
                'pj1_jawaban'    => null,
                'pj2_jawaban'    => null,
                'nilai'          => null,
            ])->values()->toArray();

            return response()->json([
                'record' => [
                    'id'                    => null,
                    'kode_unit'             => $validated['kode_unit'],
                    'nama_tim'              => $namaTim,
                    'tahun'                 => $validated['tahun'],
                    'triwulan'              => $validated['triwulan'],
                    'detail'                => $detail,
                    'jumlah_kesesuaian'     => null,
                    'persentase_kesesuaian' => null,
                    'is_valid'              => null,
                    'is_required'           => false,
                    'pj_data_1_id'          => null,
                    'pj_data_2_id'          => null,
                    'pj1_submitted_at'      => null,
                    'pj2_submitted_at'      => null,
                    'is_submitted'          => false,
                    'pj1_info'              => null,
                    'pj2_info'              => null,
                ],
            ]);
        }

        // Sync detail jika ada indikator baru yang belum ada di detail
        $record = $this->syncDetail($record, $namaTim);

        return response()->json([
            'record' => $this->formatRecord($record),
        ]);
    }

    /**
     * Admin toggle is_required untuk TW tertentu.
     */
    public function setRequired(Request $request)
    {
        $user = Auth::user();
        $isAdmin = ($user->role ?? '') === 'admin_mutu' || $user->email === 'admin@mutu.rsud.go.id';
        if (!$isAdmin) {
            return response()->json(['error' => 'Tidak memiliki kewenangan'], 403);
        }

        $validated = $request->validate([
            'kode_unit'   => 'required|string',
            'nama_tim'    => 'nullable|string',
            'tahun'       => 'required|integer',
            'triwulan'    => 'required|integer|min:1|max:4',
            'is_required' => 'required|boolean',
        ]);

        $namaTim = $validated['nama_tim'] ?? null;

        $query = ValidasiDataIndikator::where('kode_unit', $validated['kode_unit'])
            ->where('tahun', $validated['tahun'])
            ->where('triwulan', $validated['triwulan']);

        if ($namaTim !== null) {
            $query->where('nama_tim', $namaTim);
        } else {
            $query->whereNull('nama_tim');
        }

        $record = $query->first();

        if (!$record) {
            // Buat record baru jika belum ada (hanya perlu is_required=true)
            $indikators = $this->getActiveIndikators($validated['kode_unit'], $namaTim);
            $detail = $indikators->map(fn($ind) => [
                'indikator_id'   => $ind->id,
                'nama_indikator' => $ind->indikator,
                'pj1_jawaban'    => null,
                'pj2_jawaban'    => null,
                'nilai'          => null,
            ])->values()->toArray();

            $record = ValidasiDataIndikator::create([
                'kode_unit'   => $validated['kode_unit'],
                'nama_tim'    => $namaTim,
                'tahun'       => $validated['tahun'],
                'triwulan'    => $validated['triwulan'],
                'detail'      => $detail,
                'is_required' => $validated['is_required'],
            ]);
        } else {
            $record->is_required = $validated['is_required'];
            $record->save();
        }

        return response()->json([
            'success' => true,
            'record'  => $this->formatRecord($record),
        ]);
    }

    /**
     * PJ Data simpan jawaban kolom mereka.
     */
    public function save(Request $request)
    {
        $user = Auth::user();
        $userRole = $user->role ?? '';
        $isAdmin = $userRole === 'admin_mutu' || $user->email === 'admin@mutu.rsud.go.id';
        $isPjData = str_starts_with($userRole, 'PJ Data - ') || $userRole === 'PJ Data';

        if (!$isPjData && !$isAdmin) {
            return response()->json(['error' => 'Tidak memiliki kewenangan untuk mengisi validasi'], 403);
        }

        $validated = $request->validate([
            'kode_unit'    => 'required|string',
            'nama_tim'     => 'nullable|string',
            'tahun'        => 'required|integer',
            'triwulan'     => 'required|integer|min:1|max:4',
            'jawaban'      => 'required|array',   // [{indikator_id, jawaban: D/N}]
            'jawaban.*.indikator_id' => 'required|integer',
            'jawaban.*.jawaban'      => 'required|in:D,N',
            'as_pj'        => 'nullable|integer|in:1,2', // hanya admin
        ]);

        $namaTim = $validated['nama_tim'] ?? null;

        $query = ValidasiDataIndikator::where('kode_unit', $validated['kode_unit'])
            ->where('tahun', $validated['tahun'])
            ->where('triwulan', $validated['triwulan']);

        if ($namaTim !== null) {
            $query->where('nama_tim', $namaTim);
        } else {
            $query->whereNull('nama_tim');
        }

        $record = $query->first();

        if (!$record) {
            // Buat record baru dengan detail dari indikator aktif
            $indikators = $this->getActiveIndikators($validated['kode_unit'], $namaTim);
            $detail = $indikators->map(fn($ind) => [
                'indikator_id'   => $ind->id,
                'nama_indikator' => $ind->indikator,
                'pj1_jawaban'    => null,
                'pj2_jawaban'    => null,
                'nilai'          => null,
            ])->values()->toArray();

            $record = ValidasiDataIndikator::create([
                'kode_unit'   => $validated['kode_unit'],
                'nama_tim'    => $namaTim,
                'tahun'       => $validated['tahun'],
                'triwulan'    => $validated['triwulan'],
                'detail'      => $detail,
                'is_required' => true,
            ]);
        }

        // Tentukan user ini sebagai PJ Data 1 atau 2
        $asPj = null;
        if ($isAdmin && isset($validated['as_pj'])) {
            $asPj = (int) $validated['as_pj'];
        } elseif ($record->pj_data_1_id === null) {
            $asPj = 1;
        } elseif ($record->pj_data_1_id == $user->id) {
            $asPj = 1;
        } elseif ($record->pj_data_2_id === null) {
            $asPj = 2;
        } elseif ($record->pj_data_2_id == $user->id) {
            $asPj = 2;
        } else {
            return response()->json(['error' => 'Kolom pengumpul data sudah penuh (sudah diisi oleh 2 PJ Data)'], 422);
        }

        // Buat lookup jawaban
        $jawabanMap = collect($validated['jawaban'])->keyBy('indikator_id');

        // Update detail
        $detail = $record->detail ?? [];
        foreach ($detail as &$row) {
            $j = $jawabanMap->get($row['indikator_id']);
            if ($j) {
                if ($asPj === 1) {
                    $row['pj1_jawaban'] = $j['jawaban'];
                } else {
                    $row['pj2_jawaban'] = $j['jawaban'];
                }
            }
        }
        unset($row);

        $record->detail = $detail;

        if ($asPj === 1) {
            $record->pj_data_1_id   = $user->id;
            $record->pj1_submitted_at = now();
        } else {
            $record->pj_data_2_id   = $user->id;
            $record->pj2_submitted_at = now();
        }

        $record->save();

        // Recalculate
        self::recalculate($record);

        return response()->json([
            'success' => true,
            'record'  => $this->formatRecord($record),
        ]);
    }

    /**
     * Hitung ulang jumlah_kesesuaian, persentase, is_valid, is_submitted.
     */
    public static function recalculate(ValidasiDataIndikator $v): void
    {
        $detail = $v->detail ?? [];
        $total  = count($detail);
        if ($total === 0) {
            $v->jumlah_kesesuaian     = 0;
            $v->persentase_kesesuaian = 0;
            $v->is_valid              = false;
            $v->save();
            return;
        }

        $jumlah = 0;
        foreach ($detail as &$row) {
            $pj1 = $row['pj1_jawaban'] ?? null;
            $pj2 = $row['pj2_jawaban'] ?? null;
            if ($pj1 !== null && $pj2 !== null) {
                $row['nilai'] = ($pj1 === $pj2) ? 1 : 0;
                if ($row['nilai'] === 1) $jumlah++;
            } else {
                $row['nilai'] = null;
            }
        }
        unset($row);

        $v->detail = $detail;

        // Hitung persentase hanya dari baris yang sudah terisi kedua jawaban
        $terisi = collect($detail)->filter(fn($r) => ($r['nilai'] ?? null) !== null)->count();
        $v->jumlah_kesesuaian = $jumlah;

        if ($terisi > 0) {
            $v->persentase_kesesuaian = round(($jumlah / $total) * 100, 2);
            $v->is_valid              = $v->persentase_kesesuaian >= 90;
        } else {
            $v->persentase_kesesuaian = null;
            $v->is_valid              = null;
        }

        $v->is_submitted = $v->pj1_submitted_at !== null && $v->pj2_submitted_at !== null;
        $v->save();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getActiveIndikators(string $kodeUnit, ?string $namaTim)
    {
        $q = Indikator::where('kode_unit', $kodeUnit)->where('is_active', true);
        if ($namaTim !== null) {
            $q->where('tim_unit', $namaTim);
        }
        return $q->select(['id', 'indikator'])->get();
    }

    private function syncDetail(ValidasiDataIndikator $record, ?string $namaTim): ValidasiDataIndikator
    {
        $indikators = $this->getActiveIndikators($record->kode_unit, $namaTim);
        $existing = collect($record->detail ?? [])->keyBy('indikator_id');
        $detail = $indikators->map(function ($ind) use ($existing) {
            if ($existing->has($ind->id)) {
                return $existing->get($ind->id);
            }
            return [
                'indikator_id'   => $ind->id,
                'nama_indikator' => $ind->indikator,
                'pj1_jawaban'    => null,
                'pj2_jawaban'    => null,
                'nilai'          => null,
            ];
        })->values()->toArray();

        if (count($detail) !== count($record->detail ?? [])) {
            $record->detail = $detail;
            $record->save();
        }

        return $record;
    }

    /**
     * Ambil semua record untuk tahun tertentu (untuk admin di halaman Indikator).
     * Return map keyed by "kode_unit|nama_tim|triwulan" → status singkat.
     */
    public function getAllForYear(Request $request)
    {
        $user = Auth::user();
        $isAdmin = ($user->role ?? '') === 'admin_mutu' || $user->email === 'admin@mutu.rsud.go.id';
        if (!$isAdmin) {
            return response()->json(['error' => 'Tidak memiliki kewenangan'], 403);
        }

        $tahun = $request->input('tahun', now()->year);

        $records = ValidasiDataIndikator::where('tahun', $tahun)->get();

        $map = [];
        foreach ($records as $rec) {
            $key = ($rec->kode_unit ?? '') . '|' . ($rec->nama_tim ?? '') . '|' . $rec->triwulan;
            $map[$key] = [
                'is_required'  => (bool) $rec->is_required,
                'is_submitted' => (bool) $rec->is_submitted,
                'is_valid'     => $rec->is_valid,
            ];
        }

        return response()->json(['map' => $map]);
    }

    public static function formatRecord(ValidasiDataIndikator $v): array
    {
        $pj1 = $v->pj_data_1_id ? User::find($v->pj_data_1_id) : null;
        $pj2 = $v->pj_data_2_id ? User::find($v->pj_data_2_id) : null;

        return [
            'id'                    => $v->id,
            'kode_unit'             => $v->kode_unit,
            'nama_tim'              => $v->nama_tim,
            'tahun'                 => $v->tahun,
            'triwulan'              => $v->triwulan,
            'detail'                => $v->detail,
            'jumlah_kesesuaian'     => $v->jumlah_kesesuaian,
            'persentase_kesesuaian' => $v->persentase_kesesuaian,
            'is_valid'              => $v->is_valid,
            'is_required'           => (bool) $v->is_required,
            'pj_data_1_id'          => $v->pj_data_1_id,
            'pj_data_2_id'          => $v->pj_data_2_id,
            'pj1_submitted_at'      => $v->pj1_submitted_at?->toISOString(),
            'pj2_submitted_at'      => $v->pj2_submitted_at?->toISOString(),
            'is_submitted'          => (bool) $v->is_submitted,
            'pj1_info'              => $pj1 ? ['name' => $pj1->name, 'nip' => $pj1->nip] : null,
            'pj2_info'              => $pj2 ? ['name' => $pj2->name, 'nip' => $pj2->nip] : null,
        ];
    }
}
