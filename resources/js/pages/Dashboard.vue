<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Title,
    Tooltip,
    type TooltipItem,
} from 'chart.js';
import {
    AlertTriangle,
    Award,
    CheckCircle,
    CheckSquare2,
    ClipboardList,
    Clock,
    Download,
    FileSpreadsheet,
    ImageIcon,
    InfoIcon,
    LoaderCircleIcon,
    LoaderIcon,
    Maximize2,
    MessageSquare,
    RotateCcwIcon,
    Stamp,
    StarIcon,
    TrendingDown,
    TrendingUp,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Bar } from 'vue-chartjs';
import * as XLSX from 'xlsx';

// Define chart component type with chart property
interface ChartComponentInstance {
    chart: {
        canvas: HTMLCanvasElement;
    };
}
type ChartComponentRef = ChartComponentInstance | null;

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend,
);

interface JenisGroup {
    jenis: string;
    units: string[];
}

interface CapaianBulanan {
    persentase: number;
    indikatorTerlaporkan: number;
    totalIndikator: number;
    timUnitBelumMelaporkan: number;
    daftarUnitBelumMelaporkan: string[];
    daftarBelumMengisi: string[];
    daftarBelumApprove: string[];
    daftarBelumMengisiPerJenis: JenisGroup[];
    daftarBelumApprovePerJenis: JenisGroup[];
    belumAdaIndikator?: boolean;
    bulan: string;
}

interface DetailJenis {
    jenis: string;
    persentase: number;
}

interface DetailBulan {
    bulan: string;
    persentase: number;
    detailJenis?: DetailJenis[];
}

interface CapaianTriwulan {
    triwulan: number;
    persentase: number;
    totalIndikator: number;
    detailPerBulan: DetailBulan[];
    status: string;
}

interface CapaianTahunan {
    persentase: number;
    totalIndikator: number;
    totalValidasi: number;
    jumlahBulan: number;
    status: string;
    twSebelumnya: {
        triwulan: number;
        persentase: number;
    } | null;
}

interface Aktivitas {
    type: string;
    icon: string;
    color: string;
    text_color: string;
    message: string;
    timestamp: string;
    time_display: string;
}

interface GrafikBulanan {
    labels: string[];
    data: number[];
    bulan: string;
    tahun: number;
}

interface DatasetTriwulanan {
    label: string;
    data: number[];
}

interface GrafikTriwulanan {
    labels: string[];
    datasets: DatasetTriwulanan[];
    triwulan: number;
    tahun: number;
}

interface DataCapaianTahunan {
    labels: string[];
    data: Record<string, number>[];
    bulanHeaders: string[];
    tahun: number;
}

interface DataCapaianTriwulanDetailItem {
    jenis_indikator: string;
    tim: string;
    indikator: string;
    target: number;
    [key: string]: string | number;
}

interface PerUnitTriwulanItem {
    indikator: string;
    jenis_indikator: string;
    units: ({ unit: string } & { [key: string]: string | number })[];
}

interface DataCapaianTriwulanDetail {
    data: DataCapaianTriwulanDetailItem[];
    perUnitDetail: PerUnitTriwulanItem[];
    bulanHeaders: string[];
    bulanKeys: string[];
    triwulan: number;
    tahun: number;
}

interface DataCapaianTahunanDetailItem {
    jenis_indikator: string;
    tim: string;
    indikator: string;
    target: number;
    jan: number;
    feb: number;
    mar: number;
    apr: number;
    may: number;
    jun: number;
    jul: number;
    aug: number;
    sep: number;
    oct: number;
    nov: number;
    des: number;
}

interface DataCapaianTahunanDetail {
    data: DataCapaianTahunanDetailItem[];
    bulanHeaders: string[];
    tahun: number;
}

interface RankingItem {
    unit: string;
    kode_unit: string;
    total_approved: number;
    total_komentar: number;
    pct: number;
    belum_terisi?: number;
    bulan_belum_terisi?: string[];
    jumlah_bulan_belum?: number;
    waktu_input?: string | null;
    waktu_input_ts?: string | null;
}

interface DiAtasStandarItem {
    unit: string;
    kode_unit: string;
    above_standar: number;
    total_validated: number;
}

interface RankingData {
    topTercepat: RankingItem[];
    topKomentar: RankingItem[];
    bottomTerlambat: RankingItem[];
    topDiAtasStandar: DiAtasStandarItem[];
}

interface DataCapaianBulananDetailItem {
    jenis_indikator: string;
    tim: string;
    indikator: string;
    target: number;
    capaian: number;
}

interface PerUnitBulananItem {
    indikator: string;
    jenis_indikator: string;
    units: { unit: string; capaian: number }[];
}

interface DataCapaianBulananDetail {
    data: DataCapaianBulananDetailItem[];
    perUnitDetail: PerUnitBulananItem[];
    bulan: string;
    tahun: number;
}

interface Props {
    totalIndikator: number;
    indikatorBaru: number;
    perubahanIndikator: number;
    totalIndikatorKemarin: number;
    daftarUnitIndikatorBaru: string[];
    capaianBulanan: CapaianBulanan;
    capaianTriwulan: CapaianTriwulan;
    capaianTahunan: CapaianTahunan;
    aktivitasTerbaru: Aktivitas[];
    grafikBulanan: GrafikBulanan;
    dataCapaianBulananDetail: DataCapaianBulananDetail;
    semuaGrafikTriwulanan: Record<number, GrafikTriwulanan>;
    semuaDataCapaianTriwulanDetail: Record<number, DataCapaianTriwulanDetail>;
    triwulanSekarang: number;
    dataCapaianTahunanSebelumnya: DataCapaianTahunan;
    dataCapaianTahunanDetail: DataCapaianTahunanDetail;
    rankingData: RankingData;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const showTooltip = ref(false);
const showTooltipTW = ref(false);
const showTooltipTahunan = ref(false);
let _t1: ReturnType<typeof setTimeout> | null = null;
let _t2: ReturnType<typeof setTimeout> | null = null;
let _t3: ReturnType<typeof setTimeout> | null = null;
function enterTooltip()  { if (_t1) clearTimeout(_t1); showTooltip.value = true; }
function leaveTooltip()  { _t1 = setTimeout(() => { showTooltip.value = false; }, 300); }
function enterTooltipTW()  { if (_t2) clearTimeout(_t2); showTooltipTW.value = true; }
function leaveTooltipTW()  { _t2 = setTimeout(() => { showTooltipTW.value = false; }, 300); }
function enterTooltipTahunan()  { if (_t3) clearTimeout(_t3); showTooltipTahunan.value = true; }
function leaveTooltipTahunan()  { _t3 = setTimeout(() => { showTooltipTahunan.value = false; }, 300); }
const showDownloadMenuBulanan = ref(false);
const showDownloadMenuTriwulan = ref(false);
const showChartPopup = ref<null | 'bulanan' | 'triwulan'>(null);

// Selected triwulan for navigation
const selectedTriwulan = ref(props.triwulanSekarang);

// Get current triwulan data based on selection
const grafikTriwulanan = computed(() => props.semuaGrafikTriwulanan[selectedTriwulan.value]);

// Navigation functions
const prevTriwulan = () => {
    if (selectedTriwulan.value > 1) {
        selectedTriwulan.value--;
    }
};

const nextTriwulan = () => {
    if (selectedTriwulan.value < 4) {
        selectedTriwulan.value++;
    }
};

// Chart refs
const chartBulananRef = ref<ChartComponentRef>(null);
const chartTriwulanRef = ref<ChartComponentRef>(null);

const statusPerubahan = computed(() => {
    if (props.indikatorBaru > 0) {
        return {
            text: `${props.indikatorBaru} indikator baru ditambahkan`,
            color: 'text-green-600',
            icon: TrendingUp,
        };
    } else if (props.perubahanIndikator < 0) {
        return {
            text: `Pengurangan ${Math.abs(props.perubahanIndikator)} indikator`,
            color: 'text-red-600',
            icon: TrendingDown,
        };
    } else {
        return {
            text: 'Belum ada tambahan indikator hari ini',
            color: 'text-gray-500',
            icon: InfoIcon,
        };
    }
});

const statusCapaianText = computed(() => {
    if (props.capaianBulanan.belumAdaIndikator) {
        return 'Belum ada indikator';
    }
    const belumIsi = props.capaianBulanan.daftarBelumMengisi?.length || 0;
    const belumApprove = props.capaianBulanan.daftarBelumApprove?.length || 0;
    const total = belumIsi + belumApprove;
    if (total === 0) {
        return 'Semua unit telah melaporkan';
    }
    const parts: string[] = [];
    if (belumIsi > 0) parts.push(`${belumIsi} belum isi`);
    if (belumApprove > 0) parts.push(`${belumApprove} belum approve`);
    return parts.join(', ');
});

// Chart configuration for Capaian Bulanan
const chartBulananData = computed(() => ({
    labels: props.grafikBulanan.labels,
    datasets: [
        {
            label: `Capaian ${props.grafikBulanan.bulan} ${props.grafikBulanan.tahun}`,
            backgroundColor: '#3b82f6',
            borderColor: '#2563eb',
            borderWidth: 1,
            borderRadius: 4,
            data: props.grafikBulanan.data,
        },
    ],
}));

const chartBulananOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'top' as const,
        },
        title: {
            display: false,
        },
        tooltip: {
            callbacks: {
                label: function (context: TooltipItem<'bar'>) {
                    return `Capaian: ${context.parsed.y ?? 0}%`;
                },
            },
        },
    },
    scales: {
        x: {
            grid: {
                display: false,
            },
            ticks: {
                maxRotation: 45,
                minRotation: 45,
            },
        },
        y: {
            beginAtZero: true,
            max: 100,
            ticks: {
                callback: function (value: number | string) {
                    return value + '%';
                },
            },
        },
    },
}));

// Chart configuration for Capaian Triwulanan
const chartTriwulanColors = ['#f59e0b', '#10b981', '#8b5cf6'];

const chartTriwulanData = computed(() => ({
    labels: grafikTriwulanan.value.labels,
    datasets: grafikTriwulanan.value.datasets.map((dataset: DatasetTriwulanan, index: number) => ({
        label: dataset.label,
        backgroundColor: chartTriwulanColors[index % chartTriwulanColors.length],
        borderColor: chartTriwulanColors[index % chartTriwulanColors.length],
        borderWidth: 1,
        borderRadius: 4,
        data: dataset.data,
    })),
}));

const chartTriwulanOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    layout: {
        padding: {
            left: 10,
            right: 10,
            bottom: 20,
        },
    },
    plugins: {
        legend: {
            display: true,
            position: 'top' as const,
        },
        title: {
            display: false,
        },
        tooltip: {
            callbacks: {
                label: function (context: TooltipItem<'bar'>) {
                    return `${context.dataset.label ?? ''}: ${context.parsed.y ?? 0}%`;
                },
            },
        },
    },
    scales: {
        x: {
            grid: {
                display: false,
            },
            ticks: {
                maxRotation: 90,
                minRotation: 45,
                font: {
                    size: 10,
                },
            },
        },
        y: {
            beginAtZero: true,
            max: 100,
            ticks: {
                callback: function (value: number | string) {
                    return value + '%';
                },
            },
        },
    },
}));

// Download chart as PNG image
const downloadChartAsImage = (chartRef: ChartComponentRef, filename: string) => {
    if (!chartRef?.chart) return;

    const canvas = chartRef.chart.canvas;
    const link = document.createElement('a');
    link.download = `${filename}.png`;
    link.href = canvas.toDataURL('image/png', 1.0);
    link.click();

    // Close dropdown menu
    showDownloadMenuBulanan.value = false;
    showDownloadMenuTriwulan.value = false;
};

// Download data as Excel with individual indicators
const jenisLabelMap: Record<string, string> = {
    INM: 'INM', SPM: 'SPM', PRIORITAS: 'PRIORITAS', IMUT_RS: 'IMUT RS', IMUT_UNIT: 'IMUT UNIT',
};
const jenisOrder = ['INM', 'SPM', 'PRIORITAS', 'IMUT_RS', 'IMUT_UNIT'];

function buildBulananSheet(items: DataCapaianBulananDetailItem[], bulan: string, tahun: number, title: string, perUnitDetail: PerUnitBulananItem[] = []) {
    const wsData: (string | number | null)[][] = [
        [title],
        [],
        ['NO', 'JENIS', 'UNIT/TIM', 'INDIKATOR', 'TARGET (%)', 'CAPAIAN (%)'],
    ];
    const merges = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 5 } }];
    items.forEach((item, idx) => {
        wsData.push([
            idx + 1,
            jenisLabelMap[item.jenis_indikator] ?? item.jenis_indikator,
            item.tim,
            item.indikator,
            item.target,
            item.capaian,
        ]);
    });

    // Per-unit detail section at the bottom
    if (perUnitDetail.length > 0) {
        const allUnits = [...new Set(perUnitDetail.flatMap(d => d.units.map(u => u.unit)))];
        const sectionStartRow = wsData.length;
        wsData.push([]);
        wsData.push([`DETAIL PER UNIT - ${bulan} ${tahun}`]);
        merges.push({ s: { r: sectionStartRow + 1, c: 0 }, e: { r: sectionStartRow + 1, c: 3 + allUnits.length } });
        wsData.push(['NO', 'JENIS', 'INDIKATOR', 'TARGET (%)', ...allUnits]);
        perUnitDetail.forEach((item, idx) => {
            const vals = allUnits.map(u => {
                const found = item.units.find(x => x.unit === u);
                return found !== undefined ? found.capaian : '-';
            });
            wsData.push([idx + 1, jenisLabelMap[item.jenis_indikator] ?? item.jenis_indikator, item.indikator, (item as any).target ?? '', ...vals]);
        });
    }

    const ws = XLSX.utils.aoa_to_sheet(wsData);
    ws['!cols'] = [{ wch: 5 }, { wch: 12 }, { wch: 35 }, { wch: 55 }, { wch: 12 }, { wch: 12 }];
    ws['!merges'] = merges;
    return ws;
}

const downloadBulananExcel = () => {
    const data = props.dataCapaianBulananDetail;
    const bulan = props.grafikBulanan.bulan;
    const tahun = props.grafikBulanan.tahun;
    const allItems = [...data.data].sort((a, b) =>
        (jenisOrder.indexOf(a.jenis_indikator) - jenisOrder.indexOf(b.jenis_indikator))
    );

    const wb = XLSX.utils.book_new();

    const perUnitDetail = data.perUnitDetail ?? [];

    // Sheet 1: Semua
    XLSX.utils.book_append_sheet(wb, buildBulananSheet(allItems, bulan, tahun,
        `CAPAIAN MUTU BULANAN - ${bulan} ${tahun}`, perUnitDetail), 'Semua Indikator');

    // Sheet 2-5 per jenis
    const jenisList = [
        { key: 'INM',       label: 'INM' },
        { key: 'SPM',       label: 'SPM' },
        { key: 'PRIORITAS', label: 'Prioritas' },
        { key: 'IMUT_UNIT', label: 'IMUT Unit' },
    ];
    jenisList.forEach(({ key, label }) => {
        const filtered = allItems.filter(i => i.jenis_indikator === key);
        const filteredPerUnit = perUnitDetail.filter(i => i.jenis_indikator === key);
        XLSX.utils.book_append_sheet(wb, buildBulananSheet(filtered, bulan, tahun,
            `CAPAIAN ${label.toUpperCase()} - ${bulan} ${tahun}`, filteredPerUnit), label);
    });

    XLSX.writeFile(wb, `Capaian_Bulanan_${bulan}_${tahun}.xlsx`);
    showDownloadMenuBulanan.value = false;
};

function buildTriwulanSheet(items: DataCapaianTriwulanDetailItem[], bulanHeaders: string[], bulanKeys: string[], title: string, perUnitDetail: PerUnitTriwulanItem[] = []) {
    const colCount = 4 + bulanKeys.length + 1; // NO+JENIS+TIM+IND+bulan...+RATA2
    const wsData: (string | number | null)[][] = [
        [title],
        [],
        ['NO', 'JENIS', 'UNIT/TIM', 'INDIKATOR', 'TARGET (%)', ...bulanHeaders.map(h => `${h.toUpperCase()} (%)`), 'RATA-RATA (%)'],
    ];
    const merges = [{ s: { r: 0, c: 0 }, e: { r: 0, c: colCount } }];
    items.forEach((item, idx) => {
        const vals = bulanKeys.map(b => (item[b] as number) ?? 0);
        const valid = vals.filter(v => v > 0);
        const avg = valid.length > 0 ? Math.round(valid.reduce((a, b) => a + b, 0) / valid.length * 100) / 100 : 0;
        wsData.push([
            idx + 1,
            jenisLabelMap[item.jenis_indikator] ?? item.jenis_indikator,
            item.tim,
            item.indikator,
            item.target,
            ...vals,
            avg,
        ]);
    });

    // Per-unit detail section
    if (perUnitDetail.length > 0) {
        const allUnits = [...new Set(perUnitDetail.flatMap(d => d.units.map((u: any) => u.unit)))];
        bulanKeys.forEach((bulan, bi) => {
            const sectionStartRow = wsData.length;
            wsData.push([]);
            wsData.push([`DETAIL PER UNIT - ${bulanHeaders[bi]}`]);
            merges.push({ s: { r: sectionStartRow + 1, c: 0 }, e: { r: sectionStartRow + 1, c: 3 + allUnits.length } });
            wsData.push(['NO', 'JENIS', 'INDIKATOR', 'TARGET (%)', ...allUnits]);
            perUnitDetail.forEach((item, idx) => {
                const vals = allUnits.map(u => {
                    const found = (item.units as any[]).find((x: any) => x.unit === u);
                    return found !== undefined ? (found[bulan] ?? '-') : '-';
                });
                wsData.push([idx + 1, jenisLabelMap[item.jenis_indikator] ?? item.jenis_indikator, item.indikator, (item as any).target ?? '', ...vals]);
            });
        });
    }

    const ws = XLSX.utils.aoa_to_sheet(wsData);
    ws['!cols'] = [{ wch: 5 }, { wch: 12 }, { wch: 35 }, { wch: 55 }, { wch: 12 }, ...bulanKeys.map(() => ({ wch: 14 })), { wch: 14 }];
    ws['!merges'] = merges;
    return ws;
}

const downloadTriwulanExcel = () => {
    const tw = selectedTriwulan.value;
    const detail = props.semuaDataCapaianTriwulanDetail[tw];
    if (!detail) return;

    const allItems = [...detail.data].sort((a, b) =>
        (jenisOrder.indexOf(a.jenis_indikator) - jenisOrder.indexOf(b.jenis_indikator))
    );
    const { bulanHeaders, bulanKeys, triwulan, tahun } = detail;
    const perUnitDetail = detail.perUnitDetail ?? [];

    const wb = XLSX.utils.book_new();

    XLSX.utils.book_append_sheet(wb, buildTriwulanSheet(allItems, bulanHeaders, bulanKeys,
        `CAPAIAN MUTU TRIWULAN ${triwulan} - ${tahun}`, perUnitDetail), 'Semua Indikator');

    const jenisList = [
        { key: 'INM',       label: 'INM' },
        { key: 'SPM',       label: 'SPM' },
        { key: 'PRIORITAS', label: 'Prioritas' },
        { key: 'IMUT_UNIT', label: 'IMUT Unit' },
    ];
    jenisList.forEach(({ key, label }) => {
        const filtered = allItems.filter(i => i.jenis_indikator === key);
        const filteredPerUnit = perUnitDetail.filter(i => i.jenis_indikator === key);
        XLSX.utils.book_append_sheet(wb, buildTriwulanSheet(filtered, bulanHeaders, bulanKeys,
            `CAPAIAN ${label.toUpperCase()} TW${triwulan} - ${tahun}`, filteredPerUnit), label);
    });

    XLSX.writeFile(wb, `Capaian_Triwulan_${triwulan}_${tahun}.xlsx`);
    showDownloadMenuTriwulan.value = false;
};

// Download annual data (previous year) as Excel with individual indicators
const downloadTahunanExcel = () => {
    const data = props.dataCapaianTahunanDetail;
    const tahun = props.dataCapaianTahunanSebelumnya.tahun;
    const bulanKeys: (keyof DataCapaianTahunanDetailItem)[] = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'des'];
    const bulanNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    // Build header row 1 (TW groups) - now with 3 columns for TIM, INDIKATOR
    const headerRow1: (string | number)[] = ['', '', '', 'TRIWULAN 1', '', '', '', '', '', '', 'TRIWULAN 2', '', '', '', '', '', '', 'TRIWULAN 3', '', '', '', '', '', '', 'TRIWULAN 4', '', '', '', '', '', '', ''];

    // Build header row 2 (month names)
    const headerRow2: (string | number)[] = ['', '', ''];
    for (let tw = 0; tw < 4; tw++) {
        const startMonth = tw * 3;
        headerRow2.push(bulanNames[startMonth], '', bulanNames[startMonth + 1], '', bulanNames[startMonth + 2], '', 'Rata2');
    }
    headerRow2.push('RATA2 TAHUNAN');

    // Build header row 3 (Target/Capaian sub-headers)
    const headerRow3: (string | number)[] = ['NO', 'TIM', 'INDIKATOR'];
    for (let tw = 0; tw < 4; tw++) {
        headerRow3.push('Target', 'Capaian', 'Target', 'Capaian', 'Target', 'Capaian', '');
    }
    headerRow3.push('');

    const wsData: (string | number)[][] = [
        [`CAPAIAN MUTU TAHUNAN - ${tahun}`],
        [], // Empty row
        headerRow1,
        headerRow2,
        headerRow3,
    ];

    // Add data rows with individual indicators
    data.data.forEach((item, index) => {
        const rowData: (string | number)[] = [index + 1, item.tim, item.indikator];
        const target = item.target || 0;
        const twAverages: number[] = [];

        // Process each quarter
        for (let tw = 0; tw < 4; tw++) {
            const twBulanKeys = bulanKeys.slice(tw * 3, tw * 3 + 3);
            const twValues: number[] = [];

            twBulanKeys.forEach((bulan) => {
                const capaian = Number(item[bulan]) || 0;
                rowData.push(target, capaian); // Target first, then Capaian
                if (capaian > 0) twValues.push(capaian);
            });

            // Calculate TW average
            const twAvg = twValues.length > 0 ? Math.round((twValues.reduce((a, b) => a + b, 0) / twValues.length) * 100) / 100 : 0;
            rowData.push(twAvg);
            if (twAvg > 0) twAverages.push(twAvg);
        }

        // Calculate annual average
        const annualAvg = twAverages.length > 0 ? Math.round((twAverages.reduce((a, b) => a + b, 0) / twAverages.length) * 100) / 100 : 0;
        rowData.push(annualAvg);

        wsData.push(rowData);
    });

    // Create workbook and worksheet
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(wsData);

    // Set column widths (added INDIKATOR column)
    const colWidths: { wch: number }[] = [{ wch: 5 }, { wch: 25 }, { wch: 50 }];
    for (let tw = 0; tw < 4; tw++) {
        colWidths.push({ wch: 7 }, { wch: 8 }, { wch: 7 }, { wch: 8 }, { wch: 7 }, { wch: 8 }, { wch: 8 });
    }
    colWidths.push({ wch: 12 });
    ws['!cols'] = colWidths;

    // Merge cells (adjusted for extra INDIKATOR column)
    ws['!merges'] = [
        // Title row
        { s: { r: 0, c: 0 }, e: { r: 0, c: 31 } },
        // NO column header (rows 2-4)
        { s: { r: 2, c: 0 }, e: { r: 4, c: 0 } },
        // TIM column header (rows 2-4)
        { s: { r: 2, c: 1 }, e: { r: 4, c: 1 } },
        // INDIKATOR column header (rows 2-4)
        { s: { r: 2, c: 2 }, e: { r: 4, c: 2 } },
        // TW headers (row 2, spans 7 columns each)
        { s: { r: 2, c: 3 }, e: { r: 2, c: 9 } },   // TW1
        { s: { r: 2, c: 10 }, e: { r: 2, c: 16 } }, // TW2
        { s: { r: 2, c: 17 }, e: { r: 2, c: 23 } }, // TW3
        { s: { r: 2, c: 24 }, e: { r: 2, c: 30 } }, // TW4
        // Month headers (row 3, each spans 2 columns)
        { s: { r: 3, c: 3 }, e: { r: 3, c: 4 } },   // Jan
        { s: { r: 3, c: 5 }, e: { r: 3, c: 6 } },   // Feb
        { s: { r: 3, c: 7 }, e: { r: 3, c: 8 } },   // Mar
        { s: { r: 3, c: 10 }, e: { r: 3, c: 11 } }, // Apr
        { s: { r: 3, c: 12 }, e: { r: 3, c: 13 } }, // Mei
        { s: { r: 3, c: 14 }, e: { r: 3, c: 15 } }, // Jun
        { s: { r: 3, c: 17 }, e: { r: 3, c: 18 } }, // Jul
        { s: { r: 3, c: 19 }, e: { r: 3, c: 20 } }, // Agu
        { s: { r: 3, c: 21 }, e: { r: 3, c: 22 } }, // Sep
        { s: { r: 3, c: 24 }, e: { r: 3, c: 25 } }, // Okt
        { s: { r: 3, c: 26 }, e: { r: 3, c: 27 } }, // Nov
        { s: { r: 3, c: 28 }, e: { r: 3, c: 29 } }, // Des
        // Rata2 TW headers (rows 3-4)
        { s: { r: 3, c: 9 }, e: { r: 4, c: 9 } },   // Rata2 TW1
        { s: { r: 3, c: 16 }, e: { r: 4, c: 16 } }, // Rata2 TW2
        { s: { r: 3, c: 23 }, e: { r: 4, c: 23 } }, // Rata2 TW3
        { s: { r: 3, c: 30 }, e: { r: 4, c: 30 } }, // Rata2 TW4
        // Rata2 Tahunan header (rows 2-4)
        { s: { r: 2, c: 31 }, e: { r: 4, c: 31 } },
    ];

    XLSX.utils.book_append_sheet(wb, ws, 'Capaian Tahunan');

    // Download file
    XLSX.writeFile(wb, `Capaian_Tahunan_${tahun}.xlsx`);

    showDownloadMenuTriwulan.value = false;
};

// Close dropdown when clicking outside
const closeDropdowns = () => {
    showDownloadMenuBulanan.value = false;
    showDownloadMenuTriwulan.value = false;
};

// Bottom 5 tooltip (fixed position to escape overflow-y-auto container)
const activeBottomTooltip = ref<string | null>(null);
const bottomTooltipStyle = ref({ top: '0px', right: '0px' });

function onBottomRowEnter(event: MouseEvent, kodeUnit: string) {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    bottomTooltipStyle.value = {
        top: `${rect.top - 4}px`,
        right: `${window.innerWidth - rect.right + 4}px`,
    };
    activeBottomTooltip.value = kodeUnit;
}
function onBottomRowLeave() {
    activeBottomTooltip.value = null;
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-3 overflow-x-auto rounded-xl p-4"
        >
            <div class="grid gap-4 md:grid-cols-4">
                <!-- Card Total Indikator -->
                <div
                    class="relative flex min-h-[140px] flex-col rounded-xl border-l-4 border-blue-500 bg-white p-6 shadow-md transition-shadow hover:shadow-lg"
                >
                    <div
                        class="grid grid-cols-2"
                        style="grid-template-columns: 50% 50%"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-600">
                                Total Indikator
                            </p>
                            <h3 class="mt-2 text-3xl font-bold text-gray-800">
                                {{ totalIndikator }}
                            </h3>
                        </div>
                        <div
                            class="flex items-center justify-end text-blue-800"
                        >
                            <ClipboardList :size="40" />
                        </div>
                    </div>
                    <p
                        class="mt-2 flex items-center gap-1 text-sm"
                        :class="statusPerubahan.color"
                    >
                        <component :is="statusPerubahan.icon" :size="12" />
                        {{ statusPerubahan.text }}
                    </p>
                </div>

                <!-- Card Capaian Bulanan -->
                <div
                    class="relative flex min-h-[140px] flex-col rounded-xl border-l-4 border-yellow-500 bg-white p-6 shadow-md transition-shadow hover:shadow-lg"
                >
                    <div
                        class="grid grid-cols-2"
                        style="grid-template-columns: 60% 40%"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-600">
                                Capaian Bulanan
                            </p>
                            <h3 class="mt-2 text-3xl font-bold text-gray-800">
                                {{ Math.min(capaianBulanan.persentase, 100) }} %
                                <span v-if="capaianBulanan.persentase > 100" class="text-sm font-normal text-gray-400 ml-1">({{ capaianBulanan.persentase }}%)</span>
                            </h3>
                        </div>
                        <div
                            class="flex items-center justify-end text-yellow-600"
                        >
                            <Stamp :size="40" />
                        </div>
                    </div>

                    <div
                        class="relative mt-2"
                        @mouseenter="enterTooltip"
                        @mouseleave="leaveTooltip"
                    >
                        <p
                            class="flex cursor-help items-center gap-1 text-sm"
                            :class="
                                capaianBulanan.belumAdaIndikator
                                    ? 'text-yellow-600'
                                    : capaianBulanan.timUnitBelumMelaporkan > 0
                                        ? 'text-red-600'
                                        : 'text-green-600'
                            "
                        >
                            <InfoIcon
                                v-if="capaianBulanan.belumAdaIndikator"
                                :size="12"
                            />
                            <RotateCcwIcon
                                v-else-if="capaianBulanan.timUnitBelumMelaporkan > 0"
                                :size="12"
                            />
                            <CheckCircle v-else :size="12" />
                            {{ statusCapaianText }}
                        </p>

                        <div
                            v-if="
                                showTooltip &&
                                capaianBulanan.timUnitBelumMelaporkan > 0
                            "
                            class="absolute top-full left-0 z-50 mt-2 w-80 rounded-lg bg-gray-900 p-3 shadow-2xl max-h-96 overflow-y-auto"
                            @mouseenter="enterTooltip"
                            @mouseleave="leaveTooltip"
                        >
                            <!-- Belum Mengisi Per Jenis -->
                            <div v-if="capaianBulanan.daftarBelumMengisiPerJenis?.length > 0">
                                <div class="mb-2 flex items-center gap-2 border-b border-gray-700 pb-2">
                                    <RotateCcwIcon :size="14" class="text-red-400" />
                                    <p class="text-xs font-semibold text-red-300">Belum Mengisi Data:</p>
                                </div>
                                <div v-for="(group, gIdx) in capaianBulanan.daftarBelumMengisiPerJenis" :key="'isi-g-'+gIdx" class="mb-2">
                                    <p class="text-[10px] font-semibold text-red-200 uppercase mb-1">{{ group.jenis }}</p>
                                    <ul class="space-y-0.5 text-xs text-gray-300 ml-2">
                                        <li v-for="(unit, index) in group.units" :key="'isi-'+gIdx+'-'+index" class="flex items-start gap-2 py-0.5">
                                            <span class="text-red-400">•</span>
                                            <span>{{ unit }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <!-- Belum Approve Per Jenis -->
                            <div v-if="capaianBulanan.daftarBelumApprovePerJenis?.length > 0">
                                <div class="mb-2 flex items-center gap-2 border-b border-gray-700 pb-2">
                                    <Clock :size="14" class="text-yellow-400" />
                                    <p class="text-xs font-semibold text-yellow-300">Belum Approve Kepala:</p>
                                </div>
                                <div v-for="(group, gIdx) in capaianBulanan.daftarBelumApprovePerJenis" :key="'app-g-'+gIdx" class="mb-2">
                                    <p class="text-[10px] font-semibold text-yellow-200 uppercase mb-1">{{ group.jenis }}</p>
                                    <ul class="space-y-0.5 text-xs text-gray-300 ml-2">
                                        <li v-for="(unit, index) in group.units" :key="'app-'+gIdx+'-'+index" class="flex items-start gap-2 py-0.5">
                                            <span class="text-yellow-400">•</span>
                                            <span>{{ unit }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div
                                class="absolute -top-2 left-4 h-0 w-0 border-x-8 border-b-8 border-x-transparent border-b-gray-900"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- Card Capaian TW - DYNAMIC -->
                <div
                    class="relative flex min-h-[140px] flex-col rounded-xl border-l-4 border-orange-500 bg-white p-6 shadow-md transition-shadow hover:shadow-lg"
                >
                    <div
                        class="grid grid-cols-2"
                        style="grid-template-columns: 60% 40%"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-600">
                                Capaian TW {{ capaianTriwulan.triwulan }}
                            </p>
                            <h3 class="mt-2 text-3xl font-bold text-gray-800">
                                {{ Math.min(capaianTriwulan.persentase, 100) }} %
                                <span v-if="capaianTriwulan.persentase > 100" class="text-sm font-normal text-gray-400 ml-1">({{ capaianTriwulan.persentase }}%)</span>
                            </h3>
                        </div>
                        <div
                            class="flex items-center justify-end text-orange-600"
                        >
                            <CheckSquare2 :size="40" />
                        </div>
                    </div>

                    <div
                        class="relative mt-2"
                        @mouseenter="enterTooltipTW"
                        @mouseleave="leaveTooltipTW"
                    >
                        <p
                            class="flex cursor-help items-center gap-1 text-sm text-orange-600"
                        >
                            <LoaderIcon :size="12" />
                            {{ capaianTriwulan.status }}
                        </p>

                        <!-- Tooltip Detail Per Bulan -->
                        <div
                            v-if="showTooltipTW"
                            class="absolute top-full left-0 z-50 mt-2 w-80 rounded-lg bg-gray-900 p-3 shadow-2xl max-h-96 overflow-y-auto"
                            @mouseenter="enterTooltipTW"
                            @mouseleave="leaveTooltipTW"
                        >
                            <div
                                class="mb-2 flex items-center gap-2 border-b border-gray-700 pb-2"
                            >
                                <InfoIcon :size="16" class="text-orange-400" />
                                <p class="text-xs font-semibold text-white">
                                    Detail Capaian TW
                                    {{ capaianTriwulan.triwulan }}:
                                </p>
                            </div>
                            <div class="space-y-3 text-xs text-gray-300">
                                <div
                                    v-for="(detail, index) in capaianTriwulan.detailPerBulan"
                                    :key="index"
                                >
                                    <div class="flex items-center justify-between py-1 border-b border-gray-700 mb-1">
                                        <span class="font-semibold text-white">{{ detail.bulan }}</span>
                                        <span class="font-semibold text-orange-400">{{ Math.min(detail.persentase, 100) }}%<span v-if="detail.persentase > 100" class="text-[10px] text-gray-400 ml-1">({{ detail.persentase }}%)</span></span>
                                    </div>
                                    <ul v-if="detail.detailJenis && detail.detailJenis.length > 0" class="space-y-0.5 ml-2">
                                        <li
                                            v-for="(jenis, jIdx) in detail.detailJenis"
                                            :key="jIdx"
                                            class="flex items-center justify-between py-0.5"
                                        >
                                            <span class="text-gray-400 text-[10px]">{{ jenis.jenis }}</span>
                                            <span class="text-[10px] text-orange-300">{{ Math.min(jenis.persentase, 100) }}%<span v-if="jenis.persentase > 100" class="text-[9px] text-gray-400 ml-1">({{ jenis.persentase }}%)</span></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div
                                class="absolute -top-2 left-4 h-0 w-0 border-x-8 border-b-8 border-x-transparent border-b-gray-900"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- Card Capaian Tahunan - DYNAMIC -->
                <div
                    class="relative flex min-h-[140px] flex-col rounded-xl border-l-4 border-green-500 bg-white p-6 shadow-md transition-shadow hover:shadow-lg"
                >
                    <div
                        class="grid grid-cols-2"
                        style="grid-template-columns: 60% 40%"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-600">
                                Capaian Tahunan
                            </p>
                            <h3 class="mt-2 text-3xl font-bold text-gray-800">
                                {{ Math.min(capaianTahunan.persentase, 100) }} %
                                <span v-if="capaianTahunan.persentase > 100" class="text-sm font-normal text-gray-400 ml-1">({{ capaianTahunan.persentase }}%)</span>
                            </h3>
                        </div>
                        <div
                            class="flex items-center justify-end text-green-600"
                        >
                            <StarIcon :size="40" />
                        </div>
                    </div>
                    <div
                        class="relative mt-2"
                        @mouseenter="enterTooltipTahunan"
                        @mouseleave="leaveTooltipTahunan"
                    >
                        <p
                            class="flex cursor-help items-center gap-1 text-sm"
                            :class="capaianTahunan.twSebelumnya ? 'text-green-600' : 'text-gray-600'"
                        >
                            <LoaderCircleIcon :size="12" />
                            {{ capaianTahunan.status }}
                        </p>

                        <!-- Tooltip TW Sebelumnya -->
                        <div
                            v-if="showTooltipTahunan && capaianTahunan.twSebelumnya"
                            class="absolute top-full left-0 z-50 mt-2 w-56 rounded-lg bg-gray-900 px-3 py-2 shadow-2xl"
                            @mouseenter="enterTooltipTahunan"
                            @mouseleave="leaveTooltipTahunan"
                        >
                            <div class="flex items-center gap-2">
                                <InfoIcon :size="13" class="text-green-400 shrink-0" />
                                <span class="text-xs text-white">Capaian TW {{ capaianTahunan.twSebelumnya.triwulan }}</span>
                                <span class="ml-auto text-sm font-bold text-green-400 whitespace-nowrap">
                                    {{ Math.min(capaianTahunan.twSebelumnya.persentase, 100) }}%
                                </span>
                            </div>
                            <div class="absolute -top-2 left-4 h-0 w-0 border-x-8 border-b-8 border-x-transparent border-b-gray-900"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts + Ranking Side by Side -->
            <div class="grid gap-3 lg:grid-cols-4">

                <!-- Charts Column (kiri, 3/4) -->
                <div class="flex flex-col gap-3 lg:col-span-3">

                    <!-- Grafik side by side -->
                    <div class="grid gap-3 md:grid-cols-2" @click="closeDropdowns">

                    <!-- Grafik Tren Capaian Mutu Bulanan -->
                    <div
                        class="relative rounded-xl border-l-4 border-blue-500 bg-white p-3 shadow-md transition-shadow hover:shadow-lg"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">
                                    Tren Capaian Mutu Bulanan
                                </p>
                                <p class="text-xs text-gray-400">
                                    {{ grafikBulanan.bulan }}
                                    {{ grafikBulanan.tahun }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2" @click.stop>
                                <!-- Expand Button -->
                                <button
                                    class="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
                                    title="Perbesar grafik"
                                    @click="showChartPopup = 'bulanan'"
                                >
                                    <Maximize2 :size="15" />
                                </button>
                                <!-- Download Button with Dropdown -->
                                <div class="relative">
                                    <button
                                        class="flex items-center gap-2 rounded-lg bg-blue-600 px-2.5 py-1.5 text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="grafikBulanan.labels.length === 0"
                                        @click="showDownloadMenuBulanan = !showDownloadMenuBulanan"
                                    >
                                        <Download :size="14" />
                                    </button>
                                    <div
                                        v-if="showDownloadMenuBulanan"
                                        class="absolute right-0 top-full z-50 mt-2 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                                    >
                                        <button
                                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                                            @click="downloadChartAsImage(chartBulananRef, `Grafik_Capaian_Bulanan_${grafikBulanan.bulan}_${grafikBulanan.tahun}`)"
                                        >
                                            <ImageIcon :size="16" class="text-blue-600" />
                                            Download Gambar (PNG)
                                        </button>
                                        <button
                                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                                            @click="downloadBulananExcel"
                                        >
                                            <FileSpreadsheet :size="16" class="text-green-600" />
                                            Download Data (Excel)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="h-[175px] 2xl:h-[230px]">
                            <Bar
                                v-if="grafikBulanan.labels.length > 0"
                                ref="chartBulananRef"
                                :data="chartBulananData"
                                :options="chartBulananOptions"
                            />
                            <div
                                v-else
                                class="flex h-full items-center justify-center text-gray-400"
                            >
                                <div class="text-center">
                                    <InfoIcon :size="40" class="mx-auto mb-2 opacity-50" />
                                    <p class="text-sm">Belum ada data capaian</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grafik Tren Capaian Mutu Triwulanan -->
                    <div
                        class="relative rounded-xl border-l-4 border-orange-500 bg-white p-3 shadow-md transition-shadow hover:shadow-lg"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <!-- Navigation Buttons -->
                                <button
                                    class="rounded-lg p-1.5 text-gray-500 transition-colors hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-30"
                                    :disabled="selectedTriwulan <= 1"
                                    @click="prevTriwulan"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <div>
                                    <p class="text-sm font-medium text-gray-600">
                                        Tren Capaian Mutu Triwulanan
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        Triwulan {{ grafikTriwulanan.triwulan }} -
                                        {{ grafikTriwulanan.tahun }}
                                    </p>
                                </div>
                                <button
                                    class="rounded-lg p-1.5 text-gray-500 transition-colors hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-30"
                                    :disabled="selectedTriwulan >= 4"
                                    @click="nextTriwulan"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                            <div class="flex items-center gap-2" @click.stop>
                                <!-- Expand Button -->
                                <button
                                    class="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
                                    title="Perbesar grafik"
                                    @click="showChartPopup = 'triwulan'"
                                >
                                    <Maximize2 :size="15" />
                                </button>
                                <!-- Download Button with Dropdown -->
                                <div class="relative">
                                    <button
                                        class="flex items-center gap-2 rounded-lg bg-orange-600 px-2.5 py-1.5 text-white transition-colors hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="grafikTriwulanan.labels.length === 0"
                                        @click="showDownloadMenuTriwulan = !showDownloadMenuTriwulan"
                                    >
                                        <Download :size="14" />
                                    </button>
                                    <!-- Dropdown Menu -->
                                    <div
                                        v-if="showDownloadMenuTriwulan"
                                        class="absolute right-0 top-full z-50 mt-2 w-64 rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                                    >
                                        <button
                                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                                            @click="downloadChartAsImage(chartTriwulanRef, `Grafik_Capaian_Triwulan_${grafikTriwulanan.triwulan}_${grafikTriwulanan.tahun}`)"
                                        >
                                            <ImageIcon :size="16" class="text-orange-600" />
                                            Download Gambar (PNG)
                                        </button>
                                        <button
                                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                                            @click="downloadTriwulanExcel"
                                        >
                                            <FileSpreadsheet :size="16" class="text-green-600" />
                                            Download Triwulan (Excel)
                                        </button>
                                        <!-- Show annual download option when on TW1 -->
                                        <template v-if="selectedTriwulan === 1">
                                            <div class="my-1 border-t border-gray-200"></div>
                                            <button
                                                class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                                                @click="downloadTahunanExcel"
                                            >
                                                <FileSpreadsheet :size="16" class="text-purple-600" />
                                                Download Tahunan {{ dataCapaianTahunanSebelumnya.tahun }} (Excel)
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="h-[175px] 2xl:h-[230px]">
                            <Bar
                                v-if="grafikTriwulanan.labels.length > 0"
                                ref="chartTriwulanRef"
                                :data="chartTriwulanData"
                                :options="chartTriwulanOptions"
                            />
                            <div
                                v-else
                                class="flex h-full items-center justify-center text-gray-400"
                            >
                                <div class="text-center">
                                    <InfoIcon :size="40" class="mx-auto mb-2 opacity-50" />
                                    <p class="text-sm">Belum ada data capaian</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    </div><!-- end charts sub-grid -->

                    <!-- Activity Section -->
                    <div
                        class="relative rounded-xl border border-l-4 border-sidebar-border/70 bg-white shadow-md dark:border-sidebar-border"
                    >
                        <div class="border-b border-gray-200 p-3 pb-2">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-gray-800">
                                    Aktivitas Terbaru
                                </h3>
                                <div
                                    class="flex items-center gap-1.5 text-xs text-gray-500"
                                >
                                    <Clock :size="13" />
                                    <span>12 jam terakhir</span>
                                </div>
                            </div>
                        </div>

                        <div class="max-h-[200px] 2xl:max-h-[380px] overflow-y-auto p-3 pt-2">
                            <div v-if="aktivitasTerbaru.length > 0" class="space-y-3">
                                <div
                                    v-for="(aktivitas, index) in aktivitasTerbaru"
                                    :key="index"
                                    class="flex items-start gap-3 rounded-lg border border-gray-100 p-3 transition-all hover:shadow-md"
                                    :class="aktivitas.color"
                                >
                                    <div class="flex-shrink-0 text-xl">
                                        {{ aktivitas.icon }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="text-sm leading-relaxed font-medium"
                                            :class="aktivitas.text_color"
                                        >
                                            {{ aktivitas.message }}
                                        </p>
                                        <div class="mt-1 flex items-center gap-2">
                                            <Clock :size="12" class="text-gray-400" />
                                            <p class="text-xs text-gray-500">
                                                {{ aktivitas.time_display }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="flex flex-col items-center justify-center py-12 text-gray-400"
                            >
                                <InfoIcon :size="48" class="mb-3 opacity-50" />
                                <p class="text-sm font-medium">
                                    Belum ada aktivitas dalam 12 jam terakhir
                                </p>
                                <p class="mt-1 text-xs text-gray-400">
                                    Aktivitas akan muncul di sini
                                </p>
                            </div>
                        </div>
                    </div><!-- end Activity Section -->

                </div><!-- end Charts + Activity Column -->

                <!-- Ranking Column (kanan, 1/4) -->
                <div class="flex flex-col gap-2 h-full">

                <!-- Top 5 Tercepat Input -->
                <div class="flex-1 flex flex-col min-h-0 rounded-lg border border-gray-100 bg-white p-3 shadow-sm">
                    <div class="mb-2 flex items-center gap-1.5 shrink-0">
                        <Award :size="14" class="text-green-600" />
                        <h3 class="text-xs font-bold text-gray-800">Top 5 Tercepat Input</h3>
                    </div>
                    <div v-if="rankingData.topTercepat.length" class="flex-1 min-h-0 overflow-y-auto space-y-0.5">
                        <div
                            v-for="(item, idx) in rankingData.topTercepat"
                            :key="item.kode_unit"
                            class="flex items-center gap-2 border-b border-gray-50 py-1 last:border-0"
                        >
                            <span
                                class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full text-[9px] font-bold"
                                :class="idx === 0 ? 'bg-yellow-400 text-white' : idx === 1 ? 'bg-gray-300 text-gray-700' : idx === 2 ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-600'"
                            >{{ idx + 1 }}</span>
                            <span class="flex-1 text-[11px] leading-tight text-gray-700">{{ item.unit }}</span>
                            <span class="text-[11px] font-semibold text-green-600 whitespace-nowrap">{{ item.waktu_input ?? '-' }}</span>
                        </div>
                    </div>
                    <div v-else class="flex-1 flex items-center justify-center text-xs text-gray-400">Belum ada data</div>
                </div>

                <!-- Top 5 Di Atas Standar -->
                <div class="flex-1 flex flex-col min-h-0 rounded-lg border border-gray-100 bg-white p-3 shadow-sm">
                    <div class="mb-2 flex items-center gap-1.5 shrink-0">
                        <TrendingUp :size="14" class="text-emerald-600" />
                        <h3 class="text-xs font-bold text-gray-800">Top 5 Di Atas Standar</h3>
                    </div>
                    <div v-if="rankingData.topDiAtasStandar.length" class="flex-1 min-h-0 overflow-y-auto space-y-0.5">
                        <div
                            v-for="(item, idx) in rankingData.topDiAtasStandar"
                            :key="item.kode_unit"
                            class="flex items-center gap-2 border-b border-gray-50 py-1 last:border-0"
                        >
                            <span
                                class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full text-[9px] font-bold"
                                :class="idx === 0 ? 'bg-yellow-400 text-white' : idx === 1 ? 'bg-gray-300 text-gray-700' : idx === 2 ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-600'"
                            >{{ idx + 1 }}</span>
                            <span class="flex-1 text-[11px] leading-tight text-gray-700">{{ item.unit }}</span>
                            <span class="text-[11px] font-semibold text-emerald-600">{{ item.above_standar }} ind</span>
                        </div>
                    </div>
                    <div v-else class="flex-1 flex items-center justify-center text-xs text-gray-400">Belum ada data</div>
                </div>

                <!-- Top 5 Banyak Catatan Admin -->
                <div class="flex-1 flex flex-col min-h-0 rounded-lg border border-gray-100 bg-white p-3 shadow-sm">
                    <div class="mb-2 flex items-center gap-1.5 shrink-0">
                        <MessageSquare :size="14" class="text-blue-600" />
                        <h3 class="text-xs font-bold text-gray-800">Top 5 Banyak Catatan Admin</h3>
                    </div>
                    <div v-if="rankingData.topKomentar.length" class="flex-1 min-h-0 overflow-y-auto space-y-0.5">
                        <div
                            v-for="(item, idx) in rankingData.topKomentar"
                            :key="item.kode_unit"
                            class="flex items-center gap-2 border-b border-gray-50 py-1 last:border-0"
                        >
                            <span
                                class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full text-[9px] font-bold"
                                :class="idx === 0 ? 'bg-blue-500 text-white' : idx === 1 ? 'bg-blue-400 text-white' : idx === 2 ? 'bg-blue-300 text-white' : 'bg-gray-100 text-gray-600'"
                            >{{ idx + 1 }}</span>
                            <span class="flex-1 text-[11px] leading-tight text-gray-700">{{ item.unit }}</span>
                            <span class="text-[11px] font-semibold text-blue-600">{{ item.total_komentar }} catatan</span>
                        </div>
                    </div>
                    <div v-else class="flex-1 flex items-center justify-center text-xs text-gray-400">Belum ada data</div>
                </div>

                <!-- Bottom 5 Terlambat Input -->
                <div class="flex-1 flex flex-col min-h-0 rounded-lg border border-gray-100 bg-white p-3 shadow-sm">
                    <div class="mb-2 flex items-center gap-1.5 shrink-0">
                        <AlertTriangle :size="14" class="text-red-500" />
                        <h3 class="text-xs font-bold text-gray-800">Bottom 5 Terlambat Input</h3>
                    </div>
                    <div v-if="rankingData.bottomTerlambat.length" class="flex-1 min-h-0 overflow-y-auto space-y-0.5">
                        <div
                            v-for="(item, idx) in rankingData.bottomTerlambat"
                            :key="item.kode_unit"
                            class="flex items-center gap-2 border-b border-gray-50 py-1 last:border-0"
                            @mouseenter="(e: MouseEvent) => onBottomRowEnter(e, item.kode_unit)"
                            @mouseleave="onBottomRowLeave"
                        >
                            <span class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full bg-red-100 text-[9px] font-bold text-red-600">{{ idx + 1 }}</span>
                            <span class="flex-1 text-[11px] leading-tight text-gray-700">{{ item.unit }}</span>
                            <span class="cursor-help text-[11px] font-semibold text-red-500 whitespace-nowrap">{{ item.jumlah_bulan_belum ?? item.bulan_belum_terisi?.length ?? 0 }} bln belum</span>
                        </div>
                    </div>
                    <div v-else class="flex-1 flex items-center justify-center text-xs text-gray-400">Belum ada data</div>
                </div>
                </div>
            </div>

            <!-- Bottom 5 Tooltip (Teleport to body to escape overflow containers) -->
            <Teleport to="body">
                <div
                    v-if="activeBottomTooltip"
                    class="pointer-events-none fixed z-[9999] w-max max-w-[200px] -translate-y-full rounded-lg border border-red-100 bg-white px-3 py-2 shadow-lg"
                    :style="bottomTooltipStyle"
                >
                    <template v-for="item in rankingData.bottomTerlambat" :key="'tt-' + item.kode_unit">
                        <template v-if="item.kode_unit === activeBottomTooltip && item.bulan_belum_terisi?.length">
                            <p class="mb-1 text-[10px] font-semibold text-red-600">Belum diisi:</p>
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="bln in item.bulan_belum_terisi"
                                    :key="bln"
                                    class="rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-medium text-red-700"
                                >{{ bln }}</span>
                            </div>
                        </template>
                    </template>
                </div>
            </Teleport>

            <!-- Chart Popup Modal -->
            <div
                v-if="showChartPopup"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
                @click.self="showChartPopup = null"
            >
                <div class="relative w-full max-w-4xl rounded-xl bg-white p-6 shadow-2xl">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-gray-800">
                                {{ showChartPopup === 'bulanan' ? 'Tren Capaian Mutu Bulanan' : 'Tren Capaian Mutu Triwulanan' }}
                            </h3>
                            <p class="text-xs text-gray-400">
                                <template v-if="showChartPopup === 'bulanan'">{{ grafikBulanan.bulan }} {{ grafikBulanan.tahun }}</template>
                                <template v-else>Triwulan {{ grafikTriwulanan.triwulan }} - {{ grafikTriwulanan.tahun }}</template>
                            </p>
                        </div>
                        <button
                            class="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700"
                            @click="showChartPopup = null"
                        >
                            <X :size="20" />
                        </button>
                    </div>
                    <div class="h-[480px]">
                        <Bar
                            v-if="showChartPopup === 'bulanan' && grafikBulanan.labels.length > 0"
                            :data="chartBulananData"
                            :options="chartBulananOptions"
                        />
                        <Bar
                            v-else-if="showChartPopup === 'triwulan' && grafikTriwulanan.labels.length > 0"
                            :data="chartTriwulanData"
                            :options="chartTriwulanOptions"
                        />
                        <div
                            v-else
                            class="flex h-full items-center justify-center text-gray-400"
                        >
                            <div class="text-center">
                                <InfoIcon :size="48" class="mx-auto mb-2 opacity-50" />
                                <p class="text-sm">Belum ada data capaian</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<style scoped>
.tooltip-enter-active,
.tooltip-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}

.tooltip-enter-from,
.tooltip-leave-to {
    opacity: 0;
    transform: translateY(-5px);
}

.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.overflow-y-auto {
    scroll-behavior: smooth;
}
</style>
