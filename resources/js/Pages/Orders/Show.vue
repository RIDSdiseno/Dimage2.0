<template>
    <AppLayout title="Ver Orden">

        <!-- Image lightbox -->
        <Teleport to="body">
            <div v-if="lightbox.open" @click.self="lightbox.open = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
                <div class="relative max-w-5xl max-h-full w-full">
                    <button @click="lightbox.open = false"
                        class="absolute -top-10 right-0 text-white hover:text-gray-300 text-2xl font-bold">
                        <i class="pi pi-times" />
                    </button>
                    <img :src="lightbox.src" :alt="lightbox.name"
                        class="max-h-[85vh] max-w-full mx-auto rounded-lg shadow-2xl object-contain" />
                    <p class="text-center text-white/70 text-xs mt-2">{{ lightbox.name }}</p>
                </div>
            </div>
        </Teleport>

        <div class="p-6 max-w-5xl mx-auto">

            <!-- Header -->
            <div class="flex flex-wrap items-center gap-3 mb-5">
                <Link :href="route('ordenes.index')">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <div class="flex-1 min-w-0">
                    <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <i class="pi pi-file-edit" style="color:#3452ff" />
                        Orden #{{ order.id }}
                    </h1>
                    <span class="text-xs text-gray-400">Creada el {{ order.created_at }}</span>
                </div>
                <!-- Badges -->
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
                        :class="estadoBadgeClass">
                        <i :class="estadoBadgeIcon" class="text-xs" />
                        {{ order.estado.label }}
                    </span>
                    <span v-if="order.prioridad === 'Urgente'"
                        class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                        <i class="pi pi-exclamation-circle text-xs" /> URGENTE
                    </span>
                </div>
                <!-- Actions -->
                <div class="flex items-center gap-2">
                    <Link v-if="order.estadoradiologo != 1 && !esRadiologo" :href="route('ordenes.edit', order.id)">
                        <Button label="Editar" icon="pi pi-pencil" size="small" severity="secondary" />
                    </Link>
                    <a :href="route('ordenes.zip', order.id)">
                        <Button label="ZIP" icon="pi pi-download" size="small" severity="secondary" />
                    </a>
                    <a :href="route('ordenes.pdf', order.id)" target="_blank" v-if="order.estadoradiologo == 1">
                        <Button label="PDF" icon="pi pi-file-pdf" size="small" severity="danger" />
                    </a>
                    <Link v-if="puedeResponder" :href="route('ordenes.responder', order.id)">
                        <Button label="Responder" icon="pi pi-send" size="small"
                            style="background-color:#3452ff;border-color:#3452ff;" />
                    </Link>
                </div>
            </div>

            <!-- Flash message -->
            <div v-if="$page.props.flash?.success"
                class="flex items-center gap-2 bg-green-50 border border-green-200 rounded-xl p-3 mb-5 text-sm text-green-700">
                <i class="pi pi-check-circle text-green-500" />
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error"
                class="flex items-center gap-2 bg-red-50 border border-red-200 rounded-xl p-3 mb-5 text-sm text-red-700">
                <i class="pi pi-times-circle text-red-500" />
                {{ $page.props.flash.error }}
            </div>

            <!-- Status banner -->
            <div v-if="order.estadoradiologo == 1 && order.estadoodontologo == 1"
                class="flex items-center gap-2 bg-green-50 border border-green-200 rounded-xl p-3 mb-5 text-sm font-medium text-green-700">
                <i class="pi pi-check-circle" /> Orden Respondida el {{ order.respondida }}
            </div>
            <div v-else-if="order.estadoradiologo == 0"
                class="flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-xl p-3 mb-5 text-sm font-medium text-amber-700">
                <i class="pi pi-clock" /> Orden Pendiente de Informe
            </div>
            <div v-else-if="order.estadoradiologo == 4"
                class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-xl p-3 mb-5 text-sm font-medium text-gray-600">
                <i class="pi pi-save" /> Orden Guardada (borrador)
            </div>

            <!-- 3-col info block -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">

                <!-- Paciente -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide mb-3 flex items-center gap-1.5"
                        style="color:#3452ff">
                        <i class="pi pi-user text-xs" /> Paciente
                    </p>
                    <div class="space-y-1.5 text-sm">
                        <p class="font-semibold text-gray-800 text-base">{{ paciente?.name }}</p>
                        <p class="text-gray-600"><span class="text-gray-400">{{ terms.id_label }}:</span> {{ paciente?.rut }}</p>
                        <p v-if="paciente?.edad !== null" class="text-gray-600">
                            <span class="text-gray-400">Edad:</span> {{ paciente?.edad }} años
                        </p>
                        <p v-if="paciente?.telefono" class="text-gray-600">
                            <span class="text-gray-400">Teléfono:</span> {{ paciente.telefono }}
                        </p>
                        <p v-if="paciente?.email" class="text-gray-600 truncate">
                            <span class="text-gray-400">Email:</span> {{ paciente.email }}
                        </p>
                        <p v-if="paciente?.dateofbirth" class="text-gray-600">
                            <span class="text-gray-400">F. Nacimiento:</span> {{ paciente.dateofbirth }}
                        </p>
                        <div class="mt-2 pt-2 border-t border-gray-100">
                            <p class="text-xs text-gray-400 mb-0.5">Diagnóstico clínico</p>
                            <p class="text-sm text-gray-700 font-medium">
                                {{ order.sin_diagnostico ? 'Sin diagnóstico' : order.diagnostico }}
                            </p>
                            <p v-if="order.observaciones" class="text-xs text-gray-500 mt-1">
                                {{ order.observaciones }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Examen / profesionales -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide mb-3 flex items-center gap-1.5"
                        style="color:#3452ff">
                        <i class="pi pi-building text-xs" /> Examen
                    </p>
                    <div class="space-y-1.5 text-sm">
                        <p class="text-gray-600">
                            <span class="text-gray-400">Clínica:</span>
                            <span class="font-medium text-gray-800 ml-1">{{ clinica }}</span>
                        </p>
                        <p class="text-gray-600">
                            <span class="text-gray-400">Odontólogo:</span>
                            <span class="ml-1">{{ odontologo?.nombre ?? '-' }}</span>
                        </p>
                        <p v-if="odontologo?.rut" class="text-gray-600">
                            <span class="text-gray-400">{{ terms.id_label }} Odontólogo:</span>
                            <span class="ml-1">{{ odontologo.rut }}</span>
                        </p>
                        <div v-if="radiologos?.length">
                            <span class="text-gray-400">Radiólogo(s):</span>
                            <div v-for="r in radiologos" :key="r.id" class="flex items-center gap-2 mt-0.5 ml-1">
                                <span class="text-gray-800">{{ r.name }}</span>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium"
                                    :class="r.respondida ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">
                                    {{ r.respondida ? 'Respondido' : 'Pendiente' }}
                                </span>
                            </div>
                        </div>
                        <p v-else class="text-gray-400 italic text-xs">Sin radiólogo asignado</p>
                        <p class="text-gray-600 mt-2 pt-2 border-t border-gray-100">
                            <span class="text-gray-400">Prioridad:</span>
                            <span class="ml-1 font-semibold"
                                :class="order.prioridad === 'Urgente' ? 'text-red-600' : 'text-gray-700'">
                                {{ order.prioridad }}
                            </span>
                        </p>
                        <p class="text-gray-600">
                            <span class="text-gray-400">Estado:</span>
                            <span class="ml-1 font-medium">{{ order.estado.label }}</span>
                        </p>
                    </div>
                </div>

                <!-- Fechas -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide mb-3 flex items-center gap-1.5"
                        style="color:#3452ff">
                        <i class="pi pi-calendar text-xs" /> Información
                    </p>
                    <div class="space-y-3 text-sm">
                        <div>
                            <p class="text-xs text-gray-400">Creada el</p>
                            <p class="font-medium text-gray-700">{{ order.created_at }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Enviada el</p>
                            <p class="font-medium text-gray-700">{{ order.enviada ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Respondida el</p>
                            <p class="font-medium" :class="order.respondida ? 'text-green-600' : 'text-gray-400 italic'">
                                {{ order.respondida ?? 'Pendiente' }}
                            </p>
                        </div>
                        <div v-if="order.tiempo_respuesta">
                            <p class="text-xs text-gray-400">Tiempo de respuesta</p>
                            <p class="font-medium text-blue-600 flex items-center gap-1">
                                <i class="pi pi-stopwatch text-xs" />
                                {{ order.tiempo_respuesta }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Correcciones -->
            <div v-if="correcciones?.length"
                class="bg-orange-50 border border-orange-200 rounded-xl p-5 mb-5">
                <p class="text-sm font-semibold text-orange-700 mb-3 flex items-center gap-2">
                    <i class="pi pi-exclamation-triangle" /> Solicitudes de Corrección
                </p>
                <div v-for="c in correcciones" :key="c.id"
                    class="bg-white border border-orange-100 rounded-lg p-3 mb-2 last:mb-0">
                    <p class="text-xs text-orange-500 mb-1">Fecha: <strong>{{ c.enviada }}</strong></p>
                    <p class="text-sm text-gray-700">{{ c.detalle }}</p>
                </div>
            </div>

            <!-- Exámenes -->
            <div class="space-y-4">
                <div v-for="examen in examenes" :key="examen.id"
                    class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">

                    <!-- Exam header -->
                    <div class="flex items-center justify-between px-5 py-3" style="background-color:#0b2a4a;">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-file-edit text-blue-300 text-sm" />
                            <span class="font-semibold text-white text-sm">{{ examLabel(examen.descripcion) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                                :class="examen.respuesta ? 'bg-green-500 text-white' : 'bg-gray-600 text-gray-200'">
                                <i :class="examen.respuesta ? 'pi pi-check' : 'pi pi-clock'" class="text-xs" />
                                {{ examen.respuesta ? 'Informado' : 'Pendiente' }}
                            </span>
                            <button v-if="order.estadoradiologo != 1"
                                @click="eliminarExamen(examen.id)"
                                class="text-red-300 hover:text-red-100 transition p-1 rounded"
                                title="Eliminar examen">
                                <i class="pi pi-trash text-xs" />
                            </button>
                        </div>
                    </div>

                    <div class="p-5 space-y-4">

                        <!-- Archivos de imagen -->
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-1">
                                <i class="pi pi-images text-xs" /> Archivos de imagen
                            </p>
                            <div v-if="examen.archivos?.length" class="flex flex-wrap gap-3">
                                <div v-for="f in examen.archivos" :key="f.id" class="relative group">
                                    <FileThumbnail :file="f" @lightbox="openLightbox" />
                                    <button v-if="order.estadoradiologo != 1 && !esRadiologo"
                                        @click="eliminarArchivo(f.id)"
                                        class="absolute top-1 right-1 hidden group-hover:flex items-center justify-center w-5 h-5 rounded-full bg-red-500 text-white shadow"
                                        title="Eliminar archivo">
                                        <i class="pi pi-times" style="font-size:9px" />
                                    </button>
                                </div>
                            </div>
                            <p v-else class="text-xs text-gray-400 italic">Sin archivos de imagen.</p>
                        </div>

                        <!-- Informe del radiólogo -->
                        <div v-if="examen.respuesta" class="border-t border-gray-100 pt-4">
                            <p class="text-xs font-semibold text-green-700 uppercase tracking-wide mb-2 flex items-center gap-1">
                                <i class="pi pi-check-circle text-xs" /> Informe del Radiólogo
                            </p>
                            <div v-if="examen.respuesta.texto"
                                class="bg-green-50 border border-green-100 rounded-lg p-4 text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">
                                {{ examen.respuesta.texto }}
                            </div>
                            <p v-else-if="examen.respuesta.solo_adjunto"
                                class="text-xs text-gray-400 italic">Informe adjunto como archivo.</p>

                            <!-- Archivos del informe -->
                            <div v-if="examen.archivos_informe?.length" class="mt-4">
                                <p class="text-xs text-gray-500 mb-2">Archivos adjuntos del informe:</p>
                                <div class="flex flex-wrap gap-3">
                                    <FileThumbnail
                                        v-for="f in examen.archivos_informe" :key="f.id"
                                        :file="f"
                                        @lightbox="openLightbox" />
                                </div>
                            </div>

                            <a v-if="examen.url_texto" :href="examen.url_texto" target="_blank"
                                class="inline-flex items-center gap-1 text-xs text-blue-600 mt-2 hover:underline">
                                <i class="pi pi-external-link text-xs" /> Ver informe externo
                            </a>
                        </div>
                        <div v-else class="border-t border-gray-100 pt-4">
                            <p class="text-xs text-gray-400 italic flex items-center gap-1">
                                <i class="pi pi-clock text-xs" /> Sin informe aún
                            </p>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Footer actions -->
            <div class="flex justify-between items-center mt-6">
                <div>
                    <Button v-if="esAdmin" label="Eliminar Orden" icon="pi pi-trash"
                        severity="danger" outlined @click="eliminarOrden" />
                </div>
                <div class="flex gap-3">
                    <Link :href="route('ordenes.index')">
                        <Button label="Volver al listado" severity="secondary" icon="pi pi-list" />
                    </Link>
                    <a :href="route('ordenes.zip', order.id)">
                        <Button label="Descargar ZIP" icon="pi pi-download" severity="secondary" />
                    </a>
                    <a :href="route('ordenes.pdf', order.id)" target="_blank" v-if="order.estadoradiologo == 1">
                        <Button label="Generar PDF" icon="pi pi-file-pdf" severity="danger" />
                    </a>
                    <Link v-if="puedeResponder" :href="route('ordenes.responder', order.id)">
                        <Button label="Responder Orden" icon="pi pi-send"
                            style="background-color:#3452ff;border-color:#3452ff;" />
                    </Link>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import { useTerms } from '@/composables/useTerms.js';

const { terms, examLabel } = useTerms();
import FileThumbnail from '@/Components/FileThumbnail.vue';

const props = defineProps({
    order:          Object,
    paciente:       Object,
    clinica:        String,
    odontologo:     Object,
    radiologos:     Array,
    correcciones:   Array,
    examenes:       Array,
    puedeResponder: Boolean,
    esAdmin:        Boolean,
    esRadiologo:    Boolean,
});

const lightbox = reactive({ open: false, src: '', name: '' });

function eliminarOrden() {
    if (!confirm(`¿Confirma ELIMINAR la orden #${props.order.id}? Esta acción no se puede deshacer.`)) return;
    router.delete(route('ordenes.destroy', props.order.id));
}

function eliminarExamen(examenId) {
    if (!confirm('¿Confirma eliminar este examen y todos sus archivos?')) return;
    router.delete(route('ordenes.examenes.destroy', { order: props.order.id, examination: examenId }), {
        preserveScroll: true,
    });
}

function eliminarArchivo(id) {
    if (!confirm('¿Eliminar este archivo?')) return;
    router.delete(route('archivos.destroy', id), { preserveScroll: true });
}

function openLightbox(file) {
    lightbox.src  = file.url;
    lightbox.name = file.name || 'Imagen';
    lightbox.open = true;
}

const estadoBadgeClass = computed(() => {
    const map = {
        success:   'bg-green-100 text-green-700',
        warn:      'bg-amber-100 text-amber-700',
        danger:    'bg-red-100 text-red-700',
        secondary: 'bg-gray-100 text-gray-600',
    };
    return map[props.order.estado.color] ?? map.secondary;
});

const estadoBadgeIcon = computed(() => {
    const map = {
        success:   'pi pi-check-circle',
        warn:      'pi pi-clock',
        danger:    'pi pi-times-circle',
        secondary: 'pi pi-save',
    };
    return map[props.order.estado.color] ?? 'pi pi-info-circle';
});
</script>
