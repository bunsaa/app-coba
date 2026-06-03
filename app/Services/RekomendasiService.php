<?php

namespace App\Services;

/**
 * Rule-based recommendation generator for quality indicators.
 *
 * References:
 *  - Permenkes No. 30 Tahun 2022 — Indikator Nasional Mutu (INM) Pelayanan Kesehatan
 *  - Pergub DKI Jakarta No. 20 Tahun 2016 — SPM RSUD
 */
class RekomendasiService
{
    // -----------------------------------------------------------------------
    // INM Keyword Map — Permenkes No. 30 Tahun 2022
    // -----------------------------------------------------------------------
    private const INM_MAP = [
        [
            'keywords'     => ['kebersihan tangan'],
            'source'       => 'INM 1 — Permenkes No. 30 Tahun 2022',
            'action_green' => 'Capaian kepatuhan kebersihan tangan memenuhi standar INM ≥85%. Pertahankan program hand hygiene dengan audit rutin 5 moment cuci tangan dan pastikan fasilitas hand rub tersedia di setiap titik pelayanan. Dokumentasikan bukti capaian untuk pelaporan INM kepada Kemenkes.',
            'action_red'   => 'Capaian kepatuhan kebersihan tangan belum memenuhi target INM ≥85%. Tindak lanjut: (1) Adakan pelatihan hand hygiene 5 moment untuk seluruh staf klinis, (2) Jadwalkan supervisi langsung oleh IPCN minimal 2x/bulan, (3) Evaluasi ketersediaan dan penempatan fasilitas hand rub di area strategis, (4) Lakukan audit kepatuhan secara rutin dan berikan umpan balik hasil kepada kepala ruangan.',
        ],
        [
            'keywords'     => ['penggunaan apd', 'alat pelindung diri'],
            'source'       => 'INM 2 — Permenkes No. 30 Tahun 2022',
            'action_green' => 'Kepatuhan penggunaan APD telah mencapai 100%. Pertahankan ketersediaan stok APD, lanjutkan edukasi berkala tentang pemilihan APD sesuai level risiko, dan dokumentasikan hasil monitoring.',
            'action_red'   => 'Kepatuhan penggunaan APD belum 100%. Tindak lanjut: (1) Identifikasi staf yang tidak patuh dan berikan pembinaan individual, (2) Pastikan stok APD selalu tersedia di setiap zona risiko, (3) Sosialisasi ulang jenis dan penggunaan APD yang tepat sesuai level risiko, (4) Perkuat komitmen melalui kebijakan sanksi bagi yang tidak patuh sesuai peraturan RS.',
        ],
        [
            'keywords'     => ['identifikasi pasien'],
            'source'       => 'INM 3 — Permenkes No. 30 Tahun 2022',
            'action_green' => 'Kepatuhan identifikasi pasien sudah 100%. Pertahankan standar prosedur minimal 2 identifikasi (nama lengkap + tanggal lahir/nomor rekam medis) dan lakukan audit kepatuhan secara berkala.',
            'action_red'   => 'Capaian identifikasi pasien belum 100%. Tindak lanjut: (1) Sosialisasi ulang SOP identifikasi pasien menggunakan minimal 2 identifikasi sebelum setiap tindakan, (2) Lakukan simulasi identifikasi pasien di unit-unit kritis, (3) Perkuat pengawasan supervisor di titik risiko tinggi (sebelum operasi, transfusi darah, pemberian obat LASA).',
        ],
        [
            'keywords'     => ['seksio sesarea', 'seksio sesaria', 'sc emergensi', 'sectio caesarea', 'operasi caesar'],
            'source'       => 'INM 4 — Permenkes No. 30 Tahun 2022',
            'action_green' => 'Waktu tanggap SC emergensi memenuhi standar INM ≥80% kasus selesai dalam ≤30 menit. Pertahankan kesiapan tim OK, komunikasi lintas disiplin, dan dokumentasikan waktu tanggap setiap kasus untuk pelaporan.',
            'action_red'   => 'Waktu tanggap SC emergensi belum mencapai ≥80% dalam ≤30 menit. Tindak lanjut: (1) Lakukan Root Cause Analysis (RCA) pada kasus yang melebihi 30 menit, (2) Evaluasi alur aktivasi tim SC emergensi — pastikan komunikasi cepat antara VK, OK, anestesi, dan neonatus, (3) Pastikan dokter SpOG, SpAn, dan tim OK selalu dalam kondisi on-call siap sesuai ketentuan, (4) Adakan simulasi tanggap darurat SC minimal 1x/kuartal.',
        ],
        [
            'keywords'     => ['waktu tunggu rawat jalan', 'tunggu rawat jalan', 'waktu tunggu poli'],
            'source'       => 'INM 5 — Permenkes No. 30 Tahun 2022',
            'action_green' => 'Waktu tunggu rawat jalan ≤60 menit tercapai pada ≥80% kunjungan. Pertahankan manajemen antrian, kehadiran dokter tepat waktu, ketersediaan rekam medis, dan proses pendaftaran yang efisien.',
            'action_red'   => 'Waktu tunggu rawat jalan belum memenuhi standar ≥80% dalam ≤60 menit. Analisis penyebab: (1) Keterlambatan kehadiran dokter — koordinasikan dengan Komite Medis untuk pembinaan, (2) Proses pendaftaran lambat — evaluasi sistem antrean dan optimasi alur, (3) Rekam medis belum tersedia tepat waktu — perkuat koordinasi dengan unit rekam medis, (4) Kapasitas poliklinik terbatas — pertimbangkan pengaturan jadwal dan penambahan sesi.',
        ],
        [
            'keywords'     => ['penundaan operasi elektif', 'tunda operasi elektif'],
            'source'       => 'INM 6 — Permenkes No. 30 Tahun 2022',
            'action_green' => 'Penundaan operasi elektif sudah di bawah standar ≤5%. Pertahankan manajemen jadwal OK, koordinasi pre-operatif yang baik, dan ketersediaan sumber daya (SDM, alat, dan bahan).',
            'action_red'   => 'Penundaan operasi elektif melebihi standar ≤5%. Identifikasi penyebab utama: (1) Persiapan pre-operatif pasien tidak optimal — perkuat prosedur asesmen pra-operasi, (2) Alat atau bahan tidak tersedia — tingkatkan koordinasi dengan logistik dan CSSD, (3) Keterbatasan SDM atau kamar operasi — buat protokol eskalasi dan evaluasi ulang penjadwalan, (4) Laporkan kasus penundaan secara sistematis untuk RCA berkala.',
        ],
        [
            'keywords'     => ['waktu visite dokter', 'visite dokter', 'kunjungan dokter'],
            'source'       => 'INM 7 — Permenkes No. 30 Tahun 2022',
            'action_green' => 'Kepatuhan waktu visite dokter memenuhi target ≥80% (pukul 06.00–14.00). Pertahankan monitoring jadwal visite dan pastikan dokumentasi waktu visite dalam rekam medis selalu terisi.',
            'action_red'   => 'Kepatuhan waktu visite dokter belum ≥80% pada pukul 06.00–14.00. Tindak lanjut: (1) Laporkan hasil capaian kepada Komite Medis untuk pembinaan DPJP, (2) Monitoring waktu visite secara real-time oleh kepala ruangan, (3) Catat kepatuhan per dokter dan sampaikan dalam rapat komite medis bulanan, (4) Evaluasi kendala (jadwal poliklnik, kamar bedah) yang menjadi penghambat waktu visite.',
        ],
        [
            'keywords'     => ['pelaporan hasil kritis', 'hasil kritis laboratorium', 'hasil kritis lab', 'nilai kritis laboratorium', 'nilai kritis'],
            'source'       => 'INM 8 — Permenkes No. 30 Tahun 2022',
            'action_green' => 'Pelaporan hasil kritis laboratorium 100% dalam ≤30 menit sudah tercapai. Pertahankan prosedur eskalasi nilai kritis, pastikan semua staf laboratorium memahami alur, dan dokumentasikan setiap pelaporan secara lengkap.',
            'action_red'   => 'Pelaporan hasil kritis laboratorium belum 100% dalam ≤30 menit. Tindak lanjut: (1) Sosialisasi ulang dan simulasi SOP pelaporan nilai kritis kepada seluruh analis, (2) Pastikan daftar nilai kritis yang berlaku ditempel di area kerja laboratorium, (3) Audit rekam medis untuk memverifikasi dokumentasi pelaporan dan waktu respons DPJP, (4) Evaluasi sistem komunikasi antara laboratorium dan ruang rawat (telepon/SIMRS).',
        ],
        [
            'keywords'     => ['formularium nasional', 'fornas'],
            'source'       => 'INM 9 — Permenkes No. 30 Tahun 2022',
            'action_green' => 'Kepatuhan penggunaan formularium nasional ≥80% tercapai. Lanjutkan sosialisasi daftar Fornas terbaru kepada DPJP, monitoring ketersediaan obat Fornas secara berkala, dan pastikan obat Fornas selalu tersedia di Instalasi Farmasi.',
            'action_red'   => 'Kepatuhan penggunaan formularium nasional belum ≥80%. Tindak lanjut: (1) Sosialisasi ulang daftar terbaru Fornas kepada seluruh DPJP dalam forum Komite Farmasi dan Terapi, (2) Identifikasi obat non-Fornas yang sering diresepkan dan cari alternatif substitusi, (3) Tingkatkan koordinasi dengan Komite Farmasi dan Terapi untuk pengkajian resep, (4) Pastikan obat-obatan Fornas tercukupi stoknya — koordinasikan dengan bagian pengadaan.',
        ],
        [
            'keywords'     => ['clinical pathway', 'clinical path'],
            'source'       => 'INM 10 — Permenkes No. 30 Tahun 2022',
            'action_green' => 'Kepatuhan terhadap clinical pathway ≥80% tercapai. Pertahankan monitoring audit kepatuhan CP, catat deviasi yang terjadi, dan lakukan review CP secara periodik oleh Komite Medis untuk memastikan relevansinya.',
            'action_red'   => 'Kepatuhan clinical pathway belum ≥80%. Tindak lanjut: (1) Identifikasi diagnosa dengan tingkat kepatuhan CP terendah dan lakukan RCA, (2) Sosialisasikan CP yang berlaku kepada DPJP dan seluruh PPA (Profesional Pemberi Asuhan), (3) Supervisi Komite Medis terhadap pengisian CP secara rutin, (4) Review dan perbarui CP yang sudah tidak relevan dengan praktik klinis terkini.',
        ],
        [
            'keywords'     => ['risiko jatuh', 'pencegahan jatuh', 'pasien jatuh'],
            'source'       => 'INM 11 — Permenkes No. 30 Tahun 2022',
            'action_green' => 'Upaya pencegahan risiko jatuh sudah 100%. Pertahankan asesmen risiko jatuh pada setiap pasien masuk dan implementasi tindakan pencegahan sesuai level risiko (rendah, sedang, tinggi) berdasarkan skor Morse/Humpty Dumpty.',
            'action_red'   => 'Pencegahan risiko jatuh belum 100%. Tindak lanjut: (1) Pastikan asesmen Morse Fall Scale (dewasa) / Humpty Dumpty (anak) dilakukan pada setiap pasien masuk dan diperbarui saat kondisi berubah, (2) Pasang gelang kuning dan tanda peringatan risiko jatuh pada pasien risiko sedang-tinggi, (3) Edukasi pasien dan keluarga tentang pencegahan jatuh, (4) Audit kelengkapan asesmen risiko jatuh oleh kepala ruangan setiap minggu.',
        ],
        [
            'keywords'     => ['kecepatan tanggap keluhan', 'tanggap keluhan', 'penanganan keluhan', 'komplain pasien', 'pengaduan pasien'],
            'source'       => 'INM 12 — Permenkes No. 30 Tahun 2022',
            'action_green' => 'Kecepatan tanggap keluhan ≥80% dalam ≤24 jam sudah tercapai. Pertahankan alur pengelolaan pengaduan, pastikan setiap keluhan tercatat dan ditindaklanjuti, serta dokumentasikan respons untuk pelaporan.',
            'action_red'   => 'Kecepatan tanggap keluhan belum ≥80% dalam ≤24 jam. Tindak lanjut: (1) Sosialisasikan SOP penanganan pengaduan kepada seluruh staf yang berhadapan langsung dengan pasien, (2) Pastikan semua keluhan tercatat melalui sistem yang tersedia (buku/form/SIMRS) dan direspons dalam 24 jam, (3) Tingkatkan koordinasi antara unit pelayanan dan tim Humas/Pengaduan, (4) Laporkan kasus yang tidak tertangani tepat waktu kepada pimpinan untuk tindak lanjut.',
        ],
        [
            'keywords'     => ['kepuasan pasien', 'survei kepuasan'],
            'source'       => 'INM 13 — Permenkes No. 30 Tahun 2022',
            'action_green' => 'Indeks kepuasan pasien ≥76,61% tercapai. Pertahankan kualitas pelayanan, lanjutkan survei kepuasan secara berkala, dan tindaklanjuti setiap temuan untuk perbaikan berkelanjutan.',
            'action_red'   => 'Indeks kepuasan pasien belum mencapai standar ≥76,61%. Analisis dimensi pelayanan yang rendah: (1) Keandalan (Reliability) — pastikan setiap janji pelayanan dipenuhi, (2) Daya tanggap (Responsiveness) — tingkatkan kecepatan respons staf terhadap kebutuhan pasien, (3) Jaminan (Assurance) — tingkatkan kompetensi dan keramahan staf, (4) Empati (Empathy) — kembangkan budaya pelayanan dengan hati, (5) Bukti fisik (Tangibles) — perbaiki kebersihan, kerapian, dan kenyamanan fasilitas. Susun program peningkatan berbasis hasil survei dan libatkan seluruh staf unit.',
        ],
    ];

    // -----------------------------------------------------------------------
    // SPM Generic Fallback — Pergub DKI No. 20 Tahun 2016
    // -----------------------------------------------------------------------
    private const SPM_SOURCE       = 'SPM RSUD — Pergub DKI Jakarta No. 20 Tahun 2016';
    private const SPM_ACTION_GREEN = 'Capaian memenuhi standar SPM yang ditetapkan. Pertahankan kinerja, dokumentasikan bukti capaian untuk pelaporan, dan lakukan evaluasi berkala untuk memastikan konsistensi.';
    private const SPM_ACTION_RED   = 'Capaian belum memenuhi standar SPM. Lakukan analisis akar masalah (RCA), susun Rencana Tindak Lanjut (RTL) dengan target waktu yang jelas, libatkan PIC terkait, dan laporkan progress kepada pimpinan unit setiap bulan.';

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Generate a recommendation for one indicator at one data point.
     *
     * @param  string      $indikatorText   Nama/deskripsi indikator
     * @param  string      $standar         Target (e.g. "≥85%", "≤5%", "100%")
     * @param  float|null  $n               Numerator value
     * @param  float|null  $d               Denominator value
     * @param  string      $satuan          'persen' | 'rata-rata' | 'permil'
     * @param  string|null $jenisIndikator  Classification (unused by matching, reserved for future)
     * @return array{status:string, color:string, achievement:float|null, gap:string|null, recommendation:string, source:string|null}
     */
    public function generate(
        string  $indikatorText,
        string  $standar,
        ?float  $n,
        ?float  $d,
        string  $satuan = 'persen',
        ?string $jenisIndikator = null
    ): array {
        // Guard: missing data
        if ($n === null || $d === null || $d == 0) {
            return $this->noDataResult($indikatorText);
        }

        $achievement = $this->computeAchievement($n, $d, $satuan);

        // Unit suffix untuk display
        $unit = match ($satuan) {
            'permil'    => '‰',
            'rata-rata' => '',
            default     => '%',
        };
        $achievementStr = number_format($achievement, 1, ',', '.') . $unit;
        $nStr           = number_format((int) $n, 0, ',', '.');
        $dStr           = number_format((int) $d, 0, ',', '.');

        // Deteksi indikator sentinel (standar = 0 / = 0)
        if ($this->isSentinel($standar)) {
            return $this->sentinelResult($indikatorText, $achievement, $achievementStr, $nStr, $dStr);
        }

        ['op' => $op, 'value' => $targetValue] = $this->parseStandar($standar);

        if ($op === null || $targetValue === null) {
            return $this->genericResult($indikatorText, $achievement, $achievementStr, $nStr, $dStr, $standar);
        }

        $achieved = $this->evaluate($achievement, $op, $targetValue);
        $gap      = $achieved ? null : $this->buildGap($achievement, $op, $targetValue, $satuan, $standar);
        $gapStr   = $gap ? " ({$gap})" : '';
        $rule     = $this->matchRule($indikatorText);
        $source   = $rule !== null ? $rule['source'] : self::SPM_SOURCE;

        // Kalimat pembuka resume hasil pengisian
        if ($achieved) {
            $intro = "Indikator \"{$indikatorText}\" mencapai {$achievementStr} (N={$nStr}, D={$dStr}), "
                   . "memenuhi standar {$standar} sesuai {$source}. ";
        } else {
            $intro = "Indikator \"{$indikatorText}\" mencapai {$achievementStr} (N={$nStr}, D={$dStr}){$gapStr}, "
                   . "belum memenuhi standar {$standar} sesuai {$source}. ";
        }

        $actionText   = $rule !== null
            ? ($achieved ? $rule['action_green'] : $rule['action_red'])
            : ($achieved ? self::SPM_ACTION_GREEN : self::SPM_ACTION_RED);

        return [
            'status'         => $achieved ? 'Tercapai' : 'Tidak Tercapai',
            'color'          => $achieved ? 'green' : 'red',
            'achievement'    => round($achievement, 2),
            'gap'            => $gap,
            'recommendation' => $intro . $actionText,
            'source'         => $source,
        ];
    }

    /**
     * Cek apakah indikator merupakan sentinel event (standar = 0).
     */
    private function isSentinel(string $standar): bool
    {
        $s = trim(str_replace(['≥', '≤'], ['>=', '<='], $standar));
        return $s === '0' || $s === '= 0' || $s === '=0';
    }

    /**
     * Rekomendasi khusus untuk indikator sentinel event.
     */
    private function sentinelResult(
        string $indikatorText,
        float  $achievement,
        string $achievementStr,
        string $nStr,
        string $dStr
    ): array {
        if ($achievement == 0) {
            $status = 'Tercapai';
            $color  = 'green';
            $rec    = "Indikator \"{$indikatorText}\" mencatat 0 kejadian (N={$nStr}, D={$dStr}), "
                    . "telah memenuhi standar yang ditetapkan. "
                    . "Pertahankan kondisi ini dengan menjaga kepatuhan terhadap prosedur dan protokol yang berlaku. "
                    . "Lakukan evaluasi rutin dan edukasi berkala kepada staf untuk mencegah terjadinya kejadian serupa.";
        } elseif ($achievement <= 10) {
            $status = 'Risiko Rendah';
            $color  = 'yellow';
            $rec    = "Indikator \"{$indikatorText}\" mencapai {$achievementStr} (N={$nStr}, D={$dStr}), "
                    . "tergolong kategori risiko rendah (0,01–10%). "
                    . "Meskipun masih dalam kategori rendah, setiap kejadian tetap harus diinvestigasi. "
                    . "Lakukan analisis akar masalah (root cause analysis), dokumentasikan temuan, dan susun langkah pencegahan agar tidak terjadi pengulangan. "
                    . "Laporkan hasil investigasi kepada pimpinan unit secara berkala sesuai Permenkes No. 30 Tahun 2022.";
        } elseif ($achievement <= 50) {
            $status = 'Risiko Sedang';
            $color  = 'orange';
            $rec    = "Indikator \"{$indikatorText}\" mencapai {$achievementStr} (N={$nStr}, D={$dStr}), "
                    . "tergolong kategori risiko sedang (10–50%). "
                    . "Segera lakukan evaluasi mendalam terhadap prosedur pelayanan dan identifikasi faktor penyebab. "
                    . "Susun rencana tindak lanjut terstruktur, tingkatkan supervisi, dan lakukan pelatihan staf yang relevan. "
                    . "Pantau perkembangan secara intensif dan eskalasikan kepada pimpinan sesuai ketentuan Pergub DKI Jakarta No. 20 Tahun 2016.";
        } else {
            $status = 'Risiko Tinggi';
            $color  = 'red';
            $rec    = "Indikator \"{$indikatorText}\" mencapai {$achievementStr} (N={$nStr}, D={$dStr}), "
                    . "tergolong kategori risiko tinggi (>50%). "
                    . "Kondisi ini memerlukan penanganan segera dan komprehensif. "
                    . "Lakukan investigasi menyeluruh, perbaikan sistem, dan terapkan langkah korektif secepat mungkin. "
                    . "Eskalasikan kepada pimpinan unit sebagai prioritas utama dan laporkan rencana perbaikan secara tertulis sesuai Permenkes No. 30 Tahun 2022 dan Pergub DKI Jakarta No. 20 Tahun 2016.";
        }

        return [
            'status'         => $status,
            'color'          => $color,
            'achievement'    => round($achievement, 2),
            'gap'            => null,
            'recommendation' => $rec,
            'source'         => self::SPM_SOURCE,
        ];
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function computeAchievement(float $n, float $d, string $satuan): float
    {
        return match ($satuan) {
            'rata-rata' => $n / $d,
            'permil'    => ($n / $d) * 1000,
            default     => ($n / $d) * 100,
        };
    }

    /**
     * Parse standar string into operator + numeric value.
     * Handles: "≥85%", "≤5%", "100%", "≥80% (≤30 menit)", "= 100"
     */
    private function parseStandar(string $standar): array
    {
        // Normalise Unicode comparison chars
        $s = str_replace(['≥', '≤'], ['>=', '<='], $standar);
        // Match first operator+number (ignores parenthetical like "(≤30 menit)")
        if (preg_match('/^(>=|<=|=|>|<)?\s*([\d]+(?:[.,]\d+)?)/', trim($s), $m)) {
            $op    = ($m[1] !== '') ? $m[1] : '>=';
            $value = (float) str_replace(',', '.', $m[2]);
            return ['op' => $op, 'value' => $value];
        }
        return ['op' => null, 'value' => null];
    }

    private function evaluate(float $achievement, string $op, float $target): bool
    {
        return match ($op) {
            '>='    => $achievement >= $target,
            '<='    => $achievement <= $target,
            '>'     => $achievement >  $target,
            '<'     => $achievement <  $target,
            default => $achievement >= $target,
        };
    }

    private function matchRule(string $indikatorText): ?array
    {
        $lower = mb_strtolower($indikatorText);
        foreach (self::INM_MAP as $rule) {
            foreach ($rule['keywords'] as $kw) {
                if (str_contains($lower, $kw)) {
                    return $rule;
                }
            }
        }
        return null;
    }

    private function buildGap(
        float  $achievement,
        string $op,
        float  $target,
        string $satuan,
        string $standar
    ): string {
        $unit = match ($satuan) {
            'permil'    => '‰',
            'rata-rata' => '',
            default     => '%',
        };
        $diff    = abs($achievement - $target);
        $rounded = number_format($diff, 1, ',', '.');

        if ($op === '<=') {
            return "Melebihi target {$standar} sebesar {$rounded}{$unit}";
        }
        return "Kurang {$rounded}{$unit} dari target {$standar}";
    }

    private function noDataResult(string $indikatorText = ''): array
    {
        $label = $indikatorText ? "Indikator \"{$indikatorText}\"" : 'Indikator ini';
        return [
            'status'         => 'Data Belum Lengkap',
            'color'          => 'gray',
            'achievement'    => null,
            'gap'            => null,
            'recommendation' => "{$label} belum memiliki data numerator (N) dan/atau denominator (D). Lengkapi data terlebih dahulu untuk mendapatkan rekomendasi perbaikan.",
            'source'         => null,
        ];
    }

    private function genericResult(
        string $indikatorText,
        float  $achievement,
        string $achievementStr,
        string $nStr,
        string $dStr,
        string $standar
    ): array {
        return [
            'status'         => 'Data Tersedia',
            'color'          => 'gray',
            'achievement'    => round($achievement, 2),
            'gap'            => null,
            'recommendation' => "Indikator \"{$indikatorText}\" mencapai {$achievementStr} (N={$nStr}, D={$dStr}). "
                              . "Standar yang ditetapkan: {$standar} sesuai " . self::SPM_SOURCE . ". "
                              . "Evaluasi capaian sesuai kebijakan SPM dan ketentuan unit.",
            'source'         => self::SPM_SOURCE,
        ];
    }
}
