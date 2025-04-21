<template>
  <v-container>
    <v-row>
      <v-col cols="12" class="d-flex align-items-center mb-4">
        <v-btn icon @click="$router.go(-1)" class="mr-2">
          <v-icon>mdi-arrow-left</v-icon>
        </v-btn>
        <h1 class="text-h5 flex-grow-1">{{ trip.name }}</h1>
        <template v-if="currentUserWasOnTrip">
          <v-btn icon @click="$router.push({name: '/trip/[id].edit', params: {id: trip.id}})" class="ml-2">
            <v-icon>mdi-pencil</v-icon>
          </v-btn>
          <v-btn icon @click="showDeleteConfirmDialog = true" color="error" class="ml-2">
            <v-icon>mdi-delete</v-icon>
          </v-btn>
        </template>
      </v-col>
    </v-row>

    <v-row>
      <!-- Main Content Column -->
      <v-col cols="12" sm="8">
        <v-card class="mb-4">
          <v-card-title>Trip Details</v-card-title>
          <v-list density="compact">
            <v-list-item>
              <v-list-item-title>System</v-list-item-title>
              <v-list-item-subtitle>{{ trip.system.name }}</v-list-item-subtitle>
            </v-list-item>
             <v-list-item>
               <v-list-item-title>Entrance</v-list-item-title>
               <v-list-item-subtitle>
                 <router-link :to="{name: '/cave/[id]', params: {id: trip.entrance.slug}}"> {{ trip.entrance.name }} </router-link>
               </v-list-item-subtitle>
             </v-list-item>
             <v-list-item v-if="trip.entrance.id !== trip.exit.id">
               <v-list-item-title>Exit</v-list-item-title>
               <v-list-item-subtitle>
                 <router-link :to="{name: '/cave/[id]', params: {id: trip.exit.slug}}">{{ trip.exit.name }}</router-link>
               </v-list-item-subtitle>
             </v-list-item>
             <v-list-item>
               <v-list-item-title>Date</v-list-item-title>
               <v-list-item-subtitle>{{ formatDate(trip.start_time) }}</v-list-item-subtitle>
             </v-list-item>
             <v-list-item>
               <v-list-item-title>Time</v-list-item-title>
               <v-list-item-subtitle>{{ formatTime(trip.start_time) }}</v-list-item-subtitle>
             </v-list-item>
             <v-list-item>
               <v-list-item-title>Duration</v-list-item-title>
               <v-list-item-subtitle>{{ moment(trip.end_time).diff(trip.start_time, 'hours')}} hours</v-list-item-subtitle>
             </v-list-item>
          </v-list>
          <v-card-text v-if="trip.description">
            <h3 class="text-h6 mb-2">Description</h3>
            <vue-markdown :source="trip.description" />
          </v-card-text>
        </v-card>

      </v-col>

      <!-- Participants Sidebar Column -->
      <v-col cols="12" sm="4">
        <v-card class="mb-4">
          <v-card-title>Participants ({{ trip.participants.length }})</v-card-title>
          <v-list lines="two">
             <v-list-item
               v-for="participant in trip.participants"
               :key="participant.id"
               :prepend-avatar="participant.photo"
               :title="participant.name"
               :subtitle="participant.club || 'No club specified'"
             >
               <!-- Potential future actions per participant -->
               <!-- <template v-slot:append>
                 <v-btn
                   color="grey-lighten-1"
                   icon="mdi-information"
                   variant="text"
                 ></v-btn>
               </template> -->
             </v-list-item>
           </v-list>
        </v-card>

        <v-card v-if="trip.media && trip.media.length > 0">
          <v-card-title>Media</v-card-title>
          <v-card-text>
            <v-row>
              <v-col
                v-for="media in trip.media"
                :key="media.filename"
                cols="6" sm="4" md="3" 
              >
                <v-img
                  :src="media.url" 
                  :alt="media.filename"
                  aspect-ratio="1"
                  cover
                  class="bg-grey-lighten-2 media-thumbnail"
                  @click="() => openMedia(media.url)"
                >
                  <template v-slot:placeholder>
                    <v-row
                      class="fill-height ma-0"
                      align="center"
                      justify="center"
                    >
                      <v-progress-circular
                        indeterminate
                        color="grey-lighten-5"
                      ></v-progress-circular>
                    </v-row>
                  </template>
                </v-img>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="showDeleteConfirmDialog" persistent max-width="400">
      <v-card>
        <v-card-title class="text-h5">Confirm Deletion</v-card-title>
        <v-card-text>Are you sure you want to delete this trip? This action cannot be undone.</v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="blue darken-1" text @click="showDeleteConfirmDialog = false">Cancel</v-btn>
          <v-btn color="red darken-1" text @click="confirmDelete">Delete</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

  </v-container>
</template>

<script setup>
import moment from 'moment'
import VueMarkdown from 'vue-markdown-render'
import { useRouter, useRoute } from 'vue-router'
import { useAppStore } from '@/stores/app'; 
import { ref, computed, onMounted } from 'vue'; // Ensure ref is imported

const appStore = useAppStore()

const router = useRouter()
const route = useRoute()

const formatDate = (date) => {
  return moment(date).format('DD-MM-YYYY')
}

const formatTime = (date) => {
  return moment(date).format('HH:mm')
}

const currentUserWasOnTrip = computed(()=> {
  return trip.value.participants.some((participant) => participant.id === appStore.user.id)
})

const showDeleteConfirmDialog = ref(false);

const deleteTrip = async () => {
  // Now this function just triggers the dialog
  showDeleteConfirmDialog.value = true;
}

const confirmDelete = async () => {
  showDeleteConfirmDialog.value = false; // Close dialog first
  const response = await fetch(`/api/trips/${route.params.id}`, {
    method: 'DELETE',
  })
  if (response.ok) {
    router.push({ name: '/trips' })
  }
  // TODO: Handle potential errors if the delete fails (e.g., show a snackbar message)
}

  const trip = ref({
    description: "",
    id: "",
    name: "",
    media: [],
    system: {},
    entrance: {
      location: {},
    },
    exit: {
      location: {},
    },
    participants: [],
  })

  onMounted(async () => {
    const response = await fetch(`/api/trips/${route.params.id}`)
    trip.value = (await response.json()).data
  })

  const openMedia = (url) => {
    window.open(url, '_blank');
  }
</script>

<style scoped>
 .media-thumbnail {
   border: 1px solid rgba(0,0,0,0.1);
   border-radius: 4px;
   cursor: pointer; /* Keep cursor pointer */
   transition: transform 0.2s ease-in-out;
 }
 .media-thumbnail:hover {
    transform: scale(1.05);
 }
</style>