<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.go(-1)">
          <v-icon :icon="mdiArrowLeft" />
        </v-btn>
        <v-toolbar-title>Add Cave</v-toolbar-title>
        <v-divider v-if="system">{{ system.name }}</v-divider>
      </v-col>
    </v-row>
    <v-form ref="form" @submit.prevent="saveCave">
      <v-row>
        <v-col cols="12">
          <CaveForm v-model="cave" />
        </v-col>
      </v-row>

      <v-row v-if="system">
        <v-col cols="12">
          <v-card>
            <v-card-title>System</v-card-title>
            <v-card-subtitle>{{ system.name }}</v-card-subtitle>
            <v-card-text>
              <MarkdownRenderer v-if="system.description" :source="system.description" />
              <p v-if="system.tags && system.tags.length"> Tags:
                <v-chip v-for="tag in system.tags" :key="tag.tag" class="ma-1">
                  {{ tag.tag }}
                </v-chip>
              </p>
              <p><strong>Length:</strong> {{ Math.round(system.length) }} m</p>
              <p><strong>Vertical Range:</strong> {{ system.vertical_range }} m</p>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-row>
        <v-col>
          <v-card-text>
            <v-btn type="submit" color="primary" block size="large" :loading="loading">
              Save
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

import { ref, onMounted } from "vue"
import { useRoute, useRouter } from "vue-router"
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'
import CaveForm from '@/components/CaveForm.vue'
import { useAppStore } from '@/stores/app'
import { toFormData } from '@/utilities'
import { api } from '@/plugins/api'

const appStore = useAppStore()
const router = useRouter()
const route = useRoute()
const form = ref(null)
const loading = ref(false)
const errorSnackbar = ref(false)
const errorMessage = ref('')
const system = ref(null)

const cave = ref({
  name: '',
  description: '',
  hero_image: null,
  entrance_image: null,
  location_name: '',
  location_country: '',
  location_lat: null,
  location_lng: null,
  location_alt: null,
  access_info: '',
  slug: '',
  tags: [],
  cave_system_id: route.query.system_id ? parseInt(route.query.system_id) : null
})

const fetchSystem = async () => {
  if (!cave.value.cave_system_id) return

  try {
    const response = await api.get(`/api/cave_systems/${cave.value.cave_system_id}`)
    system.value = response.data.data
  } catch (error) {
    console.error("Error fetching system:", error)
  }
}

const saveCave = async () => {
  const { valid } = await form.value.validate()
  if (!valid) return

  loading.value = true
  try {
    const formData = toFormData(cave.value)

    const response = await api.post('/api/caves', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    router.push('/caves/' + response.data.data.slug)
  } catch (error) {
    const data = error.response?.data
    errorMessage.value = data?.message || 'Failed to create cave'
    errorSnackbar.value = true
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  // Access control check
  if (!appStore.user?.is_admin && (!appStore.user?.roles || !appStore.user.roles.some(r => r.slug === 'data_admin'))) {
    router.push('/')
    return
  }
  fetchSystem()
})
</script>

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";
</style>
