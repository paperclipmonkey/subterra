<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.go(-1)">
          <v-icon :icon="mdiArrowLeft" />
        </v-btn>
        <v-toolbar-title>Add Cave System & Cave</v-toolbar-title>
      </v-col>
    </v-row>
    <v-form ref="form" @submit.prevent="submit">
      <v-row>
        <v-col cols="12" md="6">
          <h2 class="text-h5 mb-4">Cave System Details</h2>
          <CaveSystemForm v-model="system" />
        </v-col>
        <v-col cols="12" md="6">
          <h2 class="text-h5 mb-4">Cave Details</h2>
          <CaveForm v-model="cave" />
        </v-col>
      </v-row>
      <v-row>
        <v-col cols="12">
          <v-btn type="submit" color="primary" block size="large" :loading="loading">
            Create System & Cave
          </v-btn>
        </v-col>
      </v-row>
    </v-form>

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

import { ref } from 'vue'
import { useRouter } from 'vue-router'
import CaveSystemForm from '@/components/CaveSystemForm.vue'
import CaveForm from '@/components/CaveForm.vue'
import { toFormData } from '@/utilities'

const router = useRouter()
const form = ref(null)
const loading = ref(false)
const errorSnackbar = ref(false)
const errorMessage = ref('')

const system = ref({
  name: '',
  description: '',
  length: null,
  vertical_range: null,
  slug: '',
  references: '',
  catchment_id: null
})

const cave = ref({
  name: '',
  description: '',
  location_name: '',
  location_country: '',
  location_lat: 0,
  location_lng: 0,
  location_alt: null,
  access_info: '',
  slug: '',
  tags: []
})

const submit = async () => {
  const { valid } = await form.value.validate()
  if (!valid) return

  loading.value = true
  try {
    const formData = toFormData({ system: system.value, cave: cave.value })

    const response = await fetch('/api/cave_systems_with_cave', {
      method: 'POST',
      headers: {
        'Accept': 'application/json'
      },
      body: formData
    })

    if (response.ok) {
      const data = await response.json()
      router.push({ name: '/caves/[id]', params: { id: data.cave.slug } })
    } else {
      const data = await response.json()
      errorMessage.value = data.message || 'Failed to create cave system and cave'
      errorSnackbar.value = true
    }
  } catch (error) {
    errorMessage.value = 'An unexpected error occurred'
    errorSnackbar.value = true
  } finally {
    loading.value = false
  }
}
</script>

