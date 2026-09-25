<template>
  <v-card class="mb-4">
    <v-card-title class="text-h6">
      <v-icon start :icon="mdiLayers" />
      Map Overlays (GeoTIFF)
    </v-card-title>
    <v-card-subtitle>
      Upload georeferenced survey images (.tif / .tiff) to overlay on the map. Visitors can toggle each overlay on or off.
    </v-card-subtitle>
    <v-card-text>
      <!-- Existing overlays -->
      <v-list v-if="overlays.length" density="compact" class="mb-2">
        <v-list-item
          v-for="ov in overlays"
          :key="ov.id"
          class="overlay-item"
        >
          <template #prepend>
            <v-icon :icon="mdiImageFilterHdr" />
          </template>
          <v-list-item-title>{{ ov.name }}</v-list-item-title>
          <v-list-item-subtitle>{{ ov.original_filename }}</v-list-item-subtitle>

          <div class="d-flex align-center ga-4 mt-2 flex-wrap">
            <div class="opacity-control">
              <span class="text-caption text-medium-emphasis">Opacity</span>
              <v-slider
                :model-value="ov.opacity"
                :min="0"
                :max="1"
                :step="0.05"
                hide-details
                density="compact"
                color="primary"
                style="min-width: 140px"
                @end="(val) => updateOverlay(ov, { opacity: val })"
              />
            </div>
            <v-switch
              :model-value="ov.visible_by_default"
              label="Shown by default"
              color="primary"
              density="compact"
              hide-details
              @update:model-value="(val) => updateOverlay(ov, { visible_by_default: val })"
            />
            <v-spacer />
            <v-btn
              color="red"
              variant="text"
              size="small"
              :prepend-icon="mdiDelete"
              :loading="deletingId === ov.id"
              @click="removeOverlay(ov)"
            >
              Delete
            </v-btn>
          </div>
          <v-divider class="mt-2" />
        </v-list-item>
      </v-list>
      <p v-else class="text-medium-emphasis text-body-2 mb-3">No overlays yet.</p>

      <!-- Upload new overlay -->
      <v-row class="align-center">
        <v-col cols="12" md="4">
          <v-text-field
            v-model="newName"
            label="Overlay name"
            density="compact"
            variant="outlined"
            hide-details
            placeholder="e.g. Main survey"
          />
        </v-col>
        <v-col cols="12" md="5">
          <v-file-input
            v-model="newFile"
            label="GeoTIFF file (.tif / .tiff)"
            accept=".tif,.tiff,.gtiff,.geotiff,image/tiff"
            density="compact"
            variant="outlined"
            hide-details
            :prepend-icon="mdiImagePlus"
            show-size
          />
        </v-col>
        <v-col cols="12" md="3">
          <v-btn
            color="primary"
            block
            :loading="uploading"
            :disabled="!canUpload"
            :prepend-icon="mdiUpload"
            @click="uploadOverlay"
          >
            Upload
          </v-btn>
        </v-col>
      </v-row>

      <v-alert
        v-if="error"
        type="error"
        variant="tonal"
        density="compact"
        class="mt-3"
        closable
        @click:close="error = ''"
      >
        {{ error }}
      </v-alert>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { mdiLayers, mdiDelete, mdiUpload, mdiImagePlus, mdiImageFilterHdr } from '@mdi/js'
import { api } from '@/plugins/api'
import { parseGeoTiff } from '@/utilities/geotiffOverlay'

const props = defineProps({
  caveSystemId: {
    type: [Number, String],
    required: true,
  },
  initialOverlays: {
    type: Array,
    default: () => [],
  },
})

const overlays = ref([...props.initialOverlays])
const newName = ref('')
const newFile = ref(null)
const uploading = ref(false)
const deletingId = ref(null)
const error = ref('')

// v-file-input may yield a File or an array depending on Vuetify version/config
const selectedFile = computed(() => {
  const f = newFile.value
  if (!f) return null
  return Array.isArray(f) ? f[0] : f
})

const canUpload = computed(() => !!selectedFile.value && newName.value.trim().length > 0)

onMounted(async () => {
  // Refresh from the server in case the parent passed a stale/empty list
  if (!props.initialOverlays.length) {
    try {
      const res = await api.get(`/api/cave_systems/${props.caveSystemId}/map_overlays`)
      overlays.value = res.data.data ?? res.data
    } catch {
      /* non-fatal — leave whatever was passed in */
    }
  }
})

async function uploadOverlay () {
  const file = selectedFile.value
  if (!file || !newName.value.trim()) return

  error.value = ''
  uploading.value = true
  try {
    // Decode the GeoTIFF client-side to extract its WGS84 bounds. This also
    // validates the file is a usable, supported-CRS GeoTIFF before uploading.
    const buffer = await file.arrayBuffer()
    const { bounds } = await parseGeoTiff(buffer)

    const formData = new FormData()
    formData.append('name', newName.value.trim())
    formData.append('file', file)
    bounds.forEach(b => formData.append('bounds[]', b))
    formData.append('opacity', '0.8')
    formData.append('visible_by_default', '1')

    const res = await api.post(
      `/api/cave_systems/${props.caveSystemId}/map_overlays`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    )

    overlays.value.push(res.data.data ?? res.data)
    newName.value = ''
    newFile.value = null
  } catch (e) {
    if (e?.message?.includes('coordinate system')) {
      error.value = e.message
    } else if (e?.response?.data?.message) {
      error.value = e.response.data.message
    } else {
      error.value = 'Could not read that file as a GeoTIFF. Please check it is a valid georeferenced .tif / .tiff.'
    }
    console.error('GeoTIFF upload failed', e)
  } finally {
    uploading.value = false
  }
}

async function updateOverlay (overlay, changes) {
  try {
    const res = await api.put(
      `/api/cave_systems/${props.caveSystemId}/map_overlays/${overlay.id}`,
      changes,
    )
    const updated = res.data.data ?? res.data
    const idx = overlays.value.findIndex(o => o.id === overlay.id)
    if (idx !== -1) overlays.value[idx] = updated
  } catch (e) {
    error.value = 'Failed to update overlay.'
    console.error(e)
  }
}

async function removeOverlay (overlay) {
  if (!window.confirm(`Delete overlay "${overlay.name}"? This cannot be undone.`)) return
  deletingId.value = overlay.id
  try {
    await api.delete(`/api/cave_systems/${props.caveSystemId}/map_overlays/${overlay.id}`)
    overlays.value = overlays.value.filter(o => o.id !== overlay.id)
  } catch (e) {
    error.value = 'Failed to delete overlay.'
    console.error(e)
  } finally {
    deletingId.value = null
  }
}
</script>

<style scoped>
.opacity-control {
  display: flex;
  flex-direction: column;
}
</style>
