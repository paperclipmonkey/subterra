<template>
  <!--
    A route through a cave's 3D survey, as cave-viewer reported it: its length
    and climb, the passages it goes through and its stops. On a trip, the time
    the party reached each stop (typed in while editing), and how long other
    parties usually take where enough have gone the same way.
  -->
  <div class="viewer-route">
    <div class="d-flex flex-wrap ga-2 mb-2">
      <v-chip size="small" variant="tonal">{{ fmtM(route.length) }}</v-chip>
      <v-chip size="small" variant="tonal">↑ {{ route.up }} m ↓ {{ route.down }} m</v-chip>
      <v-chip v-if="route.another" size="small" variant="tonal">Back another way</v-chip>
      <v-chip v-if="typical?.duration" size="small" color="primary" variant="tonal">
        Most parties: {{ fmtMins(typical.duration.quick) }}–{{ fmtMins(typical.duration.slow) }} ({{ typical.trips }} trips)
      </v-chip>
    </div>

    <div class="text-body-small text-medium-emphasis mb-1">
      From <b>{{ from }}</b><template v-if="route.passages?.length">, through {{ route.passages.slice(0, 6).join(' → ') }}<template v-if="route.passages.length > 6"> …</template></template>
    </div>

    <v-table density="compact" class="stops">
      <tbody>
        <tr v-for="(stop, k) in route.stops" :key="k">
          <td class="dot"><span :class="k === route.stops.length - 1 ? 'end' : 'via'" /></td>
          <td>{{ stop.name }}</td>
          <td class="text-right text-medium-emphasis">{{ fmtM(stop.at) }}</td>
          <td v-if="times" class="time">
            <v-text-field v-if="editTimes" :model-value="hhmm(stop.time)" type="time" density="compact" hide-details variant="underlined"
                          :aria-label="`When you reached ${stop.name}`" @update:model-value="v => setTime(k, v)" />
            <span v-else-if="stop.time">{{ hhmm(stop.time) }}<span v-if="startTime" class="text-medium-emphasis"> ({{ fmtMins(minsIn(stop.time)) }} in)</span></span>
            <span v-else-if="typicalAt(k)" class="text-medium-emphasis">usually {{ fmtMins(typicalAt(k).median) }} in</span>
          </td>
        </tr>
      </tbody>
    </v-table>
    <div v-if="editTimes" class="text-body-small text-medium-emphasis mt-1">
      Times are optional: when you got to each stop, if you noted it. They help others plan.
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import moment from 'moment'
import { api } from '@/plugins/api'

const props = defineProps({
  route: { type: Object, required: true },
  times: { type: Boolean, default: false },        // a trip's: each stop's time
  editTimes: { type: Boolean, default: false },
  startTime: { type: [String, Object], default: null },
  caveSystemId: { type: [Number, String], default: null },   // for how long others take (trips)
})
const emit = defineEmits(['update:route'])

const from = computed(() => props.route.from || new URLSearchParams((props.route.link || '').slice(1)).get('from') || '')
const fmtM = m => m == null ? '' : m >= 1000 ? `${(m / 1000).toFixed(2)} km` : `${Math.round(m)} m`
const fmtMins = m => m == null ? '' : m >= 60 ? `${Math.floor(m / 60)}h${String(m % 60).padStart(2, '0')}` : `${m} min`
const hhmm = t => t ? moment(t).format('HH:mm') : ''
const minsIn = t => props.startTime ? Math.max(0, Math.round(moment(t).diff(moment(props.startTime), 'minutes', true))) : null

// a time of day typed against a stop, on the trip's day (or the next, if it's past midnight)
function stopTime(v) {
  if (!v || !props.startTime) return null
  const start = moment(props.startTime)
  const t = moment(start.format('YYYY-MM-DD') + ' ' + v, 'YYYY-MM-DD HH:mm')
  if (t.isBefore(start)) t.add(1, 'day')
  return t.format()
}
function setTime(k, v) {
  emit('update:route', { ...props.route, stops: props.route.stops.map((s, i) => i === k ? { ...s, time: stopTime(v) } : s) })
}

// how long others take over the same route (trips only)
const typical = ref(null)
const typicalAt = k => typical.value?.stops?.find(s => s.stop === k) || null
let pending = null
watch(() => [props.times && props.caveSystemId, props.route.link], () => {
  clearTimeout(pending)
  if (!props.times || !props.caveSystemId || !props.route.link) { typical.value = null; return }
  pending = setTimeout(async () => {
    try {
      const r = await api.get(`/api/cave_systems/${props.caveSystemId}/route-times`, { params: { link: props.route.link } })
      typical.value = r.data     // (duration null and no stops until enough trips have the route)
    } catch { typical.value = null }
  }, 300)
}, { immediate: true })
</script>

<style scoped>
.stops td { vertical-align: middle; }
.stops .dot { width: 18px; padding-right: 0 !important; }
.stops .dot span { display: inline-block; width: 10px; height: 10px; border-radius: 50%; }
.stops .dot .via { background: #ffd27a; }
.stops .dot .end { background: #ff6b6b; }
.stops .time { width: 120px; }
</style>
