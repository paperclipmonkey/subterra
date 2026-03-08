<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.go(-1)">
          <v-icon :icon="mdiArrowLeft" />
        </v-btn>
        <v-toolbar-title>Suggest Edit for {{ originalSystem.name }}</v-toolbar-title>
      </v-col>
    </v-row>
    <v-form ref="form" @submit.prevent="submitSuggestion">
      <v-row>
        <v-col cols="12">
          <v-alert type="info" class="mb-4">
            Thank you for helping improve our data! Your suggestions will be reviewed by an admin.
          </v-alert>
          <!-- Assuming CaveSystemForm exists or similar logic -->
          <CaveSystemForm v-model="system" />
        </v-col>
      </v-row>

      <v-row>
        <v-col>
          <v-card-text>
            <v-btn type="submit" color="primary" block size="large" :loading="loading">Submit Suggestion</v-btn>
          </v-card-text>
        </v-col>
      </v-row>
    </v-form>

    <v-snackbar v-model="successSnackbar" color="success">
      Suggestion submitted! Redirecting...
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { mdiArrowLeft } from '@mdi/js'

import { ref, onMounted } from "vue"
import { useRoute, useRouter } from "vue-router"
import CaveSystemForm from '@/components/CaveSystemForm.vue'
import { api } from '@/plugins/api.js'

const router = useRouter()
const route = useRoute()
const form = ref(null)
const loading = ref(false)
const successSnackbar = ref(false)

const originalSystem = ref({})
const system = ref({
    name: '',
    description: '',
    length: 0,
    vertical_range: 0,
    // ... other fields
})

const fetchSystem = async () => {
    const response = await api.get(`/api/cave_systems/${route.params.id}`)
    originalSystem.value = JSON.parse(JSON.stringify(response.data.data))
    system.value = JSON.parse(JSON.stringify(response.data.data))
}

const submitSuggestion = async () => {
    const { valid } = await form.value.validate()
    if (!valid) return

    loading.value = true
    try {
        await api.post('/api/suggested-edits', {
            suggestable_type: 'cave_system', // or App\Models\CaveSystem
            suggestable_id: originalSystem.value.id,
            original_data: originalSystem.value,
            suggested_data: system.value
        })

        successSnackbar.value = true
        setTimeout(() => {
            router.back()
        }, 1500)

    } catch (error) {
        console.error(error)
    } finally {
        loading.value = false
    }
}

onMounted(fetchSystem)
</script>
