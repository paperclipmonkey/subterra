<template>
  <v-container class="pa-4">
    <v-form class="pa-xl-4">
      <v-text-field
        v-model="trip.name"
        label="Trip Name"
        :rules="rules.name"
        :error-messages="validationErrors.name"
        @update:modelValue="validationErrors.name = []"
        required
      ></v-text-field>
      <!-- Consider adding error display for description if needed -->
      <VuetifyTiptap @change="updatedDescription" v-model="trip.description" output="text" markdown-theme="github" >
      </VuetifyTiptap>
      <v-file-input
        prepend-icon="mdi-camera"
        accept="image/*"
        label="Trip Photos"
        v-model="trip.media"
        :error-messages="validationErrors.media"
        @update:modelValue="validationErrors.media = []"
        chips
        multiple
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
      <v-autocomplete
        label="Location"
        :items="caves"
        item-title="name"
        :rules="rules.location"
        item-value="id"
        v-model="trip.entrance_cave_id"
        :error-messages="validationErrors.entrance_cave_id || validationErrors.system_id"
        @update:modelValue="() => { validationErrors.entrance_cave_id = []; validationErrors.system_id = [] }"
      >
        <template v-slot:item="{ props, item }">
          <!-- :prepend-avatar="item.raw.hero_image || '/map-icon-512-transparent.webp'" -->
          <v-list-item
            v-bind="props"
            :subtitle="item.raw.location_name + ', ' + item.raw.location_country"
            :title="item.raw.name"
          ></v-list-item>
        </template>
      </v-autocomplete>
      <template v-if="system_entrances_count > 1">
        <v-checkbox v-model="throughTrip" label="Through trip"></v-checkbox>
        <template v-if="throughTrip">
          <v-autocomplete
            label="Exit"
            :items="caves.filter(cave => cave.system.id === cave_system_id)"
            item-title="name"
            item-value="id"
            v-model="trip.exit_cave_id"
            :error-messages="validationErrors.exit_cave_id"
            @update:modelValue="validationErrors.exit_cave_id = []"
          ></v-autocomplete>
          </template>
      </template>
      <v-autocomplete
        label="Participants"
        :items="users"
        item-title="name"
        item-value="id"
        multiple
        chips
        closable-chips
        v-model="trip.participants"
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
            Can't find that user.  
            <strong>Add them manually</strong>
          </v-btn>
        </template>
    </v-autocomplete>
      <!-- --- Trip Tags Section --- -->
      <v-divider class="my-4"></v-divider>
      <h3 class="mb-2">Trip Details</h3>

      <!-- Trip Type (Single Select) -->
      <template v-if="tripTags.type && tripTags.type.length">
        <label class="v-label mb-1">Trip Type</label>
        <v-radio-group v-model="selectedTripTypeId" inline>
          <v-radio
            v-for="tag in tripTags.type"
            :key="tag.id"
            :label="tag.tag"
            :value="tag.id"
          ></v-radio>
        </v-radio-group>
      </template>

      <!-- Difficulty (Single Select) -->
      <template v-if="tripTags.difficulty && tripTags.difficulty.length">
        <label class="v-label mb-1">Difficulty</label>
        <v-radio-group v-model="selectedDifficultyId" inline>
          <v-radio
            v-for="tag in tripTags.difficulty"
            :key="tag.id"
            :label="tag.tag"
            :value="tag.id"
          ></v-radio>
        </v-radio-group>
      </template>

      <!-- Tackle Used (Multi Select) -->
      <template v-if="tripTags.tackle && tripTags.tackle.length">
        <label class="v-label mb-1">Tackle Used</label>
        <div> <!-- Wrap checkboxes for better layout if needed -->
          <v-checkbox
            v-for="tag in tripTags.tackle"
            :key="tag.id"
            v-model="selectedTackleIds"
            :label="tag.tag"
            :value="tag.id"
            hide-details
            density="compact"
            class="d-inline-block mr-4"
          ></v-checkbox>
        </div>
      </template>
      <v-divider class="my-4"></v-divider>
      <!-- --- End Trip Tags Section --- -->

      <v-row>
        <v-col cols="6">
          <v-text-field
            v-model="tripStartDate"
            label="Date"
            type="date"
            :error-messages="validationErrors.start_time || validationErrors.end_time"
            @update:modelValue="() => { validationErrors.start_time = []; validationErrors.end_time = [] }"
            required
          ></v-text-field>
        </v-col>
        <v-col cols="6">
          <v-text-field
            v-model="tripStartTime"
            label="Entry time"
            type="time"
            :error-messages="validationErrors.start_time || validationErrors.end_time"
            @update:modelValue="() => { validationErrors.start_time = []; validationErrors.end_time = [] }"
            required>
          </v-text-field>
        </v-col>
      </v-row>
      <v-row>
        <v-col cols="6">
          <v-text-field
            v-model="tripDurationHours"
            label="Duration (hours)"
            type="number"
            min="0"
            :error-messages="validationErrors.end_time"
            @update:modelValue="validationErrors.end_time = []"
            required
          ></v-text-field>
        </v-col>
        <v-col cols="6">
          <v-text-field
            v-model="tripDurationMinutes"
            label="Duration (minutes)"
            type="number"
            min="0"
            max="59"
            :error-messages="validationErrors.end_time"
            @update:modelValue="validationErrors.end_time = []"
            required
          ></v-text-field>
        </v-col>
      </v-row>
      <v-btn @click="submitForm">Save</v-btn>
    </v-form>
    <AddParticipantManual @close="closeAddParticipant" @add="addParticipant" :isActive="showAddParticipant"/>
  </v-container>
</template>

<script setup>
  import moment from 'moment'
  import { computed, reactive, ref, watch, onMounted } from 'vue'
  import AddParticipantManual from './AddParticipantManual.vue';
  import { convertFileToBase64 } from '@/utilities.js'
  import { useRouter, useRoute } from 'vue-router';

  const router = useRouter()
  const route = useRoute()

  const markdownOutput = ref('')

  const showAddParticipant = ref(false)

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
  })

  const tripStartDate = ref(moment().format('YYYY-MM-DD'))
  const tripStartTime = ref(moment().format('HH:mm'))
  const tripDurationHours = ref(4)
  const tripDurationMinutes = ref(0)

  const throughTrip = ref(false)
  const userEmail = ref({})
  const users = ref([])
  const caves = ref([])
  const tripTags = ref({}) // To store fetched tags grouped by category
  const selectedTripTypeId = ref(null)
  const selectedDifficultyId = ref(null)
  const selectedTackleIds = ref([])

  const validationErrors = ref({})

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
  }

  onMounted(async () => {
    // --- Fetch Tags ---
    try {
      const tagsResponse = await fetch('/api/tags/trip');
      if (tagsResponse.ok) {
        tripTags.value = await tagsResponse.json();
      } else {
        console.error('Failed to fetch trip tags:', tagsResponse.statusText);
        tripTags.value = {}; // Set to empty object on failure
      }
    } catch (error) {
      console.error('Error fetching trip tags:', error);
      tripTags.value = {}; // Set to empty object on error
    }
    // --- End Fetch Tags ---

    // Load caves
    let response = await fetch('/api/caves')
    caves.value = (await response.json()).data

    // Load users
    const userResonse = await fetch('/api/users/me')
    userEmail.value = (await userResonse.json()).data.email
    response = await fetch('/api/users')
    users.value = (await response.json()).data
    if(!trip.participants.length) {
      trip.participants.push(users.value.find(user => user.email === userEmail.value).email)
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

      // --- Populate Selected Tags --- 
      if (loadedTrip.tags && loadedTrip.tags.length > 0) {
        loadedTrip.tags.forEach(tag => {
          if (tag.category === 'type') {
            selectedTripTypeId.value = tag.id;
          }
          if (tag.category === 'difficulty') {
            selectedDifficultyId.value = tag.id;
          }
          if (tag.category === 'tackle') {
            selectedTackleIds.value.push(tag.id);
          }
        });
      }
      // --- End Populate Selected Tags ---

      loadedTrip.existing_media = loadedTrip.media
      loadedTrip.media = []

      loadedTrip.participants = loadedTrip.participants.map(participant => participant.email)
      
      loadedTrip.entrance_cave_id = loadedTrip.entrance.id
      
      loadedTrip.exit_cave_id = loadedTrip.exit.id
      //loadedTrip.cave_system_id = loadedTrip.system.id
      delete loadedTrip.entrance
      delete loadedTrip.exit
      delete loadedTrip.system
      delete loadedTrip.tags; // Remove tags from main trip object to avoid sending them incorrectly
      Object.assign(trip,loadedTrip)

      tripStartDate.value = moment(loadedTrip.start_time).format('YYYY-MM-DD')
      tripStartTime.value = moment(loadedTrip.start_time).format('HH:mm')

      if(loadedTrip.entrance_cave_id !== loadedTrip.exit_cave_id) {
        throughTrip.value = true
      }
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
    trip.participants.push(participant.email)
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
      console.log('Participant added successfully:', data);
    })
    .catch(error => {
      console.error('Error adding participant:', error);
    });
    users.value.push(participant) // So it can be referenced
    showAddParticipant.value = false
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
    const entry = moment(tripStartDate.value + ' ' + tripStartTime.value, 'YYYY-MM-DD HH:mm')
    return entry
  })

  const end_time = computed(() => {
    const exit = start_time.value.clone()
    exit.add(tripDurationHours.value, 'hours')
    exit.add(tripDurationMinutes.value, 'minutes')
    return exit
  })
  
  const combinedSelectedTagIds = computed(() => {
    const ids = [...selectedTackleIds.value];
    if (selectedTripTypeId.value) {
      ids.push(selectedTripTypeId.value);
    }
    if (selectedDifficultyId.value) {
      ids.push(selectedDifficultyId.value);
    }
    return ids;
  });

  const submitForm = async () => {
    validationErrors.value = {} // Clear previous errors
    if(!trip.exit_cave_id || !throughTrip.value) {
      trip.exit_cave_id = trip.entrance_cave_id
    }
    trip.start_time = `${tripStartDate.value} ${tripStartTime.value}:00` // Add seconds for format Y-m-d H:i:s
    trip.end_time = end_time.value.format('YYYY-MM-DD HH:mm:ss') // Add seconds for format Y-m-d H:i:s
    trip.cave_system_id = cave_system_id.value // Ensure system_id is set
    if(markdownOutput.value) {
      trip.description = markdownOutput.value // Copy the markdown output to the description field
    }

    // Convert only new files to base64
    const newMediaFiles = trip.media.filter(file => file instanceof File);
    const base64Media = await Promise.all(newMediaFiles.map(file => convertFileToBase64(file)));

    // Prepare payload (without tags initially)
    const payload = {
      ...trip,
      media: base64Media,
    };

    let savedTripId = null;
    if(route.params.id) {
      savedTripId = await updateTrip(payload)
    } else {
      savedTripId = await saveTrip(payload)
    }

    // --- Update Tags After Save/Update ---
    if (savedTripId) {
      await updateTripTags(savedTripId, combinedSelectedTagIds.value);
      router.push({ name: '/trip/[id]', params: { id: savedTripId } });
    } else {
      // Handle case where trip save/update failed before tag update
      console.error("Trip save/update failed, cannot update tags.");
      // Optionally show an error message to the user
    }
    // --- End Update Tags ---
  }

  const handleApiError = async (response) => {
    if (response.status === 422) {
      const errorData = await response.json();
      validationErrors.value = errorData.errors;
      console.error('Validation failed:', errorData.errors);
    } else {
      console.error('Failed operation:', response.statusText);
      // Handle other types of errors (e.g., display a generic error message)
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
      // Don't redirect here yet, return the ID for tag update
      return tripPayload.id; 
    } else {
      await handleApiError(response);
      return null; // Indicate failure
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
      // Don't redirect here yet, return the ID for tag update
      return savedTrip.id; 
    } else {
      await handleApiError(response);
      return null; // Indicate failure
    }
  }

  // --- New Function to Update Tags ---
  const updateTripTags = async (tripId, tagIds) => {
    try {
      const response = await fetch(`/api/trips/${tripId}/tags`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ tags: tagIds })
      });
      if (!response.ok) {
        // Handle tag update error specifically (optional)
        console.error('Failed to update trip tags:', response.statusText);
        const errorData = await response.json();
        console.error('Tag update error details:', errorData);
        // Optionally show a specific error message to the user about tags failing
      }
      // No need to return anything specific unless needed for further logic
    } catch (error) {
      console.error('Error updating trip tags:', error);
      // Optionally show a specific error message to the user
    }
  }
  // --- End New Function ---

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
</style>