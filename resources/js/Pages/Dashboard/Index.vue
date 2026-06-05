<template>
    <AppLayout title="Dashboard">
        <div class="p-6">

            <!-- Header + date filter -->
            <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
                    <p class="text-sm text-gray-400 mt-0.5">Bienvenido, {{ $page.props.auth?.user?.name }}</p>
                </div>
                <form @submit.prevent="applyDates"
                    class="flex flex-wrap items-end gap-3 bg-white border border-gray-100 shadow-sm rounded-xl p-3">
                    <div>
                        <label class="block text-xs text-gray-400 font-medium mb-1">Desde</label>
                        <input type="date" v-model="localDesde"
                            class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 font-medium mb-1">Hasta</label>
                        <input type="date" v-model="localHasta"
                            class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
                    </div>
                    <Button type="submit" label="Filtrar" icon="pi pi-filter" size="small"
                        style="background-color:#3452ff;border-color:#3452ff;" />
                </form>
            </div>

            <!-- Alertas por vencer / vencidas -->
            <div v-if="props.alertas" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0">
                        <i class="pi pi-clock text-blue-500" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Pendientes</p>
                        <p class="text-2xl font-bold text-blue-600">{{ props.alertas.pendientes ?? 0 }}</p>
                        <p class="text-xs text-gray-400">Sin respuesta</p>
                    </div>
                </div>
                <div class="bg-amber-50 rounded-xl border border-amber-100 shadow-sm p-4 flex items-center gap-4 cursor-pointer"
                    @click="showPorVencer = !showPorVencer">
                    <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                        <i class="pi pi-exclamation-triangle text-amber-500" />
                    </div>
                    <div>
                        <p class="text-xs text-amber-600 uppercase tracking-wide">Por Vencer</p>
                        <p class="text-2xl font-bold text-amber-600">{{ props.alertas.porVencer?.length ?? 0 }}</p>
                        <p class="text-xs text-amber-500">Vencen hoy o mañana · click para ver</p>
                    </div>
                </div>
                <div class="bg-red-50 rounded-xl border border-red-100 shadow-sm p-4 flex items-center gap-4 cursor-pointer"
                    @click="showVencidas = !showVencidas">
                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                        <i class="pi pi-times-circle text-red-500" />
                    </div>
                    <div>
                        <p class="text-xs text-red-600 uppercase tracking-wide">Vencidas</p>
                        <p class="text-2xl font-bold text-red-600">{{ props.alertas.vencidas?.length ?? 0 }}</p>
                        <p class="text-xs text-red-400">Plazo superado · click para ver</p>
                    </div>
                </div>
            </div>

            <!-- Detalle Por Vencer -->
            <div v-if="showPorVencer && props.alertas?.porVencer?.length" class="bg-white rounded-xl border border-amber-200 shadow-sm p-4 mb-4">
                <p class="text-sm font-semibold text-amber-700 mb-3">Órdenes por vencer</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead><tr class="text-gray-400 uppercase">
                            <th class="text-left py-1 pr-3">N°</th><th class="text-left py-1 pr-3">Paciente</th>
                            <th class="text-left py-1 pr-3">Clínica</th><th class="text-left py-1">Vence</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="o in props.alertas.porVencer" :key="o.id" class="border-t border-gray-50">
                                <td class="py-1 pr-3 font-mono">{{ o.id }}</td>
                                <td class="py-1 pr-3">{{ o.paciente }}</td>
                                <td class="py-1 pr-3">{{ o.clinica }}</td>
                                <td class="py-1 text-amber-600 font-medium">{{ o.deadline }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Detalle Vencidas -->
            <div v-if="showVencidas && props.alertas?.vencidas?.length" class="bg-white rounded-xl border border-red-200 shadow-sm p-4 mb-4">
                <p class="text-sm font-semibold text-red-700 mb-3">Órdenes vencidas</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead><tr class="text-gray-400 uppercase">
                            <th class="text-left py-1 pr-3">N°</th><th class="text-left py-1 pr-3">Paciente</th>
                            <th class="text-left py-1 pr-3">Clínica</th><th class="text-left py-1">Días vencida</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="o in props.alertas.vencidas" :key="o.id" class="border-t border-gray-50">
                                <td class="py-1 pr-3 font-mono">{{ o.id }}</td>
                                <td class="py-1 pr-3">{{ o.paciente }}</td>
                                <td class="py-1 pr-3">{{ o.clinica }}</td>
                                <td class="py-1 text-red-600 font-medium">{{ o.dias_vencida }} días</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Stats row 1: Órdenes -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                <div v-for="s in ordenStats" :key="s.label"
                    class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                        :style="{ backgroundColor: s.color + '18' }">
                        <i :class="'pi ' + s.icon" :style="{ color: s.color }" class="text-lg" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-800">{{ s.value }}</p>
                        <p class="text-xs text-gray-400 leading-tight">{{ s.label }}</p>
                    </div>
                </div>
            </div>

            <!-- Stats row 2: Exámenes -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div v-for="s in examenStats" :key="s.label"
                    class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                        :style="{ backgroundColor: s.color + '18' }">
                        <i :class="'pi ' + s.icon" :style="{ color: s.color }" class="text-lg" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-800">{{ s.value }}</p>
                        <p class="text-xs text-gray-400 leading-tight">{{ s.label }}</p>
                    </div>
                </div>
            </div>

            <!-- Charts row 1 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Por Radiólogo -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-700">Exámenes por Radiólogo</h3>
                        <select v-model="selRadiologo" @change="drawRadiologoChart"
                            class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none max-w-36 truncate">
                            <option v-for="r in radiologos" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div class="relative h-56">
                        <canvas ref="cvRadiologo"></canvas>
                    </div>
                </div>

                <!-- Por Clínica -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-700">Exámenes por Clínica</h3>
                        <select v-model="selClinica" @change="drawClinicaChart"
                            class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none max-w-36 truncate">
                            <option v-for="c in clinicas" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="relative h-56">
                        <canvas ref="cvClinica"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts row 2 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Por Red -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-700">Exámenes por Red</h3>
                        <select v-model="selRed" @change="drawRedChart"
                            class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none max-w-36 truncate">
                            <option v-for="r in redes" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div class="relative h-56">
                        <canvas ref="cvRed"></canvas>
                    </div>
                </div>

                <!-- 2D vs 3D -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <h3 class="font-semibold text-gray-700 mb-4">2D vs 3D</h3>
                    <div class="relative h-56">
                        <canvas ref="cvPie"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts row 3 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Tiempo de respuesta -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-700">Tiempos de Respuesta (días)</h3>
                        <select v-model="selTiempo" @change="drawTiempoChart"
                            class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none max-w-36 truncate">
                            <option v-for="r in radiologosRespuestas" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div class="relative h-56">
                        <canvas ref="cvTiempo"></canvas>
                    </div>
                </div>

                <!-- Respondidas por tipo -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-700">Exámenes Respondidos por Radiólogo</h3>
                        <select v-model="selRespuestas" @change="drawRespuestasChart"
                            class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none max-w-36 truncate">
                            <option v-for="r in radiologosRespuestas" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div class="relative h-56">
                        <canvas ref="cvRespuestas"></canvas>
                    </div>
                </div>
            </div>

        </div>

    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import { Chart, registerables } from 'chart.js';
import ChartDataLabels from 'chartjs-plugin-datalabels';

Chart.register(...registerables, ChartDataLabels);

const props = defineProps({
    fechaDesde:                   String,
    fechaHasta:                   String,
    alertas:                      Object,
    totalesOrdenes:               Object,
    totalesExamenes:              Object,
    radiologos:                   Array,
    radiologoChartData:           Object,
    clinicas:                     Array,
    clinicaChartData:             Object,
    redes:                        Array,
    redChartData:                 Object,
    radiologosRespuestas:         Array,
    radiologoTiempoChartData:     Object,
    radiologoRespuestasChartData: Object,
});

// ── Alertas toggle ───────────────────────────────────────────────────────
const showPorVencer = ref(false);
const showVencidas  = ref(false);

// ── Date filter ──────────────────────────────────────────────────────────
const localDesde = ref(props.fechaDesde);
const localHasta = ref(props.fechaHasta);

const applyDates = () =>
    router.get(route('dashboard'), { fecha_desde: localDesde.value, fecha_hasta: localHasta.value }, { preserveScroll: true });

// ── Stat cards ────────────────────────────────────────────────────────────
const ordenStats = computed(() => [
    { label: 'Órdenes Creadas',     value: props.totalesOrdenes?.total_creadas    ?? 0, icon: 'pi-file-edit',    color: '#3452ff' },
    { label: 'Órdenes Enviadas',    value: props.totalesOrdenes?.total_enviadas   ?? 0, icon: 'pi-send',          color: '#10b981' },
    { label: 'Órdenes Respondidas', value: props.totalesOrdenes?.total_respondidas ?? 0, icon: 'pi-check-circle', color: '#6366f1' },
]);

const examenStats = computed(() => [
    { label: 'Total Exámenes', value: props.totalesExamenes?.total_examenes   ?? 0, icon: 'pi-images', color: '#0b2a4a' },
    { label: 'Exámenes 2D',    value: props.totalesExamenes?.total_2d         ?? 0, icon: 'pi-image',  color: '#f59e0b' },
    { label: 'Exámenes 3D',    value: props.totalesExamenes?.total_3d         ?? 0, icon: 'pi-box',    color: '#ef4444' },
    { label: 'Respondidos',    value: props.totalesExamenes?.total_respondidos ?? 0, icon: 'pi-check',  color: '#10b981' },
]);

// ── Dropdown selections ───────────────────────────────────────────────────
const selRadiologo  = ref('-1');
const selClinica    = ref('-1');
const selRed        = ref('-1');
const selTiempo     = ref('-1');
const selRespuestas = ref('-1');

// ── Canvas refs ───────────────────────────────────────────────────────────
const cvRadiologo  = ref(null);
const cvClinica    = ref(null);
const cvRed        = ref(null);
const cvPie        = ref(null);
const cvTiempo     = ref(null);
const cvRespuestas = ref(null);

// ── Chart instances ───────────────────────────────────────────────────────
let instRadiologo  = null;
let instClinica    = null;
let instRed        = null;
let instPie        = null;
let instTiempo     = null;
let instRespuestas = null;

// ── Colors ────────────────────────────────────────────────────────────────
const COLORS = ['#3452ff','#10b981','#f59e0b','#ef4444','#6366f1','#0b2a4a','#8b5cf6','#06b6d4','#84cc16','#f97316'];

const palette = (n) => Array.from({ length: n }, (_, i) => COLORS[i % COLORS.length]);

// ── Chart helpers ─────────────────────────────────────────────────────────
function makeBarChart(canvas, data, oldInst) {
    oldInst?.destroy();
    if (!canvas || !data) return null;
    const entries = Object.values(data.dataset ?? {});
    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels: entries.map(e => e.label),
            datasets: [{
                data: entries.map(e => e.total),
                backgroundColor: palette(entries.length),
                borderRadius: 4,
                borderSkipped: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                datalabels: {
                    anchor: 'end',
                    align: 'end',
                    color: '#6b7280',
                    font: { size: 10, weight: 'bold' },
                    formatter: (v) => v > 0 ? v : null,
                },
                tooltip: {
                    callbacks: {
                        afterLabel: (ctx) => {
                            const e = entries[ctx.dataIndex];
                            return e?.details?.join('\n') ?? '';
                        },
                    },
                },
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 30 } },
                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 } } },
            },
        },
    });
}

function makePieChart(canvas, oldInst) {
    oldInst?.destroy();
    if (!canvas) return null;
    const d2 = props.totalesExamenes?.total_2d ?? 0;
    const d3 = props.totalesExamenes?.total_3d ?? 0;
    return new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['2D', '3D'],
            datasets: [{
                data: [d2, d3],
                backgroundColor: ['#3452ff', '#f59e0b'],
                borderWidth: 2,
                borderColor: '#fff',
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'bottom' },
                datalabels: {
                    color: '#fff',
                    font: { size: 13, weight: 'bold' },
                    formatter: (v) => v > 0 ? v : null,
                },
            },
        },
    });
}

function makeTiempoChart(canvas, selId, oldInst) {
    oldInst?.destroy();
    if (!canvas) return null;
    const data = props.radiologoTiempoChartData?.[selId];
    if (!data) return null;
    const entries = Object.values(data.dataset ?? {});

    let labels, values;
    if (selId === '-1') {
        // Todos: x = radiólogos, y = días promedio ponderado
        labels = entries.map(e => e.label);
        values = entries.map(e => e.total > 0 ? +(e.tiempo_respuesta / e.total).toFixed(2) : 0);
    } else {
        // Específico: x = tipos de examen, y = días promedio
        labels = entries.map(e => e.label);
        values = entries.map(e => e.tiempo_respuesta ?? 0);
    }

    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: palette(labels.length),
                borderRadius: 4,
                borderSkipped: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                datalabels: {
                    anchor: 'end',
                    align: 'end',
                    color: '#6b7280',
                    font: { size: 10, weight: 'bold' },
                    formatter: (v) => v > 0 ? v : null,
                },
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 30 } },
                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 } } },
            },
        },
    });
}

// ── Draw functions ────────────────────────────────────────────────────────
function drawRadiologoChart() {
    instRadiologo = makeBarChart(cvRadiologo.value, props.radiologoChartData?.[selRadiologo.value], instRadiologo);
}
function drawClinicaChart() {
    instClinica = makeBarChart(cvClinica.value, props.clinicaChartData?.[selClinica.value], instClinica);
}
function drawRedChart() {
    instRed = makeBarChart(cvRed.value, props.redChartData?.[selRed.value], instRed);
}
function drawPieChart() {
    instPie = makePieChart(cvPie.value, instPie);
}
function drawTiempoChart() {
    instTiempo = makeTiempoChart(cvTiempo.value, selTiempo.value, instTiempo);
}
function drawRespuestasChart() {
    instRespuestas = makeBarChart(cvRespuestas.value, props.radiologoRespuestasChartData?.[selRespuestas.value], instRespuestas);
}

onMounted(() => {
    drawRadiologoChart();
    drawClinicaChart();
    drawRedChart();
    drawPieChart();
    drawTiempoChart();
    drawRespuestasChart();
});

onBeforeUnmount(() => {
    [instRadiologo, instClinica, instRed, instPie, instTiempo, instRespuestas].forEach(c => c?.destroy());
});
</script>
