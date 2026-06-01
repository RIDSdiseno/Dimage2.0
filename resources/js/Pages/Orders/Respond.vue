<template>
    <AppLayout title="Responder Orden">

        <ImageViewer :src="lightbox.open ? lightbox.src : null" :name="lightbox.name" @close="lightbox.open = false" />

        <div class="p-6 max-w-5xl mx-auto">

            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('ordenes.show', order.id)">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Responder Orden #{{ order.id }}</h1>
                    <p class="text-sm text-gray-400 mt-0.5">Completa el informe para cada examen</p>
                </div>
            </div>

            <!-- Info cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-400 mb-1">Paciente</p>
                    <p class="font-semibold text-gray-800">{{ paciente?.name }}</p>
                    <p class="text-sm text-gray-500">{{ paciente?.rut }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-400 mb-1">Clínica</p>
                    <p class="font-semibold text-gray-800">{{ clinica }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-400 mb-1">Prioridad</p>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold"
                        :class="order.prioridad === '1 día' || order.prioridad === 'Urgente' ? 'bg-red-100 text-red-700' : order.prioridad === '2 días' ? 'bg-orange-100 text-orange-700' : 'bg-blue-50 text-blue-700'">
                        <i class="pi pi-clock text-xs" />
                        {{ order.prioridad }}
                    </span>
                    <p class="text-xs text-gray-400 mt-1">Enviada: {{ order.enviada }}</p>
                </div>
            </div>

            <!-- Diagnóstico -->
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-6">
                <p class="text-xs font-semibold text-blue-600 uppercase tracking-wide mb-1">Diagnóstico clínico</p>
                <p class="text-sm text-gray-700">{{ order.diagnostico }}</p>
                <p v-if="order.observaciones" class="text-sm text-gray-500 mt-1">
                    <span class="font-medium">Obs:</span> {{ order.observaciones }}
                </p>
            </div>

            <!-- Solo adjuntar informe -->
            <div v-if="conPermisoSoloAdjuntar"
                class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 flex items-start gap-3">
                <input id="solo_adjunto_check" type="checkbox" v-model="soloAdjunto"
                    class="w-4 h-4 accent-amber-500 cursor-pointer mt-0.5" />
                <div>
                    <label for="solo_adjunto_check" class="font-medium text-amber-800 cursor-pointer select-none">
                        Solo adjuntar informe
                    </label>
                    <p class="text-xs text-amber-600 mt-0.5">
                        Al activar esta opción, el texto del informe es opcional. Puedes responder adjuntando únicamente archivos.
                    </p>
                </div>
            </div>

            <!-- Error general -->
            <div v-if="form.errors?.general"
                class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4 text-sm text-red-700">
                {{ form.errors.general }}
            </div>

            <!-- Examenes -->
            <form @submit.prevent="submit">
                <div class="space-y-5">
                    <div v-for="(ex, idx) in examenes" :key="ex.id"
                        class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">

                        <!-- Exam header -->
                        <div class="flex items-center justify-between px-5 py-3" style="background-color:#0b2a4a;">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-bold">
                                    {{ idx + 1 }}
                                </span>
                                <span class="font-semibold text-white text-sm">{{ examLabel(ex.descripcion) }}</span>
                            </div>
                            <span class="text-xs text-blue-200">Grupo {{ ex.grupo }}</span>
                        </div>

                        <div class="p-5 space-y-4">
                            <!-- Archivos existentes -->
                            <div v-if="ex.archivos?.length">
                                <p class="text-xs font-medium text-gray-500 mb-2 uppercase tracking-wide">
                                    <i class="pi pi-paperclip mr-1" />Archivos ({{ ex.archivos.length }})
                                </p>
                                <div class="flex flex-wrap gap-3">
                                    <FileThumbnail
                                        v-for="f in ex.archivos"
                                        :key="f.id"
                                        :file="f"
                                        :showDicom="parseInt(ex.grupo ?? 0) === 4"
                                        @lightbox="openLightbox"
                                    />
                                </div>
                            </div>
                            <div v-else class="text-xs text-gray-400 italic">Sin archivos de imagen adjuntos.</div>

                            <!-- ═══ PANORÁMICA form ═══ -->
                            <template v-if="ex.kind_id === PANORAMICA_KIND_ID">
                                <div class="space-y-3">
                                    <button type="button"
                                        @click="showCampos[idx] = !showCampos[idx]"
                                        class="flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition-colors"
                                        :class="showCampos[idx] ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'">
                                        <i :class="showCampos[idx] ? 'pi pi-eye-slash' : 'pi pi-eye'" class="text-xs" />
                                        Mostrar campos para informar
                                    </button>
                                    <template v-if="showCampos[idx]">
                                        <div class="space-y-4">
                                            <!-- Maxilar -->
                                            <div class="border border-blue-100 rounded-xl overflow-hidden">
                                                <div class="bg-blue-700 px-4 py-2"><h3 class="text-white font-semibold text-sm">Maxilar</h3></div>
                                                <div class="p-4 space-y-3">
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Nivel Óseo Marginal</label><Textarea v-model="respuestas[idx].campo_2" placeholder="Nivel Óseo Marginal" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Cálculo dentario marginal</label><Textarea v-model="respuestas[idx].campo_3" placeholder="Cálculo dentario marginal" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Dientes Ausentes</label><Textarea v-model="respuestas[idx].campo_4" placeholder="Dientes Ausentes" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Observaciones</label><Textarea v-model="respuestas[idx].campo_5" placeholder="Observaciones" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <p class="text-xs font-semibold text-gray-500 uppercase mt-2">Dientes Permanentes</p>
                                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                        <div v-for="d in PERM_MAXILAR" :key="d"><label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(d) }}</label><Textarea v-model="respuestas[idx][`diente_${d}`]" :placeholder="`Escriba el diagnostico Clínico para diente número ${fmtD(d)}`" rows="2" class="w-full text-xs" autoResize /></div>
                                                    </div>
                                                    <p class="text-xs font-semibold text-gray-500 uppercase mt-2">Dientes Temporales</p>
                                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                        <div v-for="d in TEMP_MAXILAR" :key="d"><label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(d) }}</label><Textarea v-model="respuestas[idx][`diente_${d}`]" :placeholder="`Escriba el diagnostico Clínico para diente número ${fmtD(d)}`" rows="2" class="w-full text-xs" autoResize /></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Mandíbula -->
                                            <div class="border border-blue-100 rounded-xl overflow-hidden">
                                                <div class="bg-blue-700 px-4 py-2"><h3 class="text-white font-semibold text-sm">Mandíbula</h3></div>
                                                <div class="p-4 space-y-3">
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Nivel Óseo Marginal</label><Textarea v-model="respuestas[idx].campo_6" placeholder="Nivel Óseo Marginal" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Cálculo dentario marginal</label><Textarea v-model="respuestas[idx].campo_7" placeholder="Cálculo dentario marginal" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Dientes Ausentes</label><Textarea v-model="respuestas[idx].campo_8" placeholder="Dientes Ausentes" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Observaciones</label><Textarea v-model="respuestas[idx].campo_9" placeholder="Observaciones" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <p class="text-xs font-semibold text-gray-500 uppercase mt-2">Dientes Permanentes</p>
                                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                        <div v-for="d in PERM_MANDIBULA" :key="d"><label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(d) }}</label><Textarea v-model="respuestas[idx][`diente_${d}`]" :placeholder="`Escriba el diagnostico Clínico para diente número ${fmtD(d)}`" rows="2" class="w-full text-xs" autoResize /></div>
                                                    </div>
                                                    <p class="text-xs font-semibold text-gray-500 uppercase mt-2">Dientes Temporales</p>
                                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                        <div v-for="d in TEMP_MANDIBULA" :key="d"><label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(d) }}</label><Textarea v-model="respuestas[idx][`diente_${d}`]" :placeholder="`Escriba el diagnostico Clínico para diente número ${fmtD(d)}`" rows="2" class="w-full text-xs" autoResize /></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- ═══ All other exam types ═══ -->
                            <template v-else>
                                <div class="space-y-3">
                                    <!-- Contexto: piezas seleccionadas (unitaria) -->
                                    <div v-if="ex.piezas && !isRetroTotal(ex)" class="text-xs bg-blue-50 border border-blue-100 rounded-lg p-3">
                                        <p class="font-semibold text-blue-700 mb-1">Piezas seleccionadas:</p>
                                        <p class="text-blue-600">{{ parsePiezas(ex.piezas).map(fmtD).join(' · ') }}</p>
                                    </div>

                                    <!-- Contexto: análisis solicitado (cefalométrico) -->
                                    <div v-if="ex.url_texto && !isConeBeam(ex)" class="text-xs bg-amber-50 border border-amber-100 rounded-lg p-3">
                                        <p class="font-semibold text-amber-700 mb-2">Análisis solicitado:</p>
                                        <ul class="space-y-1">
                                            <li v-for="(item, i) in ex.url_texto.split(',')" :key="i"
                                                class="text-amber-700 flex items-start gap-1.5">
                                                <i class="pi pi-check-circle text-amber-500 mt-0.5 flex-shrink-0" style="font-size:11px;" />
                                                <span>{{ item.trim() }}</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <button type="button"
                                        @click="showCampos[idx] = !showCampos[idx]"
                                        class="flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition-colors"
                                        :class="showCampos[idx]
                                            ? 'border-blue-500 bg-blue-50 text-blue-700'
                                            : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'">
                                        <i :class="showCampos[idx] ? 'pi pi-eye-slash' : 'pi pi-eye'" class="text-xs" />
                                        Mostrar campos para informar
                                    </button>

                                    <template v-if="showCampos[idx]">
                                        <!-- Campos por pieza con secciones (unitaria o total) -->
                                        <template v-if="getTeethForExam(ex).length > 0">

                                            <!-- ══ RETRO (unitaria y total): secciones Maxilar/Mandíbula con campos fijos ══ -->
                                            <template v-if="isRetro(ex)">

                                                <!-- Maxilar -->
                                                <template v-if="permMaxilarForExam(ex).length || tempMaxilarForExam(ex).length">
                                                    <div class="border border-blue-100 rounded-xl overflow-hidden mb-3">
                                                        <div class="bg-blue-700 px-4 py-2">
                                                            <h3 class="text-white font-semibold text-sm">Maxilar</h3>
                                                        </div>
                                                        <div class="p-4 space-y-3">
                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-600 mb-1">Nivel Óseo Marginal</label>
                                                                <Textarea v-model="respuestas[idx].campo_2" placeholder="Nivel Óseo Marginal" rows="2" class="w-full text-sm" autoResize />
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-600 mb-1">Cálculo dentario marginal</label>
                                                                <Textarea v-model="respuestas[idx].campo_3" placeholder="Cálculo dentario marginal" rows="2" class="w-full text-sm" autoResize />
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-600 mb-1">Observaciones</label>
                                                                <Textarea v-model="respuestas[idx].campo_4" placeholder="Observaciones" rows="2" class="w-full text-sm" autoResize />
                                                            </div>
                                                            <template v-if="permMaxilarForExam(ex).length">
                                                                <p class="text-xs font-semibold text-gray-500 uppercase mt-1">Dientes Permanentes</p>
                                                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                                    <div v-for="p in permMaxilarForExam(ex)" :key="p">
                                                                        <label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(p) }}</label>
                                                                        <Textarea v-model="respuestas[idx][`diente_${p}`]" :placeholder="`Dx ${fmtD(p)}`" rows="2" class="w-full text-xs" autoResize />
                                                                    </div>
                                                                </div>
                                                            </template>
                                                            <template v-if="tempMaxilarForExam(ex).length">
                                                                <p class="text-xs font-semibold text-gray-500 uppercase mt-1">Dientes Temporales</p>
                                                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                                    <div v-for="p in tempMaxilarForExam(ex)" :key="p">
                                                                        <label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(p) }}</label>
                                                                        <Textarea v-model="respuestas[idx][`diente_${p}`]" :placeholder="`Dx ${fmtD(p)}`" rows="2" class="w-full text-xs" autoResize />
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>

                                                <!-- Mandíbula -->
                                                <template v-if="permMandibForExam(ex).length || tempMandibForExam(ex).length">
                                                    <div class="border border-blue-100 rounded-xl overflow-hidden mb-3">
                                                        <div class="bg-blue-700 px-4 py-2">
                                                            <h3 class="text-white font-semibold text-sm">Mandíbula</h3>
                                                        </div>
                                                        <div class="p-4 space-y-3">
                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-600 mb-1">Nivel Óseo Marginal</label>
                                                                <Textarea v-model="respuestas[idx].campo_5" placeholder="Nivel Óseo Marginal" rows="2" class="w-full text-sm" autoResize />
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-600 mb-1">Cálculo dentario marginal</label>
                                                                <Textarea v-model="respuestas[idx].campo_6" placeholder="Cálculo dentario marginal" rows="2" class="w-full text-sm" autoResize />
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-600 mb-1">Observaciones</label>
                                                                <Textarea v-model="respuestas[idx].campo_7" placeholder="Observaciones" rows="2" class="w-full text-sm" autoResize />
                                                            </div>
                                                            <template v-if="permMandibForExam(ex).length">
                                                                <p class="text-xs font-semibold text-gray-500 uppercase mt-1">Dientes Permanentes</p>
                                                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                                    <div v-for="p in permMandibForExam(ex)" :key="p">
                                                                        <label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(p) }}</label>
                                                                        <Textarea v-model="respuestas[idx][`diente_${p}`]" :placeholder="`Dx ${fmtD(p)}`" rows="2" class="w-full text-xs" autoResize />
                                                                    </div>
                                                                </div>
                                                            </template>
                                                            <template v-if="tempMandibForExam(ex).length">
                                                                <p class="text-xs font-semibold text-gray-500 uppercase mt-1">Dientes Temporales</p>
                                                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                                    <div v-for="p in tempMandibForExam(ex)" :key="p">
                                                                        <label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(p) }}</label>
                                                                        <Textarea v-model="respuestas[idx][`diente_${p}`]" :placeholder="`Dx ${fmtD(p)}`" rows="2" class="w-full text-xs" autoResize />
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>

                                                <!-- Observaciones generales -->
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Observaciones generales</label>
                                                    <Textarea v-model="respuestas[idx].campo_1" placeholder="Observaciones generales del examen..." rows="2" class="w-full" autoResize />
                                                </div>
                                            </template>

                                            <!-- ══ RETRO TOTAL y otros con dientes: estructura original ══ -->
                                            <template v-else>
                                                <template v-if="permMaxilarForExam(ex).length || permMandibForExam(ex).length">
                                                    <p class="text-sm font-semibold text-gray-700 mt-2">Dientes Permanentes</p>
                                                    <div v-if="permMaxilarForExam(ex).length">
                                                        <p class="text-xs font-medium text-gray-500 mb-1">Maxilar</p>
                                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                            <div v-for="p in permMaxilarForExam(ex)" :key="p">
                                                                <label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(p) }}</label>
                                                                <Textarea v-model="respuestas[idx][`diente_${p}`]" :placeholder="`Dx ${fmtD(p)}`" rows="2" class="w-full text-xs" autoResize />
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div v-if="permMandibForExam(ex).length">
                                                        <p class="text-xs font-medium text-gray-500 mb-1">Mandíbula</p>
                                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                            <div v-for="p in permMandibForExam(ex)" :key="p">
                                                                <label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(p) }}</label>
                                                                <Textarea v-model="respuestas[idx][`diente_${p}`]" :placeholder="`Dx ${fmtD(p)}`" rows="2" class="w-full text-xs" autoResize />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template v-if="tempMaxilarForExam(ex).length || tempMandibForExam(ex).length">
                                                    <p class="text-sm font-semibold text-gray-700 mt-2">Dientes Temporales</p>
                                                    <div v-if="tempMaxilarForExam(ex).length">
                                                        <p class="text-xs font-medium text-gray-500 mb-1">Maxilar</p>
                                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                            <div v-for="p in tempMaxilarForExam(ex)" :key="p">
                                                                <label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(p) }}</label>
                                                                <Textarea v-model="respuestas[idx][`diente_${p}`]" :placeholder="`Dx ${fmtD(p)}`" rows="2" class="w-full text-xs" autoResize />
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div v-if="tempMandibForExam(ex).length">
                                                        <p class="text-xs font-medium text-gray-500 mb-1">Mandíbula</p>
                                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                            <div v-for="p in tempMandibForExam(ex)" :key="p">
                                                                <label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(p) }}</label>
                                                                <Textarea v-model="respuestas[idx][`diente_${p}`]" :placeholder="`Dx ${fmtD(p)}`" rows="2" class="w-full text-xs" autoResize />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Observaciones generales</label>
                                                    <Textarea v-model="respuestas[idx].campo_1" placeholder="Observaciones generales del examen..." rows="2" class="w-full" autoResize />
                                                </div>
                                            </template>

                                        </template>

                                        <!-- ══ BITE WING BILATERAL ══ -->
                                        <template v-else-if="isBiteWingBilateral(ex)">
                                            <!-- Lado Derecho -->
                                            <div class="border border-blue-100 rounded-xl overflow-hidden mb-3">
                                                <div class="bg-blue-700 px-4 py-2"><h3 class="text-white font-semibold text-sm">Lado Derecho</h3></div>
                                                <div class="p-4 space-y-3">
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Nivel Óseo Marginal</label><Textarea v-model="respuestas[idx].campo_2" placeholder="Nivel Óseo Marginal" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Cálculo dentario marginal</label><Textarea v-model="respuestas[idx].campo_3" placeholder="Cálculo dentario marginal" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Observaciones</label><Textarea v-model="respuestas[idx].campo_4" placeholder="Observaciones" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <p class="text-xs font-semibold text-gray-500 uppercase mt-1">Dientes Permanentes</p>
                                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                        <div v-for="p in BW_DERECHO_PERM" :key="p"><label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(p) }}</label><Textarea v-model="respuestas[idx][`diente_${p}`]" :placeholder="`Dx ${fmtD(p)}`" rows="2" class="w-full text-xs" autoResize /></div>
                                                    </div>
                                                    <p class="text-xs font-semibold text-gray-500 uppercase mt-1">Dientes Temporales</p>
                                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                        <div v-for="p in BW_DERECHO_TEMP" :key="p"><label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(p) }}</label><Textarea v-model="respuestas[idx][`diente_${p}`]" :placeholder="`Dx ${fmtD(p)}`" rows="2" class="w-full text-xs" autoResize /></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Lado Izquierdo -->
                                            <div class="border border-blue-100 rounded-xl overflow-hidden mb-3">
                                                <div class="bg-blue-700 px-4 py-2"><h3 class="text-white font-semibold text-sm">Lado Izquierdo</h3></div>
                                                <div class="p-4 space-y-3">
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Nivel Óseo Marginal</label><Textarea v-model="respuestas[idx].campo_5" placeholder="Nivel Óseo Marginal" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Cálculo dentario marginal</label><Textarea v-model="respuestas[idx].campo_6" placeholder="Cálculo dentario marginal" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Observaciones</label><Textarea v-model="respuestas[idx].campo_7" placeholder="Observaciones" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <p class="text-xs font-semibold text-gray-500 uppercase mt-1">Dientes Permanentes</p>
                                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                        <div v-for="p in BW_IZQUIERDO_PERM" :key="p"><label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(p) }}</label><Textarea v-model="respuestas[idx][`diente_${p}`]" :placeholder="`Dx ${fmtD(p)}`" rows="2" class="w-full text-xs" autoResize /></div>
                                                    </div>
                                                    <p class="text-xs font-semibold text-gray-500 uppercase mt-1">Dientes Temporales</p>
                                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                        <div v-for="p in BW_IZQUIERDO_TEMP" :key="p"><label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(p) }}</label><Textarea v-model="respuestas[idx][`diente_${p}`]" :placeholder="`Dx ${fmtD(p)}`" rows="2" class="w-full text-xs" autoResize /></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Observaciones generales</label><Textarea v-model="respuestas[idx].campo_1" placeholder="Observaciones generales..." rows="2" class="w-full" autoResize /></div>
                                        </template>

                                        <!-- ══ BITE WING UNILATERAL DERECHA ══ -->
                                        <template v-else-if="isBiteWingUnilateralDerecha(ex)">
                                            <div class="border border-blue-100 rounded-xl overflow-hidden mb-3">
                                                <div class="bg-blue-700 px-4 py-2"><h3 class="text-white font-semibold text-sm">Lado Derecho</h3></div>
                                                <div class="p-4 space-y-3">
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Nivel Óseo Marginal</label><Textarea v-model="respuestas[idx].campo_2" placeholder="Nivel Óseo Marginal" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Cálculo dentario marginal</label><Textarea v-model="respuestas[idx].campo_3" placeholder="Cálculo dentario marginal" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Observaciones</label><Textarea v-model="respuestas[idx].campo_4" placeholder="Observaciones" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <p class="text-xs font-semibold text-gray-500 uppercase mt-1">Dientes Permanentes</p>
                                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                        <div v-for="p in BW_DERECHO_PERM" :key="p"><label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(p) }}</label><Textarea v-model="respuestas[idx][`diente_${p}`]" :placeholder="`Dx ${fmtD(p)}`" rows="2" class="w-full text-xs" autoResize /></div>
                                                    </div>
                                                    <p class="text-xs font-semibold text-gray-500 uppercase mt-1">Dientes Temporales</p>
                                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                        <div v-for="p in BW_DERECHO_TEMP" :key="p"><label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(p) }}</label><Textarea v-model="respuestas[idx][`diente_${p}`]" :placeholder="`Dx ${fmtD(p)}`" rows="2" class="w-full text-xs" autoResize /></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Observaciones generales</label><Textarea v-model="respuestas[idx].campo_1" placeholder="Observaciones generales..." rows="2" class="w-full" autoResize /></div>
                                        </template>

                                        <!-- ══ BITE WING UNILATERAL IZQUIERDA ══ -->
                                        <template v-else-if="isBiteWingUnilateralIzquierda(ex)">
                                            <div class="border border-blue-100 rounded-xl overflow-hidden mb-3">
                                                <div class="bg-blue-700 px-4 py-2"><h3 class="text-white font-semibold text-sm">Lado Izquierdo</h3></div>
                                                <div class="p-4 space-y-3">
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Nivel Óseo Marginal</label><Textarea v-model="respuestas[idx].campo_2" placeholder="Nivel Óseo Marginal" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Cálculo dentario marginal</label><Textarea v-model="respuestas[idx].campo_3" placeholder="Cálculo dentario marginal" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Observaciones</label><Textarea v-model="respuestas[idx].campo_4" placeholder="Observaciones" rows="2" class="w-full text-sm" autoResize /></div>
                                                    <p class="text-xs font-semibold text-gray-500 uppercase mt-1">Dientes Permanentes</p>
                                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                        <div v-for="p in BW_IZQUIERDO_PERM" :key="p"><label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(p) }}</label><Textarea v-model="respuestas[idx][`diente_${p}`]" :placeholder="`Dx ${fmtD(p)}`" rows="2" class="w-full text-xs" autoResize /></div>
                                                    </div>
                                                    <p class="text-xs font-semibold text-gray-500 uppercase mt-1">Dientes Temporales</p>
                                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                        <div v-for="p in BW_IZQUIERDO_TEMP" :key="p"><label class="block text-xs text-gray-500 mb-0.5">Diente {{ fmtD(p) }}</label><Textarea v-model="respuestas[idx][`diente_${p}`]" :placeholder="`Dx ${fmtD(p)}`" rows="2" class="w-full text-xs" autoResize /></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Observaciones generales</label><Textarea v-model="respuestas[idx].campo_1" placeholder="Observaciones generales..." rows="2" class="w-full" autoResize /></div>
                                        </template>

                                        <!-- ══ ANÁLISIS CEFALOMÉTRICO ══ -->
                                        <template v-else-if="isCefalo(ex)">
                                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Examen radiográfico</label><Textarea v-model="respuestas[idx].campo_1" placeholder="Examen radiográfico" rows="3" class="w-full" autoResize /></div>
                                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Informe Radiográfico</label><Textarea v-model="respuestas[idx].campo_2" placeholder="Informe Radiográfico" rows="4" class="w-full" autoResize /></div>
                                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Observaciones</label><Textarea v-model="respuestas[idx].campo_3" placeholder="Observaciones" rows="3" class="w-full" autoResize /></div>
                                            <template v-for="c in cefaloSolicitados(ex)" :key="c.campo">
                                                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">{{ c.label }}</label><Textarea v-model="respuestas[idx][c.campo]" :placeholder="c.label" rows="3" class="w-full" autoResize /></div>
                                            </template>
                                        </template>

                                        <!-- ══ OCLUSAL (Superior/Inferior, Adulto/Niño) ══ -->
                                        <template v-else-if="isOclusal(ex)">
                                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Examen radiográfico</label><Textarea v-model="respuestas[idx].campo_1" placeholder="Examen radiográfico" rows="3" class="w-full" autoResize /></div>
                                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Informe Radiográfico</label><Textarea v-model="respuestas[idx].campo_2" placeholder="Informe Radiográfico" rows="4" class="w-full" autoResize /></div>
                                            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Observaciones</label><Textarea v-model="respuestas[idx].campo_3" placeholder="Observaciones" rows="3" class="w-full" autoResize /></div>
                                        </template>

                                        <!-- Campos estándar (Examen / Informe / Impresión) -->
                                        <template v-else>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Examen</label>
                                                <Textarea v-model="respuestas[idx].campo_1"
                                                    placeholder="Descripción del examen..."
                                                    rows="3" class="w-full" autoResize />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Informe</label>
                                                <Textarea v-model="respuestas[idx].campo_2"
                                                    placeholder="Informe radiológico..."
                                                    rows="4" class="w-full" autoResize />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Impresión Diagnóstica</label>
                                                <Textarea v-model="respuestas[idx].campo_3"
                                                    placeholder="Impresión diagnóstica..."
                                                    rows="3" class="w-full" autoResize />
                                            </div>
                                        </template>
                                        <small v-if="respuestaErrors[idx]" class="text-red-500">{{ respuestaErrors[idx] }}</small>
                                    </template>
                                </div>
                            </template>

                            <!-- Upload informe files -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    <i class="pi pi-upload mr-1 text-xs" />Adjuntar archivos del informe
                                    <span class="text-gray-400 font-normal ml-1">(opcional)</span>
                                </label>
                                <input type="file" :name="`archivos_${ex.id}[]`" multiple
                                    @change="onFileChange(idx, $event)"
                                    class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                <div v-if="fileNames[idx]?.length" class="space-y-1 mt-1.5">
                                    <div v-for="(name, fi) in fileNames[idx]" :key="fi"
                                        class="flex items-center justify-between bg-blue-50 border border-blue-100 rounded px-2 py-1 text-xs">
                                        <span class="text-blue-700 truncate max-w-xs">
                                            <i class="pi pi-file mr-1 text-blue-400" />{{ name }}
                                        </span>
                                        <button type="button" @click="removeFile(idx, fi)"
                                            class="text-red-400 hover:text-red-600 ml-2 flex-shrink-0">
                                            <i class="pi pi-times text-xs" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Otros Datos -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mt-5">
                    <div class="px-5 py-3" style="background-color:#0b2a4a;">
                        <span class="font-semibold text-white text-sm">Otros Datos</span>
                    </div>
                    <div class="p-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Observaciones <span class="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <Textarea v-model="observaciones2" placeholder="Otras observaciones..." rows="3"
                            class="w-full" autoResize />
                    </div>
                </div>

                <!-- Solicitar corrección (collapsible) -->
                <div class="bg-white rounded-xl border border-orange-100 shadow-sm overflow-hidden mt-5">
                    <div class="px-5 py-3 flex items-center gap-3 cursor-pointer select-none"
                        style="background-color:#7c3509;" @click="solicitarCorreccion = !solicitarCorreccion">
                        <input type="checkbox" :checked="solicitarCorreccion" readonly
                            class="w-4 h-4 accent-orange-500 pointer-events-none" />
                        <span class="font-semibold text-white text-sm">Solicitar Corrección</span>
                        <i :class="solicitarCorreccion ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
                            class="text-white text-xs ml-auto" />
                    </div>
                    <div v-if="solicitarCorreccion" class="p-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Detalle de la corrección <span class="text-red-500">*</span>
                        </label>
                        <Textarea v-model="correccionMensaje"
                            placeholder="¿Por qué solicita corrección?" rows="3"
                            class="w-full" autoResize />
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-wrap justify-end gap-3 mt-6">
                    <Link :href="route('ordenes.show', order.id)">
                        <Button type="button" label="Cancelar" severity="secondary" />
                    </Link>
                    <Button type="button" :loading="submitting && currentAction === 'borrador'"
                        @click="doSubmit('borrador')"
                        label="Guardar Borrador"
                        icon="pi pi-save"
                        severity="secondary"
                        style="border-color:#6b7280;" />
                    <Button v-if="solicitarCorreccion"
                        type="button" :loading="submitting && currentAction === 'correccion'"
                        @click="doSubmit('correccion')"
                        label="Enviar Corrección"
                        icon="pi pi-exclamation-circle"
                        style="background-color:#c2410c;border-color:#c2410c;" />
                    <Button v-if="!solicitarCorreccion"
                        type="button" :loading="submitting && currentAction === 'responder'"
                        @click="doSubmit('responder')"
                        label="Enviar Informe"
                        icon="pi pi-check"
                        style="background-color:#3452ff;border-color:#3452ff;" />
                </div>
            </form>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import Textarea from 'primevue/textarea';
import { useTerms }  from '@/composables/useTerms.js';
import ImageViewer   from '@/Components/ImageViewer.vue';
import FileThumbnail from '@/Components/FileThumbnail.vue';

const { examLabel } = useTerms();

// ─── Panorámica constants ────────────────────────────────────────────────────
const PANORAMICA_KIND_ID = 15;

const PERM_MAXILAR   = [11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28];
const TEMP_MAXILAR   = [51,52,53,54,55,61,62,63,64,65];
const PERM_MANDIBULA = [31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48];
const TEMP_MANDIBULA = [71,72,73,74,75,81,82,83,84,85];
const ALL_DIENTES    = [...PERM_MAXILAR, ...TEMP_MAXILAR, ...PERM_MANDIBULA, ...TEMP_MANDIBULA];

// Maxilar uses campo_5–8, Mandibula uses campo_1–4
const MAXILAR_CAMPOS    = { campo_5: 'Nivel Óseo Marginal', campo_6: 'Cálculo dentario marginal', campo_7: 'Dientes Ausentes', campo_8: 'Observaciones' };
const MANDIBULA_CAMPOS  = { campo_1: 'Nivel Óseo Marginal', campo_2: 'Cálculo dentario marginal', campo_3: 'Dientes Ausentes', campo_4: 'Observaciones' };

const fmtD = (d) => `${Math.floor(d / 10)}.${d % 10}`;

// ─── Props ───────────────────────────────────────────────────────────────────
const props = defineProps({
    order:                  Object,
    paciente:               Object,
    clinica:                String,
    examenes:               Array,
    conPermisoSoloAdjuntar: Boolean,
});

// ─── State ───────────────────────────────────────────────────────────────────
const soloAdjunto       = ref(false);
const observaciones2    = ref(props.order.observaciones_2 ?? '');
const solicitarCorreccion = ref(false);
const correccionMensaje = ref('');
const submitting        = ref(false);
const currentAction     = ref('');
const form              = reactive({ errors: {} });

function parsePiezas(str) {
    if (!str) return [];
    return str.split(',').map(s => parseInt(s.trim())).filter(Boolean);
}
function isConeBeam(ex)      { return parseInt(ex?.grupo ?? 0) === 4; }
function isCefalometrico(ex) { return /cefalom/i.test(ex?.descripcion ?? ''); }
function isRetroTotal(ex)    { return /retroalveolar/i.test(ex?.descripcion ?? '') && /total/i.test(ex?.descripcion ?? ''); }
function isRetroUnitaria(ex) { return /retroalveolar/i.test(ex?.descripcion ?? '') && !/total/i.test(ex?.descripcion ?? ''); }
function isRetro(ex)              { return /retroalveolar/i.test(ex?.descripcion ?? ''); }
function isNinoExam(ex)           { return /ni[ñn]/i.test(ex?.descripcion ?? ''); }
function isBiteWingBilateral(ex)         { return /bite wing bilateral/i.test(ex?.descripcion ?? ''); }
function isBiteWingUnilateralDerecha(ex) { return /bite wing unilateral derecha/i.test(ex?.descripcion ?? ''); }
function isBiteWingUnilateralIzquierda(ex) { return /bite wing unilateral izquierda/i.test(ex?.descripcion ?? ''); }
function isOclusal(ex)        { return /oclusal/i.test(ex?.descripcion ?? '') || /telerradiograf/i.test(ex?.descripcion ?? '') || /carpo/i.test(ex?.descripcion ?? '') || /atm/i.test(ex?.descripcion ?? ''); }
function isCefalo(ex)         { return /cefalom/i.test(ex?.descripcion ?? ''); }

const CEFALO_CAMPOS = [
    { key: 'Análisis Rickets',  campo: 'campo_4' },
    { key: 'Análisis Roth',     campo: 'campo_5' },
    { key: 'Análisis Jaraback', campo: 'campo_6' },
    { key: 'Análisis Steiner',  campo: 'campo_7' },
    { key: 'Análisis Mcnamara', campo: 'campo_8' },
    { key: 'Otros',             campo: 'campo_9' },
];

function cefaloSolicitados(ex) {
    if (!ex?.url_texto) return [];
    const parts = ex.url_texto.split(',').map(s => s.trim());
    return CEFALO_CAMPOS.filter(c =>
        parts.some(p => p === c.key || (c.key === 'Otros' && p.startsWith('Otros')))
    ).map(c => ({
        ...c,
        label: parts.find(p => c.key === 'Otros' ? p.startsWith('Otros') : p === c.key) ?? c.key
    }));
}

const BW_DERECHO_PERM  = [13,14,15,16,17,18,43,44,45,46,47,48];
const BW_DERECHO_TEMP  = [53,54,55,83,84,85];
const BW_IZQUIERDO_PERM = [23,24,25,26,27,28,33,34,35,36,37,38];
const BW_IZQUIERDO_TEMP = [63,64,65,73,74,75];

function getTeethForExam(ex) {
    if (ex?.piezas) return parsePiezas(ex.piezas);
    if (isRetroTotal(ex)) {
        const perm = [...PERM_MAXILAR, ...PERM_MANDIBULA];
        return isNinoExam(ex) ? [...perm, ...TEMP_MAXILAR, ...TEMP_MANDIBULA] : perm;
    }
    return [];
}
function permMaxilarForExam(ex) { const t = getTeethForExam(ex); return PERM_MAXILAR.filter(n => t.includes(n)); }
function permMandibForExam(ex)  { const t = getTeethForExam(ex); return PERM_MANDIBULA.filter(n => t.includes(n)); }
function tempMaxilarForExam(ex) { const t = getTeethForExam(ex); return TEMP_MAXILAR.filter(n => t.includes(n)); }
function tempMandibForExam(ex)  { const t = getTeethForExam(ex); return TEMP_MANDIBULA.filter(n => t.includes(n)); }

// showCampos: all exams start closed by default, open only if they already have saved answers
const showCampos = reactive(
    props.examenes.map(ex => {
        const r = ex.respuesta ?? {};
        if (ex.kind_id === PANORAMICA_KIND_ID) {
            const hasInforme  = !!(r.informe_examen || r.informe_libre || r.informe_impresion);
            const hasCampos   = [1,2,3,4,5,6,7,8,9].some(i => !!r[`campo_${i}`]);
            const hasDientes  = Object.keys(r).some(k => k.startsWith('diente_') && !!r[k]);
            return hasInforme || hasCampos || hasDientes;
        }
        const teeth = getTeethForExam(ex);
        if (teeth.length > 0) {
            const hasTeeth  = teeth.some(p => !!r[`diente_${p}`]);
            const hasCampos = [1,2,3,4,5,6,7,8,9].some(i => !!r[`campo_${i}`]);
            return hasTeeth || hasCampos;
        }
        return !!(r.campo_1 || r.campo_2 || r.campo_3 ||
                  r.campo_4 || r.campo_5 || r.campo_6 ||
                  r.campo_7 || r.campo_8 || r.campo_9);
    })
);

// Build respuestas from existing answers
const respuestas = reactive(
    props.examenes.map(ex => {
        const r   = ex.respuesta ?? {};
        const obj = { id: ex.id, kind_id: ex.kind_id };

        if (ex.kind_id === PANORAMICA_KIND_ID) {
            for (let i = 1; i <= 9; i++) obj[`campo_${i}`] = r[`campo_${i}`] ?? '';
            ALL_DIENTES.forEach(d => { obj[`diente_${d}`] = r[`diente_${d}`] ?? ''; });
        } else if (isBiteWingBilateral(ex)) {
            obj.campo_1 = r.campo_1 ?? '';
            for (let i = 2; i <= 7; i++) obj[`campo_${i}`] = r[`campo_${i}`] ?? '';
            [...BW_DERECHO_PERM, ...BW_DERECHO_TEMP, ...BW_IZQUIERDO_PERM, ...BW_IZQUIERDO_TEMP]
                .forEach(p => { obj[`diente_${p}`] = r[`diente_${p}`] ?? ''; });
        } else if (isBiteWingUnilateralDerecha(ex)) {
            obj.campo_1 = r.campo_1 ?? '';
            for (let i = 2; i <= 4; i++) obj[`campo_${i}`] = r[`campo_${i}`] ?? '';
            [...BW_DERECHO_PERM, ...BW_DERECHO_TEMP].forEach(p => { obj[`diente_${p}`] = r[`diente_${p}`] ?? ''; });
        } else if (isBiteWingUnilateralIzquierda(ex)) {
            obj.campo_1 = r.campo_1 ?? '';
            for (let i = 2; i <= 4; i++) obj[`campo_${i}`] = r[`campo_${i}`] ?? '';
            [...BW_IZQUIERDO_PERM, ...BW_IZQUIERDO_TEMP].forEach(p => { obj[`diente_${p}`] = r[`diente_${p}`] ?? ''; });
        } else if (isCefalo(ex)) {
            for (let i = 1; i <= 9; i++) obj[`campo_${i}`] = r[`campo_${i}`] ?? '';
        } else {
            const teeth = getTeethForExam(ex);
            if (teeth.length > 0) {
                obj.campo_1 = r.campo_1 ?? '';
                if (isRetro(ex)) {
                    for (let i = 2; i <= 7; i++) obj[`campo_${i}`] = r[`campo_${i}`] ?? '';
                }
                teeth.forEach(p => { obj[`diente_${p}`] = r[`diente_${p}`] ?? ''; });
            } else {
                obj.campo_1 = r.campo_1 ?? '';
                obj.campo_2 = r.campo_2 ?? '';
                obj.campo_3 = r.campo_3 ?? '';
            }
        }

        return obj;
    })
);

const respuestaErrors = reactive(props.examenes.map(() => ''));
const fileNames       = reactive(props.examenes.map(() => []));
const uploadedFiles   = reactive(props.examenes.map(() => []));

// ─── Helpers ─────────────────────────────────────────────────────────────────
function onFileChange(idx, event) {
    const newFiles = Array.from(event.target.files);
    uploadedFiles[idx] = [...(uploadedFiles[idx] ?? []), ...newFiles];
    fileNames[idx]     = uploadedFiles[idx].map(f => f.name);
    event.target.value = ''; // reset so same file can be added again
}

function removeFile(idx, fileIdx) {
    uploadedFiles[idx].splice(fileIdx, 1);
    fileNames[idx] = uploadedFiles[idx].map(f => f.name);
}

const IMAGE_EXTS = ['jpg','jpeg','png','gif','bmp','webp'];
const imgErrors  = reactive({});
const lightbox   = reactive({ open: false, src: '', name: '' });

function isImage(ext) { return IMAGE_EXTS.includes((ext ?? '').toLowerCase()); }
function fileHref(f)  { return f.url ?? null; }

function openLightbox(f) {
    lightbox.src  = f.url;
    lightbox.name = f.name || 'Imagen';
    lightbox.open = true;
}

function getFileIcon(ext) {
    const e = (ext ?? '').toLowerCase();
    if (isImage(e))              return 'pi pi-image';
    if (e === 'pdf')             return 'pi pi-file-pdf';
    if (['dcm','dicom'].includes(e)) return 'pi pi-chart-bar';
    return 'pi pi-file';
}

function validate(action) {
    if (action === 'borrador') return true;
    if (action === 'correccion') {
        return !!correccionMensaje.value.trim();
    }
    // soloAdjunto: texto opcional, pero debe haber al menos un archivo subido
    if (soloAdjunto.value) {
        const hasFiles = uploadedFiles.some(arr => arr?.length > 0);
        if (!hasFiles) {
            form.errors.general = 'Al activar "Solo adjuntar informe" debes subir al menos un archivo.';
            return false;
        }
        respuestas.forEach((_, i) => { respuestaErrors[i] = ''; });
        return true;
    }
    // 'responder': al menos un campo de texto o archivo por examen
    // Excepción: cefalométrico no requiere texto (adjuntan archivos aparte)
    let ok = true;
    respuestas.forEach((r, i) => {
        respuestaErrors[i] = '';
        const ex = props.examenes[i];
        if (isCefalo(ex)) return; // cefalométrico exento: adjuntan archivos aparte
        const hasText =
            [1,2,3,4,5,6,7,8,9].some(c => (r[`campo_${c}`] ?? '').trim().length > 0) ||
            Object.keys(r).some(k => k.startsWith('diente_') && (r[k] ?? '').trim().length > 0) ||
            (r.informe_examen ?? '').trim().length > 0 ||
            (r.informe_libre  ?? '').trim().length > 0 ||
            (r.informe_impresion ?? '').trim().length > 0;
        const hasFile = uploadedFiles[i]?.length > 0;
        if (!hasText && !hasFile) {
            respuestaErrors[i] = 'Debes completar al menos un campo del informe o adjuntar un archivo.';
            ok = false;
        }
    });
    if (!ok) form.errors.general = 'Completa al menos un campo en cada examen antes de enviar el informe.';
    return ok;
}

function doSubmit(action) {
    if (!validate(action)) return;

    submitting.value    = true;
    currentAction.value = action;

    const data = new FormData();
    data.append('action',         action);
    data.append('solo_adjunto',   soloAdjunto.value ? '1' : '0');
    data.append('observaciones_2', observaciones2.value ?? '');

    if (action === 'correccion') {
        data.append('mensaje_correccion', correccionMensaje.value);
    }

    respuestas.forEach((r, i) => {
        data.append(`respuestas[${i}][id]`, r.id);

        if (r.kind_id === PANORAMICA_KIND_ID) {
            for (let c = 1; c <= 9; c++) {
                data.append(`respuestas[${i}][campo_${c}]`, r[`campo_${c}`] ?? '');
            }
            ALL_DIENTES.forEach(d => {
                data.append(`respuestas[${i}][diente_${d}]`, r[`diente_${d}`] ?? '');
            });
            data.append(`respuestas[${i}][informe_examen]`,    r.informe_examen    ?? '');
            data.append(`respuestas[${i}][informe_libre]`,     r.informe_libre     ?? '');
            data.append(`respuestas[${i}][informe_impresion]`, r.informe_impresion ?? '');
        } else {
            // Send every campo and diente that exists in this exam's respuesta
            for (let c = 1; c <= 9; c++) {
                if (`campo_${c}` in r) {
                    data.append(`respuestas[${i}][campo_${c}]`, r[`campo_${c}`] ?? '');
                }
            }
            Object.keys(r).filter(k => k.startsWith('diente_')).forEach(k => {
                data.append(`respuestas[${i}][${k}]`, r[k] ?? '');
            });
        }

        uploadedFiles[i]?.forEach(file => {
            data.append(`archivos_${r.id}[]`, file);
        });
    });

    router.post(route('ordenes.doResponder', props.order.id), data, {
        forceFormData: true,
        onError:  (errors) => { form.errors = errors; submitting.value = false; },
        onFinish: () => { submitting.value = false; },
    });
}

// Keep form submit wired (hits doSubmit('responder') if enter pressed)
function submit() { doSubmit('responder'); }
</script>
