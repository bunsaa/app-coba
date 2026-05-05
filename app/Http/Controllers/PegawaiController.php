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
        if ($user->role !== 'admin_mutu' && $user->email !== 'admin@mutu.rsud.go.id') {
            abort(403);
        }

        $users = User::with('unit')->orderBy('name', 'asc')->get();
        $units = Units::orderBy('kode_unit', 'asc')->get();

        return Inertia::render('ManajemenPegawai', [
            'users' => $users,
            'units' => $units,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'admin_mutu' && $user->email !== 'admin@mutu.rsud.go.id') {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'nip' => 'nullable|string|max:30|unique:users,nip',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin_mutu,kepala_unit,staf',
            'status_pegawai' => 'nullable|in:PNS,CPNS,PPPK,PPPK Paruh Waktu,Pegawai Blud (Tetap Non ASN),PJLP,Mitra,Pegawai Lainnya Non ASN',
            'kode_unit' => 'nullable|exists:units,kode_unit',
        ], [
            'name.required' => 'Nama wajib diisi',
            'nip.unique' => 'NIP sudah digunakan',
            'email.required' => 'Email wajib diisi',
            'email.unique' => 'Email sudah digunakan',
            'password.required' => 'Password wajib diisi',
            'password.min' => 'Password minimal 6 karakter',
            'role.required' => 'Jabatan wajib dipilih',
        ]);

        User::create([
            'name' => $request->name,
            'nip' => $request->nip ?: null,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'status_pegawai' => $request->status_pegawai,
            'kode_unit' => $request->kode_unit,
        ]);

        return redirect()->back()->with('success', 'Pegawai berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if ($user->role !== 'admin_mutu' && $user->email !== 'admin@mutu.rsud.go.id') {
            abort(403);
        }

        $pegawai = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'nip' => 'nullable|string|max:30|unique:users,nip,' . $id,
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'role' => 'required|in:admin_mutu,kepala_unit,staf',
            'status_pegawai' => 'nullable|in:PNS,CPNS,PPPK,PPPK Paruh Waktu,Pegawai Blud (Tetap Non ASN),PJLP,Mitra,Pegawai Lainnya Non ASN',
            'kode_unit' => 'nullable|exists:units,kode_unit',
        ], [
            'name.required' => 'Nama wajib diisi',
            'nip.unique' => 'NIP sudah digunakan',
            'email.required' => 'Email wajib diisi',
            'email.unique' => 'Email sudah digunakan',
            'password.min' => 'Password minimal 6 karakter',
            'role.required' => 'Jabatan wajib dipilih',
        ]);

        $updateData = [
            'name' => $request->name,
            'nip' => $request->nip ?: null,
            'email' => $request->email,
            'role' => $request->role,
            'status_pegawai' => $request->status_pegawai,
            'kode_unit' => $request->kode_unit,
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
        if ($user->role !== 'admin_mutu' && $user->email !== 'admin@mutu.rsud.go.id') {
            abort(403);
        }

        if ($user->id == $id) {
            return redirect()->back()->with('error', 'Tidak dapat menghapus akun sendiri!');
        }

        $pegawai = User::findOrFail($id);
        $pegawai->delete();

        return redirect()->back()->with('success', 'Pegawai berhasil dihapus!');
    }
}
