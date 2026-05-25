<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.go(-1)">
          <v-icon :icon="mdiArrowLeft" />
        </v-btn>
        <v-toolbar-title>{{ cave.name }}</v-toolbar-title>
        <v-divider v-if="cave.system">{{ cave.system.name }}</v-divider>
      </v-col>
    </v-row>
    <v-form ref="form" @submit.prevent="saveCave">
      <v-row>
        <v-col cols="12">
          <CaveForm ref="caveFormRef" v-model="cave" />
        </v-col>
      </v-row>

      <v-row v-if="cave.system">
        <v-col cols="12">
          <v-card>
            <v-card-title>System</v-card-title>
            <v-card-subtitle>{{ cave.system.name }}</v-card-subtitle>
            <v-card-text>
              <MarkdownRenderer v-if="cave.system.description" :source="cave.system.description" />
              <p v-if="cave.system.tags && cave.system.tags.length"> Tags:
                <v-chip v-for="tag in cave.system.tags" :key="tag.tag" class="ma-1">
                  {{ tag.tag }}
                </v-chip>
              </p>
              <p><strong>Length:</strong> {{ Math.round(cave.system.length) }} m</p>
              <p><strong>Vertical Range:</strong> {{ cave.system.vertical_range }} m</p>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-row>
        <v-col>
          <v-card-text>
            <v-btn type="submit" color="primary" block size="large" :loading="loading">
              {{ appStore.user?.is_admin ? 'Save' : 'Suggest Changes' }}
            </v-btn>
          </v-card-text>
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

import { ref, onMounted, watch, computed } from "vue"
import { useRoute, useRouter, onBeforeRouteLeave } from "vue-router"
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'
import CaveForm from '@/components/CaveForm.vue'
import { useAppStore } from '@/stores/app'
import { useNotificationStore } from '@/stores/notifications'
import { toFormData } from '@/utilities'
import { api } from '@/plugins/api'

const notifications = useNotificationStore()
const appStore = useAppStore()

const router = useRouter()
const route = useRoute()
const form = ref(null)
const caveFormRef = ref(null)
const loading = ref(false)
const errorSnackbar = ref(false)
const errorMessage = ref('')
const isSaved = ref(false)

const initialCaveState = ref(null)

const isDirty = computed(() => {
  if (isSaved.value) return false
  if (!initialCaveState.value) return false
  return JSON.stringify(cave.value) !== initialCaveState.value
})

onBeforeRouteLeave((to, from, next) => {
  if (isDirty.value) {
    const answer = window.confirm('You have unsaved changes. Are you sure you want to leave?')
    if (answer) {
      next()
    } else {
      next(false)
    }
  } else {
    next()
  }
})

const cave = ref({
  name: '',
  description: '',
  hero_image: '',
  entrance_image: '',
  system: {
    name: '',
    description: '',
    length: 0,
    vertical_range: 0,
    caves: [],
    tags: []
  },
  location_name: '',
  location_country: '',
  location_lat: 0,
  location_lng: 0,
  location_alt: 0,
  access_info: '',
  slug: '',
  tags: []
})

const fetchCave = async () => {
  try {
    const response = await api.get(`/api/caves/${route.params.id}`)
    cave.value = response.data.data
    initialCaveState.value = JSON.stringify(response.data.data)
  } catch (error) {
    console.error("Error fetching cave:", error)
  }
}

const saveCave = async () => {
  const { valid } = await form.value.validate()
  if (!valid) return

  loading.value = true
  try {
    const submitData = await caveFormRef.value.prepareForSubmit()

    if (appStore.user?.is_admin) {
      const formData = toFormData(submitData)
      formData.append('_method', 'PUT')

      try {
        await api.post(`/api/caves/${route.params.id}`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        isSaved.value = true
        router.push('/caves/' + cave.value.slug)
      } catch (err) {
        const data = err.response?.data
        errorMessage.value = data?.message || 'Failed to save cave'
        errorSnackbar.value = true
      }
    } else {
      // Suggest Edit
      const formData = toFormData({
        suggestable_type: 'cave',
        suggestable_id: cave.value.id,
        suggested_data: submitData,
        original_data: null
      })

      try {
        await api.post('/api/suggested-edits', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        // Stay on the original cave page when suggesting edits
        notifications.showSuccess('Thank you! Your suggestion has been submitted for review.')
        isSaved.value = true
        router.push('/caves/' + route.params.id)
      } catch (err) {
        const data = err.response?.data
        errorMessage.value = data?.message || 'Failed to submit suggestion'
        errorSnackbar.value = true
      }
    }
  } catch (error) {
    errorMessage.value = 'An unexpected error occurred'
    errorSnackbar.value = true
  } finally {
    loading.value = false
  }
}

onMounted(fetchCave)

watch(
  () => route.fullPath,
  fetchCave
)
</script>

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";
</style>