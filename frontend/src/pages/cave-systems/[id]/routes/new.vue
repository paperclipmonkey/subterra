<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn variant="text" size="small" :to="`/cave-systems/${route.params.id}`" class="mb-4">
          <v-icon start :icon="mdiArrowLeft" />
          Back to Cave System
        </v-btn>
        <h1 class="text-h4 mb-4">Add New Route</h1>
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-text>
            <RouteForm 
              :cave-system-id="route.params.id" 
              @saved="onSaved"
            />
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { mdiArrowLeft } from '@mdi/js'
import { useRoute, useRouter } from 'vue-router'
import RouteForm from '@/components/routes/RouteForm.vue'

const route = useRoute()
const router = useRouter()

const onSaved = () => {
    // Redirect back to cave system page
    router.push(`/cave-systems/${route.params.id}`)
}
</script>
