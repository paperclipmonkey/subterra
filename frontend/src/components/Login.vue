<template>
  <v-container class="fill-height">
    <v-responsive
      class="align-centerfill-height mx-auto"
      max-width="350"
    >
      <v-img
        class="mb-4"
        height="250"
        src="@/assets/subterra.svg"
      />

      <div class="text-center">
        <h1>.world</h1>

        <h2> Your place for all things caving </h2>
      </div>

      <div class="py-4" />

      <v-row>
        <v-col cols="12">
          <v-card
            class="py-4"
            color="surface-variant"
            prepend-icon="mdi-earth"
            rounded="lg"
            variant="outlined"
          >
            <template #title>
              <h2 class="text-h5 font-weight-bold">Browse Caves</h2>
            </template>

            <template #subtitle>
              <div class="text-subtitle-1">
                to find your next adventure
              </div>
            </template>

            <v-overlay
              opacity=".12"
              scrim="primary"
              contained
              model-value
              persistent
            />
          </v-card>
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12">
          <v-card
            class="py-4"
            color="surface-variant"
            prepend-icon="mdi-notebook-outline"
            rounded="lg"
            variant="outlined"
          >
            <template #title>
              <h2 class="text-h5 font-weight-bold">Log Trips</h2>
            </template>

            <template #subtitle>
              <div class="text-subtitle-1">
                To keep a history
              </div>
            </template>

            <v-overlay
              opacity=".12"
              scrim="primary"
              contained
              model-value
              persistent
            />
          </v-card>
        </v-col>
      </v-row>
      <v-row>
        <v-col>
          
          <a href="/api/google/redirect" class="btn btn-primary"> 
            <img class="signin" src="/google-signin.svg"/>
          </a>
        </v-col>
      </v-row>
    </v-responsive>
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
  .signin {
    display: block;
    margin: 0 auto;
  }
</style>