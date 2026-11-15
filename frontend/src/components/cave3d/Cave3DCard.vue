<template>
  <!--
    A cave (or a whole system) in its 3D survey: the still the site builds of
    it, and the view full screen when it's opened.
  -->
  <v-card class="cave-3d-card rounded-lg overflow-hidden" elevation="2">
    <button class="still" type="button" :aria-label="`Explore ${name} in 3D`" @click="open = true">
      <img v-if="preview && !noPreview" :src="preview" :alt="`${name} in 3D, under the ground surface`" loading="lazy" @error="noPreview = true">
      <span class="go">
        <v-icon :icon="mdiRotate3dVariant" size="small" class="mr-1" />
        Explore in 3D
      </span>
    </button>
    <v-card-text class="py-3">
      <div class="text-body-medium">
        The survey in 3D under the ground, with directions between any two places in the cave.
      </div>
      <div class="d-flex flex-wrap ga-2 mt-2">
        <v-btn size="small" color="primary" variant="tonal" :prepend-icon="mdiRotate3dVariant" @click="open = true">Explore in 3D</v-btn>
        <v-btn size="small" variant="text" :prepend-icon="mdiMapMarkerPath" @click="open = true; routes = true">Find a route</v-btn>
      </div>
    </v-card-text>
    <Cave3DDialog v-model="open" :viewer-url="viewerUrl" :hash="atHash(at)" :title="name" :pick="false" :open-routes="routes"
                  @update:model-value="v => { if (!v) routes = false }" />
  </v-card>
</template>

<script setup>
import { computed, ref } from 'vue'
import { mdiMapMarkerPath, mdiRotate3dVariant } from '@mdi/js'
import Cave3DDialog from './Cave3DDialog.vue'
import { atHash, viewerPreview } from './viewer'

const props = defineProps({
  viewerUrl: { type: String, required: true },
  name: { type: String, required: true },
  at: { type: String, default: '' },     // where the view opens (an entrance in it), or the whole of it
})
const open = ref(false)
const routes = ref(false)
const noPreview = ref(false)
const preview = computed(() => viewerPreview(props.viewerUrl))
</script>

<style scoped>
.still { display: block; position: relative; width: 100%; aspect-ratio: 16 / 9; background: #0b0f17; border: 0; padding: 0; cursor: pointer; }
.still img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .4s; }
.still:hover img { transform: scale(1.03); }
.go { position: absolute; left: 12px; bottom: 12px; display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 999px;
  background: rgba(11, 15, 23, .72); color: #fff; font-size: 13px; font-weight: 600; backdrop-filter: blur(6px); }
</style>
