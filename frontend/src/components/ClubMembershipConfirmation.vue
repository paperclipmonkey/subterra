<template>
  <v-container class="pa-4">
    <v-card>
      <v-card-title>
        BCA Club Membership
      </v-card-title>
      <v-card-text>
        <div>
          <div v-if="pendingClubs && pendingClubs.length">
            <h3 class="text-h6 font-weight-bold mb-4">Awaiting confirmation</h3>
            <v-list class="pa-0">
              <v-list-item v-for="club in pendingClubs" :key="club.id" class="px-3 py-3 mb-4 border rounded-lg">
                <template #prepend>
                  <v-avatar color="warning" size="40" class="mr-3">
                    <v-icon color="white" :icon="mdiAccountClock" />
                  </v-avatar>
                </template>
                <v-list-item-title class="font-weight-bold text-wrap">{{ club.name }}</v-list-item-title>
                <div class="d-flex flex-wrap align-center mt-1" style="gap: 8px;">
                  <v-chip color="warning" size="small" variant="flat">Pending</v-chip>
                  <span class="text-caption text-medium-emphasis">
                    Submitted {{ formatRelativeTime(club.pivot?.created_at) }}
                  </span>
                </div>
              </v-list-item>
            </v-list>
            <v-divider class="my-6" />
          </div>
          <p class="mb-4">
            Tell us which BCA club(s) you already belong to. Their administrators will confirm your membership.
          </p>

          <v-alert type="info" variant="tonal" class="mb-6" border="start" :icon="mdiShieldCheck">
            <div class="text-subtitle-1 font-weight-bold mb-2">Why confirm your club?</div>
            <p class="text-body-2 mb-4">
              Subterra is a community-driven platform. Many features are restricted to confirmed club members to ensure the privacy and security of sensitive cave data.
            </p>
            <v-row dense>
              <v-col cols="12" sm="6">
                <div class="font-weight-bold mb-1 text-primary">Unlocked Now:</div>
                <ul class="ml-4 text-body-2">
                  <li><strong>Log Trips</strong>: Records your underground adventures.</li>
                  <li><strong>Track Stats</strong>: See your caving progress and medals.</li>
                  <li><strong>Public Data</strong>: See cave names and basic info.</li>
                </ul>
              </v-col>
              <v-col cols="12" sm="6">
                <div class="font-weight-bold mb-1 text-success">Once confirmed:</div>
                <ul class="ml-4 text-body-2">
                  <li><strong>Interactive Maps</strong>: View precise cave locations.</li>
                  <li><strong>Access Info</strong>: Read detailed landowner contacts.</li>
                  <li><strong>Safety Callouts</strong>: Use our emergency notification system.</li>
                </ul>
              </v-col>
            </v-row>
          </v-alert>
          <v-autocomplete
            ref="clubAutocomplete"
            v-model="selectedClub"
            :items="filteredAvailableClubs"
            item-title="name"
            item-value="id"
            label="Select Club(s)"
            multiple
            chips
            :loading="loadingClubs"
            clearable
            autocomplete="off"
            name="random_unique_club_confirm_field"
          >
            <template #item="{ props: slotProps, item }">
              <v-list-item v-bind="slotProps" :title="item.raw.name" />
            </template>
            <template #no-data>
              <v-list-item>
                <v-list-item-title>No clubs available or matching your search.</v-list-item-title>
              </v-list-item>
            </template>
          </v-autocomplete>
          <v-alert v-if="success" type="success" class="mt-4">
            Club administrators have been notified. You will receive an email once they have confirmed your membership.
          </v-alert>
          <v-alert v-if="error" type="error" class="mt-4">
            {{ error }}
          </v-alert>
        </div>

      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn color="primary" :disabled="!selectedClub.length || loading" @click="submit">
          Confirm Membership
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-container>
</template>

<script setup>
import { mdiAccountClock, mdiShieldCheck } from '@mdi/js'
import { ref, onMounted, computed, watch } from 'vue'
import moment from 'moment'
import { api } from '@/plugins/api'
const props = defineProps({
  pendingClubs: {
    type: Array,
    default: () => []
  },
  user: {
    type: Object,
    default: () => ({})
  }
})
const emit = defineEmits(['membershipConfirmed'])

const availableClubs = ref([])
const loadingClubs = ref(false)
const selectedClub = ref([])
const loading = ref(false)
const success = ref(false)
const error = ref("")
const clubAutocomplete = ref(null)

const formatRelativeTime = (time) => {
  if (!time) return 'recently'
  return moment(time).fromNow()
}

const fetchAllClubs = async () => {
  loadingClubs.value = true
  try {
    const response = await api.get('/api/clubs')
    availableClubs.value = response.data.data
  } catch (e) {
    availableClubs.value = []
  } finally {
    loadingClubs.value = false
  }
}

const filteredAvailableClubs = computed(() => {
  // Exclude clubs already pending or approved
  const pendingIds = (props.pendingClubs || []).map(c => c.id)
  return availableClubs.value.filter(c => !pendingIds.includes(c.id))
})

const submit = async () => {
  if (!selectedClub.value.length) return
  loading.value = true
  error.value = ""
  success.value = false
  try {
    // Send a join request for each selected club
    for (const clubId of selectedClub.value) {
      const club = availableClubs.value.find(c => c.id === clubId)
      if (!club) continue
      await api.post(`/api/clubs/${club.slug}/join`, { club_id: club.id })
    }
    success.value = true
    emit('membershipConfirmed')
  } catch (e) {
    error.value = e.response?.data?.message || e.message || 'An error occurred.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchAllClubs()
})

// Watch for first club selection to close the autocomplete (helps on mobile to dismiss keyboard)
watch(selectedClub, (newValue) => {
  if (newValue && newValue.length === 1 && clubAutocomplete.value) {
    // Blur the autocomplete after first selection to close the dropdown and dismiss mobile keyboard
    clubAutocomplete.value.blur()
  }
})
</script>
