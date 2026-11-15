<template>
  <!--
    The route a trip took through its cave's 3D survey: cave-viewer (the
    CaveSurveying sites) in a frame. Editing, the route is drawn there (pick the
    places, add stops, drag the route) and kept as the viewer reports it, with
    the time the party reached each stop if they noted it. Showing a trip, it
    opens the trip's route, with how long other parties take over it.
  -->
  <div class="cave-route-3d">
    <div class="frame" :class="{ tall: editable }">
      <iframe ref="frame" :src="src" title="The route in 3D" allow="fullscreen; clipboard-write" loading="lazy" />
    </div>

    <div v-if="route" class="mt-3">
      <div class="d-flex flex-wrap ga-2 mb-2">
        <v-chip size="small" variant="tonal">{{ fmtM(route.length) }}</v-chip>
        <v-chip size="small" variant="tonal">↑ {{ route.up }} m ↓ {{ route.down }} m</v-chip>
        <v-chip v-if="route.another" size="small" variant="tonal">Back another way</v-chip>
        <v-chip v-if="typical?.duration" size="small" color="primary" variant="tonal">
          Most parties: {{ fmtMins(typical.duration.quick) }}–{{ fmtMins(typical.duration.slow) }} ({{ typical.trips }} trips)
        </v-chip>
      </div>

      <div class="text-body-small text-medium-emphasis mb-1">
        From <b>{{ route.from || firstName }}</b><template v-if="route.passages && route.passages.length">, through {{ route.passages.slice(0, 6).join(' → ') }}<template v-if="route.passages.length > 6"> …</template></template>
      </div>

      <v-table density="compact" class="stops">
        <tbody>
          <tr v-for="(stop, k) in route.stops" :key="k">
            <td class="dot"><span :class="k === route.stops.length - 1 ? 'end' : 'via'" /></td>
            <td>{{ stop.name }}</td>
            <td class="text-right text-medium-emphasis">{{ fmtM(stop.at) }}</td>
            <td class="time">
              <v-text-field v-if="editable" v-model="times[k]" type="time" density="compact" hide-details variant="underlined"
                            :aria-label="`When you reached ${stop.name}`" @update:model-value="emitRoute" />
              <span v-else-if="stop.time">{{ clock(stop.time) }}<span v-if="startTime" class="text-medium-emphasis"> ({{ fmtMins(minsIn(stop.time)) }} in)</span></span>
              <span v-else-if="typicalAt(k)" class="text-medium-emphasis">usually {{ fmtMins(typicalAt(k).median) }} in</span>
            </td>
          </tr>
        </tbody>
      </v-table>
      <div v-if="editable" class="text-body-small text-medium-emphasis mt-1">
        Times are optional: when you got to each stop, if you noted it. They help others plan.
      </div>
    </div>
    <div v-else-if="editable" class="text-body-small text-medium-emphasis mt-2">
      Pick where you went in the 3D view: From and To, stops on the way, or drag the route onto the passages you took.
    </div>

    <div class="mt-2">
      <a :href="openUrl" target="_blank" rel="noopener" class="text-body-small">Open in 3D ↗</a>
      <v-btn v-if="editable && route" variant="text" size="small" class="ml-2" @click="clear">Remove the route</v-btn>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import moment from 'moment'
import { api } from '@/plugins/api'

const props = defineProps({
  viewerUrl: { type: String, required: true },     // the cave system's cave-viewer page
  modelValue: { type: Object, default: null },     // the route, as cave-viewer reports it, with stop times
  editable: { type: Boolean, default: false },
  startTime: { type: [String, Object], default: null },   // the trip's start, for the stops' times
  caveSystemId: { type: [Number, String], default: null }, // for how long others take
})
const emit = defineEmits(['update:modelValue'])

const frame = ref(null)
const route = ref(props.modelValue)
// the times typed against each stop (HH:mm), kept by stop as the route changes
const times = ref((props.modelValue?.stops || []).map(s => s.time ? moment(s.time).format('HH:mm') : ''))
const typical = ref(null)

// The frame opens on the trip's route, once: after that the viewer says what it shows.
const base = computed(() => props.viewerUrl.split('#')[0])
const src = base.value + (props.modelValue?.link || '')
const openUrl = computed(() => base.value + (route.value?.link || ''))
const origin = computed(() => { try { return new URL(base.value).origin } catch { return null } })
const firstName = computed(() => route.value?.link ? new URLSearchParams(route.value.link.slice(1)).get('from') : '')

const fmtM = m => m == null ? '' : m >= 1000 ? `${(m / 1000).toFixed(2)} km` : `${Math.round(m)} m`
const fmtMins = m => m == null ? '' : m >= 60 ? `${Math.floor(m / 60)}h${String(m % 60).padStart(2, '0')}` : `${m} min`
const clock = t => moment(t).format('HH:mm')
const minsIn = t => props.startTime ? Math.max(0, Math.round(moment(t).diff(moment(props.startTime), 'minutes', true))) : null
const typicalAt = k => typical.value?.stops?.find(s => s.stop === k) || null

// a time of day typed against a stop, on the trip's day (or the next, if it's past midnight)
function stopTime(hhmm) {
  if (!hhmm || !props.startTime) return null
  const start = moment(props.startTime)
  const t = moment(start.format('YYYY-MM-DD') + ' ' + hhmm, 'YYYY-MM-DD HH:mm')
  if (t.isBefore(start)) t.add(1, 'day')
  return t.format()
}

function emitRoute() {
  if (!route.value) { emit('update:modelValue', null); return }
  emit('update:modelValue', {
    ...route.value,
    stops: route.value.stops.map((s, k) => ({ name: s.name, at: s.at, time: stopTime(times.value[k]) })),
  })
}

function onMessage(e) {
  if (!frame.value || e.source !== frame.value.contentWindow || (origin.value && e.origin !== origin.value)) return
  const m = e.data
  if (!m || m.source !== 'cave-viewer') return
  if (m.type === 'ready' && props.editable && !route.value) post({ type: 'open' })
  if (m.type === 'route' && props.editable) {
    // keep each stop's time where the stop is still there (by its name)
    const before = new Map((route.value?.stops || []).map((s, k) => [s.name, times.value[k]]))
    route.value = m.route
    times.value = (m.route?.stops || []).map(s => before.get(s.name) || '')
    emitRoute()
    loadTypical()
  }
}
const post = msg => frame.value?.contentWindow?.postMessage({ target: 'cave-viewer', ...msg }, origin.value || '*')
function clear() { post({ type: 'route', link: '' }); route.value = null; times.value = []; emitRoute() }

let pending = null
async function loadTypical() {
  if (!props.caveSystemId || !route.value?.link) { typical.value = null; return }
  clearTimeout(pending)
  pending = setTimeout(async () => {
    try {
      const r = await api.get(`/api/cave_systems/${props.caveSystemId}/route-times`, { params: { link: route.value.link } })
      typical.value = r.data     // (duration null and no stops until enough trips have the route)
    } catch { typical.value = null }
  }, 400)
}

onMounted(() => { addEventListener('message', onMessage); loadTypical() })
onBeforeUnmount(() => removeEventListener('message', onMessage))
watch(() => props.modelValue, v => { if (!props.editable) { route.value = v; loadTypical() } })
</script>

<style scoped>
.frame { position: relative; width: 100%; aspect-ratio: 16 / 10; border-radius: 12px; overflow: hidden; background: #0b0f17; }
.frame.tall { aspect-ratio: auto; height: min(70vh, 640px); }
.frame iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
.stops td { vertical-align: middle; }
.stops .dot { width: 18px; padding-right: 0 !important; }
.stops .dot span { display: inline-block; width: 10px; height: 10px; border-radius: 50%; }
.stops .dot .via { background: #ffd27a; }
.stops .dot .end { background: #ff6b6b; }
.stops .time { width: 120px; }
</style>
