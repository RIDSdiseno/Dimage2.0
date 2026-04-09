<template>
    <AppLayout title="Descargar Excel">
        <div class="p-6 max-w-xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="text-gray-400 text-xs">Administración</span>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-xs text-gray-600">Descargar Excel</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">Descargar Excel</h1>
                    <p class="text-sm text-gray-400 mt-0.5">Reportes Radiográficos</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow p-6">

                <div class="space-y-4">

                    <!-- Tipo de reporte -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Tipo de Reporte</label>
                        <div class="grid grid-cols-1 gap-2">
                            <label v-for="opt in reportTypes" :key="opt.value"
                                class="flex items-start gap-3 border rounded-lg p-3 cursor-pointer transition"
                                :class="form.tipo_reporte === opt.value
                                    ? 'border-blue-400 bg-blue-50'
                                    : 'border-gray-200 hover:border-gray-300'">
                                <input type="radio" v-model="form.tipo_reporte" :value="opt.value" class="mt-0.5" />
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ opt.label }}</p>
                                    <p class="text-xs text-gray-400">{{ opt.description }}</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Rango de fechas (oculto para uso de espacio) -->
                    <template v-if="form.tipo_reporte !== 'espacio'">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Desde *</label>
                                <InputText v-model="form.desde" type="date" class="w-full"
                                    :class="{ 'p-invalid': errors.desde }" />
                                <small class="text-red-500">{{ errors.desde }}</small>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Hasta *</label>
                                <InputText v-model="form.hasta" type="date" class="w-full"
                                    :class="{ 'p-invalid': errors.hasta }" />
                                <small class="text-red-500">{{ errors.hasta }}</small>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Filtrar por fecha de</label>
                            <div class="flex gap-4 mt-1">
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" v-model="form.tipo_fecha" value="creacion" /> Creación
                                </label>
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" v-model="form.tipo_fecha" value="envio" /> Envío
                                </label>
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" v-model="form.tipo_fecha" value="respuesta" /> Respuesta
                                </label>
                            </div>
                        </div>
                    </template>

                    <div class="bg-blue-50 rounded-lg p-3">
                        <p class="text-xs text-blue-600">
                            <i class="pi pi-info-circle mr-1" />
                            El archivo se descargará en formato Excel (.xlsx) con formato de tabla.
                        </p>
                    </div>

                </div>

                <div class="flex justify-end mt-6">
                    <Button label="Descargar Excel" icon="pi pi-download" @click="descargar"
                        :loading="descargando"
                        style="background-color:#3452ff;border-color:#3452ff;" />
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

const reportTypes = [
    {
        value: 'ordenes',
        label: 'Órdenes Radiográficas',
        description: 'Listado completo de órdenes con paciente, radiólogo, estado y fechas.',
    },
    {
        value: 'por-examen',
        label: 'Por Tipo de Examen',
        description: 'Cantidad de órdenes e informes agrupados por tipo de examen radiográfico.',
    },
    {
        value: 'por-radiologo',
        label: 'Por Radiólogo',
        description: 'Resumen de órdenes asignadas a cada radiólogo con desglose de estados.',
    },
    {
        value: 'espacio',
        label: 'Uso de Espacio',
        description: 'Cantidad de archivos y espacio de almacenamiento consumido por clínica.',
    },
];

const routeMap = {
    'ordenes':      'admin.excel.download',
    'por-examen':   'admin.excel.por-examen',
    'por-radiologo':'admin.excel.por-radiologo',
    'espacio':      'admin.excel.espacio',
};

const filenameMap = {
    'ordenes':      (f) => `ordenes_${f.desde}_${f.hasta}.xlsx`,
    'por-examen':   (f) => `por_tipo_examen_${f.desde}_${f.hasta}.xlsx`,
    'por-radiologo':(f) => `por_radiologo_${f.desde}_${f.hasta}.xlsx`,
    'espacio':      ()  => `uso_espacio.xlsx`,
};

const form = reactive({
    tipo_reporte: 'ordenes',
    desde:        '',
    hasta:        '',
    tipo_fecha:   'creacion',
});

const errors      = reactive({ desde: '', hasta: '' });
const descargando = ref(false);

const descargar = async () => {
    errors.desde = '';
    errors.hasta = '';

    if (form.tipo_reporte !== 'espacio') {
        if (!form.desde) { errors.desde = 'Ingrese fecha desde.'; return; }
        if (!form.hasta) { errors.hasta = 'Ingrese fecha hasta.'; return; }
        if (form.hasta < form.desde) { errors.hasta = 'La fecha hasta debe ser igual o posterior.'; return; }
    }

    descargando.value = true;

    try {
        const params = new URLSearchParams({
            _token:     document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        });

        if (form.tipo_reporte !== 'espacio') {
            params.append('desde',      form.desde);
            params.append('hasta',      form.hasta);
            params.append('tipo_fecha', form.tipo_fecha);
        }

        const res = await fetch(route(routeMap[form.tipo_reporte]), {
            method: 'POST',
            headers: {
                'Content-Type':  'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: params.toString(),
        });

        if (!res.ok) { alert('Error al generar el archivo.'); return; }

        const blob = await res.blob();
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = filenameMap[form.tipo_reporte](form);
        a.click();
        URL.revokeObjectURL(url);
    } finally {
        descargando.value = false;
    }
};
</script>
