<template>
  <v-fade-transition>
    <div v-if="showBanner" class="cookie-banner-container">
      <v-card class="cookie-banner pa-4 pa-md-6 rounded-xl elevation-10" border>
        <div class="d-flex flex-column flex-sm-row align-center gap-4">
          <v-avatar color="primary-lighten-5" size="48" class="flex-shrink-0">
            <v-icon color="primary" icon="mdi-cookie-outline" size="28"></v-icon>
          </v-avatar>
          
          <div class="flex-grow-1 text-center text-sm-left">
            <h4 class="text-subtitle-1 font-weight-bold mb-1">Cookies & Privacy</h4>
            <p class="text-body-2 text-medium-emphasis mb-0">
              We use essential cookies for authentication and safety features. 
              Find out more in our 
              <router-link to="/pages/privacy-policy" class="text-primary font-weight-bold text-decoration-none">
                Privacy Policy
              </router-link>.
            </p>
          </div>

          <v-btn color="primary" variant="flat" rounded="lg" class="px-6" @click="acceptCookies">
            Got it
          </v-btn>
        </div>
      </v-card>
    </div>
  </v-fade-transition>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const showBanner = ref(false)

onMounted(() => {
    const isAccepted = localStorage.getItem('subterra_cookies_accepted')
    if (!isAccepted) {
        // Delay slightly for better UX
        setTimeout(() => {
            showBanner.value = true
        }, 1000)
    }
})

const acceptCookies = () => {
    localStorage.setItem('subterra_cookies_accepted', 'true')
    showBanner.value = false
}
</script>

<style scoped>
.cookie-banner-container {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 16px;
    z-index: 9999;
    display: flex;
    justify-content: center;
}

.cookie-banner {
    width: 100%;
    max-width: 800px;
    background-color: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
}

.gap-4 {
    gap: 16px;
}

@media (max-width: 600px) {
    .cookie-banner-container {
        padding: 12px;
    }
}
</style>
