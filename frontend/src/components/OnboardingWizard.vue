<template>
  <v-dialog
    v-model="visible"
    persistent
    max-width="620"
    class="onboarding-wizard"
    scrollable
  >
    <v-card class="rounded-xl overflow-hidden">
      <!-- Progress header -->
      <div class="px-8 pt-6">
        <div class="d-flex align-center justify-space-between mb-2">
          <span class="text-caption text-medium-emphasis font-weight-medium">
            Step {{ step }} of {{ totalSteps }}
          </span>
          <span class="text-caption text-medium-emphasis">{{ currentTitle }}</span>
        </div>
        <v-progress-linear
          :model-value="(step / totalSteps) * 100"
          color="primary"
          height="6"
          rounded
        />
      </div>

      <v-window :model-value="currentKey" :touch="false" class="onboarding-window">
        <!-- Welcome & Name -->
        <v-window-item value="welcome">
          <v-card-text class="pa-8 text-center">
            <v-avatar color="primary" size="80" class="mb-6 elevation-4">
              <v-icon size="48" color="white" :icon="mdiAccountPlus" />
            </v-avatar>
            <h2 class="text-h4 font-weight-bold mb-2">Welcome to Subterra!</h2>
            <p class="text-subtitle-1 text-medium-emphasis mb-8">
              We're excited to have you underground. Let's get your profile started with your name.
            </p>

            <v-form ref="nameForm" v-model="nameValid" @submit.prevent="nextStep">
              <!-- No hint here: the alert below already explains why we need a
                   legal first and last name, and saying it twice reads as nagging. -->
              <v-text-field
                v-model="userName"
                label="Your full name (first and last)"
                placeholder="e.g., John Smith"
                variant="outlined"
                color="primary"
                :rules="nameRules"
                :prepend-inner-icon="mdiAccountOutline"
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

        <!-- Make it yours — photo & bio -->
        <v-window-item value="profile">
          <v-card-text class="pa-8">
            <div class="text-center mb-6">
              <h2 class="text-h5 font-weight-bold mb-2">Make it yours</h2>
              <p class="text-body-2 text-medium-emphasis">
                A photo and a short bio help club mates recognise you and learn about your caving experience. This is optional — you can always change it later.
              </p>
            </div>

            <div class="d-flex flex-column align-center mb-6">
              <v-avatar size="110" class="photo-avatar elevation-2 cursor-pointer" @click="triggerPhotoUpload">
                <img v-if="photoPreview || existingPhoto" :src="photoPreview || existingPhoto" alt="Profile photo">
                <v-icon v-else size="56" color="grey" :icon="mdiAccountCircleOutline" />
                <div class="avatar-overlay d-flex flex-column align-center justify-center">
                  <v-icon color="white" :icon="mdiCamera" />
                  <span class="text-caption text-white mt-1">{{ photoPreview || existingPhoto ? 'Change' : 'Add photo' }}</span>
                </div>
              </v-avatar>
              <input ref="photoInput" type="file" accept="image/*" class="d-none" @change="onPhotoSelected">
              <span class="text-caption text-medium-emphasis mt-2">Tap to upload (max 5&nbsp;MB)</span>
            </div>

            <v-textarea
              v-model="bio"
              label="Short bio"
              placeholder="e.g., SRT-trained, mostly digging in the Mendips. Happy to lead beginner trips."
              variant="outlined"
              color="primary"
              rows="3"
              auto-grow
              counter="500"
              :rules="bioRules"
              :prepend-inner-icon="mdiTextBoxOutline"
            />
          </v-card-text>
        </v-window-item>

        <!-- Join a Club -->
        <v-window-item value="club">
          <v-card-text class="pa-8">
            <div class="text-center mb-6">
              <v-avatar color="secondary" size="64" class="mb-4 elevation-2">
                <v-icon size="36" color="white" :icon="mdiAccountGroup" />
              </v-avatar>
              <h2 class="text-h5 font-weight-bold mb-2">Confirm Your Club</h2>
              <p class="text-body-2 text-medium-emphasis">
                Subterra is for BCA members. If you already belong to a caving club, find it below and confirm your membership.
              </p>

              <v-alert
                color="info"
                variant="tonal"
                :icon="mdiInformationOutline"
                class="mt-4 text-left"
                density="compact"
              >
                <div class="text-caption">
                  <strong>How it works:</strong> Find the club you're <strong>already a member of</strong> and request to confirm.
                  Your club's administrator reviews and <strong>approves</strong> you — then you'll gain full access to all protected cave data and platform features!
                </div>
              </v-alert>

              <v-alert
                color="primary"
                variant="tonal"
                :icon="mdiAccountStarOutline"
                class="mt-3 text-left"
                density="compact"
              >
                <div class="text-caption">
                  <strong>Not in a member club?</strong> If you're a member of the BCA directly, you're a
                  <strong>Direct Individual Member</strong> — you can find them in the list below.
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
                    Confirm
                    <v-icon end :icon="mdiCheck" />
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
              Can't find your club, or joining later? You can confirm your membership any time from your profile.
            </div>
          </v-card-text>
        </v-window-item>

        <!-- Email preferences -->
        <v-window-item value="email">
          <v-card-text class="pa-8">
            <div class="text-center mb-6">
              <v-avatar color="indigo" size="64" class="mb-4 elevation-2">
                <v-icon size="36" color="white" :icon="mdiEmailOutline" />
              </v-avatar>
              <h2 class="text-h5 font-weight-bold mb-2">Stay in the loop</h2>
              <p class="text-body-2 text-medium-emphasis">
                Choose which emails you'd like to receive. We only email when it matters — you can change these any time from your profile.
              </p>
            </div>

            <v-list class="bg-transparent">
              <v-list-item class="px-0">
                <template #prepend>
                  <v-icon :icon="mdiTrophyOutline" color="amber-darken-2" class="mr-3" />
                </template>
                <v-list-item-title class="font-weight-medium">Trophies</v-list-item-title>
                <v-list-item-subtitle class="text-wrap">Get notified when you earn a new medal.</v-list-item-subtitle>
                <template #append>
                  <v-switch v-model="emailTrophies" color="primary" hide-details density="compact" inset />
                </template>
              </v-list-item>
              <v-divider />
              <v-list-item class="px-0">
                <template #prepend>
                  <v-icon :icon="mdiTagOutline" color="blue-darken-1" class="mr-3" />
                </template>
                <v-list-item-title class="font-weight-medium">Tagged in trips</v-list-item-title>
                <v-list-item-subtitle class="text-wrap">When someone adds you to a trip report.</v-list-item-subtitle>
                <template #append>
                  <v-switch v-model="emailTagged" color="primary" hide-details density="compact" inset />
                </template>
              </v-list-item>
              <v-divider />
              <v-list-item class="px-0">
                <template #prepend>
                  <v-icon :icon="mdiBullhornOutline" color="green-darken-1" class="mr-3" />
                </template>
                <v-list-item-title class="font-weight-medium">Platform news</v-list-item-title>
                <v-list-item-subtitle class="text-wrap">Occasional updates about new features.</v-list-item-subtitle>
                <template #append>
                  <v-switch v-model="emailPlatformNews" color="primary" hide-details density="compact" inset />
                </template>
              </v-list-item>
            </v-list>

            <p class="text-caption text-medium-emphasis text-center mt-4">
              Important safety messages (such as overdue callout alerts) are always sent regardless of these settings.
            </p>
          </v-card-text>
        </v-window-item>

        <!-- Findability -->
        <v-window-item value="findability">
          <v-card-text class="pa-8">
            <div class="text-center mb-6">
              <v-avatar color="teal" size="64" class="mb-4 elevation-2">
                <v-icon size="36" color="white" :icon="mdiAccountSearchOutline" />
              </v-avatar>
              <h2 class="text-h5 font-weight-bold mb-2">Who can add you?</h2>
              <p class="text-body-2 text-medium-emphasis">
                This controls whether you appear in search when other cavers build trip reports and safety callouts.
              </p>
            </div>

            <v-btn-toggle
              v-model="visibilityAddable"
              mandatory
              color="primary"
              variant="text"
              class="findability-toggle mb-4"
            >
              <v-btn value="public" class="text-none">
                <v-icon start :icon="mdiEarth" />
                Public
              </v-btn>
              <v-btn value="club" class="text-none">
                <v-icon start :icon="mdiAccountGroup" />
                Club only
              </v-btn>
            </v-btn-toggle>

            <v-alert
              :color="visibilityAddable === 'public' ? 'success' : 'info'"
              variant="tonal"
              density="comfortable"
              class="text-left"
            >
              <div v-if="visibilityAddable === 'public'" class="text-body-2">
                <strong>Recommended for active cavers.</strong> Anyone can find you by name and add you to a trip or callout.
                That means when you're underground, an organiser can quickly include you in a callout so cave rescue has accurate contacts if something goes wrong.
              </div>
              <div v-else class="text-body-2">
                <strong>More private.</strong> Only members of clubs you belong to can find you by name. Cavers outside your clubs
                won't be able to add you to a trip or callout unless they know your email — which can leave gaps in shared trip records.
              </div>
            </v-alert>

            <p class="text-caption text-medium-emphasis text-center mt-4">
              <v-icon size="14" :icon="mdiInformationOutline" /> Whatever you choose, anyone who knows your email address can always add you.
            </p>
          </v-card-text>
        </v-window-item>

        <!-- Phone verification (only shown to users with callout access) -->
        <v-window-item value="phone">
          <v-card-text class="pa-8">
            <div class="text-center mb-6">
              <v-avatar color="deep-purple" size="64" class="mb-4 elevation-2">
                <v-icon size="36" color="white" :icon="mdiCellphoneCheck" />
              </v-avatar>
              <h2 class="text-h5 font-weight-bold mb-2">Verify your phone</h2>
              <p class="text-body-2 text-medium-emphasis">
                Callouts rely on us being able to reach you. Verify your mobile now and you'll be ready to create callouts the moment your club membership is approved. This is optional — you can do it later from your profile.
              </p>
            </div>

            <div v-if="phoneVerifiedLocal" class="text-center">
              <v-alert
                color="success"
                variant="tonal"
                :icon="mdiCheckCircle"
                density="comfortable"
                class="text-left"
              >
                <div class="text-body-2">
                  <strong>{{ phone }}</strong> is verified. You're all set for callouts once your club approves you.
                </div>
              </v-alert>
            </div>

            <template v-else>
              <v-text-field
                v-model="phone"
                label="Your mobile number"
                placeholder="07700 900123"
                variant="outlined"
                color="primary"
                :rules="phoneRules"
                :error-messages="phoneError"
                :prepend-inner-icon="mdiCellphone"
                hint="Must be 11 digits (07…) or 13 characters (+44…)."
                persistent-hint
                class="mb-4"
                @update:model-value="phoneError = ''"
              />
              <v-btn
                color="primary"
                variant="tonal"
                block
                size="large"
                class="text-none font-weight-bold"
                :loading="savingPhone"
                :disabled="!phone"
                :prepend-icon="mdiCellphoneCheck"
                @click="startVerify"
              >
                Send verification code
              </v-btn>
              <p class="text-caption text-medium-emphasis text-center mt-3">
                We'll text you a 6-digit code to confirm the number. Standard message rates may apply.
              </p>
            </template>

            <PhoneVerify v-model="showVerify" :phone="phone" @verified="onVerified" />
          </v-card-text>
        </v-window-item>

        <!-- Feature Tour -->
        <v-window-item value="features">
          <v-card-text class="pa-8">
            <div class="text-center mb-6">
              <v-avatar color="success" size="64" class="mb-4 elevation-2">
                <v-icon size="36" color="white" :icon="mdiCompassOutline" />
              </v-avatar>
              <h2 class="text-h5 font-weight-bold mb-2">What you can do</h2>
              <p class="text-body-2 text-medium-emphasis">
                Here's a taste of Subterra. Some features unlock once your club membership is approved.
              </p>
            </div>

            <div class="feature-tour-grid">
              <!-- Logbook -->
              <div class="tour-item border rounded-lg overflow-hidden mb-4" style="--tour-accent: 33, 150, 243;">
                <div class="tour-preview pa-3 d-flex align-center" style="gap: 12px;">
                  <div class="mock-cave-row d-flex align-center flex-grow-1 pa-2 rounded-lg">
                    <v-avatar size="28" color="blue-lighten-4" class="mr-2">
                      <v-icon size="18" color="blue-darken-2" :icon="mdiImageFilterHdr" />
                    </v-avatar>
                    <div class="flex-grow-1">
                      <div class="text-caption font-weight-bold">Swildon's Hole</div>
                      <div class="mock-bar" />
                    </div>
                    <v-chip size="x-small" color="success" variant="flat" :prepend-icon="mdiCheck">Done</v-chip>
                  </div>
                </div>
                <div class="pa-3 pt-0 d-flex align-start">
                  <v-icon :icon="mdiNotebookOutline" color="blue" class="mr-3 mt-1" />
                  <div>
                    <div class="font-weight-bold text-subtitle-2">My Trips &amp; Logbook</div>
                    <div class="text-caption text-medium-emphasis">Mark any cave as done to build your personal caving logbook.</div>
                  </div>
                </div>
              </div>

              <!-- Cave details -->
              <div class="tour-item border rounded-lg overflow-hidden mb-4" style="--tour-accent: 67, 160, 71;">
                <div class="tour-preview pa-3">
                  <div class="mock-map rounded-lg d-flex align-end pa-2">
                    <v-chip size="x-small" color="white" variant="flat" :prepend-icon="mdiMapMarkerOutline">12 entrances</v-chip>
                  </div>
                </div>
                <div class="pa-3 pt-0 d-flex align-start">
                  <v-icon :icon="mdiImageFilterHdr" color="green" class="mr-3 mt-1" />
                  <div>
                    <div class="font-weight-bold text-subtitle-2">Cave Details &amp; Maps</div>
                    <div class="text-caption text-medium-emphasis">Descriptions, history and photos. Detailed surveys unlock after club approval.</div>
                  </div>
                </div>
              </div>

              <!-- Collections -->
              <div class="tour-item border rounded-lg overflow-hidden mb-4" style="--tour-accent: 94, 53, 177;">
                <div class="tour-preview pa-3">
                  <div class="mock-collection rounded-lg pa-2">
                    <div class="d-flex align-center mb-1">
                      <v-icon size="14" color="deep-purple" :icon="mdiFormatListChecks" class="mr-1" />
                      <span class="text-caption font-weight-bold">Yorkshire Big Three</span>
                      <v-spacer />
                      <span class="text-caption text-medium-emphasis">2 / 3</span>
                    </div>
                    <v-progress-linear :model-value="66" color="deep-purple" height="5" rounded />
                  </div>
                </div>
                <div class="pa-3 pt-0 d-flex align-start">
                  <v-icon :icon="mdiFormatListChecks" color="deep-purple" class="mr-3 mt-1" />
                  <div>
                    <div class="font-weight-bold text-subtitle-2">Cave Collections</div>
                    <div class="text-caption text-medium-emphasis">Browse curated lists of caves to explore — like the classic Yorkshire trips — and track your progress ticking them off.</div>
                  </div>
                </div>
              </div>

              <!-- Permits -->
              <div class="tour-item border rounded-lg overflow-hidden mb-4" style="--tour-accent: 0, 150, 136;">
                <div class="tour-preview pa-3">
                  <div class="mock-permit rounded-lg pa-2 d-flex align-center" style="gap: 8px;">
                    <v-avatar size="28" color="teal-lighten-4" class="mr-1">
                      <v-icon size="18" color="teal-darken-2" :icon="mdiTicketConfirmationOutline" />
                    </v-avatar>
                    <div class="flex-grow-1">
                      <div class="text-caption font-weight-bold">Redhouse Lane Permit</div>
                      <div class="text-caption text-medium-emphasis">Sat 21 Jun · 4 cavers</div>
                    </div>
                    <v-chip size="x-small" color="success" variant="flat" :prepend-icon="mdiCheck">Approved</v-chip>
                  </div>
                </div>
                <div class="pa-3 pt-0 d-flex align-start">
                  <v-icon :icon="mdiTicketConfirmationOutline" color="teal" class="mr-3 mt-1" />
                  <div>
                    <div class="font-weight-bold text-subtitle-2">Access Permits</div>
                    <div class="text-caption text-medium-emphasis">Some caves need a permit. Check availability and book your spot for a date — the permit's access officers are notified to approve it.</div>
                  </div>
                </div>
              </div>

              <!-- Callouts -->
              <div class="tour-item border rounded-lg overflow-hidden mb-4" style="--tour-accent: 251, 140, 0;">
                <div class="tour-preview pa-3">
                  <div class="mock-callout rounded-lg pa-2 d-flex align-center" style="gap: 8px;">
                    <v-icon :icon="mdiAlertOctagram" color="white" size="20" />
                    <div class="flex-grow-1">
                      <div class="text-caption font-weight-bold text-white">Callout active</div>
                      <div class="text-caption text-white" style="opacity: 0.85;">Back by 18:00 · 3 cavers</div>
                    </div>
                    <v-chip size="x-small" color="white" variant="flat">On time</v-chip>
                  </div>
                </div>
                <div class="pa-3 pt-0 d-flex align-start">
                  <v-icon :icon="mdiAlertOctagram" color="orange" class="mr-3 mt-1" />
                  <div>
                    <div class="font-weight-bold text-subtitle-2">Safety Callouts</div>
                    <div class="text-caption text-medium-emphasis">Set a deadline and contacts before a trip; if you're overdue, your contacts are alerted. Unlocks after club approval.</div>
                  </div>
                </div>
              </div>

              <!-- Medals -->
              <div class="tour-item border rounded-lg overflow-hidden mb-4" style="--tour-accent: 142, 36, 170;">
                <div class="tour-preview pa-3 d-flex align-center justify-center" style="gap: 10px;">
                  <v-avatar v-for="m in ['amber','blue-grey','deep-orange']" :key="m" size="34" :color="m + '-lighten-4'">
                    <v-icon :color="m + '-darken-2'" :icon="mdiMedalOutline" />
                  </v-avatar>
                  <v-avatar size="34" color="grey-lighten-3">
                    <v-icon color="grey" :icon="mdiLockOutline" size="18" />
                  </v-avatar>
                </div>
                <div class="pa-3 pt-0 d-flex align-start">
                  <v-icon :icon="mdiTrophyOutline" color="purple" class="mr-3 mt-1" />
                  <div>
                    <div class="font-weight-bold text-subtitle-2">Medals &amp; Stats</div>
                    <div class="text-caption text-medium-emphasis">Earn medals and track caves visited, trips and hours underground as you log more.</div>
                  </div>
                </div>
              </div>
            </div>

            <v-alert
              color="primary"
              variant="tonal"
              :icon="mdiHelpCircleOutline"
              class="mt-2"
              density="compact"
            >
              <div class="text-caption">
                <strong>Pro Tip:</strong> Visit any cave page to read its description. Once you've been, tap the <strong>checkmark</strong> to mark it <em>Done</em> and keep your logbook up to date!
              </div>
            </v-alert>
          </v-card-text>
        </v-window-item>
      </v-window>

      <!-- Footer Actions -->
      <v-card-actions class="px-8 pb-8 pt-2">
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
          size="large"
          class="text-none font-weight-bold px-6"
          :loading="loading"
          @click="nextStep"
        >
          {{ nextLabel }}
          <v-icon v-if="showNextArrow" end :icon="mdiArrowRight" />
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { mdiAccountCircleOutline, mdiAccountGroup, mdiAccountOutline, mdiAccountPlus, mdiAccountSearchOutline, mdiAccountStarOutline, mdiAlertOctagram, mdiAlertOutline, mdiArrowRight, mdiBullhornOutline, mdiCamera, mdiCellphone, mdiCellphoneCheck, mdiCheck, mdiCheckCircle, mdiCompassOutline, mdiEarth, mdiEmailOutline, mdiFormatListChecks, mdiHelpCircleOutline, mdiImageFilterHdr, mdiInformationOutline, mdiLockOutline, mdiMagnify, mdiMapMarkerOutline, mdiMedalOutline, mdiNotebookOutline, mdiTagOutline, mdiTextBoxOutline, mdiTicketConfirmationOutline, mdiTrophyOutline } from '@mdi/js'
import { ref, computed, onMounted, watch } from 'vue'
import { useAppStore } from '@/stores/app'
import { api } from '@/plugins/api'
import PhoneVerify from '@/components/PhoneVerify.vue'

const store = useAppStore()
const visible = ref(false)
const step = ref(1)
const loading = ref(false)

// Whether the user can use callouts — only then do we ask them to verify a phone during
// onboarding. New users are granted callout_access by default (see the backend feature flag).
const hasCalloutAccess = computed(() => {
  if (!store.calloutsEnabled) return false
  const roles = store.user?.roles || []
  return roles.some(r => ['platform_admin', 'duty_officer', 'callout_access'].includes(r.slug))
})

// The active steps, in order. The phone-verification step is conditional, so the step
// count and progress are derived from this list rather than hard-coded.
const STEP_TITLES = {
  welcome: 'Welcome',
  profile: 'Your profile',
  club: 'Your club',
  email: 'Emails',
  findability: 'Findability',
  phone: 'Phone',
  features: 'Features',
}

const stepKeys = computed(() => {
  const keys = ['welcome', 'profile', 'club', 'email', 'findability']
  if (hasCalloutAccess.value) keys.push('phone')
  keys.push('features')
  return keys
})

const totalSteps = computed(() => stepKeys.value.length)
const currentKey = computed(() => stepKeys.value[step.value - 1])
const currentTitle = computed(() => STEP_TITLES[currentKey.value] || '')

// On the optional phone step the primary button reads "Skip" until the number is
// actually verified, then becomes "Continue".
const isSkip = computed(() => currentKey.value === 'phone' && !phoneVerifiedLocal.value)
const nextLabel = computed(() => {
  if (isSkip.value) return 'Skip'
  return step.value === totalSteps.value ? 'Get Started' : 'Continue'
})
const showNextArrow = computed(() => step.value < totalSteps.value && !isSkip.value)

const userName = ref('')
const nameValid = ref(false)
const nameForm = ref(null)

// Step 2 — profile photo & bio
const photoInput = ref(null)
const photoFile = ref(null)
const photoPreview = ref(null)
const existingPhoto = ref(null)
const bio = ref('')

// Step 4 — email preferences
const emailTrophies = ref(true)
const emailTagged = ref(true)
const emailPlatformNews = ref(true)

// Findability
const visibilityAddable = ref('public')

// Phone verification (optional, callout-access users only)
const phone = ref('')
const phoneVerifiedLocal = ref(false)
const showVerify = ref(false)
const savingPhone = ref(false)
const phoneError = ref('')

const PHONE_PATTERN = /^(07[0-9]{9}|\+44[0-9]{10})$/
const phoneRules = [
  v => !v || PHONE_PATTERN.test(v) || 'Must be 11 digits (07…) or 13 characters (+44…) long',
]

const nameRules = [
  v => !!v || 'Name is required',
  v => {
    const parts = (v || '').trim().split(/\s+/)
    if (parts.length < 2) return 'Please enter both your first and last name'
    for (const part of parts) {
      if (part.length < 2) return 'Each part of your name must be at least 2 characters'
    }
    return true
  },
  v => (v && v.length <= 100) || 'Name must be less than 100 characters',
]

const bioRules = [
  v => !v || v.length <= 500 || 'Bio must be less than 500 characters',
]

const clubSearch = ref('')
const allClubs = ref([])
const joiningClub = ref(null)
const joinedClubs = ref([])

// Guard so the form is seeded from the store only ONCE. The deep watch below re-runs
// checkOnboarding whenever store.user changes (e.g. saving the name or phone mid-wizard);
// without this guard those updates would reset the user's typed bio / toggled prefs back
// to the stored values before the final save, silently losing them.
const initialized = ref(false)

const checkOnboarding = () => {
  if (!store.user || !store.user.id || store.user.onboarding_completed_at) return
  visible.value = true
  if (initialized.value) return
  initialized.value = true

  userName.value = store.user.name || ''
  bio.value = store.user.bio || ''
  existingPhoto.value = store.user.photo || null
  // Preserve any preferences the user may already have (defaults match a fresh account)
  if (store.user.email_trophies !== undefined) emailTrophies.value = !!store.user.email_trophies
  if (store.user.email_tagged !== undefined) emailTagged.value = !!store.user.email_tagged
  if (store.user.email_platform_news !== undefined) emailPlatformNews.value = !!store.user.email_platform_news
  if (store.user.visibility_addable) visibilityAddable.value = store.user.visibility_addable
  phone.value = store.user.phone || ''
  phoneVerifiedLocal.value = !!store.user.phone_verified
  fetchClubs()
}

const startVerify = async () => {
  phoneError.value = ''
  // Validate the number before sending a code.
  const ruleError = phoneRules.map(r => r(phone.value)).find(r => r !== true)
  if (ruleError || !phone.value) return

  savingPhone.value = true
  try {
    // The verification endpoint texts a code to the user's *saved* number, so persist it
    // first. Changing the number also resets any prior verification server-side.
    await api.put('/api/users/me', { phone: phone.value })
    store.user.phone = phone.value
    phoneVerifiedLocal.value = false
    showVerify.value = true
  } catch (err) {
    // Surface server-side validation (e.g. "The phone has already been taken.") on the
    // field — the global interceptor stays silent on 422 so forms can handle it.
    phoneError.value = err.response?.data?.errors?.phone?.[0]
      || err.response?.data?.message
      || 'Could not save your phone number. Please try again.'
    console.error('Error saving phone number:', err)
  } finally {
    savingPhone.value = false
  }
}

const onVerified = () => {
  phoneVerifiedLocal.value = true
  store.user.phone_verified = true
}

onMounted(() => {
  checkOnboarding()
})

watch(() => store.user, () => {
  checkOnboarding()
}, { deep: true })

const triggerPhotoUpload = () => {
  photoInput.value?.click()
}

const onPhotoSelected = (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  photoFile.value = file
  photoPreview.value = URL.createObjectURL(file)
}

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

const nextStep = async () => {
  const key = currentKey.value
  if (key === 'welcome') {
    const { valid } = await nameForm.value.validate()
    if (!valid) return

    loading.value = true
    try {
      await api.put('/api/users/me', { name: userName.value })
      store.user.name = userName.value
      step.value++
    } catch (error) {
      console.error('Error updating name:', error)
    } finally {
      loading.value = false
    }
  } else if (key !== 'features') {
    step.value++
  } else {
    // Final step — persist profile + preferences and complete onboarding
    loading.value = true
    try {
      const now = new Date().toISOString()
      const formData = new FormData()
      formData.append('name', userName.value || '')
      formData.append('bio', bio.value || '')
      formData.append('email_trophies', emailTrophies.value ? '1' : '0')
      formData.append('email_tagged', emailTagged.value ? '1' : '0')
      formData.append('email_platform_news', emailPlatformNews.value ? '1' : '0')
      formData.append('visibility_addable', visibilityAddable.value || 'public')
      // The phone is persisted (and uniqueness-validated) via the phone step's "Send code"
      // action, not here — so a taken/invalid number can't silently fail this final save
      // and block onboarding.
      formData.append('onboarding_completed_at', now)
      formData.append('_method', 'PUT')
      if (photoFile.value) {
        formData.append('photo', photoFile.value)
      }
      await api.post('/api/users/me', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      await store.getUser(true) // Refresh user data to update clubs/photo status across the app
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

.onboarding-window {
  max-height: 64vh;
  overflow-y: auto;
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

.photo-avatar {
  position: relative;
  background: rgba(var(--v-theme-on-surface), 0.06);

  // Scale the chosen image to fill the avatar (cover), matching how it renders once
  // uploaded — a raw <img> would otherwise display at natural size and show only a crop.
  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .avatar-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    border-radius: 50%;
    opacity: 0;
    transition: opacity 0.2s;
  }

  &:hover .avatar-overlay {
    opacity: 1;
  }
}

.findability-toggle {
  width: 100%;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.22);
  border-radius: 10px;
  overflow: hidden;

  :deep(.v-btn) {
    flex: 1 1 0;
    height: 48px;
    border-radius: 0;
  }

  :deep(.v-btn:not(:last-child)) {
    border-right: 1px solid rgba(var(--v-theme-on-surface), 0.22);
  }

  :deep(.v-btn--active) {
    background: rgba(var(--v-theme-primary), 0.12);
    color: rgb(var(--v-theme-primary));
    font-weight: 600;
  }
}

.tour-item {
  // Each card carries its own accent via the --tour-accent custom property
  // (an "R, G, B" triple) so the feature previews read as distinct and pop.
  --tour-accent: var(--v-theme-on-surface);
  border-color: rgba(var(--tour-accent), 0.35) !important;
  border-left-width: 4px !important;
  transition: transform 0.2s;

  &:hover {
    transform: translateX(4px);
  }
}

.tour-preview {
  background: rgba(var(--tour-accent), 0.12);
  border-bottom: 1px solid rgba(var(--tour-accent), 0.2);
}

.mock-cave-row {
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.mock-bar {
  height: 6px;
  width: 60%;
  margin-top: 4px;
  border-radius: 3px;
  background: rgba(var(--v-theme-on-surface), 0.1);
}

.mock-map {
  height: 70px;
  background:
    linear-gradient(135deg, rgba(var(--v-theme-success), 0.18), rgba(var(--v-theme-info), 0.18)),
    repeating-linear-gradient(45deg, rgba(var(--v-theme-on-surface), 0.04) 0 8px, transparent 8px 16px);
}

.mock-callout {
  background: linear-gradient(135deg, rgb(var(--v-theme-warning)), rgb(var(--v-theme-error)));
}

.mock-collection,
.mock-permit {
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}
</style>
