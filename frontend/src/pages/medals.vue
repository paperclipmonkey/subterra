<template>
  <div class="fill-height bg-grey-lighten-5">
    <v-container v-if="loading" class="fill-height d-flex justify-center align-center">
      <v-progress-circular indeterminate color="primary" />
    </v-container>

    <v-container v-else-if="error" class="fill-height d-flex flex-column justify-center align-center text-center">
      <v-icon :icon="mdiAlertCircleOutline" size="64" color="grey" class="mb-4" />
      <h2 class="text-h5 text-grey-darken-1 mb-2">Oops!</h2>
      <p class="text-body-1 text-grey mb-6">{{ error }}</p>
      <v-btn color="primary" variant="flat" :prepend-icon="mdiRefresh" @click="loadMedals">
        Try again
      </v-btn>
    </v-container>

    <v-container v-else class="py-8 px-4" style="max-width: 1000px;">

      <!-- Summary header -->
      <v-card class="rounded-xl mb-6" elevation="0" border>
        <v-card-text class="pa-6 d-flex align-center">
          <v-avatar color="amber-lighten-5" size="56" class="mr-4 flex-shrink-0">
            <v-icon color="amber-darken-2" :icon="mdiMedalOutline" size="32" />
          </v-avatar>
          <div class="flex-grow-1" style="min-width: 0;">
            <h1 class="text-h5 font-weight-bold text-grey-darken-4 mb-1">Medals</h1>
            <div class="text-body-2 text-medium-emphasis mb-2">
              You've earned {{ earnedMedals.length }} of {{ medals.length }} medals
            </div>
            <v-progress-linear :model-value="medals.length ? (earnedMedals.length / medals.length) * 100 : 0"
                               color="amber-darken-2" bg-color="grey-lighten-3" height="8" rounded />
          </div>
        </v-card-text>
      </v-card>

      <!-- Still to earn -->
      <template v-if="unearnedMedals.length > 0">
        <div class="d-flex align-center mb-3">
          <v-icon :icon="mdiTrophyOutline" color="grey-darken-1" class="mr-2" />
          <h2 class="text-h6 font-weight-bold text-grey-darken-3">Still to earn</h2>
          <v-chip size="x-small" variant="tonal" color="grey-darken-1" class="ml-2">{{ unearnedMedals.length }}</v-chip>
        </div>
        <p class="text-body-2 text-medium-emphasis mb-4">
          Here's what each one takes — your next trip could tick one off.
        </p>
        <v-row class="mb-6">
          <v-col v-for="medal in unearnedMedals" :key="medal.id" cols="12" sm="6" md="4">
            <v-card class="rounded-xl h-100 medal-card" elevation="0" border @click="openMedalModal(medal)">
              <v-card-text class="pa-5 d-flex flex-column h-100">
                <div class="d-flex align-center mb-3">
                  <div class="medal-thumb medal-locked mr-4 flex-shrink-0">
                    <img v-if="medal.image_url" :src="medal.image_url" :alt="medal.name">
                    <v-icon v-else :icon="mdiMedalOutline" size="36" color="grey-lighten-1" />
                  </div>
                  <div class="text-subtitle-1 font-weight-bold text-grey-darken-2">{{ medal.name }}</div>
                </div>
                <p class="text-body-2 text-medium-emphasis flex-grow-1 mb-3">{{ medal.description }}</p>
                <template v-if="medal.progress && medal.progress.target > 1">
                  <div class="d-flex justify-space-between text-caption text-medium-emphasis mb-1">
                    <span>Progress</span>
                    <span class="font-weight-bold">{{ medal.progress.current }} / {{ medal.progress.target }}</span>
                  </div>
                  <v-progress-linear :model-value="(medal.progress.current / medal.progress.target) * 100"
                                     color="amber-darken-2" bg-color="grey-lighten-3" height="6" rounded />
                </template>
                <div v-else class="text-caption text-medium-emphasis d-flex align-center">
                  <v-icon :icon="mdiLockOutline" size="14" class="mr-1" />
                  Not yet earned
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </template>

      <!-- Earned -->
      <template v-if="earnedMedals.length > 0">
        <div class="d-flex align-center mb-3">
          <v-icon :icon="mdiTrophy" color="amber-darken-2" class="mr-2" />
          <h2 class="text-h6 font-weight-bold text-grey-darken-3">Earned</h2>
          <v-chip size="x-small" variant="flat" color="amber" class="ml-2">{{ earnedMedals.length }}</v-chip>
        </div>
        <v-row>
          <v-col v-for="medal in earnedMedals" :key="medal.id" cols="12" sm="6" md="4">
            <v-card class="rounded-xl h-100 medal-card" elevation="0" border @click="openMedalModal(medal)">
              <v-card-text class="pa-5 d-flex flex-column h-100">
                <div class="d-flex align-center mb-3">
                  <div class="medal-thumb mr-4 flex-shrink-0">
                    <img v-if="medal.image_url" :src="medal.image_url" :alt="medal.name">
                    <v-icon v-else :icon="mdiMedalOutline" size="36" color="amber-darken-2" />
                  </div>
                  <div class="text-subtitle-1 font-weight-bold text-grey-darken-4">{{ medal.name }}</div>
                </div>
                <p class="text-body-2 text-medium-emphasis flex-grow-1 mb-3">{{ medal.description }}</p>
                <div v-if="medal.awarded_at" class="text-caption text-amber-darken-3 d-flex align-center">
                  <v-icon :icon="mdiCheckDecagram" size="14" class="mr-1" />
                  Awarded {{ formatAwardedDate(medal.awarded_at) }}
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </template>

      <!-- Empty (no medals defined at all) -->
      <div v-if="medals.length === 0" class="text-center py-12 text-medium-emphasis">
        <v-icon :icon="mdiMedalOutline" size="64" class="mb-4 opacity-20" />
        <div class="text-h6 font-weight-regular">No medals available yet</div>
      </div>
    </v-container>

    <!-- Medal Details Modal -->
    <v-dialog v-model="isMedalModalOpen" max-width="360">
      <v-card class="rounded-xl text-center pa-6">
        <div class="medal-glow mx-auto mb-6 d-flex align-center justify-center"
             :class="{ 'medal-locked': !selectedMedal.earned }">
          <img v-if="selectedMedal.image_url" :src="selectedMedal.image_url" alt="Medal" class="medal-modal-img">
          <v-icon v-else :icon="mdiMedalOutline" size="96" color="grey-lighten-1" />
        </div>
        <h3 class="text-h5 font-weight-black text-grey-darken-3 mb-2">{{ selectedMedal.name }}</h3>
        <p class="text-body-1 text-grey-darken-1 mb-4">{{ selectedMedal.description }}</p>
        <template v-if="selectedMedal.earned">
          <v-chip v-if="selectedMedal.awarded_at" color="amber" variant="flat" class="mx-auto mb-6"
                  :prepend-icon="mdiCheckDecagram">
            Awarded {{ formatAwardedDate(selectedMedal.awarded_at) }}
          </v-chip>
        </template>
        <template v-else-if="selectedMedal.progress && selectedMedal.progress.target > 1">
          <div class="d-flex justify-space-between text-caption text-medium-emphasis mb-1">
            <span>Progress</span>
            <span class="font-weight-bold">{{ selectedMedal.progress.current }} / {{ selectedMedal.progress.target }}</span>
          </div>
          <v-progress-linear :model-value="(selectedMedal.progress.current / selectedMedal.progress.target) * 100"
                             color="amber-darken-2" bg-color="grey-lighten-3" height="8" rounded class="mb-6" />
        </template>
        <div v-else class="text-caption text-medium-emphasis mb-6 d-flex align-center justify-center">
          <v-icon :icon="mdiLockOutline" size="14" class="mr-1" />
          Not yet earned
        </div>
        <v-btn color="primary" variant="flat" block rounded="lg" size="large"
               @click="isMedalModalOpen = false">Close</v-btn>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { mdiAlertCircleOutline, mdiCheckDecagram, mdiLockOutline, mdiMedalOutline, mdiRefresh, mdiTrophy, mdiTrophyOutline } from '@mdi/js'
import { computed, onMounted, ref } from 'vue'
import { api } from '@/plugins/api'
import { usePageTitle } from '@/composables/usePageTitle'
import moment from 'moment'

usePageTitle('Medals')

const medals = ref([])
const loading = ref(true)
const error = ref(null)

const earnedMedals = computed(() => medals.value.filter(medal => medal.earned))

// Nearly-complete medals first so the next achievable goal leads the list
const unearnedMedals = computed(() =>
  medals.value
    .filter(medal => !medal.earned)
    .sort((a, b) => progressFraction(b) - progressFraction(a))
)

const progressFraction = (medal) => {
  if (!medal.progress || !medal.progress.target) return 0
  return medal.progress.current / medal.progress.target
}

const loadMedals = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await api.get('/api/me/medals')
    medals.value = response.data.data || []
  } catch (err) {
    console.error('Error fetching medals:', err)
    error.value = 'Failed to load medals. Please try again later.'
  } finally {
    loading.value = false
  }
}

onMounted(loadMedals)

const selectedMedal = ref({})
const isMedalModalOpen = ref(false)

const openMedalModal = (medal) => {
  selectedMedal.value = medal
  isMedalModalOpen.value = true
}

const formatAwardedDate = (date) => {
  const parsed = moment(date)
  return parsed.isValid() ? parsed.format('D MMM YYYY') : ''
}
</script>

<style scoped>
.medal-card {
  cursor: pointer;
  transition: all 0.2s ease;
}

.medal-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
}

.medal-thumb {
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.medal-thumb img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.15));
}

.medal-locked img,
.medal-locked .medal-modal-img {
  filter: grayscale(1) opacity(0.45);
}

.medal-glow {
  width: 140px;
  height: 140px;
  background: radial-gradient(circle, rgba(var(--v-theme-primary), 0.1) 0%, transparent 70%);
  border-radius: 50%;
}

.medal-modal-img {
  width: 120px;
  height: 120px;
  object-fit: contain;
  filter: drop-shadow(0 10px 15px rgba(0, 0, 0, 0.2));
}
</style>
