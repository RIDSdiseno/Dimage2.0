<template>
    <div style="width:150px; height:150px; flex-shrink:0; position:relative;"
        class="border border-gray-200 rounded overflow-hidden bg-gray-100 group cursor-pointer">

        <!-- PDF: icon card -->
        <template v-if="isPdf">
            <a :href="fileSrc" target="_blank"
                style="display:flex; flex-direction:column; align-items:center; justify-content:center; width:100%; height:calc(100% - 28px);"
                class="hover:bg-red-50 transition-colors no-underline">
                <i class="pi pi-file-pdf" style="font-size:3rem; color:#e3342f;" />
                <span style="font-size:11px; margin-top:6px; padding:0 6px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:130px; color:#6b7280; text-align:center;">
                    {{ file.name || 'PDF' }}
                </span>
            </a>
            <div style="position:absolute; bottom:0; left:0; right:0; display:flex; justify-content:flex-end; padding:4px; background:rgba(0,0,0,0.35);">
                <a :href="downloadUrl" title="Descargar"
                    style="display:flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:5px; background:rgba(255,255,255,0.15); color:#fff; text-decoration:none;"
                    @click.stop>
                    <i class="pi pi-download" style="font-size:11px;" />
                </a>
            </div>
        </template>

        <!-- DCM (individual o serie CBCT) → toda la tarjeta es clickeable para abrir el visor -->
        <template v-else-if="isDcm">
            <a :href="visorUrl" target="_blank"
                style="display:flex; flex-direction:column; align-items:center; justify-content:center; width:100%; height:100%; background:#0b2a4a; position:relative; text-decoration:none;"
                @click.stop>
                <i class="pi pi-box" style="font-size:2.5rem; color:#60a5fa;" />
                <span style="font-size:10px; margin-top:5px; color:#93c5fd; font-weight:700; letter-spacing:0.08em;">
                    {{ isCbctSerie ? 'CBCT / DICOM' : 'DICOM' }}
                </span>
                <span style="font-size:10px; margin-top:3px; padding:0 6px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:130px; color:#60a5fa; text-align:center;">
                    {{ file.name || 'Archivo' }}
                </span>
                <!-- Botón visor siempre visible abajo -->
                <div style="position:absolute; bottom:6px; right:6px; display:flex; gap:4px;">
                    <span style="display:flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:5px; background:rgba(52,82,255,0.9); color:#fff;">
                        <i class="pi pi-eye" style="font-size:12px;" />
                    </span>
                    <a :href="fileSrc" target="_blank" download title="Descargar"
                        style="display:flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:5px; background:rgba(255,255,255,0.15); color:#fff; text-decoration:none;"
                        @click.stop>
                        <i class="pi pi-download" style="font-size:12px;" />
                    </a>
                </div>
            </a>
        </template>

        <!-- ZIP CBCT en cola (procesando en background) -->
        <template v-else-if="isProcessing">
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; width:100%; height:100%; background:#0b2a4a; position:relative;">
                <i class="pi pi-spin pi-spinner" style="font-size:2.5rem; color:#60a5fa;" />
                <span style="font-size:10px; margin-top:5px; color:#93c5fd; font-weight:700; letter-spacing:0.08em;">CBCT</span>
                <span style="font-size:10px; margin-top:3px; color:#60a5fa; text-align:center; padding:0 6px;">Procesando...</span>
                <!-- Hover: abrir visor ahora (ZIP carga directo en dwv) -->
                <div class="opacity-0 group-hover:opacity-100 transition-opacity"
                    style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; background:rgba(11,42,74,0.92); border-radius:inherit;">
                    <a :href="processingVisorUrl" target="_blank"
                        style="display:flex; align-items:center; gap:5px; padding:6px 12px; border-radius:6px; background:#3452ff; color:#fff; text-decoration:none; font-size:11px; font-weight:600;"
                        @click.stop>
                        <i class="pi pi-eye" style="font-size:11px;" /> Ver ahora
                    </a>
                    <span style="font-size:9px; color:#93c5fd; text-align:center; padding:0 8px;">Extracción en segundo plano</span>
                </div>
            </div>
        </template>

        <!-- ZIP CBCT con error de procesamiento -->
        <template v-else-if="isZipError">
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; width:100%; height:100%; background:#3b0a0a; position:relative;">
                <i class="pi pi-times-circle" style="font-size:2.5rem; color:#f87171;" />
                <span style="font-size:10px; margin-top:5px; color:#fca5a5; font-weight:700; letter-spacing:0.08em;">Error CBCT</span>
                <span style="font-size:10px; margin-top:3px; color:#fca5a5; text-align:center; padding:0 6px;">Procesamiento fallido</span>
                <div class="opacity-0 group-hover:opacity-100 transition-opacity"
                    style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; background:rgba(59,10,10,0.92); border-radius:inherit;">
                    <button
                        style="display:flex; align-items:center; gap:5px; padding:6px 12px; border-radius:6px; background:#3452ff; color:#fff; border:none; cursor:pointer; font-size:11px; font-weight:600;"
                        @click.stop="processZip">
                        <i class="pi pi-refresh" style="font-size:11px;" /> Reintentar
                    </button>
                    <a :href="downloadUrl" :download="file.name"
                        style="display:flex; align-items:center; gap:5px; padding:5px 10px; border-radius:6px; background:rgba(255,255,255,0.1); color:#fca5a5; text-decoration:none; font-size:10px;"
                        @click.stop>
                        <i class="pi pi-download" style="font-size:10px;" /> Descargar
                    </a>
                </div>
            </div>
        </template>

        <!-- ZIP/desconocido CBCT pendiente → procesar para visor o descargar -->
        <template v-else-if="isCbctPendiente">
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; width:100%; height:100%; background:#0b2a4a; position:relative;">
                <i :class="extracting ? 'pi pi-spin pi-spinner' : 'pi pi-box'" style="font-size:2.5rem; color:#60a5fa;" />
                <span style="font-size:10px; margin-top:5px; color:#93c5fd; font-weight:700; letter-spacing:0.08em;">CBCT / DICOM</span>
                <span style="font-size:10px; margin-top:3px; padding:0 6px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:130px; color:#60a5fa; text-align:center;">
                    {{ extractMsg || file.name || 'Archivo' }}
                </span>
                <!-- Hover: procesar + descargar -->
                <div v-if="!extracting" class="opacity-0 group-hover:opacity-100 transition-opacity"
                    style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; background:rgba(11,42,74,0.92); border-radius:inherit;">
                    <button
                        style="display:flex; align-items:center; gap:5px; padding:6px 12px; border-radius:6px; background:#3452ff; color:#fff; border:none; cursor:pointer; font-size:11px; font-weight:600;"
                        @click.stop="processZip">
                        <i class="pi pi-cog" style="font-size:11px;" /> Procesar para visor
                    </button>
                    <a :href="downloadUrl" :download="file.name"
                        style="display:flex; align-items:center; gap:5px; padding:5px 10px; border-radius:6px; background:rgba(255,255,255,0.1); color:#93c5fd; text-decoration:none; font-size:10px;"
                        @click.stop>
                        <i class="pi pi-download" style="font-size:10px;" /> Descargar ZIP
                    </a>
                </div>
            </div>
        </template>

        <!-- Image: try as img, fallback to generic download -->
        <template v-else>
            <img v-if="!imgFailed"
                :src="fileSrc"
                :alt="file.name"
                loading="lazy"
                style="width:100%; height:100%; object-fit:cover; display:block;"
                @error="imgFailed = true" />

            <a v-else :href="fileSrc" target="_blank"
                style="display:flex; flex-direction:column; align-items:center; justify-content:center; width:100%; height:100%;"
                class="hover:bg-blue-50 transition-colors no-underline">
                <i class="pi pi-file" style="font-size:3rem; color:#9ca3af;" />
                <span style="font-size:11px; margin-top:6px; padding:0 6px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:130px; color:#6b7280; text-align:center;">
                    {{ file.name || 'Archivo' }}
                </span>
            </a>

            <!-- Botones siempre visibles en la parte inferior -->
            <div v-if="!imgFailed"
                style="position:absolute; bottom:0; left:0; right:0; display:flex; justify-content:flex-end; gap:4px; padding:5px; background:rgba(0,0,0,0.45);">
                <!-- Descargar -->
                <a :href="downloadUrl" title="Descargar"
                    style="display:flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:5px; background:rgba(255,255,255,0.15); color:#fff; text-decoration:none;"
                    @click.stop>
                    <i class="pi pi-download" style="font-size:11px;" />
                </a>
                <!-- Herramientas: abre el visor con panel de herramientas -->
                <button
                    @click.stop="$emit('lightbox', { ...file, url: fileSrc })"
                    title="Ver con herramientas"
                    style="display:flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:5px; background:rgba(52,82,255,0.85); color:#fff; border:none; cursor:pointer;">
                    <i class="pi pi-wrench" style="font-size:11px;" />
                </button>
                <!-- Ampliar: abre la imagen en visor full-screen en nueva pestaña -->
                <button
                    @click.stop="openAmpliar"
                    title="Ampliar (nueva pestaña)"
                    style="display:flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:5px; background:rgba(255,255,255,0.15); color:#fff; border:none; cursor:pointer;">
                    <i class="pi pi-search" style="font-size:11px;" />
                </button>
            </div>
        </template>

        <!-- Tooltip bar -->
        <div style="position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,0.6); color:white; font-size:10px; padding:2px 4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
            class="opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
            {{ file.name }}
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    file:       { type: Object, required: true },
    showDicom:  { type: Boolean, default: false }, // forzar visor DICOM (ej. ZIP de Cone Beam)
});

const emit = defineEmits(['lightbox', 'extracted']);

const imgFailed  = ref(false);
const extracting = ref(false);
const extractMsg = ref('');

// Derive extension from the field, or fall back to the filename extension
const ext = computed(() => {
    if (props.file.extension) return props.file.extension.toLowerCase();
    const m = (props.file.name || '').match(/\.([^.]+)$/);
    return m ? m[1].toLowerCase() : '';
});
const isPdf       = computed(() => ext.value === 'pdf');
// Imágenes comunes: siempre se muestran como imagen, nunca como DICOM/CBCT
const isRegularImage = computed(() => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext.value));
// Consider a file as DCM if extension says so, OR if ruta_dcm already holds a
// series prefix (covers legacy ZIPs that were processed before extension was tracked)
// Nunca tratar imágenes comunes como DCM aunque ruta_dcm esté seteado en BD
const isDcm       = computed(() =>
    !isRegularImage.value && (
        ext.value === 'dcm' || ext.value === 'dicom' ||
        (!!props.file.ruta_dcm && props.file.ruta_dcm !== 'processing')
    )
);
const isZip       = computed(() => ext.value === 'zip');
const isZipError  = computed(() => ext.value === 'zip_error');
const isProcessing = computed(() => props.file.ruta_dcm === 'processing' && !isDcm.value);
const isCbctSerie = computed(() => isDcm.value && !!props.file.ruta_dcm && props.file.ruta_dcm !== 'processing');
// Unprocessed CBCT: ZIP sin extraer o extensión vacía en examen CBCT (nunca imágenes normales ni PDFs)
const isCbctPendiente = computed(() =>
    props.showDicom && !isPdf.value && !isDcm.value && !isZipError.value && !isProcessing.value && !isRegularImage.value
);
const processingVisorUrl = computed(() => {
    const base = route('visor-dicom');
    const params = new URLSearchParams({ id: props.file.id, name: props.file.name || 'CBCT' });
    return `${base}?${params.toString()}`;
});
const serveUrl    = computed(() => route('archivos.serve', props.file.id));
const downloadUrl = computed(() => route('archivos.download', props.file.id));
const fileSrc     = computed(() => props.file.url || serveUrl.value);

async function processZip() {
    extracting.value = true;
    extractMsg.value = 'Procesando...';
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const res = await fetch(route('archivos.extraer', props.file.id), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });
        const json = await res.json();
        if (json.ok) {
            extractMsg.value = json.message || 'Listo';
            emit('extracted', props.file.id);
            // Reload page after brief delay so thumbnail refreshes
            setTimeout(() => window.location.reload(), 1200);
        } else {
            extractMsg.value = json.message || 'Error';
            extracting.value = false;
        }
    } catch (e) {
        extractMsg.value = 'Error de red';
        extracting.value = false;
    }
}

function openAmpliar() {
    const url  = fileSrc.value;
    const name = (props.file.name || 'Imagen').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    const html = `<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>${name}</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{width:100%;height:100%;background:#000;overflow:hidden}
#wrap{width:100vw;height:100vh;display:flex;align-items:center;justify-content:center;cursor:zoom-in;overflow:hidden}
#wrap.full{overflow:auto;align-items:flex-start;justify-content:flex-start;cursor:zoom-out}
img{width:100vw;height:100vh;object-fit:contain;user-select:none;display:block;flex-shrink:0}
#wrap.full img{width:auto;height:auto;min-width:100%;object-fit:none}
#hint{position:fixed;bottom:12px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.35);font:12px/1 Arial,sans-serif;pointer-events:none;white-space:nowrap}
</style>
</head>
<body>
<div id="wrap" onclick="toggle()">
  <img src="${url}" alt="${name}" />
</div>
<div id="hint">Click para ver tamaño real &middot; Esc para ajustar</div>
<script>
function toggle(){
  var w=document.getElementById('wrap');
  w.classList.toggle('full');
  document.getElementById('hint').style.display=w.classList.contains('full')?'none':'block';
}
document.addEventListener('keydown',function(e){
  if(e.key==='Escape'){document.getElementById('wrap').classList.remove('full');document.getElementById('hint').style.display='block';}
});
<\/script>
</body>
</html>`;
    const blob   = new Blob([html], { type: 'text/html' });
    const blobUrl = URL.createObjectURL(blob);
    window.open(blobUrl, '_blank');
    setTimeout(() => URL.revokeObjectURL(blobUrl), 10000);
}

const visorUrl = computed(() => {
    const base = route('visor-dicom');
    if (isCbctSerie.value) {
        // CBCT serie: pass file ID so the viewer can load all slices
        const params = new URLSearchParams({
            id:   props.file.id,
            name: props.file.name || 'DICOM',
        });
        return `${base}?${params.toString()}`;
    }
    // Single DCM: pass the serve URL with filename for dwv type detection
    const fname = encodeURIComponent(props.file.name || `file.${ext.value || 'bin'}`);
    const params = new URLSearchParams({
        url: `/archivos/${props.file.id}/${fname}`,
        name: props.file.name || 'DICOM',
    });
    return `${base}?${params.toString()}`;
});
</script>
