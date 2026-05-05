<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * AKUN DUMMY UNTUK LOGIN (NIP + password):
     * ┌─────────────────┬──────────────┬──────────────┬──────────────┐
     * │ Nama            │ NIP          │ Password     │ Role         │
     * ├─────────────────┼──────────────┼──────────────┼──────────────┤
     * │ Admin Mutu      │ 1000000001   │ Admin@123    │ admin_mutu   │
     * │ Kepala Unit Demo│ 2000000001   │ Demo@123     │ kepala_unit  │
     * │ Staf Demo       │ 3000000001   │ Demo@123     │ staf         │
     * └─────────────────┴──────────────┴──────────────┴──────────────┘
     */
    public function run(): void
    {
        // ── Akun admin utama ───────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'admin@mutu.rsud.go.id'],
            [
                'name'     => 'Admin Mutu',
                'nip'      => '1000000001',
                'password' => Hash::make('Admin@123'),
                'role'     => 'admin_mutu',
            ]
        );

        // ── Akun dummy Kepala Unit ─────────────────────────────────
        User::updateOrCreate(
            ['email' => 'kepalaunit@mutu.rsud.go.id'],
            [
                'name'           => 'Kepala Unit Demo',
                'nip'            => '2000000001',
                'password'       => Hash::make('Demo@123'),
                'role'           => 'kepala_unit',
                'status_pegawai' => 'PNS',
            ]
        );

        // ── Akun dummy Staf ────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'staf@mutu.rsud.go.id'],
            [
                'name'           => 'Staf Demo',
                'nip'            => '3000000001',
                'password'       => Hash::make('Demo@123'),
                'role'           => 'staf',
                'status_pegawai' => 'PNS',
            ]
        );

        // ── Unit-unit lama (dengan NIP sequential, role kepala_unit) ─
        // NIP format: 40000000XX (XX = urutan 01-23)
        $unitUsers = [
            ['name' => 'Komite Etik dan Hukum',                         'email' => 'etikhukum@mutu.rsud.go.id',        'nip' => '4000000001'],
            ['name' => 'Komite Etik Penelitian',                        'email' => 'etikpenelitian@mutu.rsud.go.id',   'nip' => '4000000002'],
            ['name' => 'Komite Medik',                                  'email' => 'komdik@mutu.rsud.go.id',           'nip' => '4000000003'],
            ['name' => 'Komite PRA',                                    'email' => 'pra@mutu.rsud.go.id',              'nip' => '4000000004'],
            ['name' => 'Komite Rekam Medik',                            'email' => 'krm@mutu.rsud.go.id',              'nip' => '4000000005'],
            ['name' => 'Komkordik',                                     'email' => 'komkordik@mutu.rsud.go.id',        'nip' => '4000000006'],
            ['name' => 'Komite Keperawatan',                            'email' => 'keperawatan@mutu.rsud.go.id',      'nip' => '4000000007'],
            ['name' => 'Komite Mutu dan Keselamatan Pasien',            'email' => 'mutu@mutu.rsud.go.id',             'nip' => '4000000008'],
            ['name' => 'Komite PPI',                                    'email' => 'ppi@mutu.rsud.go.id',              'nip' => '4000000009'],
            ['name' => 'Komite Profesi Kesehatan Lain',                 'email' => 'k3kl@mutu.rsud.go.id',            'nip' => '4000000010'],
            ['name' => 'Komite Farmasi & Terapi',                       'email' => 'farmasiterapi@mutu.rsud.go.id',    'nip' => '4000000011'],
            ['name' => 'Dewan Pengawas',                                'email' => 'dewas@mutu.rsud.go.id',            'nip' => '4000000012'],
            ['name' => 'Satuan Pengawas Internal',                      'email' => 'spi@mutu.rsud.go.id',              'nip' => '4000000013'],
            ['name' => 'Wakil Direktur Pelayanan',                      'email' => 'wadir.pel@mutu.rsud.go.id',        'nip' => '4000000014'],
            ['name' => 'Wakil Direktur Administrasi Umum dan Keuangan', 'email' => 'wadir.adminkum@mutu.rsud.go.id',   'nip' => '4000000015'],
            ['name' => 'Bidang Pelayanan Medik',                        'email' => 'medik@mutu.rsud.go.id',            'nip' => '4000000016'],
            ['name' => 'Bidang Pelayanan Penunjang',                    'email' => 'penunjang@mutu.rsud.go.id',        'nip' => '4000000017'],
            ['name' => 'Bidang Pelayanan Keperawatan',                  'email' => 'layanan.keperawatan@mutu.rsud.go.id', 'nip' => '4000000018'],
            ['name' => 'Bagian SDM, Pendidikan dan Penelitian',         'email' => 'bsdm@mutu.rsud.go.id',             'nip' => '4000000019'],
            ['name' => 'Bagian Data dan Teknologi Informasi',           'email' => 'datin@mutu.rsud.go.id',            'nip' => '4000000020'],
            ['name' => 'Bagian Umum dan Pemasaran',                     'email' => 'umum.pemasaran@mutu.rsud.go.id',   'nip' => '4000000021'],
            ['name' => 'Bagian Keuangan dan Perencanaan',               'email' => 'keu@mutu.rsud.go.id',              'nip' => '4000000022'],
            ['name' => 'Kelompok Jabatan Fungsional',                   'email' => 'kjf@mutu.rsud.go.id',              'nip' => '4000000023'],
        ];

        foreach ($unitUsers as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name'     => $userData['name'],
                    'nip'      => $userData['nip'],
                    'password' => Hash::make('password'),
                    'role'     => 'kepala_unit',
                ]
            );
        }
    }
}
