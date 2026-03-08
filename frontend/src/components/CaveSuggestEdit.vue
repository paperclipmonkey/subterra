<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.go(-1)">
          <v-icon :icon="mdiArrowLeft" />
        </v-btn>
        <v-toolbar-title>Suggest Edit for {{ originalCave.name }}</v-toolbar-title>
      </v-col>
    </v-row>
    <v-form ref="form" @submit.prevent="submitSuggestion">
      <v-row>
        <v-col cols="12">
          <v-alert type="info" class="mb-4">
            Thank you for contributing! Your changes will be reviewed by an administrator before being published.
          </v-alert>
          <CaveForm ref="caveFormRef" v-model="cave" />
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
      Suggestion submitted successfully! Redirecting...
    </v-snackbar>

    <v-snackbar v-model="errorSnackbar" color="error">
      {{ errorMessage }}
      <template #actions>
        <v-btn variant="text" @click="errorSnackbar = false">Close</v-btn>
      </template>
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { mdiArrowLeft } from '@mdi/js'

import { ref, onMounted } from "vue"
import { useRoute, useRouter } from "vue-router"
import CaveForm from '@/components/CaveForm.vue'
import { api } from '@/plugins/api.js'
import { objectToFormData } from '@/utils/formData'

const router = useRouter()
const route = useRoute()
const form = ref(null)
const caveFormRef = ref(null)
const loading = ref(false)
const successSnackbar = ref(false)
const errorSnackbar = ref(false)
const errorMessage = ref('')

const originalCave = ref({})
const cave = ref({
  name: '',
  description: '',
  // ... other fields initialized empty, will be filled by fetch
  tags: []
})

const fetchCave = async () => {
  try {
    const response = await api.get(`/api/caves/${route.params.id}`)
    const data = response.data.data
    originalCave.value = JSON.parse(JSON.stringify(data)) // Deep copy
    cave.value = JSON.parse(JSON.stringify(data))
  } catch (error) {
    console.error("Error fetching cave:", error)
    errorMessage.value = "Failed to load cave data"
    errorSnackbar.value = true
  }
}

const submitSuggestion = async () => {
  const { valid } = await form.value.validate()
  if (!valid) return

  loading.value = true
  try {
    const submitData = await caveFormRef.value.prepareForSubmit()
    const formData = objectToFormData({
      suggestable_type: 'cave',
      suggestable_id: originalCave.value.id,
      original_data: originalCave.value,
      suggested_data: submitData
    })

    await api.post('/api/suggested-edits', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })

    successSnackbar.value = true
    setTimeout(() => {
      router.push(`/caves/${originalCave.value.slug}`)
    }, 1500)

  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Failed to submit suggestion'
    errorSnackbar.value = true
    console.error(error)
  } finally {
    loading.value = false
  }
}

onMounted(fetchCave)
</script>
