<?php
/**
 * Standalone script: Approve + fill analisis/RTL DATIN Juli & Agustus 2026
 * Run: php scripts/approve_datin_run.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Indikator;
use App\Models\CapaianIndikator;
use Illuminate\Support\Facades\Http;

$apiKey = config('services.groq.api_key');
if (!$apiKey) {
    die("ERROR: GROQ_API_KEY belum dikonfigurasi\n");
}

$bulanList = ['jul' => 'Juli', 'aug' => 'Agustus'];
$satuan_unit = ['persen' => '%', 'permil' => '‰', 'rata-rata' => ''];

$indikators = Indikator::whereIn('id', [35, 36, 37])->get()->keyBy('id');
$capaianList = CapaianIndikator::where('kode_unit', 'Datin')->where('tahun', 2026)->get();

foreach ($capaianList as $capaian) {
    $ind = $indikators[$capaian->indikator_id] ?? null;
    if (!$ind) { echo "SKIP: indikator_id {$capaian->indikator_id} tidak ditemukan\n"; continue; }

    foreach ($bulanList as $bulanKey => $bulanNama) {
        $n = $capaian->{$bulanKey . '_n'};
        $d = $capaian->{$bulanKey . '_d'};

        if ($n === null || $d === null || $d == 0) {
            echo "SKIP [{$ind->id}] {$bulanNama}: data kosong\n";
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

        echo "Generating AI for: [{$ind->id}] {$ind->indikator} | {$bulanNama} | Capaian: {$capaianStr} | Standar: {$ind->standar}{$unitStr}\n";

        $prompt = "Kamu adalah konsultan mutu rumah sakit berpengalaman yang ahli dalam standar akreditasi JCI, SNARS, Permenkes No. 30 Tahun 2022 tentang INM, dan Standar Pelayanan Minimal (SPM) RSUD.\n\nBerikan analisis dan rencana tindak lanjut (RTL) berdasarkan data capaian indikator mutu berikut:\n\n- Nama Indikator : {$ind->indikator}\n- Jenis Indikator : {$ind->jenis_indikator}\n- Standar/Target  : {$ind->standar}{$unitStr}\n- Capaian Bulan   : {$bulanNama} 2026\n- Nilai Capaian   : {$capaianStr}\n- Unit/Instalasi  : Bagian Data dan Teknologi Informasi (DATIN)\n\nTulis respons dalam format JSON berikut (tanpa markdown, hanya JSON murni):\n{\n  \"analisis\": \"<paragraf analisis singkat 2-4 kalimat>\",\n  \"rtl\": \"<rencana tindak lanjut berupa 3-5 langkah konkret>\"\n}\n\nGunakan bahasa Indonesia yang formal dan profesional. Jangan tambahkan penjelasan di luar JSON.";

        try {
            $response = Http::withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                ->asJson()->timeout(30)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model'      => 'openai/gpt-oss-120b',
                    'max_tokens' => 1024,
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                ]);

            if ($response->failed()) {
                echo "  ERROR API {$response->status()}: " . $response->body() . "\n";
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
                echo "  ERROR: Format AI tidak valid. Content: " . substr($content, 0, 300) . "\n";
                continue;
            }

            $analisis = trim($parsed['analisis']);
            $rtl      = trim($parsed['rtl'] ?? '');

            // History
            $oldAnalisis = $capaian->{$bulanKey . '_analisis'};
            $oldRtl      = $capaian->{$bulanKey . '_rtl'};
            if (!empty($oldAnalisis) || !empty($oldRtl)) {
                $history = $capaian->analisis_rtl_history ?? [];
                $history[$bulanKey] = $history[$bulanKey] ?? [];
                array_unshift($history[$bulanKey], [
                    'analisis' => $oldAnalisis ?? '', 'rtl' => $oldRtl ?? '',
                    'changed_at' => now()->format('Y-m-d H:i:s'),
                    'changed_by' => 'System (AI Auto-fill)', 'changed_by_role' => 'admin_mutu',
                ]);
                $capaian->analisis_rtl_history = $history;
            }

            $capaian->{$bulanKey . '_analisis'} = $analisis;
            $capaian->{$bulanKey . '_rtl'}      = $rtl;
            $capaian->{$bulanKey . '_approved'}  = true;
            $capaian->{$bulanKey . '_validated'} = true;
            $capaian->updated_by = 'Admin Mutu (System)';
            $capaian->save();

            echo "  OK - approved + validated + analisis/RTL tersimpan\n";
            echo "  ANALISIS: " . substr($analisis, 0, 120) . "...\n\n";

        } catch (Exception $e) {
            echo "  EXCEPTION: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== Selesai! ===\n";
