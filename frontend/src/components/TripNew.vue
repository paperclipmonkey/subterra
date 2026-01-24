<template>
  <v-container class="pa-4">
    <template v-if="loading">
      <v-card class="pa-8 text-center">
        <v-progress-circular indeterminate size="64" color="primary" class="mb-4"></v-progress-circular>
        <h3 class="text-h6 mb-2">Loading trip data...</h3>
        <p class="text-body-2 text-medium-emphasis">Please wait while we load caves, users, and trip information.</p>
      </v-card>
    </template>
    <v-form v-else class="pa-xl-4">
      <v-row>
        <v-col cols="12" md="6">
          <v-card title="Where" class="mb-4">
            <v-card-text>
              <v-autocomplete label="Location" :items="caves" item-title="name" :rules="rules.location" item-value="id"
                v-model="trip.entrance_cave_id" :error-messages="validationErrors.entrance_cave_id"
                hint="Select the cave entrance where the trip started." persistent-hint variant="outlined"
                autocomplete="off" name="random_unique_cave_search_field">
                <template v-slot:item="{ props, item }">
                  <v-list-item v-bind="props" :subtitle="item.raw.location_name + ', ' + item.raw.location_country"
                    :title="item.raw.name"></v-list-item>
                </template>
              </v-autocomplete>
              <template v-if="system_entrances_count > 1">
                <v-checkbox v-model="throughTrip" label="Through trip"
                  hint="Tick if you exited from a different entrance." persistent-hint class="mt-2"></v-checkbox>
                <v-expand-transition>
                  <div v-if="throughTrip">
                    <v-autocomplete label="Exit"
                      :items="caves.filter(cave => cave.system.id === cave_system_id && cave.id !== trip.entrance_cave_id)"
                      item-title="name" item-value="id" v-model="trip.exit_cave_id"
                      :error-messages="validationErrors.exit_cave_id"
                      hint="Select the cave entrance where the trip ended." persistent-hint variant="outlined"
                      autocomplete="off" name="random_unique_exit_search_field"
                      class="mt-2"></v-autocomplete>
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
                    @update:modelValue="() => { delete validationErrors.start_time; delete validationErrors.end_time }"
                    required hint="The date the trip started." persistent-hint variant="outlined"></v-text-field>
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="tripStartTime" label="Entry time" type="time"
                    :error-messages="validationErrors.start_time || validationErrors.end_time"
                    @update:modelValue="() => { delete validationErrors.start_time; delete validationErrors.end_time }"
                    required hint="The time you entered the cave." persistent-hint variant="outlined"></v-text-field>
                </v-col>
              </v-row>
              <v-row>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="tripDurationHours" label="Duration (hours)" type="number" min="0"
                    :rules="rules.duration" :error-messages="validationErrors.end_time"
                    @update:modelValue="delete validationErrors.end_time" required
                    hint="How many hours the trip lasted." persistent-hint variant="outlined"></v-text-field>
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="tripDurationMinutes" label="Duration (minutes)" type="number" min="0" max="59"
                    :rules="rules.duration" :error-messages="validationErrors.end_time"
                    @update:modelValue="delete validationErrors.end_time" required
                    hint="How many minutes the trip lasted." persistent-hint variant="outlined"></v-text-field>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>

          <v-card title="Who" class="mb-4">
            <v-card-text>
              <v-autocomplete label="Participants" :items="users" item-title="name" item-value="id" multiple chips
                closable-chips v-model="trip.participants" :rules="rules.participants"
                :error-messages="validationErrors.participants"
                hint="Add everyone who was on the trip. All participants can edit this report." persistent-hint
                autocomplete="off" name="random_unique_user_search_field"
                variant="outlined">
                <template v-slot:chip="{ props, item }">
                  <v-chip v-bind="props" :prepend-avatar="item.raw.photo" :text="item.raw.name"></v-chip>
                </template>
                <template v-slot:item="{ props, item }">
                  <v-list-item v-bind="props" :prepend-avatar="item.raw.photo" :subtitle="item.raw.club"
                    :title="item.raw.name"></v-list-item>
                </template>
                <template v-slot:no-data>
                  <v-list-item>
                    <v-list-item-title>
                      User not found
                    </v-list-item-title>
                    <template v-slot:append>
                      <v-btn @click="showAddParticipant = true" color="primary" variant="text">
                        Add manually
                      </v-btn>
                    </template>
                  </v-list-item>
                </template>
              </v-autocomplete>
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
                variant="outlined" class="mb-4"></v-text-field>

              <div class="text-caption mb-2 text-medium-emphasis">Description</div>
              <MilkdownEditor v-model="trip.description" @change="updatedDescription"
                placeholder="Describe your adventure..." class="mb-4" />

              <div class="v-messages mb-4">
                <div class="v-messages__wrapper">
                  <div class="v-messages__message">Describe what happened on the trip. This will be visible to all
                    participants.</div>
                </div>
              </div>

              <v-select v-model="trip.visibility" label="Trip Visibility" :items="visibilityOptions" item-title="label"
                item-value="value" :error-messages="validationErrors.visibility" hint="Who can see this trip report"
                persistent-hint variant="outlined" class="mb-4"></v-select>

              <v-file-input prepend-icon="" prepend-inner-icon="mdi-camera" accept="image/*" label="Trip Photos"
                v-model="trip.media" :error-messages="validationErrors.media"
                @update:modelValue="delete validationErrors.media" chips multiple
                hint="Upload photos from the trip. You can add multiple images." persistent-hint
                variant="outlined"></v-file-input>

              <template v-if="trip.existing_media && trip.existing_media.length">
                <div class="text-subtitle-2 mt-4 mb-2">Existing media:</div>
                <v-row>
                  <v-col v-for="(media, i) in trip.existing_media" :key="i" cols="4" sm="3">
                    <v-img cover aspect-ratio="1" class="rounded bg-grey-lighten-2" :src="media.url"
                      :alt="media.file_name">
                      <v-btn icon="mdi-delete" size="x-small" color="error" class="position-absolute top-0 right-0 ma-1"
                        @click="removeExistingMedia(media)"></v-btn>
                    </v-img>
                  </v-col>
                </v-row>
              </template>
            </v-card-text>
            <v-divider></v-divider>
            <v-card-actions class="pa-4">
              <v-spacer></v-spacer>
              <v-btn text="Cancel" variant="text" @click="router.back()"></v-btn>
              <v-btn @click="submitForm" color="primary" size="large" elevation="2" :loading="isSaving"
                :disabled="isSaving" min-width="150">
                <template v-if="!isSaving">
                  <v-icon start>mdi-content-save</v-icon>
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
    <AddParticipantManual @close="closeAddParticipant" @add="addParticipant" :isActive="showAddParticipant" />
  </v-container>
</template>

<script setup>
import moment from 'moment'
import { computed, reactive, ref, watch, onMounted } from 'vue'
import AddParticipantManual from './AddParticipantManual.vue';
import MilkdownEditor from './MilkdownEditor.vue';
import { convertFileToBase64 } from '@/utilities.js'
import { useRouter, useRoute } from 'vue-router'
import { useNotificationStore } from '@/stores/notifications';

const router = useRouter()
const route = useRoute()
const notificationStore = useNotificationStore()

const markdownOutput = ref('')

const showAddParticipant = ref(false)
const isSaving = ref(false)

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

const validationErrors = ref({})

const visibilityOptions = [
  {
    value: 'public',
    label: 'Public',
    description: 'Visible to everyone'
  },
  {
    value: 'private',
    label: 'Private',
    description: 'Visible only to trip participants'
  },
  {
    value: 'club',
    label: 'Club Members',
    description: 'Visible to members of your clubs'
  }
]

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
  duration: [
    () => {
      if (tripDurationHours.value > 0 || tripDurationMinutes.value > 0) return true
      return 'Duration must be greater than zero.'
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
    let response = await fetch('/api/caves')
    caves.value = (await response.json()).data

    // Load users
    const userResonse = await fetch('/api/users/me')
    userId.value = (await userResonse.json()).data.id
    response = await fetch('/api/users')
    users.value = (await response.json()).data
    if (!trip.participants.length) {
      trip.participants.push(users.value.find(user => user.id === userId.value).id)
    }

    if (route.query.cave_id) {
      const foundCave = caves.value.find(cave => cave.id == route.query.cave_id)
      if (!foundCave) {
        console.error('Cave not found')
        return
      }
      trip.entrance_cave_id = foundCave.id
      trip.cave_system_id = foundCave.system.id
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
        const res = await fetch(`/api/callouts/${route.query.callout_id}`);
        const calloutData = (await res.json()).data;
        if (calloutData) {
          // Pre-fill plan/description
          trip.description = `**Originally a Callout:**\n\n${calloutData.trip_plan}`;
          if (calloutData.trip_plan) {
            markdownOutput.value = trip.description; // Ensure tiptap sees it if needed
          }

          // Pre-fill participants
          // calloutData.participants -> array of objects with user_id or manual name
          if (calloutData.participants && Array.isArray(calloutData.participants)) {
            calloutData.participants.forEach(p => {
              if (p.user_id) {
                // Add existing user ID if not already there
                if (!trip.participants.includes(p.user_id)) {
                  trip.participants.push(p.user_id);
                }
              } else {
                // If it's a manual participant (no user_id), we might need to add them manually to the trip?
                // For now, simpler to just handle registered users or log it
                console.log('Manual participant from callout, consider adding to description:', p.name);
                trip.description += `\n- Guest: ${p.name}`;
              }
            });
          }
        }
      } catch (e) {
        console.error("Failed to load callout data", e);
      }
    }

    // Load existing trip
    if (route.params.id) {
      const response = await fetch(`/api/trips/${route.params.id}`)
      let loadedTrip = (await response.json()).data

      loadedTrip.existing_media = loadedTrip.media
      loadedTrip.media = []

      loadedTrip.participants = loadedTrip.participants.map(participant => participant.id)

      loadedTrip.entrance_cave_id = loadedTrip.entrance.id

      loadedTrip.exit_cave_id = loadedTrip.exit.id
      //loadedTrip.cave_system_id = loadedTrip.system.id
      delete loadedTrip.entrance
      delete loadedTrip.exit
      delete loadedTrip.system
      Object.assign(trip, loadedTrip)

      tripStartDate.value = moment(loadedTrip.start_time).format('YYYY-MM-DD')
      tripStartTime.value = moment(loadedTrip.start_time).format('HH:mm')

      // Calculate duration from start_time and end_time
      const startTime = moment(loadedTrip.start_time)
      const endTime = moment(loadedTrip.end_time)
      const durationInMinutes = endTime.diff(startTime, 'minutes')
      tripDurationHours.value = Math.floor(durationInMinutes / 60)
      tripDurationMinutes.value = durationInMinutes % 60

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
  // Add the user using an api endpoint
  fetch('/api/users', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(participant),
  })
    .then(response => {
      if (!response.ok) {
        throw new Error('Failed to add participant');
      }
      return response.json();
    })
    .then(data => {
      // Use the full user object returned from the API
      const newUser = data.data;
      users.value.push(newUser); // So it can be referenced with all fields
      trip.participants.push(newUser.id);
      console.log('Participant added successfully:', newUser);
      showAddParticipant.value = false;
    })
    .catch(error => {
      console.error('Error adding participant:', error);
    });
}

const cave_system_id = computed(() => {
  const found = caves.value.find(cave => cave.id === trip.entrance_cave_id)
  return found ? found.system.id : null
})

const system_entrances_count = computed(() => {
  if (!cave_system_id.value) return 0
  return caves.value.filter((cave => cave.system.id === cave_system_id.value)).length
})

watch(() => trip.entrance_cave_id, (cave_id) => {
  if (!cave_id) return
  if (throughTrip.value) { // Currently set as through trip
    const currentSystem = caves.value.find(cave => cave.id === trip.entrance_cave_id)
    const multipleEntrances = caves.value.filter((cave => cave.system.id == currentSystem.id))
    throughTrip.value = !!multipleEntrances
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

const submitForm = async () => {
  validationErrors.value = {} // Clear previous errors
  isSaving.value = true // Start loading state

  try {
    if (!trip.exit_cave_id || !throughTrip.value) {
      trip.exit_cave_id = trip.entrance_cave_id
    }
    trip.start_time = `${tripStartDate.value} ${tripStartTime.value}:00` // Add seconds for format Y-m-d H:i:s
    trip.end_time = end_time.value.format('YYYY-MM-DD HH:mm:ss') // Add seconds for format Y-m-d H:i:s
    trip.cave_system_id = cave_system_id.value // Ensure system_id is set
    if (markdownOutput.value) {
      trip.description = markdownOutput.value // Copy the markdown output to the description field
    }

    // Convert only new files to base64
    const newMediaFiles = trip.media.filter(file => file instanceof File);
    const base64Media = await Promise.all(newMediaFiles.map(file => convertFileToBase64(file)));

    // Prepare payload, separating existing media IDs if needed by the backend
    const payload = {
      ...trip,
      media: base64Media,
      // If your backend needs existing media IDs separately, adjust here
      // existing_media_ids: trip.existing_media?.map(m => m.id) || []
    };
    // Remove properties not expected by the backend if necessary
    // delete payload.existing_media;

    if (route.params.id) {
      await updateTrip(payload)
    } else {
      await saveTrip(payload)
    }
  } catch (error) {
    console.error('Error saving trip:', error)
    notificationStore.showError('An unexpected error occurred while saving the trip. Please try again.')
  } finally {
    isSaving.value = false // End loading state
  }
}

const handleApiError = async (response) => {
  if (response.status === 422) {
    const errorData = await response.json();
    validationErrors.value = errorData.errors;
    console.error('Validation failed:', errorData.errors);
    notificationStore.showError('Please fix the validation errors and try again.')
  } else if (response.status >= 500) {
    console.error('Server error:', response.statusText);
    notificationStore.showError('A server error occurred. Please try again later.')
  } else {
    console.error('Failed operation:', response.statusText);
    notificationStore.showError('Failed to save trip. Please check your connection and try again.')
  }
}

const updateTrip = async (tripPayload) => {
  const response = await fetch(`/api/trips/${tripPayload.id}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json' // Ensure backend knows we want JSON
    },
    body: JSON.stringify(tripPayload)
  })
  if (response.ok) {
    validationErrors.value = {} // Clear errors on success
    notificationStore.showSuccess('Trip updated successfully! 🎉')
    router.push('/trips/' + tripPayload.id);
  } else {
    await handleApiError(response);
  }
}

const saveTrip = async (tripPayload) => {
  const response = await fetch('/api/trips', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json' // Ensure backend knows we want JSON
    },
    body: JSON.stringify(tripPayload)
  })
  if (response.ok) {
    validationErrors.value = {} // Clear errors on success
    const savedTrip = (await response.json()).data;
    notificationStore.showSuccess('Trip saved successfully! 🚀')
    router.push('/trips/' + savedTrip.id);
  } else {
    await handleApiError(response);
  }
}

</script>

<style>
.vuetify-pro-tiptap-editor__content+.v-toolbar {
  display: none;
}

/* TODO tidy this hack */
.vuetify-pro-tiptap-editor {
  margin-bottom: 20px;
}

.existing_media {
  max-width: 200px;
}

/* Fun spinning animation for loading icon */
.mdi-spin {
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