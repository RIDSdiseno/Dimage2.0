<template>
    <AppLayout :title="staff ? `Editar ${tipo.label} - ${staff.name}` : `Crear ${tipo.label}`">
        <div class="p-6 max-w-2xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <Link :href="route(tipo.route_index)">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <Link :href="route('admin.index')" class="text-gray-400 hover:text-blue-600 text-xs">Administración</Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <Link :href="route(tipo.route_index)" class="text-gray-400 hover:text-blue-600 text-xs">{{ tipo.label_plural }}</Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-xs text-gray-600">{{ staff ? 'Editar' : 'Crear' }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        {{ staff ? staff.name : `Nuevo ${tipo.label}` }}
                    </h1>
                </div>
            </div>

            <Message v-if="$page.props.flash?.success" severity="success" class="mb-4">
                {{ $page.props.flash.success }}
            </Message>

            <div class="bg-white rounded-xl shadow p-6 space-y-6">

                <!-- Datos del usuario -->
                <div>
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                        Datos del usuario
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Nombre *</label>
                            <InputText v-model="form.name" class="w-full" placeholder="Nombre"
                                :class="{ 'p-invalid': form.errors.name }" />
                            <small class="text-red-500">{{ form.errors.name }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">E-mail</label>
                            <InputText v-model="form.mail" type="email" class="w-full" placeholder="E-mail" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Usuario *</label>
                            <InputText v-model="form.username" class="w-full" placeholder="Usuario"
                                :class="{ 'p-invalid': form.errors.username }"
                                :disabled="!!staff" />
                            <small v-if="staff" class="text-gray-400">El usuario no se puede cambiar</small>
                            <small class="text-red-500">{{ form.errors.username }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">
                                Contraseña {{ staff ? '' : '*' }}
                            </label>
                            <InputText v-model="form.password" type="password" class="w-full"
                                :placeholder="staff ? 'Dejar en blanco para no cambiar' : 'Contraseña'"
                                :class="{ 'p-invalid': form.errors.password }" />
                            <small class="text-red-500">{{ form.errors.password }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">
                                Confirmar Contraseña {{ staff ? '' : '*' }}
                            </label>
                            <InputText v-model="form.password_confirmation" type="password" class="w-full"
                                :placeholder="staff ? 'Confirmar nueva contraseña' : 'Confirmar Contraseña'"
                                :class="{ 'p-invalid': form.errors.password_confirmation }" />
                            <small class="text-red-500">{{ form.errors.password_confirmation }}</small>
                        </div>

                        <!-- Teléfono con selector de país -->
                        <div>
                            <label class="block text-sm font-medium mb-1">Teléfono</label>
                            <div class="flex">
                                <Select
                                    v-model="selectedCountry"
                                    :options="countryCodes"
                                    optionLabel="name"
                                    dataKey="iso"
                                    filter
                                    filterPlaceholder="Buscar país..."
                                    class="rounded-r-none border-r-0 shrink-0"
                                    style="min-width:130px"
                                >
                                    <template #value="{ value }">
                                        <span v-if="value" class="flex items-center gap-1.5">
                                            <span :class="`fi fi-${value.iso}`" style="font-size:1.1rem"></span>
                                            <span class="text-sm font-medium">{{ value.code }}</span>
                                        </span>
                                    </template>
                                    <template #option="{ option }">
                                        <span class="flex items-center gap-2 w-full">
                                            <span :class="`fi fi-${option.iso}`" style="font-size:1.1rem;flex-shrink:0"></span>
                                            <span class="text-sm flex-1">{{ option.name }}</span>
                                            <span class="text-gray-400 text-xs">{{ option.code }}</span>
                                        </span>
                                    </template>
                                </Select>
                                <InputText
                                    v-model="phoneRaw"
                                    class="w-full rounded-l-none"
                                    placeholder="912345678"
                                />
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Datos profesionales -->
                <div>
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                        Datos profesionales
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <!-- RUT con formato y validación -->
                        <div>
                            <label class="block text-sm font-medium mb-1">RUT</label>
                            <InputText
                                :value="rutDisplay"
                                @input="onRutInput"
                                @blur="onRutBlur"
                                class="w-full"
                                placeholder="12.345.678-9"
                                :class="{ 'p-invalid': rutError }"
                            />
                            <small v-if="rutError" class="text-red-500">{{ rutError }}</small>
                            <small v-else class="text-red-500">{{ form.errors.rut }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Dirección</label>
                            <InputText v-model="form.address" class="w-full" placeholder="Dirección" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Pertenece a (Clínicas)</label>
                            <MultiSelect
                                v-model="form.clinica_ids"
                                :options="clinicasList"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Seleccionar clínicas"
                                class="w-full"
                                display="chip"
                            />
                        </div>

                        <div v-if="esRadiologo">
                            <label class="block text-sm font-medium mb-1">Pertenece a Red (Holdings)</label>
                            <MultiSelect
                                v-model="form.holding_ids"
                                :options="holdingsList"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Seleccionar red"
                                class="w-full"
                                display="chip"
                            />
                        </div>

                        <div v-if="esRadiologo" class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Tipos de exámen</label>
                            <MultiSelect
                                v-model="form.kind_ids"
                                :options="kindsList"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Seleccionar tipos de examen"
                                class="w-full"
                                display="chip"
                                filter
                            />
                        </div>

                        <div v-if="esRadiologo" class="md:col-span-2">
                            <label class="block text-sm font-medium mb-2">Firma del Radiólogo</label>

                            <!-- Firma actual o preview -->
                            <div v-if="firmaPreview || staff?.firma_url" class="mb-3">
                                <img :src="firmaPreview || staff.firma_url" alt="Firma"
                                    class="max-h-20 border rounded p-1 bg-gray-50" />
                                <p v-if="!firmaPreview" class="text-xs text-gray-400 mt-1">
                                    Firma actual — reemplaza con una nueva abajo
                                </p>
                            </div>

                            <!-- Botones de acción -->
                            <div class="flex gap-2 mb-3">
                                <button type="button" @click="$refs.firmaInput.click()"
                                    class="px-3 py-1.5 text-sm rounded-lg font-medium border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 transition-colors">
                                    <i class="pi pi-upload mr-1 text-xs" />Subir imagen
                                </button>
                                <input ref="firmaInput" type="file" accept="image/*" class="hidden" @change="onFirmaChange" />
                                <button type="button" @click="firmaMode = firmaMode === 'draw' ? 'upload' : 'draw'"
                                    class="px-3 py-1.5 text-sm rounded-lg font-medium border transition-colors"
                                    :class="firmaMode === 'draw' ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'">
                                    <i class="pi pi-pencil mr-1 text-xs" />Dibujar firma
                                </button>
                            </div>

                            <!-- Canvas para dibujar -->
                            <div v-if="firmaMode === 'draw'">
                                <div class="border-2 border-dashed border-gray-300 rounded-lg bg-white overflow-hidden" style="cursor:crosshair;">
                                    <canvas ref="signatureCanvas" width="500" height="140"
                                        style="display:block; touch-action:none; width:100%; height:140px;"
                                        @mousedown="startDraw" @mousemove="draw" @mouseup="stopDraw" @mouseleave="stopDraw"
                                        @touchstart.prevent="startDrawTouch" @touchmove.prevent="drawTouch" @touchend="stopDraw" />
                                </div>
                                <p class="text-xs text-gray-400 mt-1 mb-2">Dibuja tu firma con el mouse o dedo</p>
                                <div class="flex gap-2">
                                    <button type="button" @click="clearCanvas"
                                        class="px-3 py-1.5 text-xs text-red-500 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                                        <i class="pi pi-trash mr-1" />Limpiar
                                    </button>
                                    <button type="button" @click="useCanvasAsFirma"
                                        class="px-3 py-1.5 text-xs text-green-700 border border-green-300 rounded-lg hover:bg-green-50 transition-colors font-medium">
                                        <i class="pi pi-check mr-1" />Guardar firma
                                    </button>
                                </div>
                            </div>

                            <small class="text-red-500">{{ form.errors.firma }}</small>
                        </div>

                    </div>
                </div>

                <!-- Permisos (solo radiólogos) -->
                <div v-if="esRadiologo">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                        Permisos para este usuario
                    </h2>
                    <div class="flex items-center gap-3">
                        <Checkbox v-model="form.solo_adjuntar_informe" :binary="true" inputId="solo_adjuntar" />
                        <label for="solo_adjuntar" class="text-sm cursor-pointer">
                            Permiso para solo adjuntar informe
                        </label>
                    </div>
                    <div class="flex items-center gap-3 mt-3">
                        <Checkbox v-model="form.puede_crear_ordenes" :binary="true" inputId="puede_crear_ordenes" />
                        <label for="puede_crear_ordenes" class="text-sm cursor-pointer">
                            Es administrador (puede crear órdenes)
                        </label>
                    </div>
                </div>

                <!-- Permisos (técnico y odontólogo) -->
                <div v-if="esOperador">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                        Permisos para este usuario
                    </h2>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <Checkbox v-model="form.puede_seleccionar_radiologo" :binary="true" inputId="puede_seleccionar_radiologo" />
                            <label for="puede_seleccionar_radiologo" class="text-sm cursor-pointer">
                                Permiso para seleccionar Radiólogo
                            </label>
                        </div>
                        <div class="flex items-center gap-3">
                            <Checkbox v-model="form.puede_sin_diagnostico" :binary="true" inputId="puede_sin_diagnostico" />
                            <label for="puede_sin_diagnostico" class="text-sm cursor-pointer">
                                Permiso para examen sin diagnóstico
                            </label>
                        </div>
                        <div class="flex items-center gap-3">
                            <Checkbox v-model="form.puede_derivacion_clinica" :binary="true" inputId="puede_derivacion_clinica" />
                            <label for="puede_derivacion_clinica" class="text-sm cursor-pointer">
                                Permiso para derivación clínica
                            </label>
                        </div>
                        <div class="flex items-center gap-3">
                            <Checkbox v-model="form.puede_ver_menu_busqueda" :binary="true" inputId="puede_ver_menu_busqueda" />
                            <label for="puede_ver_menu_busqueda" class="text-sm cursor-pointer">
                                Permiso para ver menú de búsqueda de orden
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <Link :href="route(tipo.route_index)">
                        <Button label="Cancelar" severity="secondary" type="button" />
                    </Link>
                    <Button
                        :label="staff ? 'Guardar Cambios' : `Crear ${tipo.label}`"
                        icon="pi pi-save"
                        @click="submit"
                        :loading="form.processing"
                        style="background-color:#3452ff;border-color:#3452ff;"
                    />
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import Checkbox from 'primevue/checkbox';
import Message from 'primevue/message';

const props = defineProps({
    staff:        Object,
    tipo:         Object,
    clinicasList: Array,
    holdingsList: Array,
    kindsList:    Array,
});

const esRadiologo  = computed(() => props.tipo?.type_staff === 3);
const esTecnico    = computed(() => props.tipo?.type_staff === 11);
const esOdontologo = computed(() => props.tipo?.type_staff === 6);
// Operador = técnico u odontólogo (ambos tienen los mismos permisos configurables)
const esOperador   = computed(() => esTecnico.value || esOdontologo.value);

// ── Códigos de país ───────────────────────────────────────────────────────────
// iso: código ISO 3166-1 alpha-2 en minúsculas (usado por flag-icons: class="fi fi-{iso}")
const countryCodes = [
    // América Latina
    { code: '+56',  iso: 'cl', name: 'Chile'            },
    { code: '+54',  iso: 'ar', name: 'Argentina'        },
    { code: '+55',  iso: 'br', name: 'Brasil'           },
    { code: '+51',  iso: 'pe', name: 'Perú'            },
    { code: '+52',  iso: 'mx', name: 'México'          },
    { code: '+57',  iso: 'co', name: 'Colombia'        },
    { code: '+58',  iso: 've', name: 'Venezuela'       },
    { code: '+593', iso: 'ec', name: 'Ecuador'         },
    { code: '+595', iso: 'py', name: 'Paraguay'        },
    { code: '+598', iso: 'uy', name: 'Uruguay'         },
    { code: '+591', iso: 'bo', name: 'Bolivia'         },
    { code: '+506', iso: 'cr', name: 'Costa Rica'      },
    { code: '+502', iso: 'gt', name: 'Guatemala'       },
    { code: '+504', iso: 'hn', name: 'Honduras'        },
    { code: '+505', iso: 'ni', name: 'Nicaragua'       },
    { code: '+507', iso: 'pa', name: 'Panamá'         },
    { code: '+503', iso: 'sv', name: 'El Salvador'     },
    { code: '+53',  iso: 'cu', name: 'Cuba'            },
    { code: '+1787',iso: 'pr', name: 'Puerto Rico'     },
    { code: '+1809',iso: 'do', name: 'Rep. Dominicana' },
    { code: '+509', iso: 'ht', name: 'Haití'          },
    { code: '+592', iso: 'gy', name: 'Guyana'          },
    { code: '+597', iso: 'sr', name: 'Surinam'         },
    // América del Norte
    { code: '+1',   iso: 'us', name: 'Estados Unidos'  },
    { code: '+1',   iso: 'ca', name: 'Canadá'         },
    // Europa
    { code: '+34',  iso: 'es', name: 'España'         },
    { code: '+351', iso: 'pt', name: 'Portugal'        },
    { code: '+44',  iso: 'gb', name: 'Reino Unido'     },
    { code: '+33',  iso: 'fr', name: 'Francia'         },
    { code: '+49',  iso: 'de', name: 'Alemania'        },
    { code: '+39',  iso: 'it', name: 'Italia'          },
    { code: '+31',  iso: 'nl', name: 'Países Bajos'   },
    { code: '+32',  iso: 'be', name: 'Bélgica'        },
    { code: '+41',  iso: 'ch', name: 'Suiza'           },
    { code: '+43',  iso: 'at', name: 'Austria'         },
    { code: '+46',  iso: 'se', name: 'Suecia'          },
    { code: '+47',  iso: 'no', name: 'Noruega'         },
    { code: '+45',  iso: 'dk', name: 'Dinamarca'       },
    { code: '+358', iso: 'fi', name: 'Finlandia'       },
    { code: '+48',  iso: 'pl', name: 'Polonia'         },
    { code: '+380', iso: 'ua', name: 'Ucrania'         },
    { code: '+7',   iso: 'ru', name: 'Rusia'           },
    // Asia / Oceanía
    { code: '+81',  iso: 'jp', name: 'Japón'          },
    { code: '+82',  iso: 'kr', name: 'Corea del Sur'  },
    { code: '+86',  iso: 'cn', name: 'China'           },
    { code: '+91',  iso: 'in', name: 'India'           },
    { code: '+61',  iso: 'au', name: 'Australia'       },
    { code: '+64',  iso: 'nz', name: 'Nueva Zelanda'  },
    // África / Medio Oriente
    { code: '+27',  iso: 'za', name: 'Sudáfrica'      },
    { code: '+20',  iso: 'eg', name: 'Egipto'          },
    { code: '+971', iso: 'ae', name: 'Emiratos Árabes'},
    { code: '+972', iso: 'il', name: 'Israel'          },
];

const parsePhone = (telephone) => {
    const fallback = countryCodes.find(c => c.iso === 'cl');
    if (!telephone) return { country: fallback, number: '' };
    const s = telephone.trim();
    if (s.startsWith('+')) {
        const spaceIdx = s.indexOf(' ');
        const dialCode = spaceIdx > 0 ? s.slice(0, spaceIdx) : null;
        // match longest code first to avoid +1 eating +1787 etc.
        const sorted = [...countryCodes].sort((a, b) => b.code.length - a.code.length);
        if (dialCode) {
            const found = sorted.find(c => c.code === dialCode);
            if (found) return { country: found, number: s.slice(spaceIdx + 1) };
        }
        for (const c of sorted) {
            if (s.startsWith(c.code)) return { country: c, number: s.slice(c.code.length).trimStart() };
        }
    }
    return { country: fallback, number: s };
};

const initialPhone    = parsePhone(props.staff?.telephone ?? '');
const selectedCountry = ref(initialPhone.country);
const phoneRaw        = ref(initialPhone.number);

// ── RUT ───────────────────────────────────────────────────────────────────────
const formatRut = (value) => {
    if (!value) return '';
    const clean = value.replace(/[^0-9kK]/g, '').toUpperCase();
    if (clean.length < 2) return clean;
    const dv   = clean.slice(-1);
    const body = clean.slice(0, -1);
    return body + '-' + dv;
};

const validarRut = (rut) => {
    if (!rut) return true;
    const clean = rut.replace(/[.\-]/g, '').toUpperCase();
    if (clean.length < 2) return false;
    const body = clean.slice(0, -1);
    const dv   = clean.slice(-1);
    if (!/^\d+$/.test(body)) return false;
    let sum = 0, factor = 2;
    for (let i = body.length - 1; i >= 0; i--) {
        sum += parseInt(body[i]) * factor;
        factor = factor === 7 ? 2 : factor + 1;
    }
    const rem      = sum % 11;
    const expected = rem === 0 ? '0' : rem === 1 ? 'K' : String(11 - rem);
    return expected === dv;
};

const rutDisplay = ref(props.staff?.rut ? formatRut(props.staff.rut) : '');
const rutError   = ref('');

const onRutInput = (event) => {
    rutDisplay.value = formatRut(event.target.value);
    rutError.value   = '';
};

const onRutBlur = () => {
    if (rutDisplay.value && !validarRut(rutDisplay.value)) {
        rutError.value = 'RUT inválido';
    }
};

// ── Firma ─────────────────────────────────────────────────────────────────────
const firmaPreview     = ref(null);
const firmaFileName    = ref('');
const firmaMode        = ref('upload');
const signatureCanvas  = ref(null);
let isDrawing = false;
let lastX = 0, lastY = 0;

const onFirmaChange = (event) => {
    const file = event.target.files[0];
    if (!file) return;
    form.firma = file;
    firmaFileName.value = file.name;
    const reader = new FileReader();
    reader.onload = (e) => { firmaPreview.value = e.target.result; };
    reader.readAsDataURL(file);
};

function getCtx() {
    const c = signatureCanvas.value;
    if (!c) return null;
    const ctx = c.getContext('2d');
    ctx.strokeStyle = '#1e293b';
    ctx.lineWidth   = 2;
    ctx.lineCap     = 'round';
    ctx.lineJoin    = 'round';
    return ctx;
}
function coordsFromEvent(e, canvas) {
    const r = canvas.getBoundingClientRect();
    const scaleX = canvas.width  / r.width;
    const scaleY = canvas.height / r.height;
    return [(e.clientX - r.left) * scaleX, (e.clientY - r.top) * scaleY];
}
function startDraw(e) {
    isDrawing = true;
    [lastX, lastY] = coordsFromEvent(e, signatureCanvas.value);
}
function draw(e) {
    if (!isDrawing) return;
    const ctx = getCtx();
    if (!ctx) return;
    const [x, y] = coordsFromEvent(e, signatureCanvas.value);
    ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(x, y); ctx.stroke();
    [lastX, lastY] = [x, y];
}
function stopDraw() { isDrawing = false; }
function startDrawTouch(e) {
    const t = e.touches[0];
    isDrawing = true;
    const r = signatureCanvas.value.getBoundingClientRect();
    const c = signatureCanvas.value;
    lastX = (t.clientX - r.left) * (c.width  / r.width);
    lastY = (t.clientY - r.top)  * (c.height / r.height);
}
function drawTouch(e) {
    if (!isDrawing) return;
    const t = e.touches[0];
    const ctx = getCtx();
    if (!ctx) return;
    const r = signatureCanvas.value.getBoundingClientRect();
    const c = signatureCanvas.value;
    const x = (t.clientX - r.left) * (c.width  / r.width);
    const y = (t.clientY - r.top)  * (c.height / r.height);
    ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(x, y); ctx.stroke();
    [lastX, lastY] = [x, y];
}
function clearCanvas() {
    const c = signatureCanvas.value;
    if (c) c.getContext('2d').clearRect(0, 0, c.width, c.height);
}
function useCanvasAsFirma() {
    const c = signatureCanvas.value;
    if (!c) return;
    c.toBlob((blob) => {
        if (!blob) return;
        form.firma         = new File([blob], 'firma.png', { type: 'image/png' });
        firmaFileName.value = 'firma_dibujada.png';
        firmaPreview.value  = c.toDataURL('image/png');
    }, 'image/png');
}

// ── Formulario ────────────────────────────────────────────────────────────────
const form = useForm({
    name:                   props.staff?.name ?? '',
    username:               props.staff?.username ?? '',
    password:               '',
    password_confirmation:  '',
    mail:                   props.staff?.mail ?? '',
    telephone:              props.staff?.telephone ?? '',
    rut:                    rutDisplay.value,
    address:                props.staff?.address ?? '',
    clinica_ids:            props.staff?.clinica_ids ?? [],
    holding_ids:            props.staff?.holding_ids ?? [],
    kind_ids:               props.staff?.kind_ids ?? [],
    firma:                        null,
    solo_adjuntar_informe:        props.staff?.solo_adjuntar_informe ?? false,
    puede_crear_ordenes:          props.staff?.puede_crear_ordenes ?? false,
    puede_seleccionar_radiologo:  props.staff?.puede_seleccionar_radiologo ?? false,
    puede_sin_diagnostico:        props.staff?.puede_sin_diagnostico ?? false,
    puede_derivacion_clinica:     props.staff?.puede_derivacion_clinica ?? false,
    puede_ver_menu_busqueda:      props.staff?.puede_ver_menu_busqueda ?? false,
});

// Sincroniza telephone cuando cambia país o número
watch([selectedCountry, phoneRaw], ([country, number]) => {
    form.telephone = number && country ? `${country.code} ${number}` : '';
});

const submit = () => {
    if (rutDisplay.value && !validarRut(rutDisplay.value)) {
        rutError.value = 'RUT inválido';
        return;
    }
    form.rut = rutDisplay.value;

    if (props.staff) {
        form.put(route(props.tipo.route_update, props.staff.id), { forceFormData: true });
    } else {
        form.post(route(props.tipo.route_store), { forceFormData: true });
    }
};
</script>
