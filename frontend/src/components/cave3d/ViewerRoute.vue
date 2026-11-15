<template>
  <!--
    A route through the cave system's 3D survey: on a trip, the way the party
    went; on a route guide, the way it describes. Drawn and looked at full
    screen in the 3D view (Cave3DDialog); here, what it came to.
  -->
  <div class="viewer-route-block">
    <ViewerRouteSummary v-if="modelValue" :route="modelValue" :times="times" :edit-times="editable && times"
                        :start-time="startTime" :cave-system-id="caveSystemId"
                        @update:route="r => emit('update:modelValue', r)" />
    <div v-else-if="editable" class="text-body-medium text-medium-emphasis">
      {{ emptyText }}
    </div>

    <div class="d-flex flex-wrap ga-2 mt-3">
      <template v-if="editable">
        <v-btn color="primary" variant="tonal" :prepend-icon="mdiMapMarkerPath" @click="pick = true; open = true">
          {{ modelValue ? 'Change it in 3D' : 'Draw it in 3D' }}
        </v-btn>
        <v-btn v-if="modelValue" variant="text" @click="emit('update:modelValue', null)">Remove</v-btn>
      </template>
      <v-btn v-else-if="modelValue" color="primary" variant="tonal" :prepend-icon="mdiRotate3dVariant" @click="pick = false; open = true">
        {{ viewLabel }}
      </v-btn>
    </div>

    <Cave3DDialog v-model="open" :viewer-url="viewerUrl" :hash="modelValue?.link || atHash(at)" :title="title" :pick="pick"
                  @done="keep" />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { mdiMapMarkerPath, mdiRotate3dVariant } from '@mdi/js'
import Cave3DDialog from './Cave3DDialog.vue'
import ViewerRouteSummary from './ViewerRouteSummary.vue'
import { atHash } from './viewer'

const props = defineProps({
  viewerUrl: { type: String, required: true },
  modelValue: { type: Object, default: null },
  editable: { type: Boolean, default: false },
  times: { type: Boolean, default: false },         // a trip's: when the party reached each stop
  startTime: { type: [String, Object], default: null },
  caveSystemId: { type: [Number, String], default: null },
  title: { type: String, default: 'The route' },
  at: { type: String, default: '' },                // where the view opens to draw a new route
  viewLabel: { type: String, default: 'View in 3D' },
  emptyText: { type: String, default: 'Draw the route in the cave\'s 3D survey: pick where it starts and ends, add stops on the way, or drag it onto the passages.' },
})
const emit = defineEmits(['update:modelValue'])
const open = ref(false)
const pick = ref(false)

// the route as the view had it at Done, keeping each stop's time where the stop's still there
function keep(r) {
  if (!r) return
  const before = new Map((props.modelValue?.stops || []).map(s => [s.name, s.time]))
  const { link, from, to, length, up, down, another, passages } = r
  emit('update:modelValue', {
    link, from, to, length, up, down, another, passages,
    stops: r.stops.map(s => ({ name: s.name, at: s.at, ...(props.times ? { time: before.get(s.name) || null } : {}) })),
  })
}
defineExpose({ keep })
</script>
