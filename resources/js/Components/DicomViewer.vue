<template>
    <Teleport to="body">
        <div v-if="url" class="fixed inset-0 z-50 flex flex-col bg-black select-none" @keydown.esc="$emit('close')" tabindex="0" ref="container">

            <!-- Toolbar -->
            <div class="flex items-center justify-between px-4 py-2 bg-black/80 z-10 shrink-0 gap-3">
                <span class="text-white/70 text-sm truncate max-w-xs">{{ name }}</span>

                <!-- Herramientas -->
                <div class="flex items-center gap-1 flex-wrap">
                    <button v-for="t in tools" :key="t.id" @click="setTool(t.id)"
                        :title="t.label"
                        class="flex items-center gap-1 px-2 py-1 rounded text-xs transition-colors"
                        :class="activeTool === t.id
                            ? 'bg-blue-600 text-white'
                            : 'bg-white/10 text-white/70 hover:bg-white/20 hover:text-white'">
                        <i :class="`pi ${t.icon} text-xs`" />
                        <span class="hidden sm:inline">{{ t.label }}</span>
                    </button>

                    <div class="w-px h-5 bg-white/20 mx-1" />

                    <!-- Windowing presets -->
                    <select v-if="loaded" v-model="preset" @change="applyPreset"
                        class="bg-white/10 text-white/80 text-xs rounded px-2 py-1 border-none outline-none cursor-pointer">
                        <option value="">Preset...</option>
                        <option value="default">Defecto</option>
                        <option value="ct-head">CT Cabeza</option>
                        <option value="ct-chest">CT Tórax</option>
                        <option value="ct-bone">Hueso</option>
                        <option value="ct-soft">Tejido blando</option>
                    </select>

                    <div class="w-px h-5 bg-white/20 mx-1" />

                    <a :href="url" target="_blank" download
                        title="Descargar DCM"
                        class="flex items-center justify-center w-7 h-7 rounded bg-white/10 text-white/70 hover:bg-white/20 hover:text-white transition text-sm">
                        <i class="pi pi-download" />
                    </a>
                    <button @click="$emit('close')" title="Cerrar (Esc)"
                        class="flex items-center justify-center w-7 h-7 rounded bg-white/10 text-red-400 hover:bg-white/20 hover:text-red-300 transition text-sm ml-1">
                        <i class="pi pi-times" />
                    </button>
                </div>
            </div>

            <!-- Canvas area -->
            <div class="flex-1 relative overflow-hidden flex items-center justify-center bg-black">
                <!-- Estado de carga -->
                <div v-if="!loaded && !error" class="absolute inset-0 flex flex-col items-center justify-center text-white/50 gap-3">
                    <i class="pi pi-spin pi-spinner text-4xl" />
                    <p class="text-sm">Cargando imagen DICOM...</p>
                </div>

                <!-- Error -->
                <div v-if="error" class="absolute inset-0 flex flex-col items-center justify-center text-white/50 gap-3">
                    <i class="pi pi-exclamation-triangle text-4xl text-red-400" />
                    <p class="text-sm text-red-300">No se pudo cargar el archivo DICOM.</p>
                    <a :href="url" target="_blank" download
                        class="mt-2 px-4 py-2 bg-white/10 hover:bg-white/20 rounded text-sm text-white/80 transition">
                        <i class="pi pi-download mr-1" /> Descargar archivo
                    </a>
                </div>

                <!-- dwv mount point -->
                <div ref="dwvRoot" id="dwv-container" class="w-full h-full" />
            </div>

            <!-- Windowing info -->
            <div v-if="loaded" class="shrink-0 flex items-center justify-center gap-6 py-1 bg-black/80 text-white/30 text-xs">
                <span>WC: {{ wc }} / WW: {{ ww }}</span>
                <span>Rueda: zoom · Arrastrar: pan · Esc: cerrar</span>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch, onUnmounted, nextTick } from 'vue';

const props = defineProps({
    url:  { type: String, default: null },
    name: { type: String, default: 'DICOM' },
});
const emit = defineEmits(['close']);

const container = ref(null);
const dwvRoot   = ref(null);
const loaded    = ref(false);
const error     = ref(false);
const activeTool = ref('Scroll');
const preset    = ref('');
const wc        = ref('-');
const ww        = ref('-');

let app = null;

const tools = [
    { id: 'Scroll',    icon: 'pi-arrows-v',       label: 'Desplazar' },
    { id: 'ZoomAndPan',icon: 'pi-search',          label: 'Zoom/Pan' },
    { id: 'WindowLevel',icon: 'pi-sliders-h',      label: 'Ventana' },
];

const PRESETS = {
    'ct-head':  { center: 40,   width: 80   },
    'ct-chest': { center: -500, width: 1500 },
    'ct-bone':  { center: 400,  width: 1800 },
    'ct-soft':  { center: 50,   width: 400  },
};

function setTool(tool) {
    activeTool.value = tool;
    if (app && loaded.value) {
        try { app.setTool(tool); } catch {}
    }
}

function applyPreset() {
    if (!app || !loaded.value || !preset.value) return;
    const p = PRESETS[preset.value];
    if (!p) return;
    try {
        app.setWindowLevel(p.center, p.width);
        wc.value = p.center;
        ww.value = p.width;
    } catch {}
}

async function initDwv(url) {
    loaded.value = false;
    error.value  = false;
    preset.value = '';
    wc.value = '-';
    ww.value = '-';

    if (app) {
        try { app.reset(); } catch {}
        app = null;
    }

    await nextTick();
    if (!dwvRoot.value) return;

    // Clean DOM
    dwvRoot.value.innerHTML = '';
    const layerDiv = document.createElement('div');
    layerDiv.id = 'layerGroup0';
    layerDiv.style.cssText = 'width:100%;height:100%;position:relative;';
    dwvRoot.value.appendChild(layerDiv);

    try {
        const { App } = await import('dwv');
        app = new App();
        app.init({
            dataViewConfigs: { '*': [{ divId: 'layerGroup0' }] },
            tools: {
                Scroll:      {},
                ZoomAndPan:  {},
                WindowLevel: {},
            },
        });

        app.addEventListener('load', () => {
            loaded.value = true;
            try { app.setTool(activeTool.value); } catch {}
            // Read windowing info
            try {
                const wlc = app.getViewController(app.getActiveLayerGroup().getActiveViewLayer());
                if (wlc) {
                    wc.value = Math.round(wlc.getWindowLevel().center);
                    ww.value = Math.round(wlc.getWindowLevel().width);
                }
            } catch {}
            nextTick(() => container.value?.focus());
        });

        app.addEventListener('loaderror', () => {
            error.value = true;
        });

        app.addEventListener('wlchange', (event) => {
            if (event.value) {
                wc.value = Math.round(event.value[0]);
                ww.value = Math.round(event.value[1]);
            }
        });

        app.loadURLs([url]);
    } catch (e) {
        console.error('dwv error:', e);
        error.value = true;
    }
}

watch(() => props.url, async (url) => {
    if (url) {
        await nextTick();
        initDwv(url);
    } else {
        if (app) { try { app.reset(); } catch {} app = null; }
        loaded.value = false;
        error.value  = false;
    }
}, { immediate: true });

onUnmounted(() => {
    if (app) { try { app.reset(); } catch {} app = null; }
});
</script>

<style>
#dwv-container canvas {
    display: block;
}
</style>
