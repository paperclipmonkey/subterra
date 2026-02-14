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
            v-model:files-to-delete="filesToDelete"
            v-model:new-files="newFiles"
            v-model:updated-files="updatedFiles"
            :show-files="true"
          />
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
import { ref, watch, onMounted } from "vue"
import { useRoute, useRouter } from "vue-router"
import CaveSystemForm from '@/components/CaveSystemForm.vue'
import { useAppStore } from '@/stores/app'
import { convertFileToBase64 } from '@/utilities.js'
import { useToast } from "vue-toastification"

const toast = useToast()
const appStore = useAppStore()

const router = useRouter()
const route = useRoute()
const form = ref(null)
const loading = ref(false)
const errorSnackbar = ref(false)
const errorMessage = ref('')

const filesToDelete = ref([])
const newFiles = ref([])
const updatedFiles = ref([])

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
    updatedFiles.value = []
  } catch (error) {
    console.error("Error loading cave system data:", error)
  }
}

const save = async () => {
  const { valid } = await form.value.validate()
  if (!valid) return

  loading.value = true

  if (appStore.user?.is_admin) {
    const formData = new FormData()

    formData.append('_method', 'PUT')
    formData.append('name', cavesystem.value.name || '')
    formData.append('description', cavesystem.value.description || '')
    formData.append('length', cavesystem.value.length || '')
    formData.append('vertical_range', cavesystem.value.vertical_range || '')
    formData.append('slug', cavesystem.value.slug || '')
    formData.append('references', cavesystem.value.references || '')
    if (cavesystem.value.catchment_id) {
      formData.append('catchment_id', cavesystem.value.catchment_id)
    }

    filesToDelete.value.forEach(fileId => {
      formData.append('deleted_files[]', fileId)
    })

    newFiles.value.forEach((fileObj, index) => {
      formData.append('new_files[]', fileObj.file)
      // Send details corresponding to the file index
      if (fileObj.details) {
        formData.append(`new_file_details[${index}]`, fileObj.details)
      }
    })

    updatedFiles.value.forEach((file, index) => {
      formData.append(`updated_files[${index}][id]`, file.id)
      formData.append(`updated_files[${index}][original_filename]`, file.original_filename)
      formData.append(`updated_files[${index}][details]`, file.details)
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
        // Go back to previous page (likely the cave page)
        // Check if we have history to go back to, otherwise fallback to system page
        if (window.history.state && window.history.state.back) {
          router.go(-1)
        } else {
          router.push('/cave-systems/' + cavesystem.value.slug)
        }
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
  } else {
    // Suggest Edit
    try {
      const processedNewFiles = await Promise.all(
        newFiles.value.map(async (fileObj) => {
          const base64 = await convertFileToBase64(fileObj.file)
          return {
            data: base64,
            name: fileObj.file.name,
            details: fileObj.details,
            mime_type: fileObj.file.type,
            size: fileObj.file.size
          }
        })
      )

      const response = await fetch('/api/suggested-edits', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          suggestable_type: 'cave_system',
          suggestable_id: cavesystem.value.id,
          suggested_data: {
            ...cavesystem.value,
            media: processedNewFiles, // Use 'media' key to be picked up by MediaSuggestionService
            deleted_files: filesToDelete.value
          },
          original_data: null
        }),
      })

      if (response.ok) {
        // Redirect back to the original cave system page
        toast.success('Thank you! Your suggestion has been submitted for review.')
        if (window.history.state && window.history.state.back) {
          router.go(-1)
        } else {
          router.push('/cave-systems/' + route.params.id)
        }
      } else {
        const data = await response.json()
        errorMessage.value = data.message || 'Failed to submit suggestion'
        errorSnackbar.value = true
      }
    } catch (error) {
      errorMessage.value = error.message
      errorSnackbar.value = true
    } finally {
      loading.value = false
    }
  }
}

onMounted(async () => {
  if (appStore.user && !appStore.canSuggest && !appStore.user.is_admin) {
    errorMessage.value = 'You do not have permission to suggest edits.'
    errorSnackbar.value = true
    setTimeout(() => router.push('/'), 3000)
    return
  }
  load()
})

watch(
  () => route.fullPath,
  (newPath) => {
    if (route.params.id && newPath.includes(`/cave-systems/${route.params.id}`)) {
      load()
    }
  }
)
</script>

<style scoped>
.file-marked-for-deletion {
  opacity: 0.6;
  text-decoration: line-through;
}
</style>
