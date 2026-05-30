<template>
  <v-container class="pa-4">
    <template v-if="loading">
      <v-card class="pa-8 text-center">
        <v-progress-circular indeterminate size="64" color="primary" class="mb-4" />
        <h3 class="text-h6 mb-2">Loading trip data...</h3>
        <p class="text-body-2 text-medium-emphasis">Please wait while we load caves, users, and trip information.</p>
      </v-card>
    </template>
    <v-form v-else class="pa-xl-4">
      <v-row>
        <v-col cols="12" md="6">
          <v-card title="Where" class="mb-4">
            <v-card-text>
              <CaveSearchAutocomplete
                v-model="trip.entrance_cave_id"
                label="Location"
                :items="caves"
                :loading="loading"
                :rules="rules.location"
                :error-messages="validationErrors.entrance_cave_id"
                hint="Select the cave entrance where the trip started."
                :persistent-hint="true"
                input-name="random_unique_cave_search_field"
              />
              <template v-if="system_entrances_count > 1">
                <v-checkbox v-model="throughTrip" label="Through trip"
                            hint="Tick if you exited from a different entrance." persistent-hint class="mt-2" />
                <v-expand-transition>
                  <div v-if="throughTrip">
                    <CaveSearchAutocomplete
                      v-model="trip.exit_cave_id"
                      label="Exit"
                      :items="caves.filter(cave => cave.cave_system_id === cave_system_id && cave.id !== trip.entrance_cave_id)"
                      :error-messages="validationErrors.exit_cave_id"
                      hint="Select the cave entrance where the trip ended."
                      :persistent-hint="true"
                      input-name="random_unique_exit_search_field"
                      class="mt-2"
                    />
                  </div>
                </v-expand-transition>
              </template>
            </v-card-text>
          </v-card>

          <v-card title="When" class="mb-4">
            <v-card-text>
              <v-row>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="tripStartDate" label="Date" type="date"
                                :error-messages="validationErrors.start_time || validationErrors.end_time"
                                required
                                hint="The date the trip started." persistent-hint variant="outlined" @update:model-value="() => { delete validationErrors.start_time; delete validationErrors.end_time }" />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="tripStartTime" label="Entry time" type="time"
                                :error-messages="validationErrors.start_time || validationErrors.end_time"
                                required
                                hint="The time you entered the cave." persistent-hint variant="outlined" @update:model-value="() => { delete validationErrors.start_time; delete validationErrors.end_time }" />
                </v-col>
              </v-row>
              <v-row>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="tripDurationHours" label="Duration (hours)" type="number" min="0" step="1"
                                :rules="rules.durationHours" :error-messages="validationErrors.end_time"
                                required hint="How many hours the trip lasted."
                                persistent-hint
                                variant="outlined" @update:model-value="val => { tripDurationHours = Math.floor(Number(val)) || 0; delete validationErrors.end_time }" />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="tripDurationMinutes" label="Duration (minutes)" type="number" min="0" max="59" step="1"
                                :rules="rules.durationMinutes" :error-messages="validationErrors.end_time"
                                required hint="How many minutes the trip lasted."
                                persistent-hint
                                variant="outlined" @update:model-value="val => { tripDurationMinutes = Math.max(0, Math.min(59, Math.floor(Number(val)) || 0)); delete validationErrors.end_time }" />
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>

          <v-card title="Who" class="mb-4">
            <v-card-text>
              <v-autocomplete ref="userAutocomplete" v-model="userSelect" v-model:search="userSearch" label="Add Participant" :items="availableUsers" item-title="name" item-value="id"
                              variant="outlined" :prepend-inner-icon="mdiAccountSearch" return-object
                              clearable hint="Type to search for users..."
                              :loading="isSearching"
                              :error-messages="validationErrors.participants"
                              autocomplete="off"
                              name="random_unique_user_search_field"
                              class="mb-4"
                              @update:model-value="addSubterraUser"
                              @update:search="onUserSearch"
                              @focus="onUserSearch('')">
                <template #item="{ props, item }">
                  <v-list-item v-bind="props">
                    <template #prepend>
                      <v-avatar v-if="item.raw.photo" :image="item.raw.photo" class="mr-3" size="40" />
                      <v-avatar v-else color="primary" class="mr-3" size="40">
                        <span class="text-white">{{ item.raw.name.charAt(0) }}</span>
                      </v-avatar>
                    </template>
                    <template #title>
                      <div class="d-flex align-center">
                        <span class="text-body-1">{{ item.raw.name }}</span>
                      </div>
                    </template>
                    <template v-if="item.raw.clubs && item.raw.clubs.length" #subtitle>
                      <span class="text-caption text-medium-emphasis">{{ item.raw.clubs.map(c => c.name).join(', ') }}</span>
                    </template>
                  </v-list-item>
                </template>
                <template #no-data>
                  <v-list-item>
                    <v-list-item-title>
                      User not found
                    </v-list-item-title>
                    <template #append>
                      <v-btn color="primary" variant="text" @click="showAddParticipant = true">
                        Add manually
                      </v-btn>
                    </template>
                  </v-list-item>
                </template>
              </v-autocomplete>

              <div class="mb-4">
                <div v-for="(participantId, index) in trip.participants" :key="participantId" class="mb-3">
                  <v-card variant="outlined" class="pa-3">
                    <div class="d-flex align-center w-100">
                      <template v-if="getParticipant(participantId)">
                        <v-avatar v-if="getParticipant(participantId).photo" :image="getParticipant(participantId).photo" class="mr-4" size="48" />
                        <v-avatar v-else color="primary" class="mr-4" size="48">
                          <span class="text-white">{{ getParticipant(participantId).name.charAt(0) }}</span>
                        </v-avatar>

                        <div class="flex-grow-1">
                          <div class="text-subtitle-1 font-weight-bold">{{ getParticipant(participantId).name }}</div>
                          <div v-if="getParticipant(participantId).clubs && getParticipant(participantId).clubs.length" class="text-caption text-medium-emphasis">
                            {{ getParticipant(participantId).clubs.map(c => c.name).join(', ') }}
                          </div>
                        </div>

                        <v-btn v-if="canRemoveParticipant(participantId)" icon color="error" size="small" variant="text" @click="removeParticipant(index)">
                          <v-icon :icon="mdiDelete" />
                        </v-btn>
                      </template>
                    </div>
                  </v-card>
                </div>
              </div>

              <v-btn variant="text" color="primary" :prepend-icon="mdiPlus" @click="showAddParticipant = true">
                Add Manual Participant
              </v-btn>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="6">
          <v-card title="What" class="mb-4">
            <v-card-text>
              <v-alert v-if="Object.keys(validationErrors).length" type="error" class="mb-4" title="Validation Error">
                Please fix the errors below before saving.
              </v-alert>

              <v-text-field v-model="trip.name" label="Trip Name" :rules="rules.name"
                            :error-messages="validationErrors.name" required
                            hint="A short, descriptive name for your trip (e.g. 'Main Chamber Survey')" persistent-hint
                            variant="outlined" class="mb-4" />

              <div class="text-caption mb-2 text-medium-emphasis">Description</div>
              <MilkdownEditor v-model="trip.description" placeholder="Describe your adventure..."
                              class="mb-4" @change="updatedDescription" />

              <div class="v-messages mb-4">
                <div class="v-messages__wrapper">
                  <div class="v-messages__message">Describe what happened on the trip. This will be visible to all
                    participants.</div>
                </div>
              </div>

              <v-alert v-if="isClosed" type="warning" class="mb-4" density="compact" variant="tonal">
                This cave is marked as <strong>Closed</strong>. You cannot create public trip reports for it.
              </v-alert>

              <v-select v-model="trip.visibility" label="Trip Visibility" :items="currentVisibilityOptions" item-title="label"
                        item-value="value" :error-messages="validationErrors.visibility" hint="Who can see this trip report"
                        persistent-hint variant="outlined" class="mb-4" item-props />

              <v-file-input prepend-icon="" :prepend-inner-icon="mdiCamera" accept="image/*" label="Add Photos"
                            :model-value="[]"
                            :error-messages="validationErrors.media"
                            chips
                            multiple
                            hint="Upload photos from the trip. You can add multiple images." persistent-hint
                            variant="outlined" @update:model-value="handleFileSelect"
                            @update:error="delete validationErrors.media" />

              <template v-if="pendingMedia.length > 0">
                <div class="text-subtitle-2 mt-4 mb-2">New Uploads:</div>
                <v-card v-for="(item, i) in pendingMedia" :key="i" class="mb-3 border" flat>
                  <v-row no-gutters>
                    <v-col cols="4" sm="3">
                      <v-img :src="item.preview" aspect-ratio="1" cover class="h-100 bg-grey-lighten-2" />
                    </v-col>
                    <v-col cols="8" sm="9" class="pa-2">
                      <v-row dense>
                        <v-col cols="12">
                          <div class="d-flex justify-space-between align-center">
                            <span class="text-caption text-truncate">{{ item.file.name }}</span>
                            <v-btn :icon="mdiClose" size="x-small" variant="text" color="error" @click="removePendingMedia(i)" />
                          </div>
                        </v-col>
                        <v-col cols="12">
                          <v-text-field v-model="item.title" label="Title" density="compact" variant="underlined" hide-details />
                        </v-col>
                        <v-col cols="12" sm="6">
                          <v-combobox v-model="item.copyright" :items="copyrightOptions" label="Licence / Copyright" density="compact" variant="underlined" hide-details 
                                      item-title="title" item-value="value" item-props :return-object="false" />
                        </v-col>
                        <v-col cols="12" sm="6">
                          <v-text-field v-model="item.photographer" label="Photographer" density="compact" variant="underlined" hide-details />
                        </v-col>

                      </v-row>
                    </v-col>
                  </v-row>
                </v-card>
              </template>

              <template v-if="trip.existing_media && trip.existing_media.length">
                <div class="text-subtitle-2 mt-4 mb-2">Existing media:</div>
                <v-card v-for="(media, i) in trip.existing_media" :key="i" class="mb-3 border" flat>
                  <v-row no-gutters>
                    <v-col cols="4" sm="3">
                      <v-img :src="media.url" aspect-ratio="1" cover class="h-100 bg-grey-lighten-2" />
                    </v-col>
                    <v-col cols="8" sm="9" class="pa-2">
                      <v-row dense>
                        <v-col cols="12">
                          <div class="d-flex justify-space-between align-center">
                            <span class="text-caption text-truncate">{{ media.filename || media.file_name }}</span>
                            <v-btn :icon="mdiDelete" size="x-small" variant="text" color="error" @click="removeExistingMedia(media)" />
                          </div>
                        </v-col>
                        <v-col cols="12">
                          <v-text-field v-model="media.title" label="Title" density="compact" variant="underlined" hide-details />
                        </v-col>
                        <v-col cols="12" sm="6">
                          <v-combobox v-model="media.copyright" :items="copyrightOptions" label="Licence / Copyright" density="compact" variant="underlined" hide-details 
                                      item-title="title" item-value="value" item-props :return-object="false" />
                        </v-col>
                        <v-col cols="12" sm="6">
                          <v-text-field v-model="media.photographer" label="Photographer" density="compact" variant="underlined" hide-details />
                        </v-col>
                      </v-row>
                    </v-col>
                  </v-row>
                </v-card>
              </template>
            </v-card-text>
            <v-divider />
            <v-card-actions class="pa-4">
              <v-btn
                v-if="route.params.id"
                color="error"
                variant="text"
                :prepend-icon="mdiDelete"
                @click="showDeleteDialog = true"
              >
                Delete Trip
              </v-btn>
              <v-spacer />
              <v-btn text="Cancel" variant="text" @click="router.back()" />
              <v-btn color="primary" size="large" elevation="2" :loading="isSaving" :disabled="isSaving"
                     min-width="150" @click="submitForm">
                <template v-if="!isSaving">
                  <v-icon start :icon="mdiContentSave" />
                  Save Trip
                </template>
                <template v-else>
                  Saving...
                </template>
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>
    </v-form>
    <AddParticipantManual
      :is-active="showAddParticipant"
      :loading="isAddingParticipant"
      :error="addParticipantError"
      @close="closeAddParticipant"
      @add="addParticipant"
    />

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="showDeleteDialog" persistent max-width="400">
      <v-card class="rounded-lg">
        <v-card-title class="text-h6 pa-4">Delete Trip?</v-card-title>
        <v-card-text class="pt-0 pb-4">Are you sure you want to delete this trip report? This action cannot be undone.</v-card-text>
        <v-card-actions class="pa-4 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="showDeleteDialog = false">Cancel</v-btn>
          <v-btn color="error" variant="flat" @click="confirmDelete">Delete</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { mdiAccountSearch, mdiCamera, mdiClose, mdiContentSave, mdiDelete, mdiPlus } from '@mdi/js'
import moment from 'moment'
import { computed, reactive, ref, watch, onMounted } from 'vue'
import AddParticipantManual from './AddParticipantManual.vue'
import CaveSearchAutocomplete from './CaveSearchAutocomplete.vue'
import MilkdownEditor from './MilkdownEditor.vue'
import { convertFileToBase64 } from '@/utilities.js'
import { useRouter, useRoute, onBeforeRouteLeave } from 'vue-router'
import { useNotificationStore } from '@/stores/notifications'
import { api } from '@/plugins/api'

const router = useRouter()
const route = useRoute()
const notificationStore = useNotificationStore()

const markdownOutput = ref('')

const showAddParticipant = ref(false)
const isSaved = ref(false)
const isSaving = ref(false)
const showDeleteDialog = ref(false)
const isAddingParticipant = ref(false)
const isSearching = ref(false)
const addParticipantError = ref(null)

const userSelect = ref(null)

const availableUsers = computed(() => {
  return users.value.filter(u => !trip.participants.includes(u.id))
})

const getParticipant = (id) => {
  return users.value.find(u => u.id === id)
}

const canRemoveParticipant = (participantId) => {
  if (trip.id === -1) {
    // New trip: current user is the creator and cannot be removed
    return participantId !== userId.value
  }
  // Existing trip: creator cannot be removed
  return participantId !== trip.creator_id
}

const addSubterraUser = (user) => {
  if (!user) return
  if (!trip.participants.includes(user.id)) {
    trip.participants.push(user.id)
  }
  // Double-nextTick: first tick lets Vuetify finish its selection commit,
  // second tick resets the component after that cycle is complete.
  nextTick(() => nextTick(() => {
    userSelect.value = null
    userSearch.value = ''
    userAutocomplete.value?.reset()
  }))
}

const removeParticipant = (index) => {
  trip.participants.splice(index, 1)
}

const initialTripState = JSON.stringify({
  id: -1,
  name: '',
  description: '',
  media: [],
  entrance_cave_id: '',
  exit_cave_id: '',
  participants: [null], // We'll handle this carefully
  cave_system_id: null,
  visibility: 'public',
  creator_id: null,
})

const isDirty = computed(() => {
  if (isSaved.value) return false
  // Basic check: has anything changed from empty?
  return trip.name !== '' ||
    trip.description !== '' ||
    trip.entrance_cave_id !== '' ||
    pendingMedia.value.length > 0
})

onBeforeRouteLeave((to, from, next) => {
  if (isDirty.value) {
    const answer = window.confirm('You have unsaved changes. Are you sure you want to leave?')
    if (answer) {
      next()
    } else {
      next(false)
    }
  } else {
    next()
  }
})

let trip = reactive({
  id: -1,
  name: '',
  description: '',
  media: [],
  entrance_cave_id: '',
  exit_cave_id: '',
  date: '',
  start_time: '',
  end_time: '',
  participants: [],
  cave_system_id: null,
  visibility: 'public',
  creator_id: null,
})

const tripStartDate = ref(moment().format('YYYY-MM-DD'))
const tripStartTime = ref(moment().format('HH:mm'))
const tripDurationHours = ref(4)
const tripDurationMinutes = ref(0)

const throughTrip = ref(false)
const userId = ref({})
const users = ref([])
const caves = ref([])
const loading = ref(true)

const userAutocomplete = ref(null)

const userSearch = ref('')
let searchTimeout = null

const onUserSearch = (val) => {
  if (searchTimeout) clearTimeout(searchTimeout)
  if (val === null) return

  isSearching.value = true
  searchTimeout = setTimeout(async () => {
    try {
      const response = await api.get(`/api/users?search=${encodeURIComponent(val)}`)
      const matches = response.data.data

      // Merge matches with existing users (to keep selected ones visible)
      const existingIds = users.value.map(u => u.id)
      matches.forEach(match => {
        if (!existingIds.includes(match.id)) {
          users.value.push(match)
        }
      })
    } catch (e) {
      console.error('Error searching users', e)
    } finally {
      isSearching.value = false
    }
  }, 300)
}

const validationErrors = ref({})

const visibilityOptions = [
  {
    value: 'public',
    label: 'Public',
    description: 'Visible to everyone'
  },
  {
    value: 'club',
    label: 'Club Members',
    description: 'Visible to members of your clubs'
  },
  {
    value: 'private',
    label: 'Private',
    description: 'Visible only to trip participants'
  }
]

const copyrightOptions = [
  { value: 'CC BY-SA 4.0', title: 'CC BY-SA 4.0', subtitle: 'Free to use & remix, must share alike.' },
  { value: 'CC BY-NC 4.0', title: 'CC BY-NC 4.0', subtitle: 'Free to use & remix, non-commercial only.' },
  { value: 'Public Domain (CC0)', title: 'Public Domain (CC0)', subtitle: 'No copyright reserved, completely free.' },
  { value: 'Copyright (All rights reserved)', title: 'Copyright', subtitle: 'Others must ask permission to use.' }
]

const isClosed = computed(() => {
  if (!trip.entrance_cave_id) return false
  const cave = caves.value.find(c => c.id === trip.entrance_cave_id)
  return cave?.is_closed ?? false
})

watch(isClosed, (newVal) => {
  if (newVal && trip.visibility === 'public') {
    // Default to private if currently public
    trip.visibility = 'private'
  }
})

const currentVisibilityOptions = computed(() => {
  if (isClosed.value) {
    return visibilityOptions.map(opt => {
      if (opt.value === 'public') {
        return { ...opt, disabled: true, description: 'Not available for closed caves' }
      }
      return opt
    })
  }
  return visibilityOptions
})

const rules = {
  name: [
    value => {
      if (value) return true
      return 'Name is required.'
    },
    value => {
      if (value?.length <= 255) return true
      return 'Name must be less than 255 characters.'
    },
  ],
  description: [
    value => {
      if (value) return true
      return 'Description is required.'
    }
  ],
  location: [
    value => {
      if (value) return true
      return 'Location is required.'
    }
  ],
  durationHours: [
    () => {
      if (Number(tripDurationHours.value) > 0 || Number(tripDurationMinutes.value) > 0) return true
      return 'Duration must be greater than zero.'
    },
    (value) => {
      const n = Math.floor(Number(value))
      if (!isNaN(n) && n >= 0 && n === Number(value)) return true
      return 'Duration must be a whole number.'
    }
  ],
  durationMinutes: [
    (value) => {
      const n = Math.floor(Number(value))
      if (!isNaN(n) && n >= 0 && n <= 59 && n === Number(value)) return true
      return 'Duration (minutes) must be a whole number between 0 and 59.'
    }
  ],
  participants: [
    value => {
      if (value && value.length > 0) return true
      return 'At least one participant is required.'
    }
  ]
}

onMounted(async () => {
  try {
    // Load caves
    let response = await api.get('/api/caves/search')
    caves.value = response.data.data

    // Load users (Removed full load)
    const userResponse = await api.get('/api/users/me')
    const userData = userResponse.data
    userId.value = userData.data.id
    // response = await api.get('/api/users')
    // users.value = response.data.data

    // Add self to users list so it displays correctly
    const me = userData.data
    users.value = [me]

    if (!trip.participants.length) {
      trip.participants.push(me.id)
    }

    if (route.query.cave_id) {
      const foundCave = caves.value.find(cave => cave.id == route.query.cave_id)
      if (!foundCave) {
        console.error('Cave not found')
        return
      }
      trip.entrance_cave_id = foundCave.id
      trip.cave_system_id = foundCave.cave_system_id
    }

    if (route.query.date) {
      tripStartDate.value = route.query.date
    }

    if (route.query.exit_cave_id) {
      const exitCave = caves.value.find(cave => cave.id == route.query.exit_cave_id)
      if (exitCave) {
        trip.exit_cave_id = exitCave.id
        throughTrip.value = (trip.exit_cave_id != trip.entrance_cave_id)
      }
    }

    if (route.query.callout_id) {
      try {
        const res = await api.get(`/api/callouts/${route.query.callout_id}`)
        const calloutData = res.data.data
        if (calloutData) {
          // Pre-fill plan/description
          trip.description = `**Originally a Callout:**\n\n${calloutData.trip_plan}`
          if (calloutData.trip_plan) {
            markdownOutput.value = trip.description
          }

          // Pre-fill participants
          // calloutData.participants -> array of objects with user_id or manual name
          if (calloutData.participants && Array.isArray(calloutData.participants)) {
            // Fetch full user data for callout participants so autocomplete can display names
            const userIdsToFetch = calloutData.participants
              .filter(p => p.user_id && !users.value.some(u => u.id === p.user_id))
              .map(p => p.user_id)
            if (userIdsToFetch.length > 0) {
              try {
                const userResponse = await api.get(`/api/users?ids=${userIdsToFetch.join(',')}`)
                const fetchedUsers = userResponse.data.data
                fetchedUsers.forEach(u => {
                  if (!users.value.some(existing => existing.id === u.id)) {
                    users.value.push(u)
                  }
                })
              } catch (e) {
                console.error('Error fetching callout participant details', e)
              }
            }
            calloutData.participants.forEach(p => {
              if (p.user_id) {
                // Add existing user ID if not already there
                if (!trip.participants.includes(p.user_id)) {
                  trip.participants.push(p.user_id)
                }
              } else {
                // If it's a manual participant (no user_id), we might need to add them manually to the trip?
                // For now, simpler to just handle registered users or log it
                trip.description += `\n- Guest: ${p.name}`
              }
            })
          }
        }
      } catch (e) {
        console.error("Failed to load callout data", e)
      }
    }

    // Load existing trip
    if (route.params.id) {
      const response = await api.get(`/api/trips/${route.params.id}`)
      let loadedTrip = response.data.data

      loadedTrip.existing_media = loadedTrip.media
      loadedTrip.media = []

      // Add participant objects to users array so autocomplete can display names/photos
      loadedTrip.participants.forEach(participant => {
        if (!users.value.some(u => u.id === participant.id)) {
          users.value.push(participant)
        }
      })
      loadedTrip.participants = loadedTrip.participants.map(participant => participant.id)

      loadedTrip.entrance_cave_id = loadedTrip.entrance.id

      loadedTrip.exit_cave_id = loadedTrip.exit.id
      //loadedTrip.cave_system_id = loadedTrip.system.id
      delete loadedTrip.entrance
      delete loadedTrip.exit
      delete loadedTrip.system
      Object.assign(trip, loadedTrip)

      if (loadedTrip.start_time) {
        tripStartDate.value = moment.utc(loadedTrip.start_time).local().format('YYYY-MM-DD')
        tripStartTime.value = moment.utc(loadedTrip.start_time).local().format('HH:mm')
      }

      // Calculate duration from start_time and end_time
      if (loadedTrip.start_time && loadedTrip.end_time) {
        const startTime = moment.utc(loadedTrip.start_time).local()
        const endTime = moment.utc(loadedTrip.end_time).local()
        const durationInMinutes = endTime.diff(startTime, 'minutes')
        if (durationInMinutes >= 0) {
          tripDurationHours.value = Math.floor(durationInMinutes / 60)
          tripDurationMinutes.value = durationInMinutes % 60
        }
      }

      if (loadedTrip.entrance_cave_id !== loadedTrip.exit_cave_id) {
        throughTrip.value = true
      }
    }
  } catch (error) {
    console.error('Error loading trip data:', error)
  } finally {
    loading.value = false
  }
})

const closeAddParticipant = () => {
  showAddParticipant.value = false
  addParticipantError.value = null
  isAddingParticipant.value = false
}

const updatedDescription = (event) => {
  // Milkdown passes { markdown } object in change event
  if (event && event.markdown) {
    markdownOutput.value = event.markdown
  }
}

const removeExistingMedia = (media) => {
  trip.existing_media = trip.existing_media.filter(m => m !== media)
}

const addParticipant = (participant) => {
  isAddingParticipant.value = true
  addParticipantError.value = null

  // Add the user using an api endpoint
  api.post('/api/users', participant)
    .then(response => {
      // Use the full user object returned from the API
      const newUser = response.data.data
      users.value.push(newUser) // So it can be referenced with all fields
      trip.participants.push(newUser.id)
      showAddParticipant.value = false
      notificationStore.showSuccess('Participant added successfully')
    })
    .catch(error => {
      console.error('Error adding participant:', error)
      addParticipantError.value = error.response?.data?.message || error.message
    })
    .finally(() => {
      isAddingParticipant.value = false
    })
}

const cave_system_id = computed(() => {
  const found = caves.value.find(cave => cave.id === trip.entrance_cave_id)
  return found ? found.cave_system_id : null
})

const system_entrances_count = computed(() => {
  if (!cave_system_id.value) return 0
  return caves.value.filter((cave => cave.cave_system_id === cave_system_id.value)).length
})

watch(() => trip.entrance_cave_id, (cave_id) => {
  if (!cave_id) return
  if (throughTrip.value) { // Currently set as through trip
    const currentSystem = caves.value.find(cave => cave.id === trip.entrance_cave_id)
    const multipleEntrances = caves.value.filter((cave => cave.cave_system_id == currentSystem?.cave_system_id))
    throughTrip.value = multipleEntrances.length > 1
  }
})

const start_time = computed(() => {
  const entry = moment(tripStartDate.value + ' ' + tripStartTime.value, 'YYYY-MM-DD HH:mm')
  return entry
})

const end_time = computed(() => {
  const exit = start_time.value.clone()
  exit.add(tripDurationHours.value, 'hours')
  exit.add(tripDurationMinutes.value, 'minutes')
  return exit
})

const pendingMedia = ref([])

const handleFileSelect = async (files) => {
  if (!files || files.length === 0) return

  for (const file of files) {
    const reader = new FileReader()
    reader.onload = (e) => {
      pendingMedia.value.push({
        file: file,
        preview: e.target.result,
        title: '',
        copyright: 'CC BY-SA 4.0',
        photographer: users.value.find(u => u.id === userId.value)?.name || '',
      })
    }
    reader.readAsDataURL(file)
  }
}

const removePendingMedia = (index) => {
  pendingMedia.value.splice(index, 1)
}

const confirmDelete = async () => {
  showDeleteDialog.value = false
  try {
    await api.delete(`/api/trips/${route.params.id}`)
    isSaved.value = true
    notificationStore.showSuccess('Trip deleted successfully')
    router.push('/trips')
  } catch (e) {
    console.error('Failed to delete trip', e)
    notificationStore.showError('Failed to delete trip: ' + (e.message || 'Unknown error'))
  }
}

const submitForm = async () => {
  validationErrors.value = {}
  isSaving.value = true

  try {
    if (!trip.exit_cave_id || !throughTrip.value) {
      trip.exit_cave_id = trip.entrance_cave_id
    }
    trip.start_time = start_time.value.format()
    trip.end_time = end_time.value.format()
      trip.cave_system_id = cave_system_id.value ?? null
    if (markdownOutput.value) {
      trip.description = markdownOutput.value
    }

    const formData = new FormData()

    Object.keys(trip).forEach(key => {
      if (trip[key] !== null && trip[key] !== undefined && key !== 'media' && key !== 'existing_media' && key !== 'participants') {
        formData.append(key, trip[key])
      }
    })

    trip.participants.forEach((participantId, index) => {
      formData.append(`participants[${index}]`, participantId)
    })

    // In Vue update forms (PUT), we need to send POST with _method = PUT to allow multipart/form-data
    if (route.params.id) {
      formData.append('_method', 'PUT')
    }

    pendingMedia.value.forEach((item, index) => {
      formData.append(`media[${index}][data]`, item.file)
      if (item.title) formData.append(`media[${index}][title]`, item.title)
      if (item.copyright) formData.append(`media[${index}][copyright]`, item.copyright)
      if (item.photographer) formData.append(`media[${index}][photographer]`, item.photographer)
    })

    if (trip.existing_media?.length > 0) {
      trip.existing_media.forEach((item, index) => {
        formData.append(`existing_media[${index}][id]`, item.id)
        if (item.title) formData.append(`existing_media[${index}][title]`, item.title)
        if (item.copyright) formData.append(`existing_media[${index}][copyright]`, item.copyright)
        if (item.photographer) formData.append(`existing_media[${index}][photographer]`, item.photographer)
      })
    }

    if (route.params.id) {
      await updateTrip(formData, trip.id)
    } else {
      await saveTrip(formData)
    }
  } catch (error) {
    console.error('Error saving trip:', error)
    notificationStore.showError('An unexpected error occurred while saving the trip. Please try again.')
  } finally {
    isSaving.value = false
  }
}

const handleApiError = async (error) => {
  const response = error.response
  if (!response) {
    console.error('Network error:', error)
    notificationStore.showError('Failed to save trip. Please check your connection and try again.')
    return
  }

  if (response.status === 422) {
    const errorData = response.data

    // Map wildcard media errors (media.0.data) back to the base 'media' field for the UI
    const mediaErrors = []
    Object.keys(errorData.errors).forEach(key => {
      if (key.startsWith('media.')) {
        mediaErrors.push(...errorData.errors[key])
        delete errorData.errors[key]
      }
    })

    if (mediaErrors.length > 0) {
      errorData.errors.media = [...(errorData.errors.media || []), ...mediaErrors]
    }

    validationErrors.value = errorData.errors
    console.error('Validation failed:', errorData.errors)
    notificationStore.showError('Please fix the validation errors and try again.')
  } else if (response.status >= 500) {
    console.error('Server error:', response.statusText)
    notificationStore.showError('A server error occurred. Please try again later.')
  } else {
    console.error('Failed operation:', response.statusText)
    notificationStore.showError('Failed to save trip. Please check your connection and try again.')
  }
}

const updateTrip = async (formData, id) => {
  try {
    await api.post(`/api/trips/${id}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    isSaved.value = true
    validationErrors.value = {}
    notificationStore.showSuccess('Trip updated successfully! 🎉')
    router.push('/trips/' + id)
  } catch (error) {
    await handleApiError(error)
  }
}

const saveTrip = async (formData) => {
  try {
    const response = await api.post('/api/trips', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    isSaved.value = true
    validationErrors.value = {}
    const savedTrip = response.data.data
    notificationStore.showSuccess('Trip saved successfully! 🚀')
    router.push('/trips/' + savedTrip.id)
  } catch (error) {
    await handleApiError(error)
  }
}

</script>

<style scoped>
.existing_media {
  max-width: 200px;
}

/* Fun spinning animation for loading icon */
:deep(.mdi-spin) {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
}
</style>