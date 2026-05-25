<template>
  <v-dialog v-model="dialog" max-width="800px">
    <template #activator="{ props: activatorProps }">
      <v-btn v-if="canEdit && (isNew || userStore.canSuggest)" color="primary" variant="text" :prepend-icon="isNew ? mdiPlus : mdiPencil"
             v-bind="activatorProps">
        {{ activatorText }}
      </v-btn>
      <v-btn v-else-if="canEdit" color="grey" variant="text" disabled :prepend-icon="mdiPencilOff">
        <v-tooltip activator="parent" location="top">
          Your account must be approved to suggest edits
        </v-tooltip>
        {{ activatorText }}
      </v-btn>
    </template>

    <v-card>
      <v-card-title>
        <span class="text-h5">{{ activatorText }}</span>
      </v-card-title>

      <v-card-text>
        <v-container>
          <CollectionForm ref="form" v-model="editedCollection" />
        </v-container>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn color="blue-darken-1" variant="text" @click="close">
          Cancel
        </v-btn>
        <v-btn color="blue-darken-1" variant="text" :loading="saving" @click="save">
          {{ saveButtonText }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { mdiPencil, mdiPencilOff, mdiPlus } from '@mdi/js'

import { ref, computed, onMounted, watch } from 'vue'
import { useAppStore } from '@/stores/app'
import { useCollectionStore } from '@/stores/collections'
import CollectionForm from '@/components/CollectionForm.vue'
import { useNotificationStore } from '@/stores/notifications'
import { toFormData } from '@/utilities.js'
import { api } from '@/plugins/api'

const notifications = useNotificationStore()

const props = defineProps({
    collection: {
        type: Object,
        required: false,
        default: null
    },
})

const userStore = useAppStore()
const collectionStore = useCollectionStore()

const dialog = ref(false)
const form = ref(null)
const saving = ref(false)

// Initialize with defaults if creating new
const defaultCollection = {
    name: '',
    description: '',
    photo_path: '',
    caves: []
}
const editedCollection = ref({ ...defaultCollection })

const isNew = computed(() => !props.collection)

const canEdit = computed(() => {
    // Everyone can suggest edits/creations now
    if (isNew.value) return true
    return true
})

const activatorText = computed(() => {
    if (isNew.value) {
        return userStore.user?.is_admin ? 'New Collection' : 'Suggest New Collection'
    }
    return userStore.user?.is_admin || userStore.user?.id === props.collection?.user_id ? 'Edit Collection' : 'Suggest Edit'
})

const saveButtonText = computed(() => {
    if (isNew.value) {
        return userStore.user?.is_admin ? 'Create Collection' : 'Suggest New Collection'
    }
    return userStore.user?.is_admin || userStore.user?.id === props.collection?.user_id ? 'Save Changes' : 'Suggest Changes'
})

const initForm = (newVal) => {
    if (newVal) {
        editedCollection.value = JSON.parse(JSON.stringify(newVal))
    } else {
        editedCollection.value = { ...defaultCollection }
    }
}

watch(() => props.collection, (newVal) => {
    initForm(newVal)
}, { immediate: true })

const close = () => {
    dialog.value = false
    initForm(props.collection)
}

const save = async () => {
    const { valid } = await form.value.validate()
    if (!valid) return

    saving.value = true
    try {
        // Prepare payload
        const payload = { ...editedCollection.value }

        // Ensure caves map correctly
        if (payload.caves) {
            payload.caves = payload.caves.map(c => ({
                id: c.id,
                description: c.playlist_description
            }))
        }

        if (userStore.user?.is_admin || (props.collection && userStore.user?.id === props.collection.user_id)) {
            if (isNew.value) {
                await collectionStore.createCollection(payload)
            } else {
                await collectionStore.updateCollection(payload)
            }
            notifications.showSuccess(isNew.value ? 'Collection created successfully' : 'Collection updated successfully')
        } else {
            // Suggest Edit or Create
            const suggestionPayload = {
                suggestable_type: 'collection',
                suggestable_id: props.collection?.id || null,
                suggested_data: payload,
                original_data: null
            }

            const formData = toFormData(suggestionPayload)

            await api.post('/api/suggested-edits', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            })
            notifications.showSuccess('Suggestion submitted for review')
            dialog.value = false
        }
    } catch (e) {
        let errorMessage = 'Failed to save collection'
        // Check for backend validation errors
        if (e.response && e.response.data) {
            if (e.response.data.errors) {
                // Combine the first error of each field for a concise message
                errorMessage = Object.values(e.response.data.errors).flat()[0]
            } else if (e.response.data.message) {
                errorMessage = e.response.data.message
            }
        } else if (e.message) {
            errorMessage = e.message
        }
        console.error(e)
        notifications.showError(errorMessage)
    } finally {
        saving.value = false
    }
}
</script>
