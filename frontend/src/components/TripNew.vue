<template>
  <v-container class="pa-4">
    <v-form class="pa-xl-4">
      <v-text-field
        v-model="trip.name"
        label="Trip Name"
        :rules="rules.name"
        required
      ></v-text-field>
      <!-- <v-textarea
        v-model="trip.description"
        label="Trip Description"
        :rules="rules.description"
        required
      ></v-textarea> -->
      <VuetifyTiptap @change="updatedDescription" v-model="trip.description" output="text" markdown-theme="github" >
      </VuetifyTiptap>
      <v-file-input
        prepend-icon="mdi-camera"
        accept="image/*"
        label="Trip Photos"
        v-model="trip.media"
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
          ></v-autocomplete>
          </template>
      </template>
      <v-autocomplete
        label="Participants"
        :items="users"
        item-title="name"
        item-value="email"
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
      <v-row>
        <v-col cols="6">
          <v-text-field
            v-model="tripStartDate"
            label="Date"
            type="date"
            required
          ></v-text-field>
        </v-col>
        <v-col cols="6">
          <v-text-field
            v-model="tripStartTime"
            label="Entry time"
            type="time"
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
  import { computed, reactive, ref, watch } from 'vue'
  import AddParticipantManual from './AddParticipantManual.vue';
  import { convertFileToBase64 } from '@/utilities.js'
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

      loadedTrip.existing_media = loadedTrip.media
      loadedTrip.media = []

      loadedTrip.participants = loadedTrip.participants.map(participant => participant.email)
      
      loadedTrip.entrance_cave_id = loadedTrip.entrance.id
      
      loadedTrip.exit_cave_id = loadedTrip.exit.id
      //sloadedTrip.cave_system_id = loadedTrip.system.id
      delete loadedTrip.entrance
      delete loadedTrip.exit
      delete loadedTrip.system
      Object.assign(trip,loadedTrip)

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
  
  const submitForm = async () => {
    if(!trip.exit_cave_id || !throughTrip.value) {
      trip.exit_cave_id = trip.entrance_cave_id
    }
    trip.start_time = `${tripStartDate.value} ${tripStartTime.value}`
    trip.end_time = end_time.value.format('YYYY-MM-DD HH:mm')
    trip.cave_system_id = cave_system_id.value
    if(markdownOutput.value) {
      trip.description = markdownOutput.value // Copy the markdown output to the description field
    }

    trip.media = await Promise.all(trip.media.map(file => convertFileToBase64(file)));
    
    if(route.params.id) {
      await updateTrip(trip)
    } else {
      await saveTrip(trip)
    }
  }

  const updateTrip = async (trip) => {
    const response = await fetch(`/api/trips/${trip.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(trip)
    })
    if (response.ok) {
      router.push({ name: '/trip/[id]', params: { id: trip.id } });
    } else {
      console.error('failed to update trip')
    }
  }

  const saveTrip = async (trip) => {
    const response = await fetch('/api/trips', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(trip)
    })
    if (response.ok) {
      const savedTrip = (await response.json()).data;
      router.push({ name: '/trip/[id]', params: { id: savedTrip.id } });
    } else {
      console.error('failed to save trip')
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
</style>