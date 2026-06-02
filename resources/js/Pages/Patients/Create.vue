<template>
    <AppLayout title="Nuevo Paciente">
        <div class="p-6 max-w-2xl mx-auto">
            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('pacientes.index')">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <h1 class="text-2xl font-bold text-gray-800">Nuevo Paciente</h1>
            </div>

            <div class="bg-white rounded-xl shadow p-6">
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Nombre *</label>
                            <InputText v-model="form.name" placeholder="Nombre" class="w-full" :class="{'p-invalid': form.errors.name}" />
                            <small class="text-red-500">{{ form.errors.name }}</small>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Rut *</label>
                            <InputText
                                :value="form.rut"
                                class="w-full"
                                placeholder="Rut"
                                :class="{'p-invalid': form.errors.rut || rutError}"
                                @input="handleRutInput"
                                @blur="touchRut"
                            />
                            <small v-if="form.errors.rut" class="text-red-500">{{ form.errors.rut }}</small>
                            <small v-else-if="rutError" class="text-red-500">{{ rutError }}</small>

                            <div class="flex items-center gap-2 mt-2">
                                <Checkbox v-model="isPassport" :binary="true" inputId="esPassport" />
                                <label for="esPassport" class="text-sm cursor-pointer">Es pasaporte extranjero.</label>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">E-mail *</label>
                            <InputText v-model="form.email" type="email" placeholder="E-mail" class="w-full" :class="{'p-invalid': form.errors.email}" />
                            <small class="text-red-500">{{ form.errors.email }}</small>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Fecha de Nacimiento *</label>
                            <DatePicker
                                v-model="dateValue"
                                dateFormat="dd-mm-yy"
                                showIcon
                                placeholder="dd-mm-aaaa"
                                class="w-full"
                                :class="{'p-invalid': form.errors.dateofbirth}"
                            />
                            <small class="text-red-500">{{ form.errors.dateofbirth }}</small>
                        </div>

                        <div v-if="puedeDerivacion" class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Paciente derivado de</label>
                            <InputText v-model="form.derivado_de" placeholder="Clínica o médico derivador" class="w-full" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Pertenece a: *</label>
                            <MultiSelect
                                ref="clinicsRef"
                                v-model="form.clinics"
                                :options="clinics"
                                optionLabel="name"
                                optionValue="id"
                                placeholder="Selecciona clínica(s)"
                                class="w-full"
                                :class="{'p-invalid': form.errors.clinics}"
                                filter
                                filterPlaceholder="Buscar clínica..."
                                @change="clinicsRef?.hide()"
                            />
                            <small class="text-red-500">{{ form.errors.clinics }}</small>
                        </div>

                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <Link :href="route('pacientes.index')">
                            <Button label="Cancelar" severity="secondary" type="button" />
                        </Link>
                        <Button label="Guardar Paciente" icon="pi pi-save" type="submit" :loading="form.processing" />
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputText from 'primevue/inputtext';
import DatePicker from 'primevue/datepicker';
import MultiSelect from 'primevue/multiselect';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';

const props = defineProps({
    clinics: Array,
});

const page = usePage();
const puedeDerivacion = computed(() => !!page.props.auth?.user?.puede_derivacion_clinica);

const dateValue  = ref(null);
const isPassport = ref(false);
const rutTouched = ref(false);

const form = useForm({
    name:        '',
    rut:         '',
    email:       '',
    clinics:     [],
    derivado_de: '',
});

watch(isPassport, () => {
    form.rut         = '';
    rutTouched.value = false;
});

function handleRutInput(event) {
    if (isPassport.value) {
        form.rut = event.target.value;
        return;
    }
    let clean = event.target.value.replace(/[.\s-]/g, '').replace(/[^0-9kK]/g, '').toUpperCase();
    clean = clean.slice(0, 9);
    form.rut = clean.length > 1 ? clean.slice(0, -1) + '-' + clean.slice(-1) : clean;
}

function validateChileanRut(rut) {
    if (!rut) return 'El RUT es requerido.';
    const clean = rut.replace(/[\.\s]/g, '').toUpperCase();
    if (!/^\d{7,8}-[\dK]$/.test(clean)) return 'Formato inválido. Ej: 12345678-9';
    const [body, dv] = clean.split('-');
    let sum = 0, mult = 2;
    for (let i = body.length - 1; i >= 0; i--) {
        sum += parseInt(body[i]) * mult;
        mult = mult === 7 ? 2 : mult + 1;
    }
    const rem      = sum % 11;
    const expected = rem === 0 ? '0' : rem === 1 ? 'K' : String(11 - rem);
    if (dv !== expected) return 'El RUT ingresado no es válido.';
    return null;
}

const rutError = computed(() => {
    if (isPassport.value || !rutTouched.value) return null;
    return validateChileanRut(form.rut);
});

function touchRut() { rutTouched.value = true; }

const submit = () => {
    rutTouched.value = true;
    if (!isPassport.value && validateChileanRut(form.rut)) return;

    let dateofbirth = null;
    if (dateValue.value instanceof Date) {
        const d = dateValue.value;
        dateofbirth = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    const data = { ...form.data(), dateofbirth, es_pasaporte: isPassport.value };
    form.transform(() => data).post(route('pacientes.store'));
};
</script>
