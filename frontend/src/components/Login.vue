<template>
  <v-container class="fill-height pa-0" fluid>
    <v-row no-gutters class="fill-height">
      <!-- Left Column: Login Form -->
      <v-col cols="12" md="6" lg="5" class="d-flex align-center justify-center bg-white fill-height position-relative">
        <div class="login-container pa-6 pa-md-10">
          <!-- Logo & Brand -->
          <div class="text-center mb-8">
            <v-img src="@/assets/subterra-logo.png" height="160" contain class="mb-4" />
            <h1 class="text-h3 font-weight-bold primary--text mb-2">Subterra</h1>
            <p class="text-subtitle-1 grey--text text--darken-1">The Community Caving Platform</p>
          </div>

          <!-- BCA Requirement Notice -->
          <v-alert color="amber darken-4" variant="tonal" icon="mdi-shield-account" class="mb-8" border="start"
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
            <v-checkbox v-model="agreedToToS" color="primary" hide-details class="mb-4">
              <template #label>
                <div class="text-body-2">
                  I agree to the 
                  <router-link to="/pages/terms-of-service" class="text-decoration-none font-weight-bold" @click.stop>
                    Terms of Service
                  </router-link>
                  and
                  <router-link to="/pages/privacy-policy" class="text-decoration-none font-weight-bold" @click.stop>
                    Privacy Policy
                  </router-link>
                </div>
              </template>
            </v-checkbox>

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
                              density="comfortable" prepend-inner-icon="mdi-email-outline" class="mb-2"
                              hide-details="auto" />
                <v-btn type="submit" color="primary" block size="large" :loading="sendingEmail"
                       :disabled="!email || sendingEmail || !agreedToToS" class="mt-4 text-none font-weight-bold" elevation="0">
                  Send Verification Link
                </v-btn>
              </v-form>
              <div class="text-center mt-3">
                <span class="text-caption grey--text">We'll send you a tailored magic link to log in.</span>
              </div>
            </v-card>

            <!-- Email Sent Success -->
            <v-card v-else class="text-center py-4 bg-green-lighten-5" variant="flat">
              <v-icon color="success" size="48" class="mb-2">mdi-email-check</v-icon>
              <h3 class="text-h6 font-weight-bold success--text mb-1">Check your inbox</h3>
              <p class="text-body-2 mb-4">
                We've sent a magic link to <strong>{{ email }}</strong>
              </p>
              <v-btn variant="text" color="primary" size="small" prepend-icon="mdi-arrow-left" @click="resetForm">
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
                             class="text-decoration-none primary--text">Open Source</a>. Only go underground with a plan.
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
          <h2 class="text-h2 font-weight-black mb-6 text-shadow">
            Explore the<br>Depths Together
          </h2>

          <v-row class="features-grid">
            <v-col cols="12" sm="6" class="mb-4">
              <div class="d-flex align-start">
                <v-avatar color="white" size="40" class="mr-3 bg-opacity-20">
                  <v-icon color="white">mdi-map-search</v-icon>
                </v-avatar>
                <div>
                  <div class="font-weight-bold text-subtitle-1">Discover Caves</div>
                  <div class="text-body-2 opacity-80">Access detailed cave data, surveys, and conditions across the UK.
                  </div>
                </div>
              </div>
            </v-col>
            <v-col cols="12" sm="6" class="mb-4">
              <div class="d-flex align-start">
                <v-avatar color="white" size="40" class="mr-3 bg-opacity-20">
                  <v-icon color="white">mdi-notebook-check</v-icon>
                </v-avatar>
                <div>
                  <div class="font-weight-bold text-subtitle-1">Plan & Track</div>
                  <div class="text-body-2 opacity-80">Organize trips and utilize our live callout tracking for safer
                    expeditions.</div>
                </div>
              </div>
            </v-col>
            <v-col cols="12" sm="6" class="mb-4">
              <div class="d-flex align-start">
                <v-avatar color="white" size="40" class="mr-3 bg-opacity-20">
                  <v-icon color="white">mdi-account-group</v-icon>
                </v-avatar>
                <div>
                  <div class="font-weight-bold text-subtitle-1">Community Logbook</div>
                  <div class="text-body-2 opacity-80">Share trip reports, photos, and contribute to the national record.
                  </div>
                </div>
              </div>
            </v-col>
          </v-row>
        </div>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { useAppStore } from '@/stores/app'
import { useRouter } from 'vue-router'

const router = useRouter()
const store = useAppStore()

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
    const response = await fetch('/api/auth/magic-link', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        email: email.value,
        agreed_to_tos: agreedToToS.value
      })
    })

    if (response.ok) {
      emailSent.value = true
    } else {
      const error = await response.json()
      console.error('Failed to send magic link:', error)

      // Show user-friendly error message
      errorMessage.value = error.message || 'Failed to send magic link. Please try again.'
      showError.value = true
    }
  } catch (error) {
    console.error('Error sending magic link:', error)
    errorMessage.value = 'Network error. Please check your connection and try again.'
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

  // Load the user endpoint to check if the user is logged in
  await fetch('/api/livez') // Warm the database
  const userResponse = await fetch('/api/users/me')
  try {
    const userData = await userResponse.json()
    if (userData && userData.data && userData.data.email) {
      console.log('User is logged in')
      router.push('/trips')
    }
  } catch (e) {
    console.log('User is not logged in')
  }
})

onUnmounted(() => {
  if (slideshowInterval) clearInterval(slideshowInterval)
})
</script>

<style scoped>
.login-container {
  width: 100%;
  max-width: 480px;
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
</style>