<template>
  <v-container class="fill-height login-bg" fluid>
    <v-responsive
      class="align-center fill-height mx-auto"
      max-width="400"
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
import { onMounted } from 'vue'
import { useAppStore } from '@/stores/app'
import { useRouter } from 'vue-router'

const router = useRouter()
const store = useAppStore()

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
  border-radius: 6px;
  padding: 0.7rem 1.2rem;
  font-weight: 600;
  font-size: 1.1rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  transition: background 0.2s;
}
.login-btn:hover {
  background: #3367d6;
}
.signin {
  display: inline-block;
  margin-right: 0.5rem;
  height: 28px;
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