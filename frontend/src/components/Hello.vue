<template>
  <v-container class="fill-height">
    <v-responsive
      class="align-centerfill-height mx-auto"
      max-width="350"
    >
      <v-img
        class="mb-4"
        height="150"
        src="@/assets/logo.png"
      />

      <div class="text-center">
        <div class="text-body-2 font-weight-light mb-n1">Welcome to</div>

        <h1 class="text-h2 font-weight-bold">Subterra</h1>
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
              <h2 class="text-h5 font-weight-bold">Caves</h2>
            </template>

            <template #subtitle>
              <div class="text-subtitle-1">
                Browse caves and cave systems
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
              <h2 class="text-h5 font-weight-bold">Trips</h2>
            </template>

            <template #subtitle>
              <div class="text-subtitle-1">
                Log your trips and view public trips
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
          <a href="/api/google/redirect" class="btn btn-primary"> Login with Google </a>
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

  onMounted(() => {
    if(store.user.email) {
      console.log('User is logged in')
      router.push({ name: '/trips' })
    } else {
      console.log('User is not logged in')
    }
  })
</script>
