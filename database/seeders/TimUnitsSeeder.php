<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TimUnit;

class TimUnitsSeeder extends Seeder
{
    public function run(): void
    {
        $timUnits = [
            // Tim di Bidang Pelayanan Medik
            ['kode_unit' => 'Medik', 'nama_tim' => 'Instalasi Gawat Darurat'],
            ['kode_unit' => 'Medik', 'nama_tim' => 'Instalasi Rawat Jalan'],
            ['kode_unit' => 'Medik', 'nama_tim' => 'Instalasi Rawat Inap'],
            ['kode_unit' => 'Medik', 'nama_tim' => 'Instalasi Bedah Sentral'],
            ['kode_unit' => 'Medik', 'nama_tim' => 'Instalasi Rawat Intensif'],
            
            // Tim di Bidang Pelayanan Penunjang
            ['kode_unit' => 'Penunjang', 'nama_tim' => 'Instalasi Farmasi'],
            ['kode_unit' => 'Penunjang', 'nama_tim' => 'Instalasi Laboratorium'],
            ['kode_unit' => 'Penunjang', 'nama_tim' => 'Instalasi Gizi'],
            ['kode_unit' => 'Penunjang', 'nama_tim' => 'Instalasi Radiologi'],
            ['kode_unit' => 'Penunjang', 'nama_tim' => 'Instalasi Rekam Medis'],
            ['kode_unit' => 'Penunjang', 'nama_tim' => 'Instalasi Penunjang Khusus'],
            ['kode_unit' => 'Penunjang', 'nama_tim' => 'Instalasi Penunjang Terapi'],
            ['kode_unit' => 'Penunjang', 'nama_tim' => 'Instalasi CSSD dan Laundry Linen Akses'],
            
            // Tim di Bidang Pelayanan Keperawatan
            ['kode_unit' => 'Layanan_Keperawatan', 'nama_tim' => 'Pelayanan Mutu dan Asuhan Keperawatan & Kebidanan'],
            ['kode_unit' => 'Layanan_Keperawatan', 'nama_tim' => 'Pelayanan Sistem Keperawatan & Kebidanan'],
            ['kode_unit' => 'Layanan_Keperawatan', 'nama_tim' => 'Pelayanan Sumber Daya Keperawatan & Kebidanan'],
            ['kode_unit' => 'Layanan_Keperawatan', 'nama_tim' => 'Pelayanan SDM Keperawatan & Kebidanan'],
            
            // Tim di Bagian SDM, Pendidikan dan Penelitian
            ['kode_unit' => 'BSDM', 'nama_tim' => 'Perencanaan & Pemberdayaan SDM'],
            ['kode_unit' => 'BSDM', 'nama_tim' => 'Administrasi Kesejahteraan SDM'],
            ['kode_unit' => 'BSDM', 'nama_tim' => 'Pengembangan SDM'],
            ['kode_unit' => 'BSDM', 'nama_tim' => 'Diklat, Pendidikan & Penelitian'],
            
            // Tim di Bagian Data dan Teknologi Informasi
            ['kode_unit' => 'Datin', 'nama_tim' => 'Pengembangan Sistem Informasi dan Aplikasi'],
            ['kode_unit' => 'Datin', 'nama_tim' => 'Pengelolaan Sistem dan Database'],
            ['kode_unit' => 'Datin', 'nama_tim' => 'Pengelolaan Sarana Informasi dan Teknologi'],
            
            // Tim di Bagian Umum dan Pemasaran
            ['kode_unit' => 'Umum_Pemasaran', 'nama_tim' => 'PKRS & Pemasaran'],
            ['kode_unit' => 'Umum_Pemasaran', 'nama_tim' => 'Sekretariat & Legal'],
            ['kode_unit' => 'Umum_Pemasaran', 'nama_tim' => 'Pemeliharaan Sarana dan Prasarana'],
            ['kode_unit' => 'Umum_Pemasaran', 'nama_tim' => 'Logistik, Rumah Tangga & Perlengkapan'],
            
            // Tim di Bagian Keuangan dan Perencanaan
            ['kode_unit' => 'Keu', 'nama_tim' => 'Mobilisasi Dana'],
            ['kode_unit' => 'Keu', 'nama_tim' => 'Perbendaharaan & Verifikasi'],
            ['kode_unit' => 'Keu', 'nama_tim' => 'Akuntansi, Perencanaan & Anggaran'],
            ['kode_unit' => 'Keu', 'nama_tim' => 'Akuntansi'],
        
            // // Tim di Bagian Komite Etik dan Hukum
            // ['kode_unit' => 'EtikHukum', 'nama_tim' => 'Komite Etik dan Hukum'],
            
            // // Tim di Bagian Komite Etik Penelitian
            // ['kode_unit' => 'EtikPenelitian', 'nama_tim' => 'Komite Etik Penelitian'],
        
            // // Tim di Bagian Komite Etik dan Hukum
            // ['kode_unit' => 'EtikHukum', 'nama_tim' => 'Komite Etik dan Hukum'],
        
            // // Tim di Bagian Komite Medik
            // ['kode_unit' => 'Komdik', 'nama_tim' => 'Komite Medik'],
            // // ['kode_unit' => 'EtikHukum', 'nama_tim' => 'Komite Etik dan Hukum'],
        
            // // Tim di Bagian Komite PRA
            // ['kode_unit' => 'PRA', 'nama_tim' => 'Komite PRA'],
            // // ['kode_unit' => 'EtikHukum', 'nama_tim' => 'Komite Etik dan Hukum'],
        
            // // Tim di Bagian Komite Rekam Medik
            // ['kode_unit' => 'KRM', 'nama_tim' => 'Komite Rekam Medik'],
            // // ['kode_unit' => 'EtikHukum', 'nama_tim' => 'Komite Etik dan Hukum'],
        
            // // Tim di Bagian Komkordik
            // ['kode_unit' => 'Komkoridk', 'nama_tim' => 'Komkordik'],
            // // ['kode_unit' => 'EtikHukum', 'nama_tim' => 'Komite Etik dan Hukum'],
        
            // // Tim di Bagian Komite Keperawatan
            // ['kode_unit' => 'Keperawatan', 'nama_tim' => 'Komite Keperawatan'],
            // // ['kode_unit' => 'EtikHukum', 'nama_tim' => 'Komite Etik dan Hukum'],
        
            // // Tim di Bagian Komite Mutu dan Keselamatan Pasien
            // ['kode_unit' => 'Mutu', 'nama_tim' => 'Komite Mutu dan Keselamatan Pasien'],
            // // ['kode_unit' => 'EtikHukum', 'nama_tim' => 'Komite Etik dan Hukum'],
        
            // // Tim di Bagian Komite PPI
            // ['kode_unit' => 'PPI', 'nama_tim' => 'Komite PPI'],
            // // ['kode_unit' => 'EtikHukum', 'nama_tim' => 'Komite Etik dan Hukum'],
        
            // // Tim di Bagian Komite Profesi Kesehatan Lain
            // ['kode_unit' => 'K3KL', 'nama_tim' => 'Komite Profesi Kesehatan Lain'],
            // // ['kode_unit' => 'EtikHukum', 'nama_tim' => 'Komite Etik dan Hukum'],
        
            // // Tim di Bagian Komite Farmasi & Terapi
            // ['kode_unit' => 'FarmasiTerapi', 'nama_tim' => 'Komite Farmasi & Terapi'],
            // // ['kode_unit' => 'EtikHukum', 'nama_tim' => 'Komite Etik dan Hukum'],
        
            //  // Tim di Bagian Satuan Pengawas Internal
            // ['kode_unit' => 'SPI', 'nama_tim' => 'Satuan Pengawas Internal'],

             // Tim di Bagian Komite Farmasi & Terapi
            // ['kode_unit' => 'FarmasiTerapi', 'nama_tim' => 'Komite Farmasi & Terapi'],
        ];

        foreach ($timUnits as $tim) {
            TimUnit::create($tim);
        }
    }
}