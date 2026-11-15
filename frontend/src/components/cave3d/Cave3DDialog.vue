<template>
  <!--
    A cave system's 3D survey (cave-viewer) full screen: room to move about in,
    and only loaded when it's opened. Drawing a route (pick), the view reports
    the route as it changes and Done keeps it.
  -->
  <v-dialog :model-value="modelValue" fullscreen transition="dialog-bottom-transition"
            @update:model-value="v => !v && close()">
    <v-card class="cave-3d d-flex flex-column" color="#0b0f17" theme="dark">
      <v-toolbar density="comfortable" color="#0b0f17">
        <v-btn :icon="mdiClose" aria-label="Close the 3D view" @click="close" />
        <v-toolbar-title>
          <span class="text-title-medium">{{ title }}</span>
          <span v-if="pick && route" class="text-body-small text-medium-emphasis ml-2">{{ summary }}</span>
        </v-toolbar-title>
        <template v-if="pick">
          <v-btn v-if="route" variant="text" class="mr-1" @click="clear">Clear</v-btn>
          <v-btn color="primary" variant="flat" class="mr-2" :disabled="!route" @click="done">Done</v-btn>
        </template>
      </v-toolbar>
      <div v-if="pick && !route" class="hint text-body-small px-4 py-2">
        Pick From and To in the route panel, add stops on the way, or drag the route onto the passages you took.
      </div>
      <div class="frame flex-grow-1">
        <iframe v-if="modelValue" ref="frame" :src="src" :title="`${title} in 3D`" allow="fullscreen; xr-spatial-tracking; clipboard-write" />
      </div>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { mdiClose } from '@mdi/js'
import { viewerBase, viewerOrigin } from './viewer'

const props = defineProps({
  modelValue: { type: Boolean, default: false },   // open
  viewerUrl: { type: String, required: true },
  hash: { type: String, default: '' },              // where it opens: '#at=…', or a route's '#from=…'
  title: { type: String, default: 'The cave' },
  pick: { type: Boolean, default: false },          // drawing a route, to keep
  openRoutes: { type: Boolean, default: false },    // open on the route panel
})
const emit = defineEmits(['update:modelValue', 'done'])

const frame = ref(null)
const route = ref(null)     // what the view shows now, as it reports it
// the frame's address is fixed while it's open: the view keeps its own after that
const src = ref('')
watch(() => props.modelValue, open => {
  if (open) { src.value = viewerBase(props.viewerUrl) + (props.hash || ''); route.value = null }
}, { immediate: true })
const origin = computed(() => viewerOrigin(props.viewerUrl))

const fmtM = m => m >= 1000 ? `${(m / 1000).toFixed(2)} km` : `${Math.round(m)} m`
const summary = computed(() => route.value ? `${fmtM(route.value.length)}, ${route.value.stops.length - 1 ? `${route.value.stops.length - 1} stop${route.value.stops.length > 2 ? 's' : ''}, ` : ''}↑ ${route.value.up} m ↓ ${route.value.down} m` : '')

const post = msg => frame.value?.contentWindow?.postMessage({ target: 'cave-viewer', ...msg }, origin.value || '*')

function onMessage(e) {
  if (!frame.value || e.source !== frame.value.contentWindow || (origin.value && e.origin !== origin.value)) return
  const m = e.data
  if (!m || m.source !== 'cave-viewer') return
  if (m.type === 'ready' && (props.pick || props.openRoutes) && !/^#from=/.test(props.hash || '')) post({ type: 'open' })
  if (m.type === 'route') route.value = m.route || null
}
onMounted(() => addEventListener('message', onMessage))
onBeforeUnmount(() => removeEventListener('message', onMessage))

function close() { emit('update:modelValue', false) }
function clear() { post({ type: 'route', link: '' }); route.value = null }
function done() { emit('done', route.value); close() }
defineExpose({ done })
</script>

<style scoped>
.cave-3d { height: 100%; }
.frame { position: relative; min-height: 0; }
.frame iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
.hint { background: rgba(255, 210, 122, .12); color: #ffd27a; }
</style>
