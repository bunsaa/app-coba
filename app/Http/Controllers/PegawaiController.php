<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Units;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class PegawaiController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user->role !== 'admin_mutu') {
            abort(403);
        }

        $users = User::with('unit')->orderBy('name', 'asc')->get();
        $units = Units::with('tim_units')->orderBy('kode_unit', 'asc')->get();

        return Inertia::render('ManajemenPegawai', [
            'users' => $users,
            'units' => $units,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'admin_mutu') {
            abort(403);
        }

        $request->validate([
            'name'           => 'required|string|max:255',
            'nip'            => 'nullable|string|max:30|unique:users,nip',
            'password'       => 'required|string|min:6',
            'base_role'      => 'required|in:admin_mutu,kepala_unit,staf',
            'status_pegawai' => 'nullable|in:PNS,CPNS,PPPK,PPPK Paruh Waktu,Pegawai Blud (Tetap Non ASN),PJLP,Mitra,Pegawai Lainnya Non ASN',
            'status_kerja'   => 'nullable|in:Aktif,Resign,Pensiun,Mutasi',
            'kode_unit'      => 'nullable|exists:units,kode_unit',
            'is_pj_data'     => 'nullable|boolean',
            'pj_data_tim'    => 'nullable|string|max:200',
        ], [
            'name.required'     => 'Nama wajib diisi',
            'nip.unique'        => 'NIP sudah digunakan',
            'password.required' => 'Password wajib diisi',
            'password.min'      => 'Password minimal 6 karakter',
            'base_role.required'=> 'Jabatan wajib dipilih',
        ]);

        $role = $this->resolveRole($request);

        User::create([
            'name'           => $request->name,
            'nip'            => $request->nip ?: null,
            'password'       => Hash::make($request->password),
            'role'           => $role,
            'status_pegawai' => $request->status_pegawai,
            'status_kerja'   => $request->status_kerja,
            'kode_unit'      => $request->kode_unit,
        ]);

        return redirect()->back()->with('success', 'Pegawai berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if ($user->role !== 'admin_mutu') {
            abort(403);
        }

        $pegawai = User::findOrFail($id);

        $request->validate([
            'name'           => 'required|string|max:255',
            'nip'            => 'nullable|string|max:30|unique:users,nip,' . $id,
            'password'       => 'nullable|string|min:6',
            'base_role'      => 'required|in:admin_mutu,kepala_unit,staf',
            'status_pegawai' => 'nullable|in:PNS,CPNS,PPPK,PPPK Paruh Waktu,Pegawai Blud (Tetap Non ASN),PJLP,Mitra,Pegawai Lainnya Non ASN',
            'status_kerja'   => 'nullable|in:Aktif,Resign,Pensiun,Mutasi',
            'kode_unit'      => 'nullable|exists:units,kode_unit',
            'is_pj_data'     => 'nullable|boolean',
            'pj_data_tim'    => 'nullable|string|max:200',
        ], [
            'name.required'      => 'Nama wajib diisi',
            'nip.unique'         => 'NIP sudah digunakan',
            'password.min'       => 'Password minimal 6 karakter',
            'base_role.required' => 'Jabatan wajib dipilih',
        ]);

        $role = $this->resolveRole($request);

        $updateData = [
            'name'           => $request->name,
            'nip'            => $request->nip ?: null,
            'role'           => $role,
            'status_pegawai' => $request->status_pegawai,
            'status_kerja'   => $request->status_kerja,
            'kode_unit'      => $request->kode_unit,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $pegawai->update($updateData);

        return redirect()->back()->with('success', 'Pegawai berhasil diupdate!');
    }

    public function destroy($id)
    {
        $user = Auth::user();
        if ($user->role !== 'admin_mutu') {
            abort(403);
        }

        if ($user->id == $id) {
            return redirect()->back()->with('error', 'Tidak dapat menghapus akun sendiri!');
        }

        $pegawai = User::findOrFail($id);
        $pegawai->delete();

        return redirect()->back()->with('success', 'Pegawai berhasil dihapus!');
    }

    /**
     * Resolusi role akhir berdasarkan base_role + is_pj_data + pj_data_tim.
     */
    private function resolveRole(Request $request): string
    {
        $baseRole = $request->base_role;

        if ($request->boolean('is_pj_data') && $baseRole === 'staf') {
            $tim = trim($request->pj_data_tim ?? '');
            return $tim !== '' ? "PJ Data - {$tim}" : 'PJ Data';
        }

        return $baseRole;
    }
}
