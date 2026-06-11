<template>
    <AppLayout title="Ver Orden">

        <ImageViewer :src="lightbox.open ? lightbox.src : null" :name="lightbox.name" @close="lightbox.open = false" />

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
                    <span :class="prioridadBadgeClass(order.prioridad)"
                        class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold">
                        <i class="pi pi-clock text-xs" /> {{ order.prioridad }}
                    </span>
                </div>
                <!-- Actions -->
                <div class="flex items-center gap-2">
                    <Link v-if="canEdit" :href="route('ordenes.edit', order.id)">
                        <Button label="Editar" icon="pi pi-pencil" size="small" severity="secondary" />
                    </Link>
                    <a :href="route('ordenes.zip', order.id)">
                        <Button label="ZIP" icon="pi pi-download" size="small" severity="secondary" />
                    </a>
                    <a v-if="order.estadoradiologo == 1 || esRadiologo || esAdmin"
                        :href="route('ordenes.pdf', order.id)" target="_blank">
                        <Button label="Imprimir" icon="pi pi-print" size="small" severity="secondary" />
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
                                :class="order.prioridad === '1 día' || order.prioridad === 'Urgente' ? 'text-red-600' : order.prioridad === '2 días' ? 'text-orange-600' : 'text-gray-700'">
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
                    <p class="text-xs text-orange-500 mb-1">Fecha: <strong>{{ localDateTime(c.enviada ?? c.created_at) }}</strong></p>
                    <p v-if="c.description" class="text-sm text-gray-700">{{ c.description }}</p>
                    <p v-else-if="c.detalle" class="text-sm text-gray-700">{{ c.detalle }}</p>
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
                                :class="order.estadoradiologo == 1 && examen.respuesta ? 'bg-green-500 text-white' : 'bg-gray-600 text-gray-200'">
                                <i :class="order.estadoradiologo == 1 && examen.respuesta ? 'pi pi-check' : 'pi pi-clock'" class="text-xs" />
                                {{ order.estadoradiologo == 1 && examen.respuesta ? 'Informado' : 'Pendiente' }}
                            </span>
                            <button v-if="esAdmin && order.estadoradiologo != 1"
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
                                    <FileThumbnail :file="f" @lightbox="openLightbox" :showDicom="parseInt(examen.grupo ?? 0) === 4" />
                                    <button v-if="esAdmin && order.estadoradiologo != 1"
                                        @click="eliminarArchivo(f.id)"
                                        class="absolute top-1 right-1 hidden group-hover:flex items-center justify-center w-5 h-5 rounded-full bg-red-500 text-white shadow"
                                        title="Eliminar archivo">
                                        <i class="pi pi-times" style="font-size:9px" />
                                    </button>
                                </div>
                            </div>
                            <p v-else class="text-xs text-gray-400 italic">Sin archivos de imagen.</p>
                        </div>

                        <!-- Informe del radiólogo (solo visible cuando está Informada, o para radiologo/admin) -->
                        <div class="border-t border-gray-100 pt-4">
                            <div v-if="examen.respuesta && (order.estadoradiologo == 1 || esRadiologo || esAdmin)">
                                <!-- Toggle button -->
                                <button type="button"
                                    @click="showInforme[idx] = !showInforme[idx]"
                                    class="flex items-center gap-2 text-xs font-semibold text-green-700 uppercase tracking-wide mb-2 hover:text-green-800 transition-colors">
                                    <i class="pi pi-check-circle text-xs" />
                                    {{ showInforme[idx] ? 'Ocultar informe del radiólogo' : 'Mostrar informe del radiólogo' }}
                                    <i :class="showInforme[idx] ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" class="text-xs" />
                                </button>

                                <template v-if="showInforme[idx]">
                                    <div v-if="examen.respuesta.solo_adjunto && !examen.respuesta.campo_1 && !examen.respuesta.campo_2"
                                        class="text-xs text-gray-400 italic mb-2">Informe adjunto como archivo.</div>

                                    <!-- Panorámica -->
                                    <template v-if="examen.respuesta.informe_examen || examen.respuesta.informe_libre || examen.respuesta.informe_impresion">
                                        <div v-if="examen.respuesta.informe_examen" class="mb-3">
                                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Examen</p>
                                            <div class="bg-green-50 border border-green-100 rounded-lg p-3 text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.informe_examen }}</div>
                                        </div>
                                        <div v-if="examen.respuesta.informe_libre" class="mb-3">
                                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Informe</p>
                                            <div class="bg-green-50 border border-green-100 rounded-lg p-3 text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.informe_libre }}</div>
                                        </div>
                                        <div v-if="examen.respuesta.informe_impresion" class="mb-3">
                                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Impresión Diagnóstica</p>
                                            <div class="bg-green-50 border border-green-100 rounded-lg p-3 text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.informe_impresion }}</div>
                                        </div>
                                    </template>

                                    <!-- Estándar: campo_1/2/3 (no aplica para Panorámica que tiene su propia sección) -->
                                    <template v-else-if="examen.kind_id != 15">
                                        <div v-if="examen.respuesta.campo_1" class="mb-3">
                                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Examen</p>
                                            <div class="bg-green-50 border border-green-100 rounded-lg p-3 text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_1 }}</div>
                                        </div>
                                        <div v-if="examen.respuesta.campo_2" class="mb-3">
                                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Informe</p>
                                            <div class="bg-green-50 border border-green-100 rounded-lg p-3 text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_2 }}</div>
                                        </div>
                                        <div v-if="examen.respuesta.campo_3" class="mb-3">
                                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Impresión Diagnóstica</p>
                                            <div class="bg-green-50 border border-green-100 rounded-lg p-3 text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_3 }}</div>
                                        </div>
                                    </template>

                                    <!-- Panorámica: mapeo específico (campo_8=obs Maxilar, campo_4=obs Mandíbula) -->
                                    <template v-if="examen.kind_id == 15">
                                        <template v-if="examen.respuesta.campo_2 || examen.respuesta.campo_3 || examen.respuesta.campo_5 || examen.respuesta.campo_8 ||
                                            getDientesConContenido(examen.respuesta).some(d => [11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28,51,52,53,54,55,61,62,63,64,65].includes(d.n))">
                                            <div class="border border-blue-100 rounded-xl overflow-hidden mb-3 mt-2">
                                                <div class="bg-blue-700 px-4 py-2"><h3 class="text-white font-semibold text-sm">Maxilar</h3></div>
                                                <div class="p-3 space-y-2">
                                                    <div v-if="examen.respuesta.campo_2"><p class="text-xs font-semibold text-gray-500">Nivel Óseo Marginal</p><p class="text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_2 }}</p></div>
                                                    <div v-if="examen.respuesta.campo_3"><p class="text-xs font-semibold text-gray-500">Cálculo dentario marginal</p><p class="text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_3 }}</p></div>
                                                    <div v-if="examen.respuesta.campo_5"><p class="text-xs font-semibold text-gray-500">Dientes Ausentes</p><p class="text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_5 }}</p></div>
                                                    <div v-if="examen.respuesta.campo_8"><p class="text-xs font-semibold text-gray-500">Observaciones</p><p class="text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_8 }}</p></div>
                                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-1">
                                                        <div v-for="d in getDientesConContenido(examen.respuesta).filter(d => [11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28,51,52,53,54,55,61,62,63,64,65].includes(d.n))" :key="d.n"
                                                            class="bg-green-50 border border-green-100 rounded-lg p-2">
                                                            <p class="text-xs font-semibold text-green-700 mb-0.5">Diente {{ toDot(d.n) }}</p>
                                                            <p class="text-xs text-gray-700 whitespace-pre-wrap">{{ d.val }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-if="examen.respuesta.campo_6 || examen.respuesta.campo_7 || examen.respuesta.campo_9 || examen.respuesta.campo_4 ||
                                            getDientesConContenido(examen.respuesta).some(d => [31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48,71,72,73,74,75,81,82,83,84,85].includes(d.n))">
                                            <div class="border border-blue-100 rounded-xl overflow-hidden mb-3">
                                                <div class="bg-blue-700 px-4 py-2"><h3 class="text-white font-semibold text-sm">Mandíbula</h3></div>
                                                <div class="p-3 space-y-2">
                                                    <div v-if="examen.respuesta.campo_6"><p class="text-xs font-semibold text-gray-500">Nivel Óseo Marginal</p><p class="text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_6 }}</p></div>
                                                    <div v-if="examen.respuesta.campo_7"><p class="text-xs font-semibold text-gray-500">Cálculo dentario marginal</p><p class="text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_7 }}</p></div>
                                                    <div v-if="examen.respuesta.campo_9"><p class="text-xs font-semibold text-gray-500">Dientes Ausentes</p><p class="text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_9 }}</p></div>
                                                    <div v-if="examen.respuesta.campo_4"><p class="text-xs font-semibold text-gray-500">Observaciones</p><p class="text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_4 }}</p></div>
                                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-1">
                                                        <div v-for="d in getDientesConContenido(examen.respuesta).filter(d => [31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48,71,72,73,74,75,81,82,83,84,85].includes(d.n))" :key="d.n"
                                                            class="bg-green-50 border border-green-100 rounded-lg p-2">
                                                            <p class="text-xs font-semibold text-green-700 mb-0.5">Diente {{ toDot(d.n) }}</p>
                                                            <p class="text-xs text-gray-700 whitespace-pre-wrap">{{ d.val }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </template>

                                    <!-- Retro/BW: secciones Maxilar/Mandíbula con mapeo original campo_2-7 -->
                                    <template v-else-if="getDientesConContenido(examen.respuesta).length">
                                        <template v-if="examen.respuesta.campo_2 || examen.respuesta.campo_3 || examen.respuesta.campo_4 ||
                                            getDientesConContenido(examen.respuesta).some(d => [11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28,51,52,53,54,55,61,62,63,64,65].includes(d.n))">
                                            <div class="border border-blue-100 rounded-xl overflow-hidden mb-3 mt-2">
                                                <div class="bg-blue-700 px-4 py-2"><h3 class="text-white font-semibold text-sm">Maxilar</h3></div>
                                                <div class="p-3 space-y-2">
                                                    <div v-if="examen.respuesta.campo_2"><p class="text-xs font-semibold text-gray-500">Nivel Óseo Marginal</p><p class="text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_2 }}</p></div>
                                                    <div v-if="examen.respuesta.campo_3"><p class="text-xs font-semibold text-gray-500">Cálculo dentario marginal</p><p class="text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_3 }}</p></div>
                                                    <div v-if="examen.respuesta.campo_4"><p class="text-xs font-semibold text-gray-500">Observaciones</p><p class="text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_4 }}</p></div>
                                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-1">
                                                        <div v-for="d in getDientesConContenido(examen.respuesta).filter(d => [11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28,51,52,53,54,55,61,62,63,64,65].includes(d.n))" :key="d.n"
                                                            class="bg-green-50 border border-green-100 rounded-lg p-2">
                                                            <p class="text-xs font-semibold text-green-700 mb-0.5">Diente {{ toDot(d.n) }}</p>
                                                            <p class="text-xs text-gray-700 whitespace-pre-wrap">{{ d.val }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-if="examen.respuesta.campo_5 || examen.respuesta.campo_6 || examen.respuesta.campo_7 ||
                                            getDientesConContenido(examen.respuesta).some(d => [31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48,71,72,73,74,75,81,82,83,84,85].includes(d.n))">
                                            <div class="border border-blue-100 rounded-xl overflow-hidden mb-3">
                                                <div class="bg-blue-700 px-4 py-2"><h3 class="text-white font-semibold text-sm">Mandíbula</h3></div>
                                                <div class="p-3 space-y-2">
                                                    <div v-if="examen.respuesta.campo_5"><p class="text-xs font-semibold text-gray-500">Nivel Óseo Marginal</p><p class="text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_5 }}</p></div>
                                                    <div v-if="examen.respuesta.campo_6"><p class="text-xs font-semibold text-gray-500">Cálculo dentario marginal</p><p class="text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_6 }}</p></div>
                                                    <div v-if="examen.respuesta.campo_7"><p class="text-xs font-semibold text-gray-500">Observaciones</p><p class="text-sm text-gray-700 whitespace-pre-wrap">{{ examen.respuesta.campo_7 }}</p></div>
                                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-1">
                                                        <div v-for="d in getDientesConContenido(examen.respuesta).filter(d => [31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48,71,72,73,74,75,81,82,83,84,85].includes(d.n))" :key="d.n"
                                                            class="bg-green-50 border border-green-100 rounded-lg p-2">
                                                            <p class="text-xs font-semibold text-green-700 mb-0.5">Diente {{ toDot(d.n) }}</p>
                                                            <p class="text-xs text-gray-700 whitespace-pre-wrap">{{ d.val }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </template>

                                    <!-- Archivos del informe -->
                                    <div v-if="examen.archivos_informe?.length" class="mt-4">
                                        <p class="text-xs text-gray-500 mb-2">Archivos adjuntos del informe:</p>
                                        <div class="flex flex-wrap gap-3">
                                            <FileThumbnail
                                                v-for="f in examen.archivos_informe" :key="f.id"
                                                :file="f"
                                                @lightbox="openLightbox"
                                                :showDicom="parseInt(examen.grupo ?? 0) === 4" />
                                        </div>
                                    </div>

                                    <a v-if="examen.url_texto" :href="examen.url_texto" target="_blank"
                                        class="inline-flex items-center gap-1 text-xs text-blue-600 mt-2 hover:underline">
                                        <i class="pi pi-external-link text-xs" /> Ver informe externo
                                    </a>
                                </template>
                            </div>
                            <div v-else>
                                <p class="text-xs text-gray-400 italic flex items-center gap-1">
                                    <i class="pi pi-clock text-xs" /> Sin informe aún
                                </p>
                            </div>
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
                    <a :href="route('ordenes.pdf', order.id)" target="_blank">
                        <Button label="Imprimir" icon="pi pi-print" severity="secondary" />
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
import { computed, reactive, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import { useTerms } from '@/composables/useTerms.js';

const { terms, examLabel } = useTerms();
import FileThumbnail from '@/Components/FileThumbnail.vue';
import ImageViewer   from '@/Components/ImageViewer.vue';

const props = defineProps({
    order:          Object,
    paciente:       Object,
    clinica:        String,
    odontologo:     Object,
    radiologos:     Array,
    correcciones:   Array,
    examenes:       Array,
    puedeResponder: Boolean,
    canEdit:        Boolean,
    esAdmin:        Boolean,
    esRadiologo:    Boolean,
});

const lightbox = reactive({ open: false, src: '', name: '' });

// Auto-reload si hay archivos CBCT en estado procesando
let pollingInterval = null;
const hasProcessingFiles = computed(() =>
    props.examenes?.some(ex => ex.archivos?.some(f => f.ruta_dcm === 'processing'))
);

onMounted(() => {
    if (hasProcessingFiles.value) {
        pollingInterval = setInterval(() => {
            router.reload({ only: ['examenes'], preserveScroll: true });
        }, 15000); // recarga cada 15 segundos
    }
});

onUnmounted(() => {
    if (pollingInterval) clearInterval(pollingInterval);
});

const showInforme = reactive(
    Object.fromEntries((props.examenes ?? []).map((_, i) => [i, false]))
);

const DIENTES_PERM = [11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28,31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48];
const DIENTES_TEMP = [51,52,53,54,55,61,62,63,64,65,71,72,73,74,75,81,82,83,84,85];

function toDot(n) { const s = String(n); return s[0] + '.' + s[1]; }

function localDateTime(utcStr) {
    if (!utcStr) return '-';
    const d = new Date(utcStr.replace(' ', 'T') + (utcStr.includes('T') ? '' : 'Z'));
    return d.toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });
}

function getDientesConContenido(respuesta) {
    if (!respuesta) return [];
    return [...DIENTES_PERM, ...DIENTES_TEMP]
        .filter(n => respuesta[`diente_${n}`])
        .map(n => ({ n, val: respuesta[`diente_${n}`] }));
}

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

function prioridadBadgeClass(p) {
    if (p === '1 día' || p === 'Urgente') return 'bg-red-100 text-red-700';
    if (p === '2 días') return 'bg-orange-100 text-orange-700';
    return 'bg-gray-100 text-gray-600';
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
