<template>
    <div class="fixed inset-0 bg-black flex flex-col" style="font-family:Arial,sans-serif;">

        <!-- Toolbar -->
        <div class="flex items-center justify-between px-4 py-2 shrink-0 gap-3 border-b border-white/10"
            style="background:#0b2a4a; min-height:48px;">

            <div class="flex items-center gap-3 shrink-0">
                <img src="/images/dimage_logo.png" alt="Dimage" class="h-7 w-7 object-contain" style="mix-blend-mode:screen;" />
                <span class="text-white font-bold text-sm hidden sm:inline">Visor DICOM</span>
                <span class="text-white/40 text-xs hidden md:inline truncate max-w-xs">{{ fileName }}</span>
            </div>

            <!-- Controles dwv -->
            <div v-if="loaded" class="flex items-center gap-1 flex-wrap justify-center">
                <button v-for="t in tools" :key="t.id"
                    @click="setTool(t.id)"
                    :title="t.label"
                    class="flex items-center gap-1 px-2.5 py-1.5 rounded text-xs font-medium transition-colors"
                    :class="activeTool === t.id ? 'text-white' : 'text-white/60 hover:text-white hover:bg-white/10'"
                    :style="activeTool === t.id ? 'background:#3452ff' : ''">
                    <i :class="`pi ${t.icon} text-xs`" />
                    <span class="hidden sm:inline">{{ t.label }}</span>
                </button>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <span v-if="sliceCount > 1 && loaded" class="text-white/40 text-xs">
                    {{ sliceCount }} cortes
                </span>
                <span v-if="loaded && wc !== '-'" class="text-white/40 text-xs hidden lg:block">
                    WC: {{ wc }} / WW: {{ ww }}
                </span>
                <a :href="downloadHref" target="_blank" download
                    class="flex items-center gap-1 px-2.5 py-1.5 rounded text-xs text-white/60 hover:text-white hover:bg-white/10 transition-colors">
                    <i class="pi pi-download text-xs" />
                    <span class="hidden sm:inline">Descargar</span>
                </a>
            </div>
        </div>

        <!-- Contenido -->
        <div class="flex-1 relative overflow-hidden">

            <!-- Loading -->
            <div v-if="!loaded && !error"
                class="absolute inset-0 flex flex-col items-center justify-center text-white/50 gap-4 z-10">
                <i class="pi pi-spin pi-spinner text-5xl text-blue-400" />
                <p class="text-sm font-medium">{{ loadMsg }}</p>
                <p class="text-xs text-white/30">{{ fileName }}</p>
                <div style="width:280px; background:rgba(255,255,255,0.08); border-radius:6px; height:8px; overflow:hidden;">
                    <div :style="`width:${loadPct}%; background:#3452ff; height:100%; transition:width 0.2s;`" />
                </div>
                <p class="text-xs text-blue-400">{{ loadPct > 0 ? `${loadPct}%` : 'Iniciando...' }}</p>
                <p v-if="sliceCount > 50" class="text-xs text-white/20 text-center max-w-xs">
                    Serie de {{ sliceCount }} cortes — cargando directo desde S3
                </p>
            </div>

            <!-- Error genérico -->
            <div v-if="error"
                class="absolute inset-0 flex flex-col items-center justify-center text-white/50 gap-4 z-10">
                <i class="pi pi-exclamation-triangle text-5xl text-red-400" />
                <p class="text-base font-medium text-red-300">No se pudo cargar el archivo DICOM</p>
                <p class="text-xs text-white/30 max-w-sm text-center">
                    El archivo puede estar en un formato no compatible o el acceso expiró.
                </p>
                <a :href="downloadHref" target="_blank" download
                    class="mt-2 px-5 py-2.5 rounded-lg text-sm font-medium text-white"
                    style="background:#3452ff;">
                    <i class="pi pi-download mr-2" />Descargar archivo
                </a>
            </div>

            <!-- dwv mount -->
            <div ref="dwvRoot" class="w-full h-full" id="dwv-layer-root" />
        </div>

        <!-- Footer -->
        <div class="shrink-0 flex items-center justify-center gap-6 py-1 border-t border-white/10 text-white/25 text-xs"
            style="background:#0b2a4a;">
            <span>Rueda: zoom</span><span>·</span>
            <span>Scroll: cambiar corte</span><span>·</span>
            <span>Click derecho / WindowLevel: ajustar ventana</span>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    fileUrl:  { type: String, default: '' },
    fileId:   { type: [String, Number], default: null },
    fileName: { type: String, default: 'Archivo DICOM' },
});

const dwvRoot    = ref(null);
const loaded     = ref(false);
const error      = ref(false);
const zipPending = ref(false);
const extracting = ref(false);
const loadMsg    = ref('Cargando imagen DICOM...');
const loadPct    = ref(0);
const sliceCount = ref(0);

const activeTool = ref('Scroll');
const wc = ref('-');
const ww = ref('-');
const tools = [
    { id: 'Scroll',      icon: 'pi-arrows-v',  label: 'Scroll'   },
    { id: 'ZoomAndPan',  icon: 'pi-search',     label: 'Zoom/Pan' },
    { id: 'WindowLevel', icon: 'pi-sliders-h',  label: 'Ventana'  },
    { id: 'Draw',        icon: 'pi-pencil',     label: 'Medir'    },
];

let dwvApp    = null;
let timeoutId = null;

const downloadHref = computed(() =>
    props.fileId ? `/archivos/${props.fileId}` : props.fileUrl
);

async function initDwv(urls) {
    dwvRoot.value.innerHTML = '';
    const lgDiv = document.createElement('div');
    lgDiv.id = 'layerGroup0';
    lgDiv.style.cssText = 'width:100%;height:100%;position:relative;overflow:hidden;';
    dwvRoot.value.appendChild(lgDiv);

    const { App, WindowLevel } = await import('dwv');
    dwvApp = new App();

    dwvApp.init({
        dataViewConfigs: { '*': [{ divId: 'layerGroup0' }] },
        tools: {
            Scroll:      {},
            ZoomAndPan:  {},
            WindowLevel: {},
            Draw: { options: ['Ruler', 'Ellipse', 'Rectangle'] },
        },
    });

    const isMulti = urls.length > 1;

    // Track PHP-proxy slice progress (/slice/ URLs).
    const origFetch = window.fetch;
    let fetchDone = 0;
    if (isMulti) {
        window.fetch = async (...args) => {
            const r = await origFetch(...args);
            const u = typeof args[0] === 'string' ? args[0] : (args[0]?.url ?? '');
            if (u.includes('/slice/')) {
                fetchDone++;
                loadPct.value = Math.min(99, Math.round((fetchDone / urls.length) * 100));
                loadMsg.value = `Cargando corte ${fetchDone} / ${urls.length}...`;
            }
            return r;
        };
    }
    const restoreFetch = () => { if (isMulti) window.fetch = origFetch; };

    dwvApp.addEventListener('loadprogress', (ev) => {
        if (ev?.loaded != null && ev?.total > 0) {
            loadPct.value = Math.round((ev.loaded / ev.total) * 100);
            if (loadPct.value >= 100) loadMsg.value = 'Procesando datos DICOM...';
        }
    });

    dwvApp.addEventListener('loaderror', (ev) => {
        console.warn('[dwv] loaderror:', ev?.error?.message || ev);
    });

    const applyWl = () => {
        // dwv auto-renders on first loaditem (viewOnFirstLoadItem default).
        // ViewLayer already exists when loadend fires — do NOT call render() here,
        // it resets WL back to DICOM metadata (absent in dental CBCT → black canvas).
        try {
            const lg0 = dwvApp.getActiveLayerGroup()
                     ?? dwvApp.getLayerGroupByDivId?.('layerGroup0');
            if (!lg0) { console.warn('[dwv] applyWl: no layerGroup'); return; }

            const vc = lg0.getActiveViewLayer()?.getViewController();
            if (!vc) { console.warn('[dwv] applyWl: no vc'); return; }

            // 'minmax' is a built-in dwv preset that computes WL from actual pixel data.
            // It is always available (set in View constructor). This is the only reliable
            // method for dental CBCT, which has no valid WindowCenter/WindowWidth in metadata.
            try {
                vc.setWindowLevelPreset('minmax');
            } catch {
                // Fallback: compute manually using the exported WindowLevel class.
                const range = vc.getImageRescaledDataRange?.();
                if (range) {
                    const w = range.max - range.min;
                    const c = range.min + w / 2;
                    vc.setWindowLevel(new WindowLevel(c, w));
                }
            }

            const finalWl = vc.getWindowLevel();
            console.log('[dwv] WL:', finalWl?.center, finalWl?.width);
            wc.value = finalWl?.center != null ? Math.round(finalWl.center) : '-';
            ww.value = finalWl?.width  != null ? Math.round(finalWl.width)  : '-';
        } catch (e) {
            console.error('[dwv] applyWl error:', e);
        }
    };

    // For single files: show immediately on first 'load'.
    // For series: 'load' fires per-slice — skip it; wait for 'loadend' (fires once when all done).
    if (!isMulti) {
        dwvApp.addEventListener('load', () => {
            if (timeoutId) { clearTimeout(timeoutId); timeoutId = null; }
            loaded.value = true;
            setTool(activeTool.value);
            applyWl();
        });
    }

    dwvApp.addEventListener('loadend', () => {
        restoreFetch();
        if (timeoutId) { clearTimeout(timeoutId); timeoutId = null; }
        loaded.value = true;
        setTool(activeTool.value);
        applyWl();
    });

    timeoutId = setTimeout(() => { if (!loaded.value) error.value = true; }, 600_000);

    let dwvDataId = null;
    dwvDataId = dwvApp.loadURLs(urls);
    console.log('[dwv] loadURLs dataId:', dwvDataId, '| urls:', urls.length);
}

async function initSerie() {
    if (!props.fileId) return;
    try {
        loadMsg.value = 'Obteniendo información de la serie...';
        const info = await fetch(`/archivos/${props.fileId}/serie/info`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).then(r => r.json());

        // ZIP not yet extracted: dwv 0.36 supports ZIP natively via JSZip.
        // Pass the signed S3 URL directly — dwv extracts DICOM files in the browser.
        if (info.type === 'zip_url') {
            loadMsg.value = 'Cargando CBCT desde ZIP...';
            await initDwv([info.url]);
            return;
        }

        // ZIP not yet extracted and no signed URL (local dev / Minio): use PHP proxy URL.
        if (info.type === 'zip_proxy') {
            loadMsg.value = 'Cargando CBCT desde ZIP (vía servidor)...';
            await initDwv([info.proxy_url]);
            return;
        }

        sliceCount.value = info.count ?? 0;
        if (sliceCount.value === 0) { error.value = true; return; }

        loadMsg.value = `Cargando serie CBCT (${sliceCount.value} cortes)...`;

        // Already extracted: load individual slices.
        // Try signed S3 URLs (direct browser→S3, parallel).
        // Range probe: only fetch 128 bytes to confirm CORS without downloading a full slice.
        let urls;
        if (info.urls?.length) {
            loadMsg.value = 'Verificando acceso S3...';
            const corsOk = await fetch(info.urls[0], {
                mode: 'cors',
                headers: { Range: 'bytes=0-127' },
            }).then(r => r.ok || r.status === 206).catch(() => false);

            if (corsOk) {
                urls = info.urls;
                loadMsg.value = `Cargando ${sliceCount.value} cortes desde S3...`;
            } else {
                urls = Array.from({ length: sliceCount.value }, (_, i) =>
                    `/archivos/${props.fileId}/slice/${i}`
                );
                loadMsg.value = `Cargando ${sliceCount.value} cortes vía servidor...`;
            }
        } else {
            urls = Array.from({ length: sliceCount.value }, (_, i) =>
                `/archivos/${props.fileId}/slice/${i}`
            );
        }

        await initDwv(urls);
    } catch (e) {
        console.error('[dwv] serie error:', e);
        error.value = true;
        if (timeoutId) { clearTimeout(timeoutId); timeoutId = null; }
    }
}

async function initFile() {
    if (!props.fileUrl) { error.value = true; return; }
    try { await initDwv([props.fileUrl]); }
    catch (e) {
        console.error('[dwv] file error:', e);
        error.value = true;
        if (timeoutId) { clearTimeout(timeoutId); timeoutId = null; }
    }
}

function setTool(toolId) {
    activeTool.value = toolId;
    if (dwvApp && loaded.value) {
        try {
            dwvApp.setTool(toolId);
            if (toolId === 'Draw') dwvApp.setToolFeatures({ shapeName: 'Ruler' });
        } catch {}
    }
}

onMounted(() => {
    if (props.fileId) initSerie();
    else              initFile();
});

onUnmounted(() => {
    if (timeoutId) clearTimeout(timeoutId);
    if (dwvApp) { try { dwvApp.reset(); } catch {} dwvApp = null; }
});

function onKey(e) {
    if (e.key === '+' || e.key === '=') { try { dwvApp?.zoom(0.1);  } catch {} }
    if (e.key === '-')                  { try { dwvApp?.zoom(-0.1); } catch {} }
}
onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<style>
#dwv-layer-root canvas { display: block; }
#layerGroup0 { cursor: crosshair; }
</style>
