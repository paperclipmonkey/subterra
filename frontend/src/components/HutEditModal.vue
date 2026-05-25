<template>
  <v-dialog v-model="dialog" max-width="800px">
    <template #activator="{ props: activatorProps }">
      <v-btn v-if="canEdit" color="primary" variant="text" :prepend-icon="isNew ? mdiPlus : mdiPencil"
             v-bind="activatorProps">
        {{ isNew ? 'New Hut' : 'Edit Hut' }}
      </v-btn>
    </template>

    <v-card>
      <v-card-title>
        <span class="text-h5">{{ isNew ? 'New Hut' : 'Edit Hut' }}</span>
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
                                item-value="id" label="Club" clearable :loading="loadingClubs" />
              </v-col>
              <v-col cols="12">
                <v-autocomplete v-model="editedHut.reciprocal_clubs" :items="clubs" item-title="name"
                                item-value="id" label="Reciprocal Clubs" multiple chips closable-chips :loading="loadingClubs"
                                autocomplete="off" />
              </v-col>
              <v-col cols="12">
                <div class="text-subtitle-2 mb-2">Description</div>
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

<script setup>
import { mdiCamera, mdiPencil, mdiPlus } from '@mdi/js'

import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, onBeforeRouteLeave } from 'vue-router'
import { useAppStore } from '@/stores/app'
import { useHutStore } from '@/stores/huts'
import { api } from '@/plugins/api'
import { convertFileToBase64 } from '@/utilities.js'
import MilkdownEditor from '@/components/MilkdownEditor.vue'

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
  if (userStore.user.is_admin) return true

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

onMounted(async () => {
  fetchClubs()
})

const fetchClubs = async () => {
  loadingClubs.value = true
  try {
    const response = await api.get('/api/admin/clubs')
    clubs.value = response.data.data || response.data
  } catch (e) {
    console.error('Error fetching clubs', e)
  } finally {
    loadingClubs.value = false
  }
}

watch(() => props.hut, (newVal) => {
  if (newVal) {
    editedHut.value = JSON.parse(JSON.stringify(newVal))
    // Ensure amenities is an array (sometimes it might be null from API)
    if (!editedHut.value.amenities) editedHut.value.amenities = []
    if (newVal.reciprocal_clubs) {
      editedHut.value.reciprocal_clubs = newVal.reciprocal_clubs.map(c => c.id)
    } else {
      editedHut.value.reciprocal_clubs = []
    }
    initialHutState.value = JSON.stringify(editedHut.value)
  } else {
    editedHut.value = { ...defaultHut }
    initialHutState.value = JSON.stringify(editedHut.value)
  }
}, { immediate: true })

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
    editedHut.value = JSON.parse(JSON.stringify(props.hut))
    if (!editedHut.value.amenities) editedHut.value.amenities = []
    if (props.hut.reciprocal_clubs) {
      editedHut.value.reciprocal_clubs = props.hut.reciprocal_clubs.map(c => c.id)
    } else {
      editedHut.value.reciprocal_clubs = []
    }
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
