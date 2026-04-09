<template>
    <div class="min-h-screen flex flex-col" style="background:#fff;">

        <!-- ── Header ── -->
        <header class="shrink-0 border-b border-gray-100 shadow-sm">
            <div class="max-w-6xl mx-auto px-8 py-3 flex items-center justify-between gap-6">
                <a href="https://www.dimage.cl/" target="_blank">
                    <img src="/images/logo.png" alt="DIMAGE" class="h-12 object-contain" />
                </a>
                <!-- Nav desktop -->
                <nav class="hidden lg:flex items-center gap-6">
                    <a v-for="link in navLinks.slice(0, 4)" :key="link.label"
                        :href="link.href" target="_blank"
                        class="text-sm text-gray-500 hover:text-gray-800 transition-colors font-medium">
                        {{ link.label }}
                    </a>
                </nav>
                <div class="flex items-center gap-3 shrink-0">
                    <a href="https://app.dimage.cl/revisar-orden" target="_blank"
                        class="hidden sm:inline-flex items-center px-4 py-2 rounded-lg border border-gray-200 text-sm font-semibold text-gray-600 hover:border-gray-400 transition">
                        Acceso Pacientes
                    </a>
                    <span class="px-4 py-2 rounded-lg text-sm font-semibold text-white"
                        style="background:#1b96cc;">
                        Acceso MorfoX
                    </span>
                </div>
            </div>
        </header>

        <!-- ── Cuerpo ── -->
        <main class="flex-1 flex items-center justify-center px-6 py-14">
            <div class="flex flex-col lg:flex-row items-center gap-16 w-full max-w-5xl">

                <!-- Foto izquierda -->
                <div class="hidden lg:block w-1/2 shrink-0">
                    <img src="/images/agency.jpg" alt="Médicos"
                        class="w-full object-cover rounded-lg shadow-md"
                        style="height:360px;" />
                </div>

                <!-- Formulario derecha -->
                <div class="flex-1 w-full max-w-sm">

                    <!-- Flash -->
                    <div v-if="$page.props.flash?.success"
                        class="flex items-center gap-2 px-4 py-3 rounded-lg border border-cyan-200 bg-cyan-50 text-cyan-800 text-sm mb-6">
                        <i class="pi pi-check-circle text-cyan-500" />
                        {{ $page.props.flash.success }}
                    </div>
                    <div v-if="$page.props.flash?.error"
                        class="flex items-center gap-2 px-4 py-3 rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm mb-6">
                        <i class="pi pi-times-circle" />
                        {{ $page.props.flash.error }}
                    </div>

                    <h2 class="text-3xl font-bold mb-8" style="color:#1b96cc;">
                        Inicio de Sesión
                    </h2>

                    <form @submit.prevent="submit" class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Usuario</label>
                            <InputText v-model="form.username" type="text" class="w-full"
                                :class="{'p-invalid': form.errors.username}"
                                placeholder="Usuario" autocomplete="username" />
                            <small class="text-red-500 mt-1 block" v-if="form.errors.username">
                                {{ form.errors.username }}
                            </small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Contraseña</label>
                            <Password v-model="form.password" :feedback="false" toggleMask
                                :class="{'p-invalid': form.errors.password}"
                                placeholder="Contraseña" autocomplete="current-password"
                                inputClass="w-full" class="w-full" />
                            <small class="text-red-500 mt-1 block" v-if="form.errors.password">
                                {{ form.errors.password }}
                            </small>
                        </div>
                        <Button type="submit" label="Iniciar Sesión" class="w-full"
                            :loading="form.processing"
                            style="background:#1b96cc;border-color:#1b96cc;
                                   height:44px;font-weight:600;font-size:15px;" />
                    </form>
                </div>
            </div>
        </main>

        <!-- ── Footer 3 columnas ── -->
        <footer style="background:#0d0d0d;" class="shrink-0 pt-12 pb-6 px-8">
            <div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-12 mb-10">

                <!-- Col 1 -->
                <div>
                    <div class="flex items-center gap-3 mb-5">
                        <div class="rounded-lg overflow-hidden shrink-0" style="background:#fff; padding:4px;">
                            <img src="/images/dimage_logo.png" alt="DIMAGE"
                                class="h-9 w-9 object-contain block" />
                        </div>
                        <div>
                            <div class="text-white font-bold tracking-widest leading-none">DIMAGE</div>
                            <div class="text-gray-500 text-xs mt-0.5">Imagenología Oral y Maxilofacial</div>
                        </div>
                    </div>
                    <p class="text-gray-500 text-sm leading-relaxed">
                        Empresa innovadora dedicada al telediagnóstico imagenológico oral y maxilofacial
                        que a través de nuestro software y servicio busca resolver las distintas necesidades
                        en el área, complementando de manera eficiente y efectiva el trabajo de nuestros
                        clientes y de esta forma ser parte de su equipo de trabajo.
                    </p>
                    <a href="https://www.linkedin.com/company/dimage" target="_blank"
                        class="inline-flex mt-5 w-9 h-9 rounded-full items-center justify-center text-white hover:opacity-80 transition"
                        style="background:#0077b5;">
                        <i class="pi pi-linkedin text-sm" />
                    </a>
                </div>

                <!-- Col 2 -->
                <div>
                    <h4 class="text-white text-xs font-semibold uppercase tracking-widest mb-5">Navegación</h4>
                    <ul class="space-y-3">
                        <li v-for="link in navLinks" :key="link.label">
                            <a :href="link.href" target="_blank"
                                class="text-gray-500 hover:text-white text-sm transition-colors">
                                {{ link.label }}
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Col 3 -->
                <div>
                    <h4 class="text-white text-xs font-semibold uppercase tracking-widest mb-5">¿Ya Eres Cliente?</h4>
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-9 h-9 rounded-full border border-gray-700 flex items-center justify-center shrink-0">
                            <i class="pi pi-envelope text-gray-400 text-sm" />
                        </div>
                        <a href="mailto:contacto@dimage.cl" class="text-gray-400 hover:text-white text-sm transition-colors">
                            contacto@dimage.cl
                        </a>
                    </div>
                    <a href="https://wa.me/56912345678" target="_blank"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-white text-sm font-semibold hover:opacity-90 transition"
                        style="background:#00bfa5;">
                        SOPORTE MORFOX <i class="pi pi-whatsapp" />
                    </a>
                </div>
            </div>

            <div class="max-w-6xl mx-auto border-t border-gray-800 pt-6 flex flex-wrap items-center justify-between gap-3">
                <p class="text-gray-600 text-xs">© {{ new Date().getFullYear() }} DIMAGE · Todos los derechos reservados.</p>
                <p class="text-gray-700 text-xs">Telediagnóstico Imagenológico</p>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Password  from 'primevue/password';
import Button    from 'primevue/button';

const form = useForm({ username: '', password: '', remember: false });
const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });

const navLinks = [
    { label: 'Inicio',            href: 'https://www.dimage.cl/' },
    { label: 'Quiénes Somos',     href: 'https://www.dimage.cl/quienes-somos/' },
    { label: 'Plataforma Morfox', href: 'https://www.dimage.cl/plataforma-morfox/' },
    { label: 'Contacto',          href: 'https://www.dimage.cl/contacto/' },
    { label: 'Acceso Pacientes',  href: 'https://app.dimage.cl/revisar-orden' },
];
</script>
