<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.go(-1)">
          <v-icon>mdi-arrow-left</v-icon>
        </v-btn>
        <v-toolbar-title>Suggest Edit for {{ originalRoute.name }}</v-toolbar-title>
      </v-col>
    </v-row>
    
    <RouteForm 
      v-if="originalRoute.id" 
      :initial-route="routeData" 
      :cave-system-id="originalRoute.cave_system_id"
      :prevent-submit="true"
      @submit="handleFormSubmit"
    />

    <v-snackbar v-model="successSnackbar" color="success">
      Suggestion submitted! Redirecting...
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from "vue"
import { useRoute, useRouter } from "vue-router"
import RouteForm from '@/components/routes/RouteForm.vue'
import { api } from '@/plugins/api.js'

const router = useRouter()
const route = useRoute()
const successSnackbar = ref(false)

const originalRoute = ref({})
const routeData = ref({})

const fetchRoute = async () => {
  const response = await api.get(`/api/routes/${route.params.id}`)
  const data = response.data.data
  originalRoute.value = JSON.parse(JSON.stringify(data))
  routeData.value = JSON.parse(JSON.stringify(data))
}

const handleFormSubmit = async (formData) => {
  try {
    await api.post('/api/suggested-edits', {
      suggestable_type: 'route',
      suggestable_id: originalRoute.value.id,
      original_data: originalRoute.value,
      suggested_data: formData
    })
    successSnackbar.value = true
    setTimeout(() => {
      router.back()
    }, 1500)
  } catch (error) {
    console.error(error)
  }
}

onMounted(fetchRoute)
</script>
