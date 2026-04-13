<template>
    <AppLayout title="Pacientes">
        <div class="p-6">

            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Pacientes</h1>
                    <p class="text-sm text-gray-400 mt-0.5">{{ total }} pacientes encontrados</p>
                </div>
                <Link :href="route('pacientes.create')">
                    <Button label="Nuevo Paciente" icon="pi pi-plus"
                        style="background-color:#3452ff;border-color:#3452ff;" />
                </Link>
            </div>

            <!-- Buscador -->
            <div class="mb-5 bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText
                        v-model="searchTerm"
                        :placeholder="`Buscar por nombre, ${terms.id_label} o email...`"
                        class="w-full md:w-96"
                        @input="onSearch"
                    />
                </IconField>
            </div>

            <!-- Tabla con skeleton -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead style="background-color:#f8fafc;">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide w-36">{{ terms.id_label }}</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Nombre</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">Email</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell w-28">Edad</th>
                            <th class="w-16"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Skeleton -->
                        <template v-if="loading">
                            <tr v-for="n in 8" :key="n" class="border-t border-gray-50 animate-pulse">
                                <td class="px-5 py-3.5"><div class="h-3 bg-gray-100 rounded w-24" /></td>
                                <td class="px-5 py-3.5"><div class="h-3 bg-gray-100 rounded w-40" /></td>
                                <td class="px-5 py-3.5 hidden md:table-cell"><div class="h-3 bg-gray-100 rounded w-36" /></td>
                                <td class="px-5 py-3.5 hidden lg:table-cell"><div class="h-3 bg-gray-100 rounded w-10" /></td>
                                <td class="px-5 py-3.5"></td>
                            </tr>
                        </template>

                        <!-- Datos -->
                        <template v-else-if="patients.length">
                            <tr v-for="p in patients" :key="p.id"
                                class="border-t border-gray-50 hover:bg-blue-50/30 transition cursor-pointer"
                                @click="router.visit(route('pacientes.edit', p.id))">
                                <td class="px-5 py-3.5 font-mono text-xs text-gray-500">{{ p.rut }}</td>
                                <td class="px-5 py-3.5 font-medium text-gray-800">{{ p.name }}</td>
                                <td class="px-5 py-3.5 text-gray-500 hidden md:table-cell">{{ p.email || '-' }}</td>
                                <td class="px-5 py-3.5 text-gray-500 hidden lg:table-cell">{{ calcAge(p.dateofbirth) }}</td>
                                <td class="px-5 py-3.5 text-right">
                                    <i class="pi pi-chevron-right text-gray-300 text-xs" />
                                </td>
                            </tr>
                        </template>

                        <!-- Empty state -->
                        <tr v-else>
                            <td colspan="5" class="px-5 py-16 text-center">
                                <i class="pi pi-users text-4xl text-gray-200 block mb-3" />
                                <p class="text-gray-400 font-medium">
                                    {{ searchTerm ? `No se encontraron pacientes con ese nombre o ${terms.id_label}.` : `Ingresa un nombre o ${terms.id_label} para buscar.` }}
                                </p>
                                <Link v-if="!searchTerm" :href="route('pacientes.create')"
                                    class="inline-block mt-3 text-sm text-blue-600 hover:underline">
                                    + Crear primer paciente
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Paginación -->
                <div v-if="total > perPage" class="flex justify-between items-center px-5 py-3 border-t border-gray-100">
                    <span class="text-xs text-gray-400">Mostrando {{ patients.length }} de {{ total }}</span>
                    <Paginator
                        :rows="perPage"
                        :totalRecords="total"
                        :first="(currentPage - 1) * perPage"
                        @page="onPageChange"
                    />
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Paginator from 'primevue/paginator';
import { useTerms } from '@/composables/useTerms.js';

const { terms } = useTerms();

const patients    = ref([]);
const loading     = ref(true);
const searchTerm  = ref('');
const total       = ref(0);
const currentPage = ref(1);
const perPage     = ref(15);
let   searchTimer = null;

const fetchPatients = async () => {
    loading.value = true;
    try {
        const res = await fetch(
            route('pacientes.search') +
            `?q=${encodeURIComponent(searchTerm.value)}&page=${currentPage.value}&per_page=${perPage.value}`
        );
        const json = await res.json();
        patients.value = json.data;
        total.value    = json.total;
    } finally {
        loading.value = false;
    }
};

const onSearch = () => {
    clearTimeout(searchTimer);
    currentPage.value = 1;
    searchTimer = setTimeout(fetchPatients, 350);
};

const onPageChange = (e) => {
    currentPage.value = e.page + 1;
    fetchPatients();
};

const calcAge = (dob) => {
    if (!dob) return '-';
    const birth = new Date(dob);
    const diff  = Date.now() - birth.getTime();
    const age   = Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25));
    return isNaN(age) ? '-' : `${age} años`;
};

onMounted(fetchPatients);
</script>
