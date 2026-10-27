<template>
  <v-dialog v-model="dialog" max-width="800px">
    <!-- Attributes land on the activator button rather than the dialog, so a
         caller can restyle it for the background it sits on. -->
    <template #activator="{ props: activatorProps }">
      <v-btn v-if="canEdit" color="primary" variant="text" :prepend-icon="isNew ? mdiPlus : mdiPencil"
             v-bind="{ ...$attrs, ...activatorProps }">
        {{ isNew ? 'New Hut' : 'Edit Hut' }}
      </v-btn>
    </template>

    <v-card>
      <v-card-title>
        <span class="text-headline-small">{{ isNew ? 'New Hut' : 'Edit Hut' }}</span>
      </v-card-title>

      <v-card-text>
        <v-container>
          <v-form ref="form" v-model="valid">
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field v-model="editedHut.name" label="Name" required />
              </v-col>
              <v-col cols="12" md="6">
                <v-autocomplete v-model="editedHut.club_id" :items="clubs" item-title="name"
                                item-value="id" label="Club" clearable :loading="loadingClubs"
                                :disabled="!userStore.isPlatformAdmin" />
              </v-col>
              <v-col cols="12">
                <v-autocomplete v-model="editedHut.reciprocal_clubs" :items="reciprocalOptions" item-title="name"
                                item-value="id" label="Reciprocal Clubs" multiple chips closable-chips :loading="loadingClubs"
                                autocomplete="off" />
              </v-col>
              <v-col cols="12">
                <div class="text-title-small mb-2">Description</div>
                <MilkdownEditor 
                  v-model="editedHut.description" 
                  placeholder="Write description here..."
                  @change="updateDescription" 
                />
              </v-col>
              <v-col cols="12">
                <v-text-field v-model="editedHut.external_url" label="External URL" />
              </v-col>
              <v-col cols="12">
                <v-textarea v-model="editedHut.booking_info" label="Booking Info" rows="3" />
              </v-col>
              <v-col cols="12">
                <div class="text-title-small mb-2">Location</div>
                <div style="height: 300px;">
                  <AppMap v-model="mapStyle" geolocate :center="mapCenter" :zoom="mapZoom" height="300px">
                    <mgl-marker v-model:coordinates="pin" :draggable="true" color="#cc0000" />
                  </AppMap>
                </div>
                <div class="text-body-small text-medium-emphasis mt-1">Drag the pin to the hut.</div>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model.number="editedHut.location_lat" label="Latitude"
                              type="number" />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model.number="editedHut.location_lng" label="Longitude"
                              type="number" />
              </v-col>
              <v-col cols="12">
                <v-file-input v-model="editedHut.image" label="Hut Image" accept="image/*"
                              :prepend-icon="mdiCamera" show-size truncate-length="50" />
              </v-col>
              <v-col cols="12">
                <v-combobox v-model="editedHut.amenities" label="Amenities" multiple chips
                            closable-chips hint="Type and press enter to add amenities"
                            persistent-hint />
              </v-col>
            </v-row>
          </v-form>
        </v-container>
      </v-card-text>

      <v-card-actions>
        <v-btn v-if="!isNew" color="error" variant="text" :loading="deleting" @click="deleteHut">
          Delete
        </v-btn>
        <v-spacer />
        <v-btn color="blue-darken-1" variant="text" @click="close">
          Cancel
        </v-btn>
        <v-btn color="blue-darken-1" variant="text" @click="save">
          Save
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
export default { inheritAttrs: false }
</script>

<script setup>
import { mdiCamera, mdiPencil, mdiPlus } from '@mdi/js'

import { ref, computed, watch } from 'vue'
import { useRouter, onBeforeRouteLeave } from 'vue-router'
import { useAppStore } from '@/stores/app'
import { useHutStore } from '@/stores/huts'
import { api } from '@/plugins/api'
import { convertFileToBase64 } from '@/utilities'
import MilkdownEditor from '@/components/MilkdownEditor.vue'
import AppMap from '@/components/AppMap.vue'
import { MglMarker } from '@indoorequal/vue-maplibre-gl'
import { LngLat } from 'maplibre-gl'

const props = defineProps({
  hut: {
    type: Object,
    required: false,
    default: null
  },
})

const userStore = useAppStore()
const hutStore = useHutStore()
const router = useRouter()

const dialog = ref(false)
const valid = ref(false)
const clubs = ref([])
const loadingClubs = ref(false)
const isSaved = ref(false)

const initialHutState = ref(null)

const isDirty = computed(() => {
  if (isSaved.value) return false
  if (!initialHutState.value) return false
  return JSON.stringify(editedHut.value) !== initialHutState.value
})

onBeforeRouteLeave((to, from, next) => {
  if (dialog.value && isDirty.value) {
    const answer = window.confirm('You have unsaved changes in the Hut form. Are you sure you want to leave?')
    if (answer) {
      next()
    } else {
      next(false)
    }
  } else {
    next()
  }
})

const defaultHut = {
  name: '',
  description: '',
  external_url: '',
  booking_info: '',
  location_lat: null,
  location_lng: null,
  club_id: null,
  amenities: [],
  reciprocal_clubs: [],
  image: null
}

const editedHut = ref({ ...defaultHut })

const isNew = computed(() => !props.hut)

const canEdit = computed(() => {
  if (!userStore.user) return false
  if (userStore.isPlatformAdmin) return true

  // For new huts, allow if user is admin of any club
  if (isNew.value) {
    return userStore.user.clubs && userStore.user.clubs.some(c => c.is_admin)
  }

  // For existing huts, allow if user is admin of the owning club
  if (props.hut && props.hut.club_id && userStore.user.clubs) {
    return userStore.user.clubs.some(c => c.id === props.hut.club_id && c.is_admin)
  }

  return false
})

watch(dialog, (open) => {
  if (open && !clubs.value.length) fetchClubs()
})

// The public list omits inactive clubs, so add back any this hut already uses.
const fetchClubs = async () => {
  loadingClubs.value = true
  try {
    const response = await api.get('/api/clubs')
    const list = response.data.data || response.data
    const current = [props.hut?.club, ...(props.hut?.reciprocal_clubs || [])].filter(Boolean)
    clubs.value = [...list, ...current.filter(c => !list.some(l => l.id === c.id))]
  } catch (e) {
    console.error('Error fetching clubs', e)
  } finally {
    loadingClubs.value = false
  }
}

const toEditable = (hut) => {
  const edited = JSON.parse(JSON.stringify(hut))
  edited.amenities ??= []
  edited.reciprocal_clubs = (hut.reciprocal_clubs || []).map(c => c.id).filter(id => id !== hut.club_id)
  return edited
}

const reciprocalOptions = computed(() => clubs.value.filter(c => c.id !== editedHut.value.club_id))

watch(() => editedHut.value.club_id, (clubId) => {
  editedHut.value.reciprocal_clubs = editedHut.value.reciprocal_clubs.filter(id => id !== clubId)
})

watch(() => props.hut, (newVal) => {
  if (newVal) {
    editedHut.value = toEditable(newVal)
    initialHutState.value = JSON.stringify(editedHut.value)
  } else {
    editedHut.value = { ...defaultHut }
    initialHutState.value = JSON.stringify(editedHut.value)
  }
}, { immediate: true })

const mapStyle = ref('https://api.maptiler.com/maps/topo/style.json?key=0gGMv4po9Mjrpd64A528')
const hasLocation = () => editedHut.value.location_lat != null && editedHut.value.location_lng != null
const pinLngLat = () => hasLocation() ? [editedHut.value.location_lng, editedHut.value.location_lat] : [-1.5, 54]
const mapCenter = ref(pinLngLat())
const mapZoom = ref(hasLocation() ? 14 : 5)
const pin = ref(LngLat.convert(pinLngLat()))
const roundToMetre = (val) => Math.round(val * 100000) / 100000

// As in CaveForm: only write the pin back once it has actually moved, so
// opening and saving the form never truncates stored coordinates.
watch(pin, (lngLat) => {
  if (roundToMetre(lngLat.lng) !== roundToMetre(editedHut.value.location_lng ?? 0)) {
    editedHut.value.location_lng = roundToMetre(lngLat.lng)
  }
  if (roundToMetre(lngLat.lat) !== roundToMetre(editedHut.value.location_lat ?? 0)) {
    editedHut.value.location_lat = roundToMetre(lngLat.lat)
  }
})

watch(() => [editedHut.value.location_lng, editedHut.value.location_lat], () => {
  if (!hasLocation()) return
  if (roundToMetre(pin.value.lng) !== roundToMetre(editedHut.value.location_lng) || roundToMetre(pin.value.lat) !== roundToMetre(editedHut.value.location_lat)) {
    pin.value = LngLat.convert(pinLngLat())
    mapCenter.value = pinLngLat()
  }
})

const updateDescription = (event) => {
  if (event && event.markdown) {
    editedHut.value.description = event.markdown
  }
}

const close = () => {
  if (isDirty.value) {
    if (!confirm('You have unsaved changes. Are you sure you want to close?')) {
      return
    }
  }
  dialog.value = false
  if (props.hut) {
    editedHut.value = toEditable(props.hut)
  } else {
    editedHut.value = { ...defaultHut }
  }
  initialHutState.value = JSON.stringify(editedHut.value)
  isSaved.value = false
}

const save = async () => {
  try {
    if (editedHut.value.image instanceof File) {
      editedHut.value.image = await convertFileToBase64(editedHut.value.image)
    } else if (Array.isArray(editedHut.value.image) && editedHut.value.image.length > 0 && editedHut.value.image[0] instanceof File) {
      // Vuetify file input can return an array
      editedHut.value.image = await convertFileToBase64(editedHut.value.image[0])
    }

    if (isNew.value) {
      await hutStore.createHut(editedHut.value)
    } else {
      await hutStore.updateHut(editedHut.value)
    }

    // Refresh list
    await hutStore.fetchHuts()
    if (!isNew.value) {
      await hutStore.fetchHut(editedHut.value.id)
    }

    isSaved.value = true
    dialog.value = false
  } catch (e) {
    console.error(e)
    alert('Failed to save: ' + e.message)
  }
}

const deleting = ref(false)

const deleteHut = async () => {
  if (!confirm('Are you certain you want to delete this hut? This action cannot be undone.')) {
    return
  }

  deleting.value = true
  try {
    await hutStore.deleteHut(editedHut.value.id)
    dialog.value = false
    await hutStore.fetchHuts()
    router.push('/huts')
  } catch (e) {
    console.error(e)
    alert('Failed to delete: ' + e.message)
  } finally {
    deleting.value = false
  }
}
</script>
