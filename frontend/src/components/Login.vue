<template>
  <v-container class="login-page pa-0" fluid>
    <v-row no-gutters class="login-hero-row">
      <!-- Left Column: Login Form -->
      <v-col ref="signInColumn" cols="12" md="6" lg="5" class="d-flex align-center justify-center bg-white fill-height position-relative">
        <div class="login-container pa-6 pa-md-10">
          <!-- Logo & Brand -->
          <div class="text-center mb-8">
            <v-img src="@/assets/subterra-logo.png" height="160" contain class="mb-4" />
            <h1 class="text-h3 font-weight-bold primary--text mb-2">Subterra</h1>
            <p class="text-subtitle-1 grey--text text--darken-1">The Community Caving Platform</p>
          </div>

          <!-- BCA Requirement Notice -->
          <v-alert color="amber darken-4" variant="tonal" :icon="mdiShieldAccount" class="mb-8" border="start"
                   density="comfortable">
            <div class="text-body-2 font-weight-medium">
              Member Access Only
            </div>
            <div class="text-caption mt-1">
              Subterra is exclusively available to active members of the <strong>British Caving Association
                (BCA)</strong>.
            </div>
          </v-alert>

          <!-- Login Methods -->
          <div class="mb-6">
            <!-- The agreement text lives OUTSIDE the checkbox's label slot. Nesting the
                 router-links inside the Vuetify <label> caused inconsistent toggling (the
                 native label-for click and the control's own handler could both fire),
                 leaving the model checked while the box appeared unticked. -->
            <div class="consent-box rounded-lg pa-3 mb-4"
                 :class="agreedToToS ? 'consent-box--agreed' : 'consent-box--required'">
              <div class="d-flex align-center">
                <v-checkbox
                  v-model="agreedToToS"
                  color="primary"
                  hide-details
                  density="compact"
                  class="flex-grow-0 me-1"
                  aria-label="I agree to the Terms of Service and Privacy Policy"
                />
                <div class="text-body-2">
                  <span class="agree-text" @click="agreedToToS = !agreedToToS">I agree to the</span>
                  <router-link to="/pages/terms-of-service" class="text-decoration-none font-weight-bold">
                    Terms of Service
                  </router-link>
                  and
                  <router-link to="/pages/privacy-policy" class="text-decoration-none font-weight-bold">
                    Privacy Policy
                  </router-link>
                </div>
              </div>
              <div class="text-caption mt-1 ms-1" :class="agreedToToS ? 'text-success' : 'text-medium-emphasis'">
                <v-icon size="14" :icon="agreedToToS ? mdiCheckCircle : mdiInformationOutline" class="me-1" />
                Required before you can sign in with Google or a magic link.
              </div>
            </div>

            <v-btn href="/api/google/redirect" block size="large" color="white" class="google-btn text-none mb-6"
                   elevation="2" :disabled="!agreedToToS">
              <img src="/google-signin.svg" height="24" class="mr-3">
              Sign in with Google
            </v-btn>

            <div class="d-flex align-center mb-6">
              <v-divider />
              <span class="mx-4 text-caption grey--text">OR</span>
              <v-divider />
            </div>

            <!-- Email Login -->
            <v-card v-if="!emailSent" elevation="0" class="transparent">
              <v-alert v-if="showError" type="error" variant="tonal" class="mb-4" closable density="compact"
                       @click:close="showError = false">
                {{ errorMessage }}
              </v-alert>

              <v-form ref="emailForm" @submit.prevent="sendMagicLink">
                <v-text-field v-model="email" label="Email Address" type="email" :rules="emailRules" variant="outlined"
                              density="comfortable" :prepend-inner-icon="mdiEmailOutline" class="mb-2"
                              hide-details="auto" />
                <v-btn type="submit" color="primary" block size="large" :loading="sendingEmail"
                       :disabled="!email || sendingEmail || !agreedToToS" class="mt-4 text-none font-weight-bold" elevation="0">
                  Send Verification Link
                </v-btn>
              </v-form>
              <div class="text-center mt-3">
                <span v-if="!agreedToToS" class="text-caption text-medium-emphasis">
                  Agree to the terms above to receive your magic link.
                </span>
                <span v-else class="text-caption grey--text">We'll send you a tailored magic link to log in.</span>
              </div>
            </v-card>

            <!-- Email Sent Success -->
            <v-card v-else class="text-center py-4 bg-green-lighten-5" variant="flat">
              <v-icon color="success" size="48" class="mb-2" :icon="mdiEmailCheck" />
              <h3 class="text-h6 font-weight-bold success--text mb-1">Check your inbox</h3>
              <p class="text-body-2 mb-4">
                We've sent a magic link to <strong>{{ email }}</strong>
              </p>
              <v-btn variant="text" color="primary" size="small" :prepend-icon="mdiArrowLeft" @click="resetForm">
                Try a different email
              </v-btn>
            </v-card>
          </div>

          <!-- Footer -->
          <div class="text-center mt-auto pt-8">
            <div class="d-flex justify-center gap-4 mb-2">
              <router-link to="/pages/terms-of-service" class="text-caption text-decoration-none grey--text">Terms</router-link>
              <span class="text-caption grey--text">•</span>
              <router-link to="/pages/privacy-policy" class="text-caption text-decoration-none grey--text">Privacy</router-link>
              <span class="text-caption grey--text">•</span>
              <a href="https://status.subterra.world/" target="_blank" rel="noopener" class="text-caption text-decoration-none grey--text">Status</a>
            </div>
            <div class="text-caption grey--text text--lighten-1">
              Subterra is <a href="https://github.com/paperclipmonkey/subterra"
                             class="text-decoration-none primary--text">Open Source</a>. Go underground with a plan.
            </div>
          </div>
        </div>
      </v-col>

      <!-- Right Column: Hero Image & Features -->
      <v-col cols="12" md="6" lg="7" class="d-none d-md-flex position-relative align-end pa-10 overflow-hidden">
        <!-- Background Slideshow -->
        <div class="hero-slideshow">
          <div v-for="(image, index) in heroImages" :key="index" class="hero-slide"
               :style="{ backgroundImage: `url(${image})` }" :class="{ active: currentHeroIndex === index }" />
        </div>

        <!-- Background Overlay -->
        <div class="hero-overlay" />

        <!-- Hero Content -->
        <div class="hero-content position-relative z-index-2 text-white mw-600">
          <h2 class="text-h2 font-weight-black mb-4 text-shadow">
            Explore the<br>Depths Together
          </h2>
          <p class="text-h6 font-weight-regular opacity-80 text-shadow">
            The UK's shared record of caves, trips and the people who explore them —
            with the safety tools to get you back out again.
          </p>
        </div>
      </v-col>
    </v-row>

    <!-- What Subterra actually does. Shown to everyone, before signing up:
         visitors shouldn't have to create an account to find out what the
         platform is for. -->
    <v-row no-gutters class="features-section">
      <v-col cols="12">
        <div class="features-inner mx-auto px-4 px-md-8 py-10 py-md-14">
          <div class="text-center mb-8 mb-md-10">
            <h2 class="text-h4 text-md-h3 font-weight-bold mb-3">What you can do on Subterra</h2>
            <p class="text-body-1 text-medium-emphasis mw-720 mx-auto">
              A logbook, a cave database and a safety net in one place. Some features open up
              once your caving club confirms you're one of their members.
            </p>
          </div>

          <v-row>
            <v-col v-for="feature in features" :key="feature.title" cols="12" sm="6" lg="4">
              <div class="feature-card h-100 pa-5 rounded-lg" :style="{ '--feature-accent': feature.accent }">
                <v-avatar size="44" rounded="lg" class="feature-card__icon mb-4">
                  <v-icon :icon="feature.icon" size="24" />
                </v-avatar>
                <h3 class="text-subtitle-1 font-weight-bold mb-1">{{ feature.title }}</h3>
                <p class="text-body-2 text-medium-emphasis mb-0">{{ feature.description }}</p>
                <div v-if="feature.requiresClub" class="text-caption mt-3 feature-card__lock d-flex align-center">
                  <v-icon :icon="mdiLockOutline" size="14" class="mr-1" />
                  Unlocks once your club confirms you
                </div>
              </div>
            </v-col>
          </v-row>

          <div class="text-center mt-8">
            <p class="text-body-2 text-medium-emphasis mb-2">
              Subterra is free and open source, run by cavers for the caving community.
            </p>
            <v-btn variant="tonal" color="primary" class="text-none" @click="scrollToSignIn">
              Sign in or join
            </v-btn>
          </div>
        </div>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { mdiAlertOctagram, mdiArrowLeft, mdiCheckCircle, mdiEmailCheck, mdiEmailOutline, mdiFormatListChecks, mdiImageFilterHdr, mdiInformationOutline, mdiLockOutline, mdiMedalOutline, mdiNotebookOutline, mdiShieldAccount, mdiTicketConfirmationOutline } from '@mdi/js'
import { onMounted, onUnmounted, ref } from 'vue'
import { useAppStore } from '@/stores/app'
import { useRouter } from 'vue-router'
import { api } from '@/plugins/api'

const router = useRouter()
const store = useAppStore()

// The feature tour that used to sit at the *end* of onboarding, where it only
// reached people who had already signed up and committed to the platform.
const features = [
  {
    title: 'Trips & logbook',
    description: 'Log every trip, or just tap a cave as done. Your caving history in one place, with photos and reports.',
    icon: mdiNotebookOutline,
    accent: '33, 150, 243',
  },
  {
    title: 'Cave details & maps',
    description: 'Descriptions, history, photos and locations for caves across the UK, kept up to date by cavers.',
    icon: mdiImageFilterHdr,
    accent: '67, 160, 71',
  },
  {
    title: 'Collections',
    description: 'Curated lists of caves to work through — the classic Yorkshire trips and more — with your progress ticked off.',
    icon: mdiFormatListChecks,
    accent: '94, 53, 177',
  },
  {
    title: 'Access permits',
    description: 'Some caves need a permit. Check availability and book a date; the access officers are notified to approve it.',
    icon: mdiTicketConfirmationOutline,
    accent: '0, 150, 136',
    requiresClub: true,
  },
  {
    title: 'Safety callouts',
    description: 'Set a deadline and contacts before you go underground. If you are overdue, your contacts are alerted automatically.',
    icon: mdiAlertOctagram,
    accent: '251, 140, 0',
    requiresClub: true,
  },
  {
    title: 'Medals & stats',
    description: 'Earn medals and track caves visited, trips logged and hours underground as your logbook grows.',
    icon: mdiMedalOutline,
    accent: '142, 36, 170',
  },
]

const signInColumn = ref(null)

const scrollToSignIn = () => {
  const el = signInColumn.value?.$el ?? signInColumn.value
  el?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

// Hero Slideshow
const currentHeroIndex = ref(0)
const heroImages = ref([
  'login-images/1.webp',
  'login-images/2.webp',
  'login-images/3.webp',
  'login-images/4.webp',
  'login-images/5.webp',
  'login-images/6.webp',
  'login-images/7.webp',
  'login-images/8.webp',
  'login-images/9.webp',
  'login-images/10.webp',
  'login-images/11.webp',
].sort(() => Math.random() - 0.5))
let slideshowInterval = null

// Email login state
const email = ref('')
const emailSent = ref(false)
const sendingEmail = ref(false)
const emailForm = ref(null)
const errorMessage = ref('')
const showError = ref(false)
const agreedToToS = ref(false)

// Email validation rules
const emailRules = [
  v => !!v || 'Email is required',
  v => /.+@.+\..+/.test(v) || 'Email must be valid'
]

const sendMagicLink = async () => {
  // Validate form first
  const { valid } = await emailForm.value.validate()
  if (!valid) return

  sendingEmail.value = true
  showError.value = false
  errorMessage.value = ''

  try {
    await api.post('/api/auth/magic-link', {
      email: email.value,
      agreed_to_tos: agreedToToS.value
    })
    emailSent.value = true
  } catch (error) {
    console.error('Failed to send magic link:', error)

    // Show user-friendly error message
    errorMessage.value = error.response?.data?.message || 'Failed to send magic link. Please try again.'
    showError.value = true
  } finally {
    sendingEmail.value = false
  }
}

const resetForm = () => {
  email.value = ''
  emailSent.value = false
  sendingEmail.value = false
  errorMessage.value = ''
  showError.value = false
  if (emailForm.value) {
    emailForm.value.resetValidation()
  }
}

onMounted(async () => {
  // Start slideshow
  slideshowInterval = setInterval(() => {
    currentHeroIndex.value = (currentHeroIndex.value + 1) % heroImages.value.length
  }, 5000)

  // Load the user endpoint to check if the user is logged in.
  // Being unauthenticated here is the expected case on the login page, so suppress
  // the global error toast (otherwise the 401 surfaces a "Session expired" message).
  await api.get('/api/livez', { suppressErrorNotification: true }) // Warm the database
  try {
    const userResponse = await api.get('/api/users/me', { suppressErrorNotification: true })
    const userData = userResponse.data
    if (userData && userData.data && userData.data.email) {
      const redirect = sessionStorage.getItem('redirectAfterLogin')
      sessionStorage.removeItem('redirectAfterLogin')
      router.push(redirect || '/trips')
    }
  } catch (e) {
    // Not logged in — stay on login page
  }
})

onUnmounted(() => {
  if (slideshowInterval) clearInterval(slideshowInterval)
})
</script>

<style scoped>
/* The page now has content below the fold, so it stacks its rows rather than
   being a single viewport-height flex row (Vuetify's .fill-height container
   centres and lays its children out in a row, which put the two side by side). */
.login-page.v-container {
  display: block;
}

.login-hero-row {
  min-height: 100vh;
}

.login-container {
  width: 100%;
  max-width: 480px;
}

.agree-text {
  cursor: pointer;
  user-select: none;
}

.consent-box {
  border: 1px solid transparent;
  transition: background-color 0.2s, border-color 0.2s;
}

.consent-box--required {
  background-color: rgba(var(--v-theme-warning), 0.08);
  border-color: rgba(var(--v-theme-warning), 0.4);
}

.consent-box--agreed {
  background-color: rgba(var(--v-theme-success), 0.08);
  border-color: rgba(var(--v-theme-success), 0.35);
}

.google-btn {
  border: 1px solid #ddd;
  transition: all 0.2s;
}

.google-btn:hover {
  background-color: #f8f9fa !important;
  border-color: #ccc;
  transform: translateY(-1px);
}

/* Removed .hero-column background-image */

.hero-slideshow {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  overflow: hidden;
  background-color: #1a1a1a;
  /* Fix for transparency artifacts */
}

.hero-slide {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-size: cover;
  background-position: center;
  opacity: 0;
  transition: opacity 1.5s ease-in-out, transform 10s linear;
  /* Smooth reset */
  transform: scale(1.05);
  will-change: opacity, transform;
  backface-visibility: hidden;
}

.hero-slide.active {
  opacity: 1;
  transform: scale(1);
  transition: opacity 1.5s ease-in-out, transform 6s linear;
}

.hero-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0.4) 60%, rgba(0, 0, 0, 0.2) 100%);
  z-index: 1;
}

.z-index-2 {
  /* Changed from z-index-1 to z-index-2 for content */
  z-index: 2;
}

.text-shadow {
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
}

.opacity-80 {
  opacity: 0.9;
}

.bg-opacity-20 {
  background-color: rgba(255, 255, 255, 0.15) !important;
}

.mw-600 {
  max-width: 600px;
}

.mw-720 {
  max-width: 720px;
}

/* Feature introduction below the fold */
.features-section {
  background-color: rgb(var(--v-theme-background));
  border-top: 1px solid rgba(24, 38, 31, 0.08);
}

.features-inner {
  max-width: 1180px;
}

/* Informational, not interactive — no hover lift or pointer cursor. The
   equivalent cards in the onboarding wizard read as clickable and weren't,
   which is exactly what we're avoiding here. */
.feature-card {
  background-color: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--feature-accent), 0.28);
  border-left-width: 4px;
}

.feature-card__icon {
  background-color: rgba(var(--feature-accent), 0.14) !important;
  color: rgb(var(--feature-accent));
}

.feature-card__lock {
  color: rgba(var(--v-theme-on-surface), 0.6);
}
</style>