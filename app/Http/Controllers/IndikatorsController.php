<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Units;
use App\Models\Indikator;
use App\Http\Requests\StoreIndikatorRequest;
use Illuminate\Support\Facades\Log;

class IndikatorsController extends Controller
{
    public function index()
    {
        $units = Units::with('tim_units')
            ->orderBy('kode_unit', 'asc')
            ->get();

        return Inertia::render('Indikator', [
            'units' => $units,
            'currentFilter' => ''
        ]);
    }

    public function store(StoreIndikatorRequest $request)
    {
        Log::info('Request diterima:', $request->all());

        try {
            $data = $request->validated();
            $createdCount = 0;

            // Cegah duplikat: tolak jika indikator dengan nama + jenis yang sama sudah ada
            $exists = Indikator::where('indikator', $data['indikator'])
                ->where('jenis_indikator', $data['jenis_indikator'])
                ->exists();

            if ($exists) {
                return redirect()->back()
                    ->withErrors(['indikator' => 'Indikator dengan nama dan jenis ini sudah ada. Gunakan fitur Edit di Rekap untuk menambah atau mengubah unit.'])
                    ->withInput();
            }

            // Jika ada pic_units (multi-select), create multiple records
            // Format: ["BSDM|Diklat, Pendidikan & Penelitian", "DATIN|Pengelolaan Sistem dan Database", "DEWAS"]
            if (isset($data['pic_units']) && is_array($data['pic_units']) && count($data['pic_units']) > 0) {
                Log::info('Creating multiple indikator records for pic_units:', $data['pic_units']);

                foreach ($data['pic_units'] as $picUnit) {
                    // Parse format: "KODE_UNIT|Tim Unit" atau "KODE_UNIT" (jika tidak ada tim)
                    $parts = explode('|', $picUnit, 2);
                    $kodeUnit = trim($parts[0]);
                    $timUnit = isset($parts[1]) ? trim($parts[1]) : null;

                    // Create indikator record untuk setiap unit
                    $indikatorData = [
                        'jenis_indikator' => $data['jenis_indikator'],
                        'kode_unit' => $kodeUnit,
                        'tim_unit' => $timUnit,
                        'indikator' => $data['indikator'],
                        'standar' => $data['standar'] ?? '',
                        'satuan' => $data['satuan'] ?? 'persen',
                        'satuan_waktu' => $data['satuan_waktu'] ?? null,
                        'standar_tw1' => $data['standar_tw1'] ?? null,
                        'satuan_tw1'  => $data['satuan_tw1'] ?? null,
                        'satuan_waktu_tw1' => $data['satuan_waktu_tw1'] ?? null,
                        'standar_tw2' => $data['standar_tw2'] ?? null,
                        'satuan_tw2'  => $data['satuan_tw2'] ?? null,
                        'satuan_waktu_tw2' => $data['satuan_waktu_tw2'] ?? null,
                        'standar_tw3' => $data['standar_tw3'] ?? null,
                        'satuan_tw3'  => $data['satuan_tw3'] ?? null,
                        'satuan_waktu_tw3' => $data['satuan_waktu_tw3'] ?? null,
                        'standar_tw4' => $data['standar_tw4'] ?? null,
                        'satuan_tw4'  => $data['satuan_tw4'] ?? null,
                        'satuan_waktu_tw4' => $data['satuan_waktu_tw4'] ?? null,
                        'pic' => $timUnit ?: $kodeUnit,
                        'pic_units' => null,
                        'numerator' => $data['numerator'],
                        'denominator' => $data['denominator'],
                        'is_active' => true,
                        'berlaku_tw' => [1,2,3,4],
                    ];

                    Indikator::create($indikatorData);
                    $createdCount++;

                    Log::info("Indikator created for {$kodeUnit}" . ($timUnit ? " - {$timUnit}" : ''), $indikatorData);
                }

                Log::info("Total {$createdCount} indikator records created");
                return redirect()->back()->with('success', "Indikator berhasil ditambahkan untuk {$createdCount} unit!");
            } else {
                // Fallback: jika tidak ada pic_units, buat single record (backward compatibility)
                $data['is_active'] = true;
                $indikator = Indikator::create($data);
                Log::info('Single indikator created:', $indikator->toArray());
                return redirect()->back()->with('success', 'Indikator berhasil ditambahkan!');
            }
        } catch (\Exception $e) {
            Log::error('Error menyimpan indikator:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Gagal menambahkan indikator: ' . $e->getMessage());
        }
    }

    public function toggleActive($id)
    {
        try {
            Log::info('Toggle active request for ID:', ['id' => $id]);

            $indikator = Indikator::findOrFail($id);

            Log::info('Before toggle:', ['is_active' => $indikator->is_active]);

            $indikator->is_active = !$indikator->is_active;
            $indikator->save();

            Log::info('After toggle:', ['is_active' => $indikator->is_active]);

            $status = $indikator->is_active ? 'diaktifkan' : 'dinonaktifkan';

            return response()->json([
                'success' => true,
                'message' => "Indikator berhasil {$status}",
                'is_active' => $indikator->is_active
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggle active:', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAllIndikators()
    {
        $indikators = Indikator::with(['unit:kode_unit,nama_unit'])
            ->select([
                'id','jenis_indikator','is_prioritas','kode_unit','tim_unit','indikator',
                'numerator','denominator',
                'standar','satuan','satuan_waktu',
                'standar_tw1','satuan_tw1','satuan_waktu_tw1',
                'standar_tw2','satuan_tw2','satuan_waktu_tw2',
                'standar_tw3','satuan_tw3','satuan_waktu_tw3',
                'standar_tw4','satuan_tw4','satuan_waktu_tw4',
                'is_active',
            ])
            ->orderByRaw("FIELD(jenis_indikator, 'INM', 'SPM', 'PRIORITAS', 'IMUT_RS', 'IMUT_UNIT')")
            ->orderBy('kode_unit')
            ->orderBy('tim_unit')
            ->orderBy('id')
            ->get()
            ->map(function ($ind) {
                return [
                    'id'               => $ind->id,
                    'jenis_indikator'  => $ind->jenis_indikator,
                    'is_prioritas'     => $ind->is_prioritas,
                    'kode_unit'        => $ind->kode_unit,
                    'tim_unit'         => $ind->tim_unit,
                    'nama_unit'        => $ind->unit?->nama_unit ?? $ind->kode_unit ?? '-',
                    'indikator'        => $ind->indikator,
                    'numerator'        => $ind->numerator,
                    'denominator'      => $ind->denominator,
                    'standar'          => $ind->standar,
                    'satuan'           => $ind->satuan,
                    'satuan_waktu'     => $ind->satuan_waktu,
                    'standar_tw1'      => $ind->standar_tw1,
                    'satuan_tw1'       => $ind->satuan_tw1,
                    'satuan_waktu_tw1' => $ind->satuan_waktu_tw1,
                    'standar_tw2'      => $ind->standar_tw2,
                    'satuan_tw2'       => $ind->satuan_tw2,
                    'satuan_waktu_tw2' => $ind->satuan_waktu_tw2,
                    'standar_tw3'      => $ind->standar_tw3,
                    'satuan_tw3'       => $ind->satuan_tw3,
                    'satuan_waktu_tw3' => $ind->satuan_waktu_tw3,
                    'standar_tw4'      => $ind->standar_tw4,
                    'satuan_tw4'       => $ind->satuan_tw4,
                    'satuan_waktu_tw4' => $ind->satuan_waktu_tw4,
                    'is_active'        => $ind->is_active,
                ];
            });

        return response()->json($indikators);
    }

    public function rekapUpdate(Request $request)
    {
        $request->validate([
            'original_indikator' => 'required|string',
            'jenis_indikator'    => 'required|string',
            'indikator'          => 'required|string',
            'standar'            => 'nullable|string',
            'numerator'          => 'required|string',
            'denominator'        => 'required|string',
            'satuan'             => 'nullable|string',
            'satuan_waktu'       => 'nullable|string',
            'standar_tw1'        => 'nullable|string',
            'satuan_tw1'         => 'nullable|string',
            'satuan_waktu_tw1'   => 'nullable|string',
            'standar_tw2'        => 'nullable|string',
            'satuan_tw2'         => 'nullable|string',
            'satuan_waktu_tw2'   => 'nullable|string',
            'standar_tw3'        => 'nullable|string',
            'satuan_tw3'         => 'nullable|string',
            'satuan_waktu_tw3'   => 'nullable|string',
            'standar_tw4'        => 'nullable|string',
            'satuan_tw4'         => 'nullable|string',
            'satuan_waktu_tw4'   => 'nullable|string',
            'pic_units'          => 'nullable|array',
        ]);

        $updateData = [
            'indikator'        => $request->indikator,
            'standar'          => $request->standar,
            'numerator'        => $request->numerator,
            'denominator'      => $request->denominator,
            'satuan'           => $request->satuan,
            'satuan_waktu'     => $request->satuan === 'rata-rata' ? $request->satuan_waktu : null,
            'standar_tw1'      => $request->standar_tw1,
            'satuan_tw1'       => $request->satuan_tw1,
            'satuan_waktu_tw1' => $request->satuan_tw1 === 'rata-rata' ? $request->satuan_waktu_tw1 : null,
            'standar_tw2'      => $request->standar_tw2,
            'satuan_tw2'       => $request->satuan_tw2,
            'satuan_waktu_tw2' => $request->satuan_tw2 === 'rata-rata' ? $request->satuan_waktu_tw2 : null,
            'standar_tw3'      => $request->standar_tw3,
            'satuan_tw3'       => $request->satuan_tw3,
            'satuan_waktu_tw3' => $request->satuan_tw3 === 'rata-rata' ? $request->satuan_waktu_tw3 : null,
            'standar_tw4'      => $request->standar_tw4,
            'satuan_tw4'       => $request->satuan_tw4,
            'satuan_waktu_tw4' => $request->satuan_tw4 === 'rata-rata' ? $request->satuan_waktu_tw4 : null,
        ];

        // If pic_units provided, sync units (add/remove records as needed)
        if ($request->has('pic_units') && is_array($request->pic_units)) {
            $currentRecords = Indikator::where('indikator', $request->original_indikator)
                ->where('jenis_indikator', $request->jenis_indikator)
                ->get();

            // Build desired unit map keyed by "KODE_UNIT" or "KODE_UNIT|Tim"
            $desiredUnits = [];
            foreach ($request->pic_units as $picUnit) {
                $parts    = explode('|', $picUnit, 2);
                $kodeUnit = trim($parts[0]);
                $timUnit  = isset($parts[1]) ? trim($parts[1]) : null;
                $key      = $timUnit ? "{$kodeUnit}|{$timUnit}" : $kodeUnit;
                $desiredUnits[$key] = ['kode_unit' => $kodeUnit, 'tim_unit' => $timUnit];
            }

            // Build current unit map keyed the same way
            $currentMap = [];
            foreach ($currentRecords as $record) {
                $key = $record->tim_unit
                    ? "{$record->kode_unit}|{$record->tim_unit}"
                    : $record->kode_unit;
                $currentMap[$key] = $record;
            }

            // Delete records for units no longer in desired set
            foreach ($currentMap as $key => $record) {
                if (!isset($desiredUnits[$key])) {
                    $record->delete();
                }
            }

            // Update existing records that remain
            foreach ($currentMap as $key => $record) {
                if (isset($desiredUnits[$key])) {
                    $record->update($updateData);
                }
            }

            // Create records for newly added units
            foreach ($desiredUnits as $key => $unit) {
                if (!isset($currentMap[$key])) {
                    Indikator::create(array_merge($updateData, [
                        'jenis_indikator' => $request->jenis_indikator,
                        'kode_unit'       => $unit['kode_unit'],
                        'tim_unit'        => $unit['tim_unit'],
                        'pic'             => $unit['tim_unit'] ?: $unit['kode_unit'],
                        'is_active'       => true,
                    ]));
                }
            }

            return response()->json([
                'message' => 'Indikator berhasil diupdate',
                'updated' => count($desiredUnits),
            ]);
        }

        // Fallback: bulk update all matching records (no unit sync)
        $updated = Indikator::where('indikator', $request->original_indikator)
            ->where('jenis_indikator', $request->jenis_indikator)
            ->update($updateData);

        return response()->json([
            'message' => 'Indikator berhasil diupdate',
            'updated' => $updated,
        ]);
    }

    public function toggleActiveGroup(Request $request)
    {
        $request->validate([
            'original_indikator' => 'required|string',
            'jenis_indikator'    => 'required|string',
            'is_active'          => 'required|boolean',
        ]);

        $updated = Indikator::where('indikator', $request->original_indikator)
            ->where('jenis_indikator', $request->jenis_indikator)
            ->update(['is_active' => $request->is_active]);

        $status = $request->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'message' => "Indikator berhasil {$status}",
            'updated' => $updated,
        ]);
    }

    public function togglePrioritas(Request $request)
    {
        $request->validate([
            'indikator_text' => 'required|string',
            'is_prioritas'   => 'required|boolean',
        ]);

        $updated = Indikator::where('indikator', $request->input('indikator_text'))
            ->where('jenis_indikator', 'INM')
            ->update(['is_prioritas' => $request->input('is_prioritas')]);

        return response()->json([
            'message' => $request->input('is_prioritas')
                ? 'Indikator ditandai sebagai Prioritas RS'
                : 'Tanda Prioritas RS dihapus',
            'updated' => $updated,
        ]);
    }

    public function getByUnit(Request $request)
    {
        try {
            $validated = $request->validate([
                'kode_unit' => 'required',
                'tim_unit' => 'nullable|string',
                'include_inactive' => 'nullable|boolean',
            ]);

            Log::info('Get by unit request:', $validated);

            // Cari indikator yang:
            // 1. kode_unit cocok (untuk indikator lama), ATAU
            // 2. pic_units mengandung kode_unit ini, ATAU
            // 3. pic_units mengandung "kode_unit|tim_unit" (untuk tim unit spesifik)

            $searchPattern = $validated['kode_unit'];
            if (!empty($validated['tim_unit'])) {
                // Jika ada tim_unit, cari yang spesifik: "UNIT-01|Tim A"
                $searchPattern = $validated['kode_unit'] . '|' . $validated['tim_unit'];
            }

            $query = Indikator::where(function($q) use ($validated, $searchPattern) {
                // Indikator lama dengan kode_unit
                $q->where('kode_unit', $validated['kode_unit'])
                  // Indikator baru: pic_units mengandung kode_unit saja
                  ->orWhereJsonContains('pic_units', $validated['kode_unit'])
                  // Indikator baru: pic_units mengandung "kode_unit|tim_unit"
                  ->orWhereJsonContains('pic_units', $searchPattern);
            });

            if (!isset($validated['include_inactive']) || !$validated['include_inactive']) {
                $query->where('is_active', true);
            }

            // Filter tim_unit
            if (!empty($validated['tim_unit'])) {
                // Tim spesifik dipilih: tampilkan indikator untuk tim tersebut
                // PLUS indikator tingkat unit (tim_unit null) yang berlaku untuk semua tim
                $query->where(function($q) use ($validated) {
                    $q->where('tim_unit', $validated['tim_unit'])
                      ->orWhereNull('tim_unit');
                });
            }
            // Jika tim_unit tidak ada: tampilkan SEMUA indikator unit ini tanpa filter tim
            // (unit tidak punya tim di DB, atau auto-load dari view modal)

            $indikators = $query->select([
                'id','jenis_indikator','kode_unit','tim_unit','indikator',
                'standar','satuan','satuan_waktu',
                'standar_tw1','satuan_tw1','satuan_waktu_tw1',
                'standar_tw2','satuan_tw2','satuan_waktu_tw2',
                'standar_tw3','satuan_tw3','satuan_waktu_tw3',
                'standar_tw4','satuan_tw4','satuan_waktu_tw4',
                'numerator','denominator','is_active',
            ])->orderBy('id', 'asc')->get();

            Log::info('Indikators found:', ['count' => $indikators->count()]);

            return response()->json($indikators);
        } catch (\Exception $e) {
            Log::error('Error get by unit:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Gagal memuat data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        Log::info('Update request received:', $request->all());

        $request->validate([
            'indikator'        => 'required|string',
            'standar'          => 'nullable|string',
            'numerator'        => 'required|string',
            'denominator'      => 'required|string',
            'satuan'           => 'nullable|in:rata-rata,persen,permil,kejadian,peserta,dokumen',
            'satuan_waktu'     => 'nullable|in:hari,jam,menit',
            'standar_tw1'      => 'nullable|string',
            'satuan_tw1'       => 'nullable|in:rata-rata,persen,permil,kejadian,peserta,dokumen',
            'satuan_waktu_tw1' => 'nullable|in:hari,jam,menit',
            'standar_tw2'      => 'nullable|string',
            'satuan_tw2'       => 'nullable|in:rata-rata,persen,permil,kejadian,peserta,dokumen',
            'satuan_waktu_tw2' => 'nullable|in:hari,jam,menit',
            'standar_tw3'      => 'nullable|string',
            'satuan_tw3'       => 'nullable|in:rata-rata,persen,permil,kejadian,peserta,dokumen',
            'satuan_waktu_tw3' => 'nullable|in:hari,jam,menit',
            'standar_tw4'      => 'nullable|string',
            'satuan_tw4'       => 'nullable|in:rata-rata,persen,permil,kejadian,peserta,dokumen',
            'satuan_waktu_tw4' => 'nullable|in:hari,jam,menit',
        ]);

        try {
            $indikator = Indikator::findOrFail($id);

            Log::info('Before update:', $indikator->toArray());

            $indikator->update([
                'indikator'        => $request->indikator,
                'standar'          => $request->standar,
                'numerator'        => $request->numerator,
                'denominator'      => $request->denominator,
                'satuan'           => $request->satuan,
                'satuan_waktu'     => $request->satuan === 'rata-rata' ? $request->satuan_waktu : null,
                'standar_tw1'      => $request->standar_tw1,
                'satuan_tw1'       => $request->satuan_tw1,
                'satuan_waktu_tw1' => $request->satuan_tw1 === 'rata-rata' ? $request->satuan_waktu_tw1 : null,
                'standar_tw2'      => $request->standar_tw2,
                'satuan_tw2'       => $request->satuan_tw2,
                'satuan_waktu_tw2' => $request->satuan_tw2 === 'rata-rata' ? $request->satuan_waktu_tw2 : null,
                'standar_tw3'      => $request->standar_tw3,
                'satuan_tw3'       => $request->satuan_tw3,
                'satuan_waktu_tw3' => $request->satuan_tw3 === 'rata-rata' ? $request->satuan_waktu_tw3 : null,
                'standar_tw4'      => $request->standar_tw4,
                'satuan_tw4'       => $request->satuan_tw4,
                'satuan_waktu_tw4' => $request->satuan_tw4 === 'rata-rata' ? $request->satuan_waktu_tw4 : null,
            ]);

            Log::info('After update:', $indikator->fresh()->toArray());

            return redirect()->back()->with('success', 'Indikator berhasil diupdate!');
        } catch (\Exception $e) {
            Log::error('Error updating indikator:', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Gagal mengupdate indikator: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $indikator = Indikator::findOrFail($id);
            $indikator->delete();

            return response()->json([
                'success' => true,
                'message' => 'Indikator berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting indikator:', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus indikator: ' . $e->getMessage(),
            ], 500);
        }
    }
}
