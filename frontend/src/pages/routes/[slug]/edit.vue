<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn variant="text" size="small" :to="`/routes/${route.params.slug}`" class="mb-4">
          <v-icon start :icon="mdiArrowLeft" />
          Back to Route
        </v-btn>
        <h1 class="text-h4 mb-4">Edit Route</h1>
      </v-col>
    </v-row>

    <div v-if="loading" class="d-flex justify-center align-center py-12">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <v-row v-else-if="routeData">
      <v-col cols="12">
        <v-card>
          <v-card-text>
            <RouteForm 
              :cave-system-id="routeData.cave_system_id" 
              :initial-route="routeData"
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
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import RouteForm from '@/components/routes/RouteForm.vue'
import { useAppStore } from '@/stores/app'
import { api } from '@/plugins/api'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()

const loading = ref(true)
const routeData = ref(null)

const load = async () => {
  loading.value = true
  try {
    const response = await api.get(`/api/routes/${route.params.slug}`)
    routeData.value = response.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const onSaved = () => {
  router.push(`/routes/${route.params.slug}`)
}

onMounted(async () => {
  await load()

  // Simple client-side check, backend enforces properly
  if (!appStore.user.is_admin) {
    router.push('/')
  }
})
</script>
