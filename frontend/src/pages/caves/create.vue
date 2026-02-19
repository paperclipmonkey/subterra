<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.go(-1)">
          <v-icon>mdi-arrow-left</v-icon>
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
import { ref, onMounted } from "vue"
import { useRoute, useRouter } from "vue-router"
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'
import CaveForm from '@/components/CaveForm.vue'
import { useAppStore } from '@/stores/app'

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
    const response = await fetch(`/api/cave_systems/${cave.value.cave_system_id}`, { headers: { 'Accept': 'application/json' } })
    if (response.ok) {
      const data = (await response.json()).data
      system.value = data

      // Inherit location from system caves if available (optional UX improvement)
      // For now, simpler is better as requested.
    }
  } catch (error) {
    console.error("Error fetching system:", error)
  }
}

const saveCave = async () => {
  const { valid } = await form.value.validate()
  if (!valid) return

  loading.value = true
  try {
    const response = await fetch('/api/caves', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(cave.value),
    })

    if (response.ok) {
      const data = (await response.json()).data
      router.push('/caves/' + data.slug)
    } else {
      const data = await response.json()
      errorMessage.value = data.message || 'Failed to create cave'
      errorSnackbar.value = true
    }
  } catch (error) {
    errorMessage.value = 'An unexpected error occurred'
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
