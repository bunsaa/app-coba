<?php
// Script: Approve + fill analisis/RTL untuk DATIN Juli & Agustus 2026
// Jalankan via: php artisan tinker < scripts/approve_datin.php

$apiKey = config('services.groq.api_key');
$bulanList = ['jul' => 'Juli', 'aug' => 'Agustus'];

$indikators = App\Models\Indikator::whereIn('id', [35, 36, 37])->get()->keyBy('id');
$capaianList = App\Models\CapaianIndikator::where('kode_unit', 'Datin')->where('tahun', 2026)->get();

$satuan_unit = ['persen' => '%', 'permil' => '‰', 'rata-rata' => ''];

foreach ($capaianList as $capaian) {
    $ind = $indikators[$capaian->indikator_id] ?? null;
    if (!$ind) continue;

    foreach ($bulanList as $bulanKey => $bulanNama) {
        $n = $capaian->{$bulanKey . '_n'};
        $d = $capaian->{$bulanKey . '_d'};

        if ($n === null || $d === null || $d == 0) {
            echo "SKIP {$ind->indikator} - {$bulanNama}: data kosong\n";
            continue;
        }

        $satuan = $ind->satuan ?? 'persen';
        $capaianVal = match($satuan) {
            'permil'    => round(($n / $d) * 1000, 2),
            'rata-rata' => round($n / $d, 2),
            default     => round(($n / $d) * 100, 2),
        };
        $unitStr = $satuan_unit[$satuan] ?? '%';
        $capaianStr = "{$capaianVal}{$unitStr} (N={$n}, D={$d})";

        $prompt = <<<PROMPT
Kamu adalah konsultan mutu rumah sakit berpengalaman yang ahli dalam standar akreditasi JCI, SNARS, Permenkes No. 30 Tahun 2022 tentang INM, dan Standar Pelayanan Minimal (SPM) RSUD.

Berikan analisis dan rencana tindak lanjut (RTL) berdasarkan data capaian indikator mutu berikut:

- Nama Indikator : {$ind->indikator}
- Jenis Indikator : {$ind->jenis_indikator}
- Standar/Target  : {$ind->standar}{$unitStr}
- Capaian Bulan   : {$bulanNama} 2026
- Nilai Capaian   : {$capaianStr}
- Unit/Instalasi  : Bagian Data dan Teknologi Informasi (DATIN)

Tulis respons dalam format JSON berikut (tanpa markdown, hanya JSON murni):
{
  "analisis": "<paragraf analisis singkat 2-4 kalimat: jelaskan kondisi capaian, apakah tercapai/tidak, faktor yang mungkin mempengaruhi>",
  "rtl": "<rencana tindak lanjut berupa 3-5 langkah konkret yang spesifik, actionable, dan relevan dengan indikator ini>"
}

Gunakan bahasa Indonesia yang formal dan profesional. Jangan tambahkan penjelasan di luar JSON.
PROMPT;

        echo "Generating AI for: [{$ind->id}] {$ind->indikator} - {$bulanNama}...\n";

        try {
            $response = Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->asJson()->timeout(30)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'      => 'openai/gpt-oss-120b',
                'max_tokens' => 1024,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if ($response->failed()) {
                echo "  ERROR API: " . $response->status() . " " . $response->body() . "\n";
                continue;
            }

            $content = $response->json('choices.0.message.content', '');
            $parsed = json_decode($content, true);
            if (!$parsed || !isset($parsed['analisis'])) {
                if (preg_match('/\{[\s\S]*\}/u', $content, $matches)) {
                    $parsed = json_decode($matches[0], true);
                }
            }

            if (!$parsed || !isset($parsed['analisis'])) {
                echo "  ERROR: Format AI tidak valid. Content: " . substr($content, 0, 200) . "\n";
                continue;
            }

            $analisis = trim($parsed['analisis']);
            $rtl      = trim($parsed['rtl'] ?? '');

            // Simpan ke history dulu jika ada isi lama
            $oldAnalisis = $capaian->{$bulanKey . '_analisis'};
            $oldRtl      = $capaian->{$bulanKey . '_rtl'};
            if (!empty($oldAnalisis) || !empty($oldRtl)) {
                $history = $capaian->analisis_rtl_history ?? [];
                $history[$bulanKey] = $history[$bulanKey] ?? [];
                array_unshift($history[$bulanKey], [
                    'analisis'        => $oldAnalisis ?? '',
                    'rtl'             => $oldRtl ?? '',
                    'changed_at'      => now()->format('Y-m-d H:i:s'),
                    'changed_by'      => 'System (AI Auto-fill)',
                    'changed_by_role' => 'admin_mutu',
                ]);
                $capaian->analisis_rtl_history = $history;
            }

            // Set analisis & RTL
            $capaian->{$bulanKey . '_analisis'} = $analisis;
            $capaian->{$bulanKey . '_rtl'}      = $rtl;

            // Approve (kepala unit level) + Validate (admin mutu level)
            $capaian->{$bulanKey . '_approved'}  = true;
            $capaian->{$bulanKey . '_validated'} = true;
            $capaian->updated_by = 'Admin Mutu (System)';
            $capaian->save();

            echo "  OK - Analisis & RTL tersimpan, approved=true, validated=true\n";

        } catch (\Exception $e) {
            echo "  EXCEPTION: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nSelesai!\n";
