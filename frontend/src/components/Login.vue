<template>
  <v-container class="fill-height login-bg" fluid>
    <v-responsive
      class="align-center fill-height"
    >
      <v-img
        class="mb-4"
        height="180"
        src="@/assets/subterra.svg"
      />
      <div class="text-center mb-2">
        <h1 class="main-title">Subterra.world</h1>
        <p class="welcome">Welcome to Subterra – the platform for cavers to plan trips, share experiences, and explore the underground world together.</p>
      </div>
      <div class="py-2" />
      <v-row>
        <v-col>
          <a href="/api/google/redirect" class="btn btn-primary login-btn">
            <img class="signin" src="/google-signin.svg"/>
          </a>
        </v-col>
      </v-row>
      
      <div class="py-4" />
      
      <v-row justify="center">
        <v-col cols="12" class="text-center">
          <v-divider class="my-4">
            <span class="divider-text">or</span>
          </v-divider>
        </v-col>
      </v-row>
      
      <!-- Magic Link Login Form -->
      <v-row v-if="!emailSent" justify="center">
        <v-col cols="12" md="8" lg="6">
          <v-card class="email-login-card" elevation="0">
            <v-card-title class="email-login-title">
              🔐 Login with Email
            </v-card-title>
            <v-card-text>
              <!-- Error Alert -->
              <v-alert
                v-if="showError"
                type="error"
                variant="tonal"
                class="mb-4"
                closable
                @click:close="showError = false"
              >
                {{ errorMessage }}
              </v-alert>
              
              <v-form @submit.prevent="sendMagicLink" ref="emailForm">
                <v-text-field
                  v-model="email"
                  label="Enter your email address"
                  type="email"
                  :rules="emailRules"
                  variant="outlined"
                  prepend-inner-icon="mdi-email"
                  class="mb-4 custom-text-field"
                  :loading="sendingEmail"
                  color="primary"
                  bg-color="white"
                />
                <v-btn
                  type="submit"
                  color="primary"
                  block
                  size="large"
                  :loading="sendingEmail"
                  :disabled="!email || sendingEmail"
                  class="custom-submit-btn"
                  elevation="2"
                >
                  <v-icon left class="mr-2">mdi-send</v-icon>
                  Send Magic Link
                </v-btn>
              </v-form>
              <div class="text-center mt-4">
                <small class="form-helper-text">
                  We'll send you a secure link to log in instantly
                </small>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
      
      <!-- Email Sent Confirmation -->
      <v-row v-if="emailSent" justify="center">
        <v-col cols="12" md="8" lg="6">
          <v-card class="email-sent-card" elevation="0">
            <v-card-text class="text-center">
              <v-icon size="80" color="success" class="mb-4">mdi-email-check-outline</v-icon>
              <h3 class="email-sent-title">✨ Check Your Email</h3>
              <p class="email-sent-text">
                We've sent a magic link to <br><strong class="email-highlight">{{ email }}</strong><br>
                Click the link in your email to log in securely.
              </p>
              <v-btn 
                variant="outlined" 
                color="primary" 
                @click="resetForm"
                class="mt-6 custom-reset-btn"
                prepend-icon="mdi-arrow-left"
              >
                Send to a different email
              </v-btn>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
      
      <div class="py-2" />
      <v-row class="features-row" justify="center">
        <v-col cols="12" class="text-center mb-2">
          <h3 class="features-title">What can you do on Subterra?</h3>
        </v-col>
        <v-col cols="6" sm="3" class="feature-col">
          <div class="feature-icon-placeholder">🗺️</div>
          <div class="feature-label">Explore caves in a region</div>
        </v-col>
        <v-col cols="6" sm="3" class="feature-col">
          <div class="feature-icon-placeholder">🕒</div>
          <div class="feature-label">Check recent trips to a cave</div>
        </v-col>
        <v-col cols="6" sm="3" class="feature-col">
          <div class="feature-icon-placeholder">📓</div>
          <div class="feature-label">Save your caving log book online</div>
        </v-col>
        <v-col cols="6" sm="3" class="feature-col">
          <div class="feature-icon-placeholder">📢</div>
          <div class="feature-label">Share trip reports with others</div>
        </v-col>
      </v-row>
      <div class="text-center mt-4">
        <small class="info-text">
          Subterra is a community-driven platform for caving enthusiasts. Share, discover, and connect!<br>
          <span class="opensource-note">
            Subterra is <strong>open source</strong> — view or contribute on
            <a href="https://github.com/paperclipmonkey/subterra" target="_blank" rel="noopener" class="github-link">GitHub</a>.
          </span>
        </small>
      </div>
    </v-responsive>
    <!-- <v-img class="cave-graphic" src="/cave-entrance.svg" height="120" contain /> -->
  </v-container>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useAppStore } from '@/stores/app'
import { useRouter } from 'vue-router'

const router = useRouter()
const store = useAppStore()

// Email login state
const email = ref('')
const emailSent = ref(false)
const sendingEmail = ref(false)
const emailForm = ref(null)
const errorMessage = ref('')
const showError = ref(false)

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
      body: JSON.stringify({ email: email.value })
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
  // Load the user endpoint to check if the user is logged in
  await fetch('/api/livez') // Warm the database
  const userResonse = await fetch('/api/users/me')
  const userEmail = (await userResonse.json()).data.email
  if(userEmail) {
    console.log('User is logged in')
    router.push({ name: '/trips' })
  } else {
    console.log('User is not logged in')
  }
})
</script>

<style scoped>
.login-bg {
  background: linear-gradient(180deg, #232526 0%, #414345 100%);
  min-height: 100vh;
  position: relative;
}
.main-title {
  font-family: 'Montserrat', sans-serif;
  font-size: 2.2rem;
  color: #fff;
  letter-spacing: 1px;
}
.subtitle {
  color: #b2bec3;
  font-size: 1.1rem;
}
.welcome {
  color: #dfe6e9;
  font-size: 1rem;
  margin-top: 0.5rem;
}
.login-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  background: #4285f4;
  color: #fff;
  border-radius: 12px;
  padding: 1rem 1.5rem;
  font-weight: 600;
  font-size: 1.1rem;
  box-shadow: 0 4px 16px rgba(66, 133, 244, 0.3);
  transition: all 0.3s ease;
  text-decoration: none;
  border: none;
}
.login-btn:hover {
  background: #3367d6;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(66, 133, 244, 0.4);
}
.signin {
  display: inline-block;
  margin-right: 0.5rem;
  height: 28px;
}

/* Divider styles */
.divider-text {
  background: linear-gradient(180deg, #232526 0%, #414345 100%);
  color: #ffffff;
  padding: 0 1.5rem;
  font-size: 1rem;
  font-weight: 600;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

/* Email login card styles */
.email-login-card {
  background: rgba(255, 255, 255, 0.98);
  backdrop-filter: blur(10px);
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
}
.email-login-title {
  color: #2d3436;
  font-weight: 700;
  font-size: 1.4rem;
  padding-bottom: 1rem;
  text-align: center;
}

/* Email sent confirmation styles */
.email-sent-card {
  background: rgba(255, 255, 255, 0.98);
  backdrop-filter: blur(10px);
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
}
.email-sent-title {
  color: #00b894;
  font-weight: 700;
  margin-bottom: 1rem;
  font-size: 1.5rem;
}
.email-sent-text {
  color: #2d3436;
  font-size: 1.1rem;
  line-height: 1.6;
  margin-bottom: 0;
}

/* Enhanced form styling */
.custom-text-field {
  border-radius: 12px;
}

.custom-submit-btn {
  background: linear-gradient(135deg, #4285f4 0%, #3367d6 100%);
  color: white;
  border-radius: 12px;
  font-weight: 600;
  text-transform: none;
  letter-spacing: 0.5px;
  box-shadow: 0 4px 16px rgba(66, 133, 244, 0.3);
  transition: all 0.3s ease;
}

.custom-submit-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(66, 133, 244, 0.4);
}

.custom-reset-btn {
  border-radius: 12px;
  font-weight: 500;
  text-transform: none;
  border: 2px solid #4285f4;
}

.form-helper-text {
  color: #636e72;
  font-style: italic;
}

.email-highlight {
  color: #4285f4;
  font-size: 1.2rem;
}

.features-row {
  margin-top: 2rem;
}
.features-title {
  color: #fff;
  font-size: 1.2rem;
  font-weight: 600;
  letter-spacing: 0.5px;
}
.feature-col {
  text-align: center;
  margin-bottom: 1rem;
}
.feature-icon-placeholder {
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 60px;
}
.feature-label {
  color: #dfe6e9;
  font-size: 0.98rem;
  font-weight: 500;
}
.info-text {
  color: #b2bec3;
  font-size: 0.95rem;
}
.opensource-note {
  display: block;
  margin-top: 0.5em;
  color: #b2bec3;
  font-size: 0.97rem;
}
.github-link {
  color: #fff;
  text-decoration: underline;
  font-weight: 500;
  transition: color 0.2s;
}
.github-link:hover {
  color: #4285f4;
}
.cave-graphic {
  position: absolute;
  left: 50%;
  bottom: 0;
  transform: translateX(-50%);
  opacity: 0.8;
  pointer-events: none;
}
</style>