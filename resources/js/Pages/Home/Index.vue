<template>
    <AppLayout title="Inicio">
        <div class="p-6 max-w-5xl mx-auto">

            <!-- Encabezado de bienvenida -->
            <div class="mb-8">
                <p class="text-sm text-gray-400">{{ greeting }}, </p>
                <h1 class="text-3xl font-bold text-gray-800">{{ userName }}</h1>
                <p class="text-sm text-gray-400 mt-1">{{ fechaHoy }}</p>
            </div>

            <!-- ── Alertas principales ──────────────────────────────────────── -->
            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Estado de órdenes</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-10">

                <!-- Pendientes -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Pendientes</span>
                        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                            <i class="pi pi-clock text-xl text-blue-500" />
                        </div>
                    </div>
                    <p class="text-5xl font-black text-gray-800 leading-none">{{ alertas?.pendientes ?? 0 }}</p>
                    <p class="text-xs text-gray-400">Órdenes enviadas sin respuesta</p>
                </div>

                <!-- Por Vencer -->
                <div class="rounded-2xl border shadow-sm p-6 flex flex-col gap-3 transition-all"
                    :class="hasPorVencer
                        ? 'bg-orange-50 border-orange-200 cursor-pointer hover:shadow-md hover:border-orange-300'
                        : 'bg-white border-gray-100'"
                    @click="hasPorVencer && (showPorVencer = true)">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide"
                            :class="hasPorVencer ? 'text-orange-500' : 'text-gray-400'">Por Vencer</span>
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                            :class="hasPorVencer ? 'bg-orange-100' : 'bg-gray-50'">
                            <i class="pi pi-exclamation-triangle text-xl"
                                :class="hasPorVencer ? 'text-orange-500' : 'text-gray-400'" />
                        </div>
                    </div>
                    <p class="text-5xl font-black leading-none"
                        :class="hasPorVencer ? 'text-orange-500' : 'text-gray-800'">
                        {{ alertas?.porVencer?.length ?? 0 }}
                    </p>
                    <p class="text-xs" :class="hasPorVencer ? 'text-orange-400' : 'text-gray-400'">
                        {{ hasPorVencer ? 'Vencen hoy o mañana · click para ver' : 'Sin órdenes próximas a vencer' }}
                    </p>
                </div>

                <!-- Vencidas -->
                <div class="rounded-2xl border shadow-sm p-6 flex flex-col gap-3 transition-all"
                    :class="hasVencidas
                        ? 'bg-red-50 border-red-200 cursor-pointer hover:shadow-md hover:border-red-300'
                        : 'bg-white border-gray-100'"
                    @click="hasVencidas && (showVencidas = true)">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide"
                            :class="hasVencidas ? 'text-red-500' : 'text-gray-400'">Vencidas</span>
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                            :class="hasVencidas ? 'bg-red-100' : 'bg-gray-50'">
                            <i class="pi pi-times-circle text-xl"
                                :class="hasVencidas ? 'text-red-500' : 'text-gray-400'" />
                        </div>
                    </div>
                    <p class="text-5xl font-black leading-none"
                        :class="hasVencidas ? 'text-red-500' : 'text-gray-800'">
                        {{ alertas?.vencidas?.length ?? 0 }}
                    </p>
                    <p class="text-xs" :class="hasVencidas ? 'text-red-400' : 'text-gray-400'">
                        {{ hasVencidas ? 'Plazo superado · click para ver' : 'Sin órdenes vencidas' }}
                    </p>
                </div>

            </div>

            <!-- ── Actividad del día ────────────────────────────────────────── -->
            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Actividad de hoy</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-10">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
                    <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0">
                        <i class="pi pi-file-plus text-lg text-indigo-500" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-800">{{ creadosHoy }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Órdenes creadas hoy</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
                        <i class="pi pi-check-circle text-lg text-emerald-500" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-800">{{ respondidosHoy }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Respondidas hoy</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
                    <div class="w-10 h-10 rounded-lg bg-slate-50 flex items-center justify-center shrink-0">
                        <i class="pi pi-folder-open text-lg text-slate-500" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-800">{{ totalActivos }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Órdenes activas totales</p>
                    </div>
                </div>
            </div>

            <!-- ── Accesos rápidos ─────────────────────────────────────────── -->
            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Accesos rápidos</h2>

            <div class="flex flex-wrap gap-3">
                <a href="/ordenes"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium bg-white border border-gray-200 text-gray-700 hover:border-blue-300 hover:text-blue-600 transition shadow-sm">
                    <i class="pi pi-list" /> Ver órdenes
                </a>
                <a v-if="!isRadiologo" href="/ordenes/crear"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-white transition shadow-sm"
                    style="background-color:#3452ff;">
                    <i class="pi pi-plus" /> Crear orden
                </a>
                <a href="/dashboard"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium bg-white border border-gray-200 text-gray-700 hover:border-indigo-300 hover:text-indigo-600 transition shadow-sm">
                    <i class="pi pi-chart-bar" /> Ver dashboard
                </a>
                <a v-if="!isRadiologo" href="/pacientes"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium bg-white border border-gray-200 text-gray-700 hover:border-gray-400 transition shadow-sm">
                    <i class="pi pi-users" /> Pacientes
                </a>
            </div>

        </div>

        <!-- ── Modal Vencidas ──────────────────────────────────────────────── -->
        <Dialog v-model:visible="showVencidas" modal
            :header="`Órdenes Vencidas (${alertas?.vencidas?.length ?? 0})`"
            :style="{ width: '860px', maxWidth: '96vw' }" :draggable="false">
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <th class="px-3 py-2 text-left">N° Orden</th>
                            <th class="px-3 py-2 text-left">Paciente</th>
                            <th class="px-3 py-2 text-left">RUT</th>
                            <th class="px-3 py-2 text-left">Clínica</th>
                            <th class="px-3 py-2 text-left">Enviada</th>
                            <th class="px-3 py-2 text-left">Venció</th>
                            <th class="px-3 py-2 text-center">Días vencida</th>
                            <th class="px-3 py-2 text-center">Prioridad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="o in alertas?.vencidas" :key="o.id"
                            class="border-t border-gray-100 hover:bg-red-50 cursor-pointer"
                            @click="router.visit(route('ordenes.show', o.id))">
                            <td class="px-3 py-2 font-semibold text-red-600">#{{ o.id }}</td>
                            <td class="px-3 py-2 font-medium">{{ o.paciente }}</td>
                            <td class="px-3 py-2 text-gray-500 text-xs">{{ o.rut }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ o.clinica }}</td>
                            <td class="px-3 py-2 text-gray-500 text-xs">{{ o.enviada }}</td>
                            <td class="px-3 py-2 text-red-600 font-medium">{{ o.vence }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-block bg-red-100 text-red-700 text-xs font-bold px-2 py-0.5 rounded-full">
                                    {{ o.dias_vencida }}d
                                </span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-block text-xs font-medium px-2 py-0.5 rounded-full"
                                    :class="o.prioridad === 'Urgente' ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600'">
                                    {{ o.prioridad }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Dialog>

        <!-- ── Modal Por Vencer ────────────────────────────────────────────── -->
        <Dialog v-model:visible="showPorVencer" modal
            :header="`Por Vencer (${alertas?.porVencer?.length ?? 0})`"
            :style="{ width: '820px', maxWidth: '96vw' }" :draggable="false">
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <th class="px-3 py-2 text-left">N° Orden</th>
                            <th class="px-3 py-2 text-left">Paciente</th>
                            <th class="px-3 py-2 text-left">RUT</th>
                            <th class="px-3 py-2 text-left">Clínica</th>
                            <th class="px-3 py-2 text-left">Enviada</th>
                            <th class="px-3 py-2 text-left">Vence</th>
                            <th class="px-3 py-2 text-center">Prioridad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="o in alertas?.porVencer" :key="o.id"
                            class="border-t border-gray-100 hover:bg-orange-50 cursor-pointer"
                            @click="router.visit(route('ordenes.show', o.id))">
                            <td class="px-3 py-2 font-semibold text-orange-600">#{{ o.id }}</td>
                            <td class="px-3 py-2 font-medium">{{ o.paciente }}</td>
                            <td class="px-3 py-2 text-gray-500 text-xs">{{ o.rut }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ o.clinica }}</td>
                            <td class="px-3 py-2 text-gray-500 text-xs">{{ o.enviada }}</td>
                            <td class="px-3 py-2 text-orange-600 font-medium">{{ o.vence }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-block text-xs font-medium px-2 py-0.5 rounded-full"
                                    :class="o.prioridad === 'Urgente' ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600'">
                                    {{ o.prioridad }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Dialog>

    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from 'primevue/dialog';

const props = defineProps({
    alertas:        Object,
    creadosHoy:     Number,
    respondidosHoy: Number,
    totalActivos:   Number,
    userName:       String,
});

const page        = usePage();
const showVencidas  = ref(false);
const showPorVencer = ref(false);

const hasVencidas  = computed(() => (props.alertas?.vencidas?.length ?? 0) > 0);
const hasPorVencer = computed(() => (props.alertas?.porVencer?.length ?? 0) > 0);

const isRadiologo = computed(() => {
    const user = page.props.auth?.user;
    return user?.roles?.includes('radiologo') || user?.type_id === 5;
});

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Buenos días';
    if (h < 20) return 'Buenas tardes';
    return 'Buenas noches';
});

const fechaHoy = computed(() =>
    new Date().toLocaleDateString('es-CL', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
);
</script>
