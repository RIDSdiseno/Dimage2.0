<template>
    <div class="min-h-screen" style="background-color:#f0f2f5;">

        <!-- Navbar -->
        <nav class="fixed top-0 left-0 right-0 z-50 h-14 flex items-center px-4 shadow-sm"
            style="background-color:#0b2a4a;">
            <button @click="sidebarOpen = !sidebarOpen"
                class="mr-3 text-white/70 hover:text-white transition p-1 rounded">
                <i class="pi pi-bars text-lg" />
            </button>

            <!-- Logo -->
            <div class="flex items-center gap-2">
                <img src="/images/dimage_logo.png" alt="Dimage" class="h-8 w-8 object-contain" style="mix-blend-mode:screen;" />
                <div class="leading-tight">
                    <span class="font-bold text-base text-white">Dimage</span>
                    <span class="text-xs text-white/50 block -mt-0.5 hidden md:block">Telediagnóstico Imagenológico</span>
                </div>
            </div>

            <div class="ml-auto flex items-center gap-3">

                <!-- Selector de región -->
                <Select
                    :modelValue="currentRegion"
                    :options="regionOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="w-36"
                    @change="setRegion($event.value)"
                >
                    <template #value="{ value }">
                        <div v-if="value" class="flex items-center gap-2">
                            <img :src="`https://flagcdn.com/20x15/${regionOptions.find(o => o.value === value)?.code}.png`"
                                :alt="value" class="rounded-sm shadow-sm" style="width:20px;height:15px;object-fit:cover;" />
                            <span class="text-xs font-medium text-gray-700">{{ regionOptions.find(o => o.value === value)?.label }}</span>
                        </div>
                    </template>
                    <template #option="{ option }">
                        <div class="flex items-center gap-2">
                            <img :src="`https://flagcdn.com/20x15/${option.code}.png`"
                                :alt="option.label" class="rounded-sm shadow-sm" style="width:20px;height:15px;object-fit:cover;" />
                            <span class="text-sm">{{ option.label }}</span>
                        </div>
                    </template>
                </Select>

                <div class="w-px h-5 bg-white/20" />

                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold"
                        style="background-color:#3452ff;">
                        {{ initials }}
                    </div>
                    <span class="text-sm text-white font-medium hidden md:block">{{ $page.props.auth?.user?.name }}</span>
                </div>
                <div class="w-px h-5 bg-white/20" />
                <Link href="/perfil"
                    class="flex items-center gap-1.5 text-sm text-white/70 hover:text-white transition px-2 py-1 rounded">
                    <i class="pi pi-user-edit text-sm" />
                    <span class="hidden md:block">Mi Perfil</span>
                </Link>
                <div class="w-px h-5 bg-white/20" />
                <Link href="/logout" method="post" as="button"
                    class="flex items-center gap-1.5 text-sm text-white/70 hover:text-white transition px-2 py-1 rounded">
                    <i class="pi pi-sign-out text-sm" />
                    <span class="hidden md:block">Salir</span>
                </Link>
            </div>
        </nav>

        <div class="flex pt-14">
            <!-- Sidebar -->
            <aside
                :class="sidebarOpen ? 'w-56' : 'w-0 overflow-hidden'"
                class="fixed top-14 left-0 bottom-0 transition-all duration-200 z-40 overflow-y-auto border-r border-gray-200 shadow-sm"
                style="background-color:#fff;"
            >
                <nav class="py-2">

                    <!-- Inicio -->
                    <NavItem href="/dashboard" icon="pi-home" label="Inicio" icon-color="#3452ff"
                        :active="isActive('/dashboard') || isActive('/')" />

                    <!-- Dashboard -->
                    <NavItem href="/dashboard" icon="pi-chart-bar" label="Dashboard" icon-color="#6366f1"
                        :active="false" />

                    <!-- ── Secciones admin ── -->
                    <template v-if="isAdmin">

                        <NavGroup label="Administrador" icon="pi-shield" icon-color="#3452ff"
                            :open="menus.admin" @toggle="menus.admin = !menus.admin">
                            <NavSub href="/admin/crear" label="Agregar Administrador" />
                            <NavSub href="/admin" label="Buscar Administrador" />
                        </NavGroup>

                        <NavGroup label="Secretarías" icon="pi-users" icon-color="#0ea5e9"
                            :open="menus.secretarias" @toggle="menus.secretarias = !menus.secretarias">
                            <NavSub href="/admin/secretarias/crear" label="Agregar Secretaria" />
                            <NavSub href="/admin/secretarias" label="Buscar Secretaria" />
                        </NavGroup>

                        <NavGroup label="Red Salud" icon="pi-building" icon-color="#8b5cf6"
                            :open="menus.holdings" @toggle="menus.holdings = !menus.holdings">
                            <NavSub href="/admin/holdings/crear" label="Agregar Red Salud" />
                            <NavSub href="/admin/holdings" label="Buscar Red Salud" />
                        </NavGroup>

                        <NavGroup label="Clínica" icon="pi-home" icon-color="#10b981"
                            :open="menus.clinicas" @toggle="menus.clinicas = !menus.clinicas">
                            <NavSub href="/admin/clinicas/crear" label="Agregar Clínica" />
                            <NavSub href="/admin/clinicas" label="Buscar Clínica" />
                        </NavGroup>

                        <NavGroup label="Radiólogos" icon="pi-star" icon-color="#f59e0b"
                            :open="menus.radiologos" @toggle="menus.radiologos = !menus.radiologos">
                            <NavSub href="/admin/radiologos/crear" label="Agregar Radiólogo" />
                            <NavSub href="/admin/radiologos" label="Buscar Radiólogo" />
                        </NavGroup>

                        <NavGroup label="Operadores" icon="pi-wrench" icon-color="#ef4444"
                            :open="menus.tecnicos" @toggle="menus.tecnicos = !menus.tecnicos">
                            <NavSub href="/admin/tecnicos/crear" label="Agregar Operador" />
                            <NavSub href="/admin/tecnicos" label="Buscar Operador" />
                        </NavGroup>

                        <NavGroup label="Odontólogos" icon="pi-user-plus" icon-color="#6366f1"
                            :open="menus.odontologos" @toggle="menus.odontologos = !menus.odontologos">
                            <NavSub href="/admin/odontologos/crear" label="Agregar Odontólogo" />
                            <NavSub href="/admin/odontologos" label="Buscar Odontólogo" />
                        </NavGroup>

                        <NavGroup label="Contralores" icon="pi-id-card" icon-color="#0b2a4a"
                            :open="menus.contralores" @toggle="menus.contralores = !menus.contralores">
                            <NavSub href="/admin/contralores/crear" label="Agregar Contralor" />
                            <NavSub href="/admin/contralores" label="Buscar Contralor" />
                        </NavGroup>

                        <NavGroup label="Contraloría" icon="pi-folder-open" icon-color="#64748b"
                            :open="menus.controloria" @toggle="menus.controloria = !menus.controloria">
                            <NavSub href="/admin/controloria/crear" label="Crear Contraloría" />
                            <NavSub href="/admin/controloria" label="Buscar Contraloría" />
                        </NavGroup>

                        <NavGroup label="Integraciones" icon="pi-link" icon-color="#8b5cf6"
                            :open="menus.integraciones" @toggle="menus.integraciones = !menus.integraciones">
                            <NavSub href="/admin/integraciones/crear" label="Crear API Key" />
                            <NavSub href="/admin/integraciones" label="Listar API Keys" />
                        </NavGroup>

                        <NavGroup label="Tipos de Examen" icon="pi-list" icon-color="#0891b2"
                            :open="menus.examenes" @toggle="menus.examenes = !menus.examenes">
                            <NavSub href="/admin/examenes/crear" label="Agregar Tipo" />
                            <NavSub href="/admin/examenes" label="Ver Tipos" />
                        </NavGroup>

                    </template>

                    <!-- Calendario (todos) -->
                    <NavItem href="/calendario" icon="pi-calendar" label="Calendario" icon-color="#0ea5e9"
                        :active="isActive('/calendario')" />

                    <!-- Pacientes (sin radiólogos) -->
                    <template v-if="!isRadiologo">
                        <NavGroup label="Pacientes" icon="pi-user" icon-color="#0b2a4a"
                            :open="menus.pacientes" @toggle="menus.pacientes = !menus.pacientes">
                            <NavSub href="/pacientes/crear" label="Agregar Paciente" />
                            <NavSub href="/pacientes" label="Buscar Paciente" />
                        </NavGroup>
                    </template>

                    <!-- Órdenes Radiográficas -->
                    <NavGroup label="Ordenes Radiográficas" icon="pi-file-edit" icon-color="#0284c7"
                        :open="menus.ordenes" @toggle="menus.ordenes = !menus.ordenes">
                        <NavSub v-if="!isRadiologo" href="/ordenes/crear" label="Crear Orden" />
                        <NavSub href="/ordenes" :label="isRadiologo ? 'Mis Órdenes' : 'Buscar Orden'" />
                    </NavGroup>

                    <!-- Excel (admin, secretaria y radiólogo) -->
                    <template v-if="isRadiologo">
                        <NavGroup label="Reportes" icon="pi-file-excel" icon-color="#16a34a"
                            :open="menus.excel" @toggle="menus.excel = !menus.excel">
                            <NavSub href="/admin/excel" label="Descargar Excel" />
                        </NavGroup>
                    </template>

                    <!-- Administración (admin) -->
                    <template v-if="isAdmin">
                        <NavGroup label="Administración" icon="pi-cog" icon-color="#64748b"
                            :open="menus.administracion" @toggle="menus.administracion = !menus.administracion">
                            <NavSub href="/admin/administracion/corregir" label="Corregir" />
                            <NavSub href="/admin/feriados/crear" label="Crear Feriado" />
                            <NavSub href="/admin/feriados" label="Buscar Feriado" />
                            <NavSub href="/admin/excel" label="Descargar Excel" />
                            <NavSub href="/admin/usuarios" label="Usuarios del sistema" />
                        </NavGroup>
                    </template>

                </nav>
            </aside>

            <!-- Contenido -->
            <main :class="sidebarOpen ? 'ml-56' : 'ml-0'"
                class="flex-1 transition-all duration-200 min-h-screen">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, defineComponent, h } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Select from 'primevue/select';

// ── Sub-components ────────────────────────────────────────────────────────

const NavItem = defineComponent({
    props: { href: String, icon: String, label: String, iconColor: String, active: Boolean },
    setup(props) {
        return () => h('a', {
            href: props.href,
            class: [
                'flex items-center gap-3 mx-2 px-3 py-2 rounded-lg text-sm transition-colors mb-0.5',
                props.active
                    ? 'bg-blue-50 text-blue-700 font-semibold'
                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
            ],
        }, [
            h('i', { class: `pi ${props.icon} text-base`, style: `color:${props.active ? '#3452ff' : props.iconColor}` }),
            h('span', { class: 'flex-1' }, props.label),
        ]);
    },
});

const NavSub = defineComponent({
    props: { href: String, label: String },
    setup(props) {
        const active = window.location.pathname === props.href;
        return () => h('a', {
            href: props.href,
            class: [
                'flex items-center gap-2 pl-9 pr-3 py-1.5 text-xs rounded-lg mx-2 transition-colors',
                active
                    ? 'text-blue-600 font-semibold bg-blue-50'
                    : 'text-gray-500 hover:text-gray-800 hover:bg-gray-100',
            ],
        }, [
            h('i', { class: 'pi pi-minus text-xs opacity-40' }),
            props.label,
        ]);
    },
});

const NavGroup = defineComponent({
    props: { label: String, icon: String, iconColor: String, open: Boolean },
    emits: ['toggle'],
    setup(props, { slots, emit }) {
        const path = window.location.pathname;
        return () => h('div', { class: 'mb-0.5' }, [
            h('button', {
                onClick: () => emit('toggle'),
                class: [
                    'w-full flex items-center gap-3 mx-2 px-3 py-2 rounded-lg text-sm transition-colors',
                    'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
                ],
                style: 'width: calc(100% - 16px);',
            }, [
                h('i', { class: `pi ${props.icon} text-base`, style: `color:${props.iconColor}` }),
                h('span', { class: 'flex-1 text-left' }, props.label),
                h('i', { class: `pi ${props.open ? 'pi-angle-down' : 'pi-angle-right'} text-xs opacity-50` }),
            ]),
            props.open ? h('div', { class: 'mt-0.5 mb-1' }, slots.default?.()) : null,
        ]);
    },
});

// ── Layout logic ──────────────────────────────────────────────────────────

const page          = usePage();
const sidebarOpen   = ref(true);
const path          = window.location.pathname;
const currentRegion = computed(() => page.props.region ?? 'CL');

const regionOptions = [
    { value: 'CL', label: 'Chile',   code: 'cl' },
    { value: 'UY', label: 'Uruguay', code: 'uy' },
];

const setRegion = (value) => {
    router.post(route('region.update'), { region: value }, { preserveScroll: true });
};

const menus = reactive({
    admin:       path.startsWith('/admin') && !path.startsWith('/admin/holdings') && !path.startsWith('/admin/clinicas') && !path.startsWith('/admin/radiologos') && !path.startsWith('/admin/odontologos') && !path.startsWith('/admin/tecnicos') && !path.startsWith('/admin/secretarias') && !path.startsWith('/admin/usuarios'),
    secretarias: path.startsWith('/admin/secretarias'),
    holdings:    path.startsWith('/admin/holdings'),
    clinicas:    path.startsWith('/admin/clinicas'),
    radiologos:  path.startsWith('/admin/radiologos'),
    tecnicos:    path.startsWith('/admin/tecnicos'),
    odontologos: path.startsWith('/admin/odontologos'),
    pacientes:   path.startsWith('/pacientes'),
    ordenes:     path.startsWith('/ordenes'),
    usuarios:       path.startsWith('/admin/usuarios'),
    contralores:    path.startsWith('/admin/contralores'),
    controloria:    path.startsWith('/admin/controloria'),
    integraciones:  path.startsWith('/admin/integraciones'),
    examenes:       path.startsWith('/admin/examenes'),
    administracion: path.startsWith('/admin/administracion') || path.startsWith('/admin/feriados') || path.startsWith('/admin/excel') || path.startsWith('/admin/usuarios'),
    excel:          path.startsWith('/admin/excel'),
});

const initials = computed(() => {
    const name = page.props.auth?.user?.name ?? '';
    return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
});

const isAdmin     = computed(() => {
    const user = page.props.auth?.user;
    return user?.roles?.includes('admin') || user?.type_id === 1;
});

const isRadiologo = computed(() => {
    const user = page.props.auth?.user;
    return user?.roles?.includes('radiologo') || user?.type_id === 5;
});

const isActive = (p) => window.location.pathname === p;
</script>
