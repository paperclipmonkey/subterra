<template>
  <v-dialog
    v-model="visible"
    persistent
    max-width="600"
    class="onboarding-wizard"
  >
    <v-card class="rounded-xl overflow-hidden">
      <!-- Header -->
      <v-window v-model="step">
        <!-- Step 1: Welcome & Name -->
        <v-window-item :value="1">
          <v-card-text class="pa-8 text-center">
            <v-avatar color="primary" size="80" class="mb-6 elevation-4">
              <v-icon size="48" color="white" :icon="mdiAccountPlus" />
            </v-avatar>
            <h2 class="text-h4 font-weight-bold mb-2">Welcome to Subterra!</h2>
            <p class="text-subtitle-1 text-medium-emphasis mb-8">
              We're excited to have you underground. Let's get your profile started with your name.
            </p>

            <v-form ref="nameForm" v-model="nameValid" @submit.prevent="nextStep">
              <v-text-field
                v-model="userName"
                label="Your full name (first and last)"
                placeholder="e.g., John Smith"
                variant="outlined"
                color="primary"
                :rules="nameRules"
                :prepend-inner-icon="mdiAccountOutline"
                hint="Please use your full name so others can identify you correctly when selecting callout members."
                persistent-hint
                class="mb-4"
              />
            </v-form>
            <v-alert
              color="warning"
              variant="tonal"
              :icon="mdiAlertOutline"
              density="compact"
              class="mt-4 text-left"
            >
              <div class="text-caption">
                Your name may be passed to <strong>cave rescue</strong> as an emergency point of contact. Please use your <strong>legal first and last name</strong>.
              </div>
            </v-alert>
          </v-card-text>
        </v-window-item>

        <!-- Step 2: Join a Club -->
        <v-window-item :value="2">
          <v-card-text class="pa-8">
            <div class="text-center mb-6">
              <v-avatar color="secondary" size="64" class="mb-4 elevation-2">
                <v-icon size="36" color="white" :icon="mdiAccountGroup" />
              </v-avatar>
              <h2 class="text-h5 font-weight-bold mb-2">Find Your Community</h2>
              <p class="text-body-2 text-medium-emphasis">
                Caving is safer and more fun with others. Search for a club to join or request membership.
              </p>
              
              <v-alert
                color="info"
                variant="tonal"
                :icon="mdiInformationOutline"
                class="mt-4 text-left"
                density="compact"
              >
                <div class="text-caption">
                  <strong>How it works:</strong> Once you request to join, your club's administrator will review your application. 
                  As soon as they <strong>approve</strong> your membership, you'll gain full access to all protected cave data and platform features!
                </div>
              </v-alert>
            </div>

            <v-text-field
              v-model="clubSearch"
              label="Search Clubs"
              variant="outlined"
              density="compact"
              :prepend-inner-icon="mdiMagnify"
              hide-details
              class="mb-4"
            />

            <v-list class="club-list py-0" height="250" style="overflow-y: auto;">
              <v-list-item
                v-for="club in filteredClubs"
                :key="club.id"
                :title="club.name"
                :subtitle="club.location"
                class="mb-2 border rounded-lg"
              >
                <template #append>
                  <v-btn
                    v-if="!getStatus(club)"
                    color="primary"
                    variant="tonal"
                    size="small"
                    :loading="joiningClub === club.id"
                    @click="joinClub(club)"
                  >
                    Join
                    <v-icon end :icon="mdiPlus" />
                  </v-btn>
                  <v-chip v-else :color="getStatus(club) === 'approved' ? 'success' : 'warning'" size="small" variant="flat">
                    {{ getStatus(club) === 'approved' ? 'Approved' : 'Pending' }}
                  </v-chip>
                </template>
              </v-list-item>
              
              <v-list-item v-if="filteredClubs.length === 0" class="text-center py-8">
                <p class="text-medium-emphasis">No clubs found matching your search.</p>
              </v-list-item>
            </v-list>

            <div class="text-center mt-4 text-caption text-medium-emphasis">
              Can't find your club? You can join later from the profile page.
            </div>
          </v-card-text>
        </v-window-item>

        <!-- Step 3: Feature Tour -->
        <v-window-item :value="3">
          <v-card-text class="pa-8" style="max-height: 70vh; overflow-y: auto;">
            <div class="text-center mb-6">
              <v-avatar color="success" size="64" class="mb-4 elevation-2">
                <v-icon size="36" color="white" :icon="mdiCompassOutline" />
              </v-avatar>
              <h2 class="text-h5 font-weight-bold mb-2">Platform Highlights</h2>
              <p class="text-body-2 text-medium-emphasis">
                Here are a few things you can do even while waiting for club approval.
              </p>
            </div>
            
            <div class="feature-tour-grid">
              <div v-for="item in tourItems" :key="item.title" class="tour-item mb-4 pa-4 border rounded-lg text-left d-flex align-start">
                <v-avatar :color="item.color || 'primary'" size="32" variant="tonal" class="mr-4 mt-1">
                  <v-icon :icon="item.icon" size="20" />
                </v-avatar>
                <div>
                  <div class="font-weight-bold text-subtitle-2">{{ item.title }}</div>
                  <div class="text-caption text-medium-emphasis">{{ item.text }}</div>
                </div>
              </div>
            </div>

            <v-alert
              color="primary"
              variant="tonal"
              :icon="mdiHelpCircleOutline"
              class="mt-4"
              density="compact"
            >
              <div class="text-caption">
                <strong>Pro Tip:</strong> Visit any cave page to see descriptions. Once you've visited, click the <strong>checkmark</strong> to mark it as <em>Done</em> and keep your logbook up to date!
              </div>
            </v-alert>
          </v-card-text>
        </v-window-item>
      </v-window>

      <!-- Footer Actions -->
      <v-card-actions class="pa-8 pt-0">
        <v-btn
          v-if="step > 1"
          variant="text"
          :disabled="loading"
          @click="step--"
        >
          Back
        </v-btn>
        <v-spacer />
        <v-btn
          color="primary"
          block
          size="large"
          class="text-none font-weight-bold"
          :loading="loading"
          @click="nextStep"
        >
          {{ step === 3 ? 'Get Started' : 'Continue' }}
          <v-icon v-if="step < 3" end :icon="mdiArrowRight" />
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { mdiAccountGroup, mdiAccountOutline, mdiAccountPlus, mdiAlertOctagram, mdiAlertOutline, mdiArrowRight, mdiCompassOutline, mdiEarth, mdiHelpCircleOutline, mdiInformationOutline, mdiMagnify, mdiNotebookOutline, mdiPlus, mdiTrophyOutline } from '@mdi/js'
import { ref, computed, onMounted } from 'vue'
import { useAppStore } from '@/stores/app'
import { api } from '@/plugins/api'

const store = useAppStore()
const visible = ref(false)
const step = ref(1)
const loading = ref(false)

const userName = ref('')
const nameValid = ref(false)
const nameForm = ref(null)

const nameRules = [
  v => !!v || 'Name is required',
  v => (v && v.trim().includes(' ')) || 'Please enter your full name (first and last name)',
  v => (v && v.trim().length >= 4) || 'Name must be at least 4 characters',
  v => (v && v.length <= 100) || 'Name must be less than 100 characters',
]

const clubSearch = ref('')
const allClubs = ref([])
const joiningClub = ref(null)
const joinedClubs = ref([])

import { watch } from 'vue'

const checkOnboarding = () => {
  if (store.user && store.user.id && !store.user.onboarding_completed_at) {
    visible.value = true
    userName.value = store.user.name || ''
    fetchClubs()
  }
}

onMounted(() => {
  checkOnboarding()
})

watch(() => store.user, () => {
  checkOnboarding()
}, { deep: true })

const fetchClubs = async () => {
  try {
    const response = await api.get('/api/clubs')
    allClubs.value = response.data.data || response.data
  } catch (error) {
    console.error('Error fetching clubs:', error)
  }
}

const filteredClubs = computed(() => {
  if (!clubSearch.value) return allClubs.value.slice(0, 10)
  const s = clubSearch.value.toLowerCase()
  return allClubs.value.filter(c =>
    c.name.toLowerCase().includes(s) ||
    (c.location && c.location.toLowerCase().includes(s))
  ).slice(0, 10)
})

const getStatus = (club) => {
  if (joinedClubs.value.includes(club.id)) return 'pending'
  const storedClub = store.user.clubs && store.user.clubs.find(c => c.id === club.id)
  return storedClub ? storedClub.status : null
}

const joinClub = async (club) => {
  joiningClub.value = club.id
  try {
    await api.post(`/api/clubs/${club.slug}/join`)
    joinedClubs.value.push(club.id)
  } catch (error) {
    console.error('Error joining club:', error)
  } finally {
    joiningClub.value = null
  }
}

const tourItems = [
  {
    title: 'My Trips & Logbook',
    icon: mdiNotebookOutline,
    color: 'blue',
    text: 'Click the checkmark on any cave to mark it as done. This builds your personal logbook.'
  },
  {
    title: 'Cave Details',
    icon: mdiEarth,
    color: 'green',
    text: 'Explore cave descriptions, history, and photos. (Access to maps will unlock after club approval)'
  },
  {
    title: 'Safety Callouts',
    icon: mdiAlertOctagram,
    color: 'orange',
    text: 'A vital safety feature for cavers. Set trip deadlines and emergency contacts (Unlocks after club approval).'
  },
  {
    title: 'Medals & Stats',
    icon: mdiTrophyOutline,
    color: 'purple',
    text: 'Earn medals for your caving achievements as you log more trips.'
  },
]

const nextStep = async () => {
  if (step.value === 1) {
    const { valid } = await nameForm.value.validate()
    if (!valid) return

    loading.value = true
    try {
      await api.put('/api/users/me', { name: userName.value })
      // Update local storage/store
      store.user.name = userName.value
      step.value = 2
    } catch (error) {
      console.error('Error updating name:', error)
    } finally {
      loading.value = false
    }
  } else if (step.value === 2) {
    step.value = 3
  } else if (step.value === 3) {
    loading.value = true
    try {
      const now = new Date().toISOString()
      await api.put('/api/users/me', { onboarding_completed_at: now })
      await store.getUser() // Refresh user data to update clubs status in App.vue
      store.user.onboarding_completed_at = now
      visible.value = false
    } catch (error) {
      console.error('Error completing onboarding:', error)
    } finally {
      loading.value = false
    }
  }
}
</script>

<style scoped lang="scss">
.onboarding-wizard {
  z-index: 2500;
}

.club-list {
  &::-webkit-scrollbar {
    width: 6px;
  }

  &::-webkit-scrollbar-thumb {
    background: #e0e0e0;
    border-radius: 10px;
  }
}

.tour-item {
  transition: transform 0.2s;

  &:hover {
    transform: translateX(4px);
    background-color: rgba(var(--v-theme-primary), 0.05);
  }
}
</style>
