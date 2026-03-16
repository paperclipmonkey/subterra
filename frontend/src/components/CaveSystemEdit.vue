<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.go(-1)">
          <v-icon :icon="mdiArrowLeft" />
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

    <!-- Merge Cave System (admin only) -->
    <template v-if="appStore.user?.is_admin">
      <v-divider class="my-6" />
      <v-row>
        <v-col class="d-flex justify-end">
          <v-btn
            variant="text"
            color="grey"
            size="small"
            @click="mergeSelectDialog = true"
          >
            Merge another system
          </v-btn>
          <v-btn
            variant="text"
            color="red"
            size="small"
            @click="deleteDialog = true"
          >
            Delete system
          </v-btn>
        </v-col>
      </v-row>

      <!-- Delete Confirmation Dialog -->
      <v-dialog v-model="deleteDialog" max-width="500" persistent>
        <v-card>
          <v-card-title class="text-h5">Delete Cave System</v-card-title>
          <v-card-text>
            <v-alert type="error" variant="tonal" class="mb-4">
              This action cannot be undone!
            </v-alert>
            <p>
              Are you sure you want to delete <strong>{{ cavesystem.name }}</strong>?
              All caves, routes, files, and tags associated with this system will also be deleted.
            </p>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
            <v-btn
              color="red"
              variant="flat"
              :loading="deleteLoading"
              @click="executeDelete"
            >
              Delete Permanently
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Merge Selection Dialog -->
      <v-dialog v-model="mergeSelectDialog" max-width="500">
        <v-card>
          <v-card-title>Merge Another Cave System</v-card-title>
          <v-card-text>
            <p class="mb-4">Merge another cave system into this one. All caves, routes, trips, files, and tags from the selected system will be moved here, and the other system will be deleted.</p>
            <v-select
              v-model="mergeSourceId"
              :items="availableSystems"
              item-title="name"
              item-value="id"
              label="Select cave system to merge in"
              clearable
              variant="outlined"
              density="compact"
              :loading="systemsLoading"
              :no-data-text="systemsLoading ? 'Loading...' : 'No other cave systems found'"
            />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="mergeSelectDialog = false">Cancel</v-btn>
            <v-btn
              color="warning"
              variant="flat"
              :disabled="!mergeSourceId"
              :loading="mergePreviewLoading"
              @click="loadMergePreview"
            >
              Preview Merge
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Merge Confirmation Dialog -->
      <v-dialog v-model="mergeDialog" max-width="600" persistent>
        <v-card>
          <v-card-title class="text-h5">Confirm Merge</v-card-title>
          <v-card-text v-if="mergePreview">
            <v-alert type="warning" variant="tonal" class="mb-4">
              This action cannot be undone!
            </v-alert>

            <p class="mb-3">
              You are about to merge <strong>{{ mergePreview.source.name }}</strong> into
              <strong>{{ mergePreview.target.name }}</strong>.
            </p>

            <v-table density="compact">
              <thead>
                <tr>
                  <th />
                  <th>{{ mergePreview.source.name }}</th>
                  <th>{{ mergePreview.target.name }}</th>
                  <th>After Merge</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Caves</td>
                  <td>{{ mergePreview.source.caves_count }}</td>
                  <td>{{ mergePreview.target.caves_count }}</td>
                  <td><strong>{{ mergePreview.result.caves_count }}</strong></td>
                </tr>
                <tr>
                  <td>Routes</td>
                  <td>{{ mergePreview.source.routes_count }}</td>
                  <td>{{ mergePreview.target.routes_count }}</td>
                  <td><strong>{{ mergePreview.result.routes_count }}</strong></td>
                </tr>
                <tr>
                  <td>Files</td>
                  <td>{{ mergePreview.source.files_count }}</td>
                  <td>{{ mergePreview.target.files_count }}</td>
                  <td><strong>{{ mergePreview.result.files_count }}</strong></td>
                </tr>
              </tbody>
            </v-table>

            <v-alert type="error" variant="tonal" class="mt-4">
              <strong>{{ mergePreview.source.name }}</strong> will be permanently deleted.
              <strong>{{ mergePreview.target.name }}</strong> will become the master system.
            </v-alert>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="mergeDialog = false">Cancel</v-btn>
            <v-btn
              color="warning"
              variant="flat"
              :loading="mergeLoading"
              @click="executeMerge"
            >
              Confirm Merge
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </template>

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

import { ref, watch, onMounted, computed } from "vue"
import { useRoute, useRouter, onBeforeRouteLeave } from "vue-router"
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
const isSaved = ref(false)

// Merge state
const mergeSourceId = ref(null)
const mergeSelectDialog = ref(false)
const mergeDialog = ref(false)
const mergePreview = ref(null)
const mergePreviewLoading = ref(false)
const mergeLoading = ref(false)
const availableSystems = ref([])
const systemsLoading = ref(false)

// Delete state
const deleteDialog = ref(false)
const deleteLoading = ref(false)

const initialSystemState = ref(null)

const isDirty = computed(() => {
  if (isSaved.value) return false
  if (!initialSystemState.value) return false
  // Check basic system data
  return JSON.stringify(cavesystem.value) !== initialSystemState.value ||
    filesToDelete.value.length > 0 ||
    newFiles.value.length > 0 ||
    updatedFiles.value.length > 0
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
    initialSystemState.value = JSON.stringify(cavesystem.value)
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
        isSaved.value = true
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
        isSaved.value = true
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

const loadAvailableSystems = async () => {
  systemsLoading.value = true
  try {
    const response = await fetch('/api/cave_systems')
    if (!response.ok) return
    const data = await response.json()
    // Filter out the current system
    availableSystems.value = (data.data || data).filter(
      s => s.id !== cavesystem.value.id
    )
  } catch (error) {
    console.error('Error loading cave systems:', error)
  } finally {
    systemsLoading.value = false
  }
}

const loadMergePreview = async () => {
  if (!mergeSourceId.value) return
  mergePreviewLoading.value = true
  try {
    const response = await fetch(
      `/api/admin/cave-systems/${cavesystem.value.id}/merge-preview?source_id=${mergeSourceId.value}`
    )
    if (response.ok) {
      mergePreview.value = await response.json()
      mergeSelectDialog.value = false
      mergeDialog.value = true
    } else {
      const data = await response.json()
      errorMessage.value = data.error || 'Failed to load merge preview'
      errorSnackbar.value = true
    }
  } catch (error) {
    errorMessage.value = error.message
    errorSnackbar.value = true
  } finally {
    mergePreviewLoading.value = false
  }
}

const executeMerge = async () => {
  mergeLoading.value = true
  try {
    const response = await fetch(
      `/api/admin/cave-systems/${cavesystem.value.id}/merge`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({ source_id: mergeSourceId.value }),
      }
    )
    if (response.ok) {
      const data = await response.json()
      mergeDialog.value = false
      mergeSourceId.value = null
      mergePreview.value = null
      toast.success(data.message)
      load()
      loadAvailableSystems()
    } else {
      const data = await response.json()
      errorMessage.value = data.error || 'Merge failed'
      errorSnackbar.value = true
    }
  } catch (error) {
    errorMessage.value = error.message
    errorSnackbar.value = true
  } finally {
    mergeLoading.value = false
  }
}

const executeDelete = async () => {
  deleteLoading.value = true
  try {
    const response = await fetch(
      `/api/admin/cave-systems/${cavesystem.value.id}`,
      {
        method: 'DELETE',
        headers: { 'Accept': 'application/json' },
      }
    )
    if (response.ok) {
      const data = await response.json()
      isSaved.value = true
      toast.success(data.message)
      router.push('/')
    } else {
      const data = await response.json()
      errorMessage.value = data.error || 'Delete failed'
      errorSnackbar.value = true
    }
  } catch (error) {
    errorMessage.value = error.message
    errorSnackbar.value = true
  } finally {
    deleteLoading.value = false
    deleteDialog.value = false
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

  if (appStore.user?.is_admin) {
    loadAvailableSystems()
  }
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
