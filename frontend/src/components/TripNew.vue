<template>
  <v-container class="pa-4">
    <template v-if="loading">
      <v-card class="pa-8 text-center">
        <v-progress-circular
          indeterminate
          size="64"
          color="primary"
          class="mb-4"
        ></v-progress-circular>
        <h3 class="text-h6 mb-2">Loading trip data...</h3>
        <p class="text-body-2 text-medium-emphasis">Please wait while we load caves, users, and trip information.</p>
      </v-card>
    </template>
    <v-form v-else class="pa-xl-4">
      <v-stepper v-model="step" :items="['Where', 'When', 'Who', 'What']" editable>
        <template v-slot:item.1>
          <v-card title="Where" flat>
            <v-autocomplete
              label="Location"
              :items="caves"
              item-title="name"
              :rules="rules.location"
              item-value="id"
              v-model="trip.entrance_cave_id"
              :error-messages="validationErrors.entrance_cave_id"
              hint="Select the cave entrance where the trip started."
              persistent-hint
            >
              <template v-slot:item="{ props, item }">
                <v-list-item
                  v-bind="props"
                  :subtitle="item.raw.location_name + ', ' + item.raw.location_country"
                  :title="item.raw.name"
                ></v-list-item>
              </template>
            </v-autocomplete>
            <template v-if="system_entrances_count > 1">
              <v-checkbox v-model="throughTrip" label="Through trip" hint="Tick if you exited from a different entrance." persistent-hint></v-checkbox>
              <template v-if="throughTrip">
                <v-autocomplete
                  label="Exit"
                  :items="caves.filter(cave => cave.system.id === cave_system_id)"
                  item-title="name"
                  item-value="id"
                  v-model="trip.exit_cave_id"
                  :error-messages="validationErrors.exit_cave_id"
                  hint="Select the cave entrance where the trip ended."
                  persistent-hint
                ></v-autocomplete>
              </template>
            </template>
          </v-card>
        </template>
        <template v-slot:item.2>
          <v-card title="When" flat>
            <v-alert v-if="trip.timezone && trip.timezone !== 'UTC'" type="info" variant="tonal" class="mb-4">
              <v-icon>mdi-clock-outline</v-icon>
              Times will be saved in cave location timezone: <strong>{{ trip.timezone }}</strong>
            </v-alert>
            <v-row>
              <v-col cols="6">
                <v-text-field
                  v-model="tripStartDate"
                  label="Date"
                  type="date"
                  :error-messages="validationErrors.start_time || validationErrors.end_time"
                  @update:modelValue="() => { delete validationErrors.start_time; delete validationErrors.end_time }"
                  required
                  hint="The date the trip started."
                  persistent-hint
                ></v-text-field>
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model="tripStartTime"
                  label="Entry time"
                  type="time"
                  :error-messages="validationErrors.start_time || validationErrors.end_time"
                  @update:modelValue="() => { delete validationErrors.start_time; delete validationErrors.end_time }"
                  required
                  hint="The time you entered the cave."
                  persistent-hint
                ></v-text-field>
              </v-col>
            </v-row>
            <v-row>
              <v-col cols="6">
                <v-text-field
                  v-model="tripDurationHours"
                  label="Duration (hours)"
                  type="number"
                  min="0"
                  :rules="rules.duration"
                  :error-messages="validationErrors.end_time"
                  @update:modelValue="delete validationErrors.end_time"
                  required
                  hint="How many hours the trip lasted."
                  persistent-hint
                ></v-text-field>
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model="tripDurationMinutes"
                  label="Duration (minutes)"
                  type="number"
                  min="0"
                  max="59"
                  :rules="rules.duration"
                  :error-messages="validationErrors.end_time"
                  @update:modelValue="delete validationErrors.end_time"
                  required
                  hint="How many minutes the trip lasted."
                  persistent-hint
                ></v-text-field>
              </v-col>
            </v-row>
          </v-card>
        </template>
        <template v-slot:item.3>
          <v-card title="Who" flat>
            <v-autocomplete
              label="Participants"
              :items="users"
              item-title="name"
              item-value="id"
              multiple
              chips
              closable-chips
              v-model="trip.participants"
              hint="Add everyone who was on the trip. All participants can edit this report."
              persistent-hint
            >
              <template v-slot:chip="{ props, item }">
                <v-chip
                  v-bind="props"
                  :prepend-avatar="item.raw.photo"
                  :text="item.raw.name"
                ></v-chip>
              </template>
              <template v-slot:item="{ props, item }">
                <v-list-item
                  v-bind="props"
                  :prepend-avatar="item.raw.photo"
                  :subtitle="item.raw.club"
                  :title="item.raw.name"
                ></v-list-item>
              </template>
              <template v-slot:no-data>
                <v-btn @click="showAddParticipant=true">
                  Can't find that user.  <strong>Add them manually</strong>
                </v-btn>
              </template>
            </v-autocomplete>
          </v-card>
        </template>
        <template v-slot:item.4>
          <v-card title="What" flat>
            <v-alert v-if="Object.keys(validationErrors).length" type="error" class="mb-4">
              Please fix the errors below before saving.
              {{ validationErrors }}
            </v-alert>
            <v-text-field
              v-model="trip.name"
              label="Trip Name"
              :rules="rules.name"
              :error-messages="validationErrors.name"
              required
              hint="A short, descriptive name for your trip (e.g. 'Main Chamber Survey')"
              persistent-hint
            ></v-text-field>
            <VuetifyTiptap @change="updatedDescription" v-model="trip.description" output="text" markdown-theme="github"
              hint="Describe what happened on the trip. This will be visible to all participants."
              persistent-hint
            >
            </VuetifyTiptap>
            <v-select
              v-model="trip.visibility"
              label="Trip Visibility"
              :items="visibilityOptions"
              item-title="label"
              item-value="value"
              :error-messages="validationErrors.visibility"
              hint="Who can see this trip report"
              persistent-hint
              class="mb-4"
            ></v-select>
            <v-file-input
              prepend-icon="mdi-camera"
              accept="image/*"
              label="Trip Photos"
              v-model="trip.media"
              :error-messages="validationErrors.media"
              @update:modelValue="delete validationErrors.media"
              chips
              multiple
              hint="Upload photos from the trip. You can add multiple images."
              persistent-hint
            ></v-file-input>
            <template v-if="trip.existing_media && trip.existing_media.length">
              Existing media:
              <v-container class="pa-1">
                <v-row>
                  <v-col
                    v-for="(media, i) in trip.existing_media"
                    :key="i"
                    cols="3"
                    md="6"
                  >
                    <v-img class="existing_media text-right pa-2" :key="media.id" :src="media.url" alt="filename">
                      <v-btn icon="mdi-delete"  @click="removeExistingMedia(media)"></v-btn>
                    </v-img>
                  </v-col>
                </v-row>
              </v-container>
            </template>
            <v-btn 
              @click="submitForm" 
              color="primary" 
              class="mt-6" 
              size="large" 
              elevation="2" 
              block
              :loading="isSaving"
              :disabled="isSaving"
            >
              <template v-if="!isSaving">
                <v-icon left>mdi-content-save</v-icon>
                Save Trip
              </template>
              <template v-else>
                <v-icon left class="mdi-spin">mdi-loading</v-icon>
                Saving Trip...
              </template>
            </v-btn>
          </v-card>
        </template>
      </v-stepper>
    </v-form>
    <AddParticipantManual @close="closeAddParticipant" @add="addParticipant" :isActive="showAddParticipant"/>
  </v-container>
</template>

<script setup>
  import moment from 'moment'
  import momentTimezone from 'moment-timezone'
  import { computed, reactive, ref, watch, onMounted } from 'vue'
  import AddParticipantManual from './AddParticipantManual.vue';
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
    timezone: 'UTC',
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
    ]
  }

  const step = ref(1)

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
      if(!trip.participants.length) {
        trip.participants.push(users.value.find(user => user.id === userId.value).id)
      }

      if(route.query.cave_id) {
        const foundCave = caves.value.find(cave => cave.id == route.query.cave_id)
        if(!foundCave) {
          console.error('Cave not found')
          return
        }
        trip.entrance_cave_id = foundCave.id
        trip.cave_system_id = foundCave.system.id
      }

      // Load existing trip
      if(route.params.id) {
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
        Object.assign(trip,loadedTrip)

        // Convert UTC times to cave timezone for editing
        const caveTimezone = loadedTrip.timezone || 'UTC'
        tripStartDate.value = momentTimezone(loadedTrip.start_time).tz(caveTimezone).format('YYYY-MM-DD')
        tripStartTime.value = momentTimezone(loadedTrip.start_time).tz(caveTimezone).format('HH:mm')

        // Calculate duration from start_time and end_time
        const startTime = momentTimezone(loadedTrip.start_time).tz(caveTimezone)
        const endTime = momentTimezone(loadedTrip.end_time).tz(caveTimezone)
        const durationInMinutes = endTime.diff(startTime, 'minutes')
        tripDurationHours.value = Math.floor(durationInMinutes / 60)
        tripDurationMinutes.value = durationInMinutes % 60

        if(loadedTrip.entrance_cave_id !== loadedTrip.exit_cave_id) {
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

  const updatedDescription = (editor) => {
    markdownOutput.value =  editor.editor.storage.markdown.getMarkdown()
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
    if(!cave_system_id.value) return 0
    return caves.value.filter((cave => cave.system.id === cave_system_id.value)).length
  })

  watch(() => trip.entrance_cave_id, (cave_id) => {
    if(!cave_id) return
    if(throughTrip.value) { // Currently set as through trip
      const currentSystem = caves.value.find(cave => cave.id === trip.entrance_cave_id)
      const multipleEntrances = caves.value.filter((cave => cave.system.id == currentSystem.id))
      throughTrip.value = !!multipleEntrances
    }
  })

  const start_time = computed(() => {
    // If we have a loaded trip with timezone, create the time in that timezone
    if (trip.timezone && trip.timezone !== 'UTC') {
      return momentTimezone.tz(tripStartDate.value + ' ' + tripStartTime.value, 'YYYY-MM-DD HH:mm', trip.timezone)
    } else {
      // For new trips or UTC, treat as local time that will be converted later
      return moment(tripStartDate.value + ' ' + tripStartTime.value, 'YYYY-MM-DD HH:mm')
    }
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
      if(!trip.exit_cave_id || !throughTrip.value) {
        trip.exit_cave_id = trip.entrance_cave_id
      }
      
      // Convert times to UTC for storage
      let startTimeUtc, endTimeUtc
      
      if (trip.timezone && trip.timezone !== 'UTC') {
        // Convert from cave timezone to UTC
        startTimeUtc = momentTimezone.tz(tripStartDate.value + ' ' + tripStartTime.value, 'YYYY-MM-DD HH:mm', trip.timezone).utc()
        endTimeUtc = startTimeUtc.clone().add(tripDurationHours.value, 'hours').add(tripDurationMinutes.value, 'minutes')
      } else {
        // For new trips, assume local time and convert to UTC
        startTimeUtc = moment(tripStartDate.value + ' ' + tripStartTime.value, 'YYYY-MM-DD HH:mm').utc()
        endTimeUtc = startTimeUtc.clone().add(tripDurationHours.value, 'hours').add(tripDurationMinutes.value, 'minutes')
      }
      
      trip.start_time = startTimeUtc.format('YYYY-MM-DD HH:mm:ss')
      trip.end_time = endTimeUtc.format('YYYY-MM-DD HH:mm:ss')
      trip.cave_system_id = cave_system_id.value // Ensure system_id is set
      if(markdownOutput.value) {
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

      if(route.params.id) {
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
      router.push({ name: '/trip/[id]', params: { id: tripPayload.id } });
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
      router.push({ name: '/trip/[id]', params: { id: savedTrip.id } });
    } else {
      await handleApiError(response);
    }
  }

</script>

<style>
  .vuetify-pro-tiptap-editor__content + .v-toolbar {
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
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }
</style>