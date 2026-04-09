<template>
    <AppLayout title="Mi Perfil">
        <div class="p-6 max-w-2xl mx-auto">

            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Mi Perfil</h1>
                <p class="text-sm text-gray-400 mt-0.5">Actualiza tu información personal y contraseña</p>
            </div>

            <Message v-if="$page.props.flash?.success" severity="success" class="mb-5">
                {{ $page.props.flash.success }}
            </Message>

            <!-- Avatar + nombre -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-5 flex items-center gap-5">
                <div class="w-16 h-16 rounded-full flex items-center justify-center text-white text-xl font-bold flex-shrink-0"
                    style="background-color:#3452ff;">
                    {{ initials }}
                </div>
                <div>
                    <p class="text-lg font-bold text-gray-800">{{ user.name }}</p>
                    <p class="text-sm text-gray-400">@{{ user.username }}</p>
                    <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                        {{ tipoLabel }}
                    </span>
                </div>
            </div>

            <!-- Datos personales -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-5">
                <h2 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="pi pi-user text-blue-500" /> Datos personales
                </h2>
                <form @submit.prevent="submitPerfil">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Nombre completo *</label>
                            <InputText v-model="perfil.name" class="w-full"
                                :class="{ 'p-invalid': perfil.errors.name }" />
                            <small class="text-red-500">{{ perfil.errors.name }}</small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Email</label>
                            <InputText v-model="perfil.mail" type="email" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Teléfono</label>
                            <InputText v-model="perfil.telephone" class="w-full" />
                        </div>
                    </div>
                    <div class="flex justify-end mt-4">
                        <Button label="Guardar Datos" icon="pi pi-save" type="submit"
                            :loading="perfil.processing"
                            style="background-color:#3452ff;border-color:#3452ff;" />
                    </div>
                </form>
            </div>

            <!-- Cambiar contraseña -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="pi pi-lock text-orange-500" /> Cambiar contraseña
                </h2>
                <form @submit.prevent="submitPassword">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Contraseña actual *</label>
                            <InputText v-model="pass.current_password" type="password" class="w-full"
                                :class="{ 'p-invalid': pass.errors.current_password }" />
                            <small class="text-red-500">{{ pass.errors.current_password }}</small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Nueva contraseña *</label>
                            <InputText v-model="pass.password" type="password" class="w-full"
                                :class="{ 'p-invalid': pass.errors.password }" />
                            <small class="text-red-500">{{ pass.errors.password }}</small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Confirmar nueva contraseña *</label>
                            <InputText v-model="pass.password_confirmation" type="password" class="w-full" />
                        </div>
                    </div>
                    <div class="flex justify-end mt-4">
                        <Button label="Cambiar Contraseña" icon="pi pi-key" type="submit"
                            :loading="pass.processing" severity="warning" />
                    </div>
                </form>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';

const props = defineProps({ user: Object });

const tipos = {
    1: 'Administrador', 2: 'Secretaria', 3: 'Holding', 4: 'Clínica',
    5: 'Radiólogo', 6: 'Odontólogo', 7: 'Contralor', 11: 'Técnico',
};

const tipoLabel = computed(() => tipos[props.user.type_id] ?? 'Usuario');
const initials  = computed(() => {
    return (props.user.name ?? '').split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
});

const perfil = useForm({
    name:      props.user.name      ?? '',
    mail:      props.user.mail      ?? '',
    telephone: props.user.telephone ?? '',
});

const pass = useForm({
    current_password:      '',
    password:              '',
    password_confirmation: '',
});

const submitPerfil   = () => perfil.put(route('perfil.update'));
const submitPassword = () => pass.put(route('perfil.password'), {
    onSuccess: () => pass.reset(),
});
</script>
