<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RekomendasiAIController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'indikator'       => 'required|string',
            'jenis'           => 'nullable|string',
            'standar'         => 'nullable|string',
            'satuan'          => 'nullable|string',
            'n'               => 'nullable|numeric',
            'd'               => 'nullable|numeric',
            'bulan'           => 'nullable|string',
            'unit'            => 'nullable|string',
        ]);

        $apiKey = config('services.groq.api_key');
        if (!$apiKey) {
            return response()->json(['error' => 'GROQ_API_KEY belum dikonfigurasi di .env'], 500);
        }

        // Hitung capaian
        $n = $request->n;
        $d = $request->d;
        $capaian = null;
        $capaianStr = 'Data belum tersedia';

        if ($n !== null && $d !== null && $d > 0) {
            $satuan = $request->satuan ?? 'persen';
            $capaian = match ($satuan) {
                'permil'    => round(($n / $d) * 1000, 2),
                'rata-rata' => round($n / $d, 2),
                default     => round(($n / $d) * 100, 2),
            };
            $unit = match ($satuan) {
                'permil'    => '‰',
                'rata-rata' => '',
                default     => '%',
            };
            $capaianStr = "{$capaian}{$unit} (N={$n}, D={$d})";
        }

        $indikator  = $request->indikator;
        $jenis      = $request->jenis ?? '-';
        $standar    = $request->standar ?? '-';
        $bulan      = $request->bulan ?? '-';
        $unit       = $request->unit ?? '-';

        $prompt = <<<PROMPT
Kamu adalah konsultan mutu rumah sakit berpengalaman yang ahli dalam standar akreditasi JCI, SNARS, Permenkes No. 30 Tahun 2022 tentang INM, dan Standar Pelayanan Minimal (SPM) RSUD.

Berikan analisis dan rencana tindak lanjut (RTL) berdasarkan data capaian indikator mutu berikut:

- Nama Indikator : {$indikator}
- Jenis Indikator : {$jenis}
- Standar/Target  : {$standar}
- Capaian Bulan   : {$bulan}
- Nilai Capaian   : {$capaianStr}
- Unit/Instalasi  : {$unit}

Tulis respons dalam format JSON berikut (tanpa markdown, hanya JSON murni):
{
  "analisis": "<paragraf analisis singkat 2-4 kalimat: jelaskan kondisi capaian, apakah tercapai/tidak, faktor yang mungkin mempengaruhi>",
  "rtl": "<rencana tindak lanjut berupa 3-5 langkah konkret yang spesifik, actionable, dan relevan dengan indikator ini>"
}

Gunakan bahasa Indonesia yang formal dan profesional. Jangan tambahkan penjelasan di luar JSON.
PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->asJson()->timeout(30)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'      => 'llama-3.3-70b-versatile',
                'max_tokens' => 1024,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if ($response->failed()) {
                $errBody = $response->json('error.message') ?? $response->body();
                return response()->json([
                    'error' => 'Gagal menghubungi layanan AI (' . $response->status() . '): ' . $errBody,
                ], 500);
            }

            $content = $response->json('choices.0.message.content', '');

            // Parse JSON dari response AI
            $parsed = json_decode($content, true);
            if (!$parsed || !isset($parsed['analisis'])) {
                // Coba ekstrak JSON dari dalam teks jika ada
                if (preg_match('/\{[\s\S]*\}/u', $content, $matches)) {
                    $parsed = json_decode($matches[0], true);
                }
            }

            if ($parsed && isset($parsed['analisis'])) {
                return response()->json([
                    'analisis' => trim($parsed['analisis']),
                    'rtl'      => trim($parsed['rtl'] ?? ''),
                ]);
            }

            return response()->json(['error' => 'Format respons AI tidak valid.'], 500);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
}
