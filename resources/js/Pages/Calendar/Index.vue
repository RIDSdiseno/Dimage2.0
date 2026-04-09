<template>
    <AppLayout title="Calendario">
        <div class="p-6">

            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Calendario</h1>
                    <p class="text-sm text-gray-400 mt-0.5">{{ isAdmin ? 'Todos los eventos' : 'Mis eventos' }}</p>
                </div>
                <Button label="Nuevo Evento" icon="pi pi-plus" @click="abrirCrear(null)"
                    style="background-color:#3452ff;border-color:#3452ff;" />
            </div>

            <!-- Navegación mes -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <button @click="cambiarMes(-1)"
                        class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition">
                        <i class="pi pi-chevron-left text-gray-500 text-sm" />
                    </button>
                    <h2 class="text-base font-bold text-gray-800 capitalize">{{ tituloMes }}</h2>
                    <button @click="cambiarMes(1)"
                        class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition">
                        <i class="pi pi-chevron-right text-gray-500 text-sm" />
                    </button>
                </div>

                <!-- Días de la semana -->
                <div class="grid grid-cols-7 border-b border-gray-100">
                    <div v-for="dia in diasSemana" :key="dia"
                        class="py-2 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">
                        {{ dia }}
                    </div>
                </div>

                <!-- Grilla del mes -->
                <div class="grid grid-cols-7">
                    <div v-for="(celda, i) in celdas" :key="i"
                        class="min-h-[110px] border-r border-b border-gray-50 p-1.5 cursor-pointer hover:bg-blue-50/20 transition"
                        :class="{
                            'bg-gray-50/60': !celda.currentMonth,
                            'ring-2 ring-inset ring-blue-400': celda.esHoy,
                            'bg-red-50/40': tieneFeriado(celda.fecha),
                        }"
                        @click="abrirCrear(celda.fecha)">

                        <!-- Número del día -->
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold px-1 py-0.5 rounded"
                                :class="celda.esHoy
                                    ? 'bg-blue-600 text-white'
                                    : celda.currentMonth ? 'text-gray-700' : 'text-gray-300'">
                                {{ celda.dia }}
                            </span>
                            <i v-if="tieneFeriado(celda.fecha)"
                                class="pi pi-flag-fill text-red-400 text-xs" title="Feriado" />
                        </div>

                        <!-- Eventos del día -->
                        <div class="space-y-0.5">
                            <div v-for="ev in eventosDelDia(celda.fecha)" :key="ev.id"
                                class="flex items-center gap-1 px-1.5 py-0.5 rounded text-xs text-white font-medium truncate transition"
                                :class="ev.type === 'feriado'
                                    ? 'cursor-default opacity-90'
                                    : 'cursor-pointer hover:opacity-90'"
                                :style="`background:${ev.color}`"
                                @click.stop="ev.type !== 'feriado' && abrirEditar(ev)">
                                <i v-if="ev.type === 'feriado'" class="pi pi-flag text-xs shrink-0 opacity-80" />
                                <span v-else-if="ev.hora_inicio" class="shrink-0 opacity-80">{{ ev.hora_inicio }}</span>
                                <span class="truncate">{{ ev.title }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── Modal crear / editar ── -->
        <Teleport to="body">
            <div v-if="modal.open"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="cerrarModal">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">

                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-lg font-bold text-gray-800">
                            {{ modal.event ? 'Editar Evento' : 'Nuevo Evento' }}
                        </h3>
                        <button @click="cerrarModal" class="text-gray-400 hover:text-gray-600">
                            <i class="pi pi-times" />
                        </button>
                    </div>

                    <div class="space-y-4">

                        <!-- Título -->
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Título *</label>
                            <InputText v-model="form.title" class="w-full"
                                :class="{'p-invalid': errors.title}" placeholder="Nombre del evento" />
                            <small v-if="errors.title" class="text-red-500">{{ errors.title }}</small>
                        </div>

                        <!-- Fecha + Hora -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Fecha *</label>
                                <InputText v-model="form.fecha" type="date" class="w-full"
                                    :class="{'p-invalid': errors.fecha}" />
                                <small v-if="errors.fecha" class="text-red-500">{{ errors.fecha }}</small>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Hora</label>
                                <InputText v-model="form.hora_inicio" type="time" class="w-full" />
                            </div>
                        </div>

                        <!-- Descripción -->
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Descripción</label>
                            <textarea v-model="form.description" rows="2"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 resize-none"
                                placeholder="Detalles del evento..."></textarea>
                        </div>

                        <!-- Color -->
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Color</label>
                            <div class="flex gap-2 flex-wrap">
                                <button v-for="c in colores" :key="c.value"
                                    type="button"
                                    @click="form.color = c.value"
                                    class="w-7 h-7 rounded-full border-2 transition"
                                    :style="`background:${c.value}`"
                                    :class="form.color === c.value ? 'border-gray-800 scale-110' : 'border-transparent'" />
                            </div>
                        </div>

                        <!-- Asignar usuario (solo admin) -->
                        <div v-if="isAdmin">
                            <label class="block text-sm font-medium text-gray-600 mb-1">Asignar a</label>

                            <!-- Buscador + filtro por tipo -->
                            <div class="flex gap-2 mb-2">
                                <div class="flex-1 relative">
                                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none" />
                                    <input v-model="userSearch" type="text"
                                        placeholder="Buscar por nombre..."
                                        class="w-full pl-8 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
                                </div>
                                <select v-model="userTipoFiltro"
                                    class="text-sm border border-gray-300 rounded-lg px-2 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500/30 text-gray-600">
                                    <option value="">Todos</option>
                                    <option value="1">Admin</option>
                                    <option value="2">Secretaria</option>
                                    <option value="3">Holding</option>
                                    <option value="4">Clínica</option>
                                    <option value="5">Radiólogo</option>
                                    <option value="6">Odontólogo</option>
                                    <option value="7">Contralor</option>
                                    <option value="11">Técnico</option>
                                </select>
                            </div>

                            <!-- Lista de usuarios filtrada -->
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <!-- Usuario seleccionado -->
                                <div v-if="usuarioSeleccionado"
                                    class="flex items-center justify-between px-3 py-2 bg-blue-50 border-b border-blue-100">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                            {{ usuarioSeleccionado.label[0] }}
                                        </div>
                                        <span class="text-sm font-medium text-blue-700">{{ usuarioSeleccionado.label }}</span>
                                        <span class="text-xs text-blue-400">{{ tipoLabel(usuarioSeleccionado.type_id) }}</span>
                                    </div>
                                    <button @click="form.user_id = null" class="text-blue-400 hover:text-blue-600 transition">
                                        <i class="pi pi-times text-xs" />
                                    </button>
                                </div>

                                <!-- Lista scroll -->
                                <div class="max-h-40 overflow-y-auto">
                                    <div v-if="usuariosFiltrados.length === 0"
                                        class="px-3 py-4 text-center text-xs text-gray-400">
                                        No se encontraron usuarios
                                    </div>
                                    <button v-for="u in usuariosFiltrados" :key="u.value"
                                        type="button"
                                        @click="form.user_id = u.value"
                                        class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50 transition border-b border-gray-50 last:border-0"
                                        :class="form.user_id === u.value ? 'bg-blue-50' : ''">
                                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
                                            :style="`background:${form.user_id === u.value ? '#3452ff' : '#94a3b8'}`">
                                            {{ u.label[0] }}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-700 truncate">{{ u.label }}</p>
                                            <p class="text-xs text-gray-400">{{ tipoLabel(u.type_id) }}</p>
                                        </div>
                                        <i v-if="form.user_id === u.value" class="pi pi-check text-blue-500 text-xs shrink-0" />
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Acciones -->
                    <div class="flex items-center justify-between mt-6">
                        <Button v-if="modal.event && puedeBorrar"
                            icon="pi pi-trash" severity="danger" text
                            label="Eliminar" @click="eliminar" />
                        <div v-else />
                        <div class="flex gap-2">
                            <Button label="Cancelar" severity="secondary" text @click="cerrarModal" />
                            <Button :label="modal.event ? 'Guardar' : 'Crear'"
                                icon="pi pi-check" :loading="saving"
                                style="background-color:#3452ff;border-color:#3452ff;"
                                @click="guardar" />
                        </div>
                    </div>

                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, reactive, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button    from 'primevue/button';
import InputText from 'primevue/inputtext';

const props = defineProps({ isAdmin: Boolean, users: Array });

const page      = usePage();
const authUser  = computed(() => page.props.auth?.user);

// ── Estado del mes ─────────────────────────────────────────────────────────
const hoy   = new Date();
const viewYear  = ref(hoy.getFullYear());
const viewMonth = ref(hoy.getMonth() + 1);  // 1-12

const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

const tituloMes = computed(() => {
    const d = new Date(viewYear.value, viewMonth.value - 1, 1);
    return d.toLocaleDateString('es-CL', { month: 'long', year: 'numeric' });
});

const celdas = computed(() => {
    const year  = viewYear.value;
    const month = viewMonth.value;
    const primerDia = new Date(year, month - 1, 1).getDay();  // 0=Dom
    const diasEnMes = new Date(year, month, 0).getDate();
    const hoyStr    = fmtFecha(hoy);
    const result    = [];

    // Días del mes anterior
    const prevDias = new Date(year, month - 1, 0).getDate();
    for (let i = primerDia - 1; i >= 0; i--) {
        const d = prevDias - i;
        result.push({ dia: d, fecha: fmtFecha(new Date(year, month - 2, d)), currentMonth: false, esHoy: false });
    }

    // Días del mes actual
    for (let d = 1; d <= diasEnMes; d++) {
        const fecha = fmtFecha(new Date(year, month - 1, d));
        result.push({ dia: d, fecha, currentMonth: true, esHoy: fecha === hoyStr });
    }

    // Completar hasta 42 celdas (6 filas)
    let nextDay = 1;
    while (result.length < 42) {
        result.push({ dia: nextDay, fecha: fmtFecha(new Date(year, month, nextDay)), currentMonth: false, esHoy: false });
        nextDay++;
    }

    return result;
});

const cambiarMes = (delta) => {
    let m = viewMonth.value + delta;
    let y = viewYear.value;
    if (m > 12) { m = 1;  y++; }
    if (m < 1)  { m = 12; y--; }
    viewMonth.value = m;
    viewYear.value  = y;
};

// ── Búsqueda de usuarios en el modal ──────────────────────────────────────
const userSearch      = ref('');
const userTipoFiltro  = ref('');

const tiposLabel = {
    1: 'Admin', 2: 'Secretaria', 3: 'Holding', 4: 'Clínica',
    5: 'Radiólogo', 6: 'Odontólogo', 7: 'Contralor', 11: 'Técnico',
};
const tipoLabel = (typeId) => tiposLabel[typeId] ?? 'Usuario';

const usuariosFiltrados = computed(() => {
    const q    = userSearch.value.trim().toLowerCase();
    const tipo = userTipoFiltro.value;
    return (props.users ?? []).filter(u => {
        if (q    && !u.label.toLowerCase().includes(q)) return false;
        if (tipo && String(u.type_id) !== tipo)         return false;
        if (u.value === form.user_id)                   return false; // ya seleccionado, no duplicar
        return true;
    });
});

const usuarioSeleccionado = computed(() =>
    form.user_id ? (props.users ?? []).find(u => u.value === form.user_id) : null
);

// ── Eventos ────────────────────────────────────────────────────────────────
const eventos = ref([]);
const loading = ref(false);

const fetchEvents = async () => {
    loading.value = true;
    try {
        const res  = await fetch(route('calendario.events') + `?year=${viewYear.value}&month=${viewMonth.value}`);
        eventos.value = await res.json();
    } finally {
        loading.value = false;
    }
};

const eventosDelDia  = (fecha) => eventos.value.filter(e => e.fecha === fecha);
const tieneFeriado   = (fecha) => eventos.value.some(e => e.fecha === fecha && e.type === 'feriado');

watch([viewYear, viewMonth], fetchEvents);
onMounted(fetchEvents);

// ── Modal ──────────────────────────────────────────────────────────────────
const colores = [
    { value: '#3452ff' }, { value: '#10b981' }, { value: '#f59e0b' },
    { value: '#ef4444' }, { value: '#8b5cf6' }, { value: '#0ea5e9' },
    { value: '#ec4899' }, { value: '#64748b' },
];

const modal  = reactive({ open: false, event: null });
const errors = reactive({});
const saving = ref(false);

const form = reactive({
    title: '', fecha: '', hora_inicio: '', description: '',
    color: '#3452ff', user_id: null,
});

const puedeBorrar = computed(() => {
    if (!modal.event) return false;
    return props.isAdmin || modal.event.created_by === authUser.value?.id;
});

const resetUserSearch = () => { userSearch.value = ''; userTipoFiltro.value = ''; };

const abrirCrear = (fecha) => {
    Object.assign(form, { title: '', fecha: fecha ?? fmtFecha(hoy), hora_inicio: '',
                          description: '', color: '#3452ff', user_id: null });
    Object.keys(errors).forEach(k => delete errors[k]);
    resetUserSearch();
    modal.event = null;
    modal.open  = true;
};

const abrirEditar = (ev) => {
    Object.assign(form, {
        title:       ev.title,
        fecha:       ev.fecha,
        hora_inicio: ev.hora_inicio ?? '',
        description: ev.description ?? '',
        color:       ev.color,
        user_id:     ev.user_id,
    });
    Object.keys(errors).forEach(k => delete errors[k]);
    resetUserSearch();
    modal.event = ev;
    modal.open  = true;
};

const cerrarModal = () => { modal.open = false; };

const guardar = () => {
    Object.keys(errors).forEach(k => delete errors[k]);
    if (!form.title.trim()) { errors.title = 'El título es requerido.'; return; }
    if (!form.fecha)         { errors.fecha = 'La fecha es requerida.';  return; }

    saving.value = true;

    const data = {
        title:       form.title,
        fecha:       form.fecha,
        hora_inicio: form.hora_inicio || null,
        description: form.description || null,
        color:       form.color,
        user_id:     form.user_id,
    };

    const url = modal.event
        ? route('calendario.update', modal.event.id)
        : route('calendario.store');

    const method = modal.event ? 'put' : 'post';

    router[method](url, data, {
        preserveScroll: true,
        onSuccess: () => { cerrarModal(); fetchEvents(); },
        onError:   (e) => Object.assign(errors, e),
        onFinish:  () => { saving.value = false; },
    });
};

const eliminar = () => {
    if (!confirm('¿Eliminar este evento?')) return;
    router.delete(route('calendario.destroy', modal.event.id), {
        preserveScroll: true,
        onSuccess: () => { cerrarModal(); fetchEvents(); },
    });
};

// ── Utils ──────────────────────────────────────────────────────────────────
const fmtFecha = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};
</script>
