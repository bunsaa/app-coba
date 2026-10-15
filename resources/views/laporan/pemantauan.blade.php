<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9pt; color: #1a1a1a; }

  .page { padding: 20mm 15mm 20mm 20mm; }

  /* HEADER */
  .header { display: table; width: 100%; margin-bottom: 6mm; }
  .header-logo { display: table-cell; width: 18mm; vertical-align: middle; text-align: center; }
  .header-logo img { width: 14mm; height: 14mm; }
  .header-text { display: table-cell; vertical-align: middle; padding-left: 4mm; }
  .header-text .hospital { font-size: 10pt; font-weight: bold; text-transform: uppercase; color: #1e3a5f; }
  .header-text .address { font-size: 7.5pt; color: #555; margin-top: 1mm; }

  .divider-thick { border-top: 2.5pt solid #1e3a5f; margin-bottom: 1mm; }
  .divider-thin  { border-top: 0.8pt solid #1e3a5f; margin-bottom: 4mm; }

  /* TITLE */
  .title-block { text-align: center; margin-bottom: 4mm; }
  .title-block h1 { font-size: 12pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5pt; }
  .title-block h2 { font-size: 10pt; font-weight: bold; margin-top: 1mm; }

  /* META INFO */
  .meta-table { width: 100%; margin-bottom: 5mm; }
  .meta-table td { font-size: 8.5pt; padding: 0.8mm 0; vertical-align: top; }
  .meta-table td:first-child { width: 38mm; font-weight: bold; }
  .meta-table td:nth-child(2) { width: 4mm; }

  /* MAIN TABLE */
  .data-table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
  .data-table th {
    background: #1e3a5f; color: #fff;
    border: 0.5pt solid #1e3a5f;
    padding: 2mm 2.5mm; font-size: 8pt; text-align: center;
    font-weight: bold;
  }
  .data-table td {
    border: 0.5pt solid #aaa; padding: 2mm 2.5mm;
    font-size: 8pt; vertical-align: top;
  }
  .data-table tr:nth-child(even) td { background: #f5f8ff; }
  .data-table .num    { text-align: center; width: 6mm; }
  .data-table .center { text-align: center; }
  .data-table .nama   { width: 45mm; }
  .data-table .capaian{ width: 16mm; text-align: center; font-weight: bold; }
  .data-table .capaian.tercapai  { color: #166534; }
  .data-table .capaian.tidak     { color: #991b1b; }
  .data-table .keterangan        { width: 32mm; }

  /* FOOTER */
  .footer-sign { margin-top: 6mm; display: table; width: 100%; }
  .sign-block  { display: table-cell; width: 50%; text-align: center; font-size: 8pt; vertical-align: top; }
  .sign-block .sign-title  { font-weight: bold; }
  .sign-block .sign-space  { height: 14mm; }
  .sign-block .sign-name   { border-top: 0.5pt solid #333; display: inline-block; min-width: 45mm; padding-top: 1mm; font-weight: bold; }
  .sign-block .sign-nip    { font-size: 7pt; color: #555; }

  .legend { margin-bottom: 2mm; font-size: 7.5pt; color: #555; }
</style>
</head>
<body>
<div class="page">

  {{-- HEADER --}}
  <div class="header">
    <div class="header-text">
      <div class="hospital">Rumah Sakit Umum Daerah Tarakan</div>
      <div class="address">Jl. Pulau Irian No. 1, Tarakan, Kalimantan Utara</div>
    </div>
  </div>
  <div class="divider-thick"></div>
  <div class="divider-thin"></div>

  {{-- TITLE --}}
  <div class="title-block">
    <h1>Laporan Pemantauan Indikator Mutu</h1>
    <h2>Periode {{ $periodeLabel }}</h2>
  </div>

  {{-- META --}}
  <table class="meta-table">
    <tr>
      <td>Unit</td><td>:</td>
      <td>{{ $namaUnit }}</td>
    </tr>
    @if($namaTimUnit)
    <tr>
      <td>Tim</td><td>:</td>
      <td>{{ $namaTimUnit }}</td>
    </tr>
    @endif
    <tr>
      <td>Tanggal Cetak</td><td>:</td>
      <td>{{ $tanggalCetak }}</td>
    </tr>
    <tr>
      <td>Dicetak oleh</td><td>:</td>
      <td>{{ $dicetakOleh }}</td>
    </tr>
  </table>

  {{-- DATA TABLE --}}
  <table class="data-table">
    <thead>
      <tr>
        <th class="num">No</th>
        <th class="nama">Nama Indikator</th>
        <th>Numerator (N)</th>
        <th>Denominator (D)</th>
        <th class="capaian">Capaian</th>
        <th>Standar</th>
        <th class="keterangan">Analisa</th>
        <th class="keterangan">RTL</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $i => $row)
      <tr>
        <td class="num">{{ $i + 1 }}</td>
        <td class="nama">{{ $row['indikator'] }}</td>
        <td class="center">{{ $row['n'] ?? '-' }}</td>
        <td class="center">{{ $row['d'] ?? '-' }}</td>
        <td class="capaian {{ $row['tercapai'] ? 'tercapai' : 'tidak' }}">
          {{ $row['capaian'] }}
        </td>
        <td class="center">{{ $row['standar'] ?? '-' }}</td>
        <td>{{ $row['analisa'] ?: '-' }}</td>
        <td>{{ $row['rtl'] ?: '-' }}</td>
      </tr>
      @empty
      <tr>
        <td colspan="8" class="center" style="color:#888; font-style:italic; padding:4mm;">
          Belum ada data capaian untuk periode ini.
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>

  <p class="legend">
    * Capaian = N/D × 100% &nbsp;|&nbsp; <span style="color:#166534">■</span> Tercapai (≥ standar) &nbsp;|&nbsp; <span style="color:#991b1b">■</span> Belum tercapai
  </p>

  {{-- SIGNATURE --}}
  <div class="footer-sign">
    <div class="sign-block">
      <div class="sign-title">Kepala Unit</div>
      <div class="sign-space"></div>
      <div>
        <span class="sign-name">{{ $kepalaUnit ?? '________________' }}</span>
      </div>
    </div>
    <div class="sign-block">
      <div class="sign-title">Admin Mutu</div>
      <div class="sign-space"></div>
      <div>
        <span class="sign-name">&nbsp;</span>
      </div>
    </div>
  </div>

</div>
</body>
</html>
