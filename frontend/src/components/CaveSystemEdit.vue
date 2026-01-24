<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.go(-1)">
          <v-icon>mdi-arrow-left</v-icon>
        </v-btn>
        <v-toolbar-title>{{ cavesystem.name }}</v-toolbar-title>
      </v-col>
    </v-row>
    <v-form ref="form" @submit.prevent="save">
      <v-row>
        <v-col cols="12">
          <CaveSystemForm
            v-model="cavesystem"
            :showFiles="true"
            v-model:filesToDelete="filesToDelete"
            v-model:newFiles="newFiles"
          />
        </v-col>
      </v-row>

      <v-row>
        <v-col>
          <v-card-text>
            <v-btn type="submit" color="primary" block size="large" :loading="loading">Save</v-btn>
          </v-card-text>
        </v-col>
      </v-row>
    </v-form>

    <v-snackbar v-model="errorSnackbar" color="error">
      {{ errorMessage }}
      <template v-slot:actions>
        <v-btn variant="text" @click="errorSnackbar = false">Close</v-btn>
      </template>
    </v-snackbar>
  </v-container>
</template>

<style scoped>
.file-marked-for-deletion {
  opacity: 0.6;
  text-decoration: line-through;
}
</style>

<script setup>
import { ref, watch, onMounted } from "vue"
import { useRoute, useRouter } from "vue-router"
import CaveSystemForm from '@/components/CaveSystemForm.vue'

const router = useRouter()
const route = useRoute()
const form = ref(null)
const loading = ref(false)
const errorSnackbar = ref(false)
const errorMessage = ref('')

const filesToDelete = ref([])
const newFiles = ref([])

const cavesystem = ref({
  name: '',
  description: '',
  length: null,
  vertical_range: null,
  slug: '',
  tags: [],
  caves: [],
  references: "",
  files: []
})

const load = async () => {
  try {
    const response = await fetch(`/api/cave_systems/${route.params.id}`)
    if (!response.ok) throw new Error('Failed to load cave system')
    cavesystem.value = (await response.json()).data
    cavesystem.value.files = cavesystem.value.files || []
    filesToDelete.value = []
    newFiles.value = []
  } catch (error) {
    console.error("Error loading cave system data:", error)
  }
}

const save = async () => {
  const { valid } = await form.value.validate()
  if (!valid) return

  loading.value = true
  const formData = new FormData()

  formData.append('_method', 'PUT')
  formData.append('name', cavesystem.value.name || '')
  formData.append('description', cavesystem.value.description || '')
  formData.append('length', cavesystem.value.length || '')
  formData.append('vertical_range', cavesystem.value.vertical_range || '')
  formData.append('slug', cavesystem.value.slug || '')
  formData.append('references', cavesystem.value.references || '')

  filesToDelete.value.forEach(fileId => {
    formData.append('deleted_files[]', fileId)
  })

  newFiles.value.forEach((file) => {
    formData.append('new_files[]', file)
  })

  try {
    const response = await fetch(`/api/cave_systems/${route.params.id}`, {
      method: 'POST',
      body: formData,
      headers: {
        'Accept': 'application/json',
      }
    })

    if (response.ok) {
      router.go(-1)
    } else {
      const data = await response.json()
      errorMessage.value = data.message || 'Save failed'
      errorSnackbar.value = true
    }
  } catch (error) {
    errorMessage.value = error.message
    errorSnackbar.value = true
  } finally {
    loading.value = false
  }
}

onMounted(load)

watch(
  () => route.fullPath,
  (newPath) => {
    if (route.params.id && newPath.includes(`/cave-systems/${route.params.id}`)) {
      load()
    }
  }
)
</script>
