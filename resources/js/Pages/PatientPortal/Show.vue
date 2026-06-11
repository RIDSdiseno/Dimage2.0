<template>
    <div style="min-height:100vh; background:#f1f5f9;">

        <!-- Header -->
        <header style="background:#0f172a; padding:0 24px; height:56px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:10;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:32px; height:32px; border-radius:8px; background:#3452ff; display:flex; align-items:center; justify-content:center;">
                    <i class="pi pi-image" style="color:#fff; font-size:16px;" />
                </div>
                <span style="font-size:16px; font-weight:700; color:#fff;">Dimage</span>
                <span style="font-size:12px; color:#475569; margin-left:4px;">· Portal de Pacientes</span>
            </div>
            <form method="POST" :action="route('paciente.logout')" @submit.prevent="logout">
                <input type="hidden" name="_token" :value="csrf" />
                <button type="submit"
                    style="display:flex; align-items:center; gap:6px; padding:6px 14px; border-radius:8px; background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.12); color:#cbd5e1; font-size:13px; cursor:pointer;">
                    <i class="pi pi-sign-out" style="font-size:13px;" /> Cerrar sesión
                </button>
            </form>
        </header>

        <main style="max-width:860px; margin:0 auto; padding:24px 16px 48px;">

            <!-- Encabezado orden -->
            <div style="background:#fff; border-radius:14px; padding:20px 24px; margin-bottom:16px; box-shadow:0 1px 3px rgba(0,0,0,0.08); display:flex; flex-wrap:wrap; gap:16px; align-items:flex-start; justify-content:space-between;">
                <div>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <h1 style="font-size:20px; font-weight:700; color:#0f172a; margin:0;">
                            Orden N° {{ order.id }}
                        </h1>
                        <span :style="badgeStyle(order.estado.color)">{{ order.estado.label }}</span>
                        <span v-if="order.prioridad && order.prioridad !== 'Normal'"
                            style="padding:2px 10px; border-radius:999px; background:#fef3c7; color:#92400e; font-size:11px; font-weight:600;">
                            {{ order.prioridad }}
                        </span>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap:16px; margin-top:10px;">
                        <span style="font-size:13px; color:#64748b; display:flex; align-items:center; gap:5px;">
                            <i class="pi pi-building" style="font-size:12px;" /> {{ order.clinica }}
                        </span>
                        <span v-if="odontologo" style="font-size:13px; color:#64748b; display:flex; align-items:center; gap:5px;">
                            <i class="pi pi-user" style="font-size:12px;" /> {{ odontologo }}
                        </span>
                        <span v-if="order.created_at" style="font-size:13px; color:#64748b; display:flex; align-items:center; gap:5px;">
                            <i class="pi pi-calendar" style="font-size:12px;" /> Creada: {{ order.created_at }}
                        </span>
                        <span v-if="order.respondida" style="font-size:13px; color:#15803d; display:flex; align-items:center; gap:5px;">
                            <i class="pi pi-check-circle" style="font-size:12px;" /> Informada: {{ order.respondida }}
                        </span>
                    </div>
                </div>

                <!-- Botón PDF -->
                <a v-if="pdfUrl" :href="pdfUrl" target="_blank"
                    style="display:inline-flex; align-items:center; gap:8px; padding:10px 18px; border-radius:10px; background:#e3342f; color:#fff; text-decoration:none; font-size:13px; font-weight:600; white-space:nowrap; flex-shrink:0;">
                    <i class="pi pi-file-pdf" style="font-size:14px;" />
                    Descargar informe PDF
                </a>
            </div>

            <!-- Info paciente -->
            <div v-if="paciente"
                style="background:#fff; border-radius:14px; padding:16px 24px; margin-bottom:16px; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                <p style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.08em; margin:0 0 10px;">Paciente</p>
                <div style="display:flex; flex-wrap:wrap; gap:16px;">
                    <span style="font-size:15px; font-weight:600; color:#0f172a;">{{ paciente.name }}</span>
                    <span style="font-size:13px; color:#64748b;">RUT: {{ paciente.rut }}</span>
                    <span v-if="paciente.dateofbirth" style="font-size:13px; color:#64748b;">Nacimiento: {{ paciente.dateofbirth }}</span>
                </div>
            </div>

            <!-- Diagnóstico / Observaciones -->
            <div v-if="order.diagnostico || order.observaciones"
                style="background:#fff; border-radius:14px; padding:16px 24px; margin-bottom:16px; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                <p style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.08em; margin:0 0 10px;">Antecedentes clínicos</p>
                <p v-if="order.diagnostico" style="font-size:14px; color:#1e293b; margin:0 0 6px;">
                    <strong>Diagnóstico:</strong> {{ order.diagnostico }}
                </p>
                <p v-if="order.observaciones" style="font-size:14px; color:#1e293b; margin:0;">
                    <strong>Observaciones:</strong> {{ order.observaciones }}
                </p>
            </div>

            <!-- Mensaje si no está informada -->
            <div v-if="order.estadoradiologo !== 1"
                style="background:#fefce8; border:1px solid #fde047; border-radius:14px; padding:16px 24px; margin-bottom:16px; display:flex; align-items:center; gap:12px;">
                <i class="pi pi-clock" style="font-size:20px; color:#ca8a04; flex-shrink:0;" />
                <div>
                    <p style="font-size:14px; font-weight:600; color:#92400e; margin:0 0 2px;">Orden en proceso</p>
                    <p style="font-size:13px; color:#a16207; margin:0;">El informe del radiólogo estará disponible una vez que la orden sea informada.</p>
                </div>
            </div>

            <!-- Exámenes -->
            <div v-for="ex in examenes" :key="ex.id"
                style="background:#fff; border-radius:14px; margin-bottom:16px; box-shadow:0 1px 3px rgba(0,0,0,0.08); overflow:hidden;">

                <!-- Cabecera examen -->
                <div style="background:#1e3a5f; padding:12px 20px; display:flex; align-items:center; gap:8px;">
                    <i class="pi pi-images" style="color:#93c5fd; font-size:14px;" />
                    <h2 style="font-size:14px; font-weight:600; color:#fff; margin:0;">{{ ex.descripcion }}</h2>
                    <span v-if="ex.piezas" style="font-size:11px; color:#93c5fd; margin-left:4px;">· Piezas: {{ ex.piezas }}</span>
                </div>

                <div style="padding:20px;">

                    <!-- Archivos -->
                    <div v-if="ex.archivos?.length" style="margin-bottom:20px;">
                        <p style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.08em; margin:0 0 12px;">Imágenes</p>
                        <div style="display:flex; flex-wrap:wrap; gap:12px;">
                            <div v-for="f in ex.archivos" :key="f.id"
                                style="width:130px; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#f8fafc;">
                                <!-- Thumbnail imagen -->
                                <div style="width:130px; height:110px; overflow:hidden; background:#f1f5f9; display:flex; align-items:center; justify-content:center; position:relative;">
                                    <img v-if="isImage(f.extension)"
                                        :src="f.url"
                                        :alt="f.name"
                                        loading="lazy"
                                        style="width:100%; height:100%; object-fit:cover;"
                                        @error="e => e.target.style.display='none'" />
                                    <i v-else-if="f.extension === 'pdf'" class="pi pi-file-pdf"
                                        style="font-size:36px; color:#e3342f;" />
                                    <i v-else class="pi pi-file"
                                        style="font-size:36px; color:#94a3b8;" />
                                </div>
                                <!-- Nombre + botones -->
                                <div style="padding:8px;">
                                    <p style="font-size:10px; color:#64748b; margin:0 0 6px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" :title="f.name">
                                        {{ f.name || 'Archivo' }}
                                    </p>
                                    <div style="display:flex; gap:4px;">
                                        <a v-if="f.url" :href="f.url" target="_blank"
                                            style="flex:1; display:flex; align-items:center; justify-content:center; gap:4px; padding:5px 0; border-radius:6px; background:#eff6ff; color:#3452ff; text-decoration:none; font-size:11px; font-weight:600;">
                                            <i class="pi pi-eye" style="font-size:10px;" /> Ver
                                        </a>
                                        <a v-if="f.download_url" :href="f.download_url"
                                            style="flex:1; display:flex; align-items:center; justify-content:center; gap:4px; padding:5px 0; border-radius:6px; background:#f0fdf4; color:#15803d; text-decoration:none; font-size:11px; font-weight:600;">
                                            <i class="pi pi-download" style="font-size:10px;" /> Descargar
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Informe (solo si informada) -->
                    <template v-if="ex.respuesta">

                        <!-- Solo adjunto -->
                        <div v-if="ex.respuesta.solo_adjunto && !ex.respuesta.campo_1 && !ex.respuesta.campo_2"
                            style="font-size:13px; color:#94a3b8; font-style:italic;">
                            Informe adjunto como archivo.
                        </div>

                        <!-- No-Panorámica: legacy informe libre -->
                        <template v-else-if="ex.kind_id !== 15 && (ex.respuesta.informe_examen || ex.respuesta.informe_libre || ex.respuesta.informe_impresion)">
                            <div style="border:1px solid #e2e8f0; border-radius:10px; overflow:hidden;">
                                <div style="background:#374151; padding:10px 16px;">
                                    <p style="font-size:13px; font-weight:600; color:#fff; margin:0;">Informe</p>
                                </div>
                                <div>
                                    <div v-if="ex.respuesta.informe_examen" style="padding:12px 16px; border-bottom:1px solid #f1f5f9;">
                                        <p style="font-size:12px; font-weight:600; color:#64748b; margin:0 0 4px;">Examen:</p>
                                        <p style="font-size:14px; color:#1e293b; margin:0; white-space:pre-wrap;">{{ ex.respuesta.informe_examen }}</p>
                                    </div>
                                    <div v-if="ex.respuesta.informe_libre" style="padding:12px 16px; border-bottom:1px solid #f1f5f9;">
                                        <p style="font-size:12px; font-weight:600; color:#64748b; margin:0 0 4px;">Informe:</p>
                                        <p style="font-size:14px; color:#1e293b; margin:0; white-space:pre-wrap;">{{ ex.respuesta.informe_libre }}</p>
                                    </div>
                                    <div v-if="ex.respuesta.informe_impresion" style="padding:12px 16px;">
                                        <p style="font-size:12px; font-weight:600; color:#64748b; margin:0 0 4px;">Impresión Diagnóstica:</p>
                                        <p style="font-size:14px; color:#1e293b; margin:0; white-space:pre-wrap;">{{ ex.respuesta.informe_impresion }}</p>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- No-Panorámica: estándar campo_1/2/3 -->
                        <template v-else-if="ex.kind_id !== 15 && (ex.respuesta.campo_1 || ex.respuesta.campo_2 || ex.respuesta.campo_3)">
                            <div style="border:1px solid #e2e8f0; border-radius:10px; overflow:hidden;">
                                <div style="background:#374151; padding:10px 16px;">
                                    <p style="font-size:13px; font-weight:600; color:#fff; margin:0;">Informe</p>
                                </div>
                                <div>
                                    <div v-if="ex.respuesta.campo_1" style="padding:12px 16px; border-bottom:1px solid #f1f5f9;">
                                        <p style="font-size:12px; font-weight:600; color:#64748b; margin:0 0 4px;">Examen:</p>
                                        <p style="font-size:14px; color:#1e293b; margin:0; white-space:pre-wrap;">{{ ex.respuesta.campo_1 }}</p>
                                    </div>
                                    <div v-if="ex.respuesta.campo_2" style="padding:12px 16px; border-bottom:1px solid #f1f5f9;">
                                        <p style="font-size:12px; font-weight:600; color:#64748b; margin:0 0 4px;">Informe:</p>
                                        <p style="font-size:14px; color:#1e293b; margin:0; white-space:pre-wrap;">{{ ex.respuesta.campo_2 }}</p>
                                    </div>
                                    <div v-if="ex.respuesta.campo_3" style="padding:12px 16px;">
                                        <p style="font-size:12px; font-weight:600; color:#64748b; margin:0 0 4px;">Impresión Diagnóstica:</p>
                                        <p style="font-size:14px; color:#1e293b; margin:0; white-space:pre-wrap;">{{ ex.respuesta.campo_3 }}</p>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Panorámica (kind_id=15) -->
                        <template v-if="ex.kind_id === 15">
                            <!-- MAXILAR -->
                            <template v-if="ex.respuesta.campo_2 || ex.respuesta.campo_3 || ex.respuesta.campo_5 || ex.respuesta.campo_8 ||
                                getDientes(ex.respuesta).some(d => [11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28,51,52,53,54,55,61,62,63,64,65].includes(d.n))">
                                <div style="border:1px solid #bfdbfe; border-radius:10px; overflow:hidden; margin-bottom:12px;">
                                    <div style="background:#1d4ed8; padding:10px 16px;">
                                        <p style="font-size:13px; font-weight:600; color:#fff; margin:0;">Maxilar</p>
                                    </div>
                                    <div>
                                        <div v-if="ex.respuesta.campo_2" style="padding:10px 16px; border-bottom:1px solid #f1f5f9;">
                                            <p style="font-size:12px; font-weight:600; color:#64748b; margin:0 0 3px;">Nivel Óseo Marginal:</p>
                                            <p style="font-size:14px; color:#1e293b; margin:0; white-space:pre-wrap;">{{ ex.respuesta.campo_2 }}</p>
                                        </div>
                                        <div v-if="ex.respuesta.campo_3" style="padding:10px 16px; border-bottom:1px solid #f1f5f9;">
                                            <p style="font-size:12px; font-weight:600; color:#64748b; margin:0 0 3px;">Cálculo dentario marginal:</p>
                                            <p style="font-size:14px; color:#1e293b; margin:0; white-space:pre-wrap;">{{ ex.respuesta.campo_3 }}</p>
                                        </div>
                                        <div v-if="ex.respuesta.campo_5" style="padding:10px 16px; border-bottom:1px solid #f1f5f9;">
                                            <p style="font-size:12px; font-weight:600; color:#64748b; margin:0 0 3px;">Dientes Ausentes:</p>
                                            <p style="font-size:14px; color:#1e293b; margin:0; white-space:pre-wrap;">{{ ex.respuesta.campo_5 }}</p>
                                        </div>
                                        <div v-if="ex.respuesta.campo_8" style="padding:10px 16px; border-bottom:1px solid #f1f5f9;">
                                            <p style="font-size:12px; font-weight:600; color:#64748b; margin:0 0 3px;">Observaciones:</p>
                                            <p style="font-size:14px; color:#1e293b; margin:0; white-space:pre-wrap;">{{ ex.respuesta.campo_8 }}</p>
                                        </div>
                                        <!-- Dientes maxilar -->
                                        <template v-for="d in getDientes(ex.respuesta).filter(d => [11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28,51,52,53,54,55,61,62,63,64,65].includes(d.n))" :key="d.n">
                                            <div style="padding:8px 16px; border-bottom:1px solid #f1f5f9; display:flex; gap:12px;">
                                                <p style="font-size:12px; font-weight:600; color:#64748b; margin:0; width:80px; flex-shrink:0;">Diente {{ toDot(d.n) }}:</p>
                                                <p style="font-size:13px; color:#1e293b; margin:0; white-space:pre-wrap; min-width:0;">{{ d.val }}</p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- MANDÍBULA -->
                            <template v-if="ex.respuesta.campo_6 || ex.respuesta.campo_7 || ex.respuesta.campo_9 || ex.respuesta.campo_4 ||
                                getDientes(ex.respuesta).some(d => [31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48,71,72,73,74,75,81,82,83,84,85].includes(d.n))">
                                <div style="border:1px solid #bfdbfe; border-radius:10px; overflow:hidden; margin-bottom:12px;">
                                    <div style="background:#1d4ed8; padding:10px 16px;">
                                        <p style="font-size:13px; font-weight:600; color:#fff; margin:0;">Mandíbula</p>
                                    </div>
                                    <div>
                                        <div v-if="ex.respuesta.campo_6" style="padding:10px 16px; border-bottom:1px solid #f1f5f9;">
                                            <p style="font-size:12px; font-weight:600; color:#64748b; margin:0 0 3px;">Nivel Óseo Marginal:</p>
                                            <p style="font-size:14px; color:#1e293b; margin:0; white-space:pre-wrap;">{{ ex.respuesta.campo_6 }}</p>
                                        </div>
                                        <div v-if="ex.respuesta.campo_7" style="padding:10px 16px; border-bottom:1px solid #f1f5f9;">
                                            <p style="font-size:12px; font-weight:600; color:#64748b; margin:0 0 3px;">Cálculo dentario marginal:</p>
                                            <p style="font-size:14px; color:#1e293b; margin:0; white-space:pre-wrap;">{{ ex.respuesta.campo_7 }}</p>
                                        </div>
                                        <div v-if="ex.respuesta.campo_9" style="padding:10px 16px; border-bottom:1px solid #f1f5f9;">
                                            <p style="font-size:12px; font-weight:600; color:#64748b; margin:0 0 3px;">Dientes Ausentes:</p>
                                            <p style="font-size:14px; color:#1e293b; margin:0; white-space:pre-wrap;">{{ ex.respuesta.campo_9 }}</p>
                                        </div>
                                        <div v-if="ex.respuesta.campo_4" style="padding:10px 16px; border-bottom:1px solid #f1f5f9;">
                                            <p style="font-size:12px; font-weight:600; color:#64748b; margin:0 0 3px;">Observaciones:</p>
                                            <p style="font-size:14px; color:#1e293b; margin:0; white-space:pre-wrap;">{{ ex.respuesta.campo_4 }}</p>
                                        </div>
                                        <!-- Dientes mandíbula -->
                                        <template v-for="d in getDientes(ex.respuesta).filter(d => [31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48,71,72,73,74,75,81,82,83,84,85].includes(d.n))" :key="d.n">
                                            <div style="padding:8px 16px; border-bottom:1px solid #f1f5f9; display:flex; gap:12px;">
                                                <p style="font-size:12px; font-weight:600; color:#64748b; margin:0; width:80px; flex-shrink:0;">Diente {{ toDot(d.n) }}:</p>
                                                <p style="font-size:13px; color:#1e293b; margin:0; white-space:pre-wrap; min-width:0;">{{ d.val }}</p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </template>

                        <!-- Dientes individuales para otros exámenes -->
                        <template v-else-if="ex.kind_id !== 15 && getDientes(ex.respuesta).length">
                            <div style="border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; margin-top:12px;">
                                <div style="background:#374151; padding:10px 16px;">
                                    <p style="font-size:13px; font-weight:600; color:#fff; margin:0;">Por diente</p>
                                </div>
                                <div>
                                    <div v-for="d in getDientes(ex.respuesta)" :key="d.n"
                                        style="padding:8px 16px; border-bottom:1px solid #f1f5f9; display:flex; gap:12px;">
                                        <p style="font-size:12px; font-weight:600; color:#64748b; margin:0; width:80px; flex-shrink:0;">Diente {{ toDot(d.n) }}:</p>
                                        <p style="font-size:13px; color:#1e293b; margin:0; white-space:pre-wrap; min-width:0;">{{ d.val }}</p>
                                    </div>
                                </div>
                            </div>
                        </template>

                    </template>

                    <!-- Sin informe ni archivos -->
                    <p v-if="!ex.archivos?.length && !ex.respuesta"
                        style="font-size:13px; color:#94a3b8; font-style:italic; margin:0;">
                        Sin archivos ni informe disponibles.
                    </p>
                </div>
            </div>

        </main>

        <!-- Footer -->
        <footer style="text-align:center; padding:16px; font-size:11px; color:#94a3b8; background:#f1f5f9; border-top:1px solid #e2e8f0;">
            Dimage · Sistema de Radiología Digital
        </footer>
    </div>
</template>

<script setup>
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    order:      { type: Object, required: true },
    paciente:   { type: Object, default: null },
    odontologo: { type: String, default: null },
    examenes:   { type: Array, default: () => [] },
    pdfUrl:     { type: String, default: null },
});

const page = usePage();
const csrf = page.props.csrf_token ?? document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const DIENTES_PERM = [11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28,31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48];
const DIENTES_TEMP = [51,52,53,54,55,61,62,63,64,65,71,72,73,74,75,81,82,83,84,85];

function toDot(n) { const s = String(n); return s[0] + '.' + s[1]; }

function getDientes(respuesta) {
    if (!respuesta) return [];
    return [...DIENTES_PERM, ...DIENTES_TEMP]
        .filter(n => respuesta[`diente_${n}`])
        .map(n => ({ n, val: respuesta[`diente_${n}`] }));
}

function isImage(ext) {
    return ['jpg','jpeg','png','gif','webp','bmp'].includes((ext || '').toLowerCase());
}

function badgeStyle(color) {
    const map = {
        success:   { bg: '#dcfce7', text: '#15803d' },
        warning:   { bg: '#fef9c3', text: '#a16207' },
        danger:    { bg: '#fee2e2', text: '#b91c1c' },
        secondary: { bg: '#f1f5f9', text: '#475569' },
    };
    const c = map[color] ?? map.secondary;
    return `padding:2px 10px; border-radius:999px; background:${c.bg}; color:${c.text}; font-size:11px; font-weight:600;`;
}

function logout() {
    router.post(route('paciente.logout'));
}
</script>
