<template>
  <v-dialog v-model="dialog" max-width="800px">
    <template #activator="{ props: activatorProps }">
      <v-btn v-if="canEdit" color="primary" variant="text" :prepend-icon="isNew ? 'mdi-plus' : 'mdi-pencil'"
             v-bind="activatorProps">
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
import { ref, computed, onMounted, watch } from 'vue'
import { useAppStore } from '@/stores/app'
import { useCollectionStore } from '@/stores/collections'
import CollectionForm from '@/components/CollectionForm.vue'
import { useToast } from "vue-toastification";

const toast = useToast();

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
    if (isNew.value) return true;
    return true;
})

const activatorText = computed(() => {
    if (isNew.value) {
        return userStore.user?.is_admin ? 'New Collection' : 'Suggest New Collection';
    }
    return userStore.user?.is_admin || userStore.user?.id === props.collection?.user_id ? 'Edit Collection' : 'Suggest Edit';
})

const saveButtonText = computed(() => {
    if (isNew.value) {
        return userStore.user?.is_admin ? 'Create Collection' : 'Suggest New Collection';
    }
    return userStore.user?.is_admin || userStore.user?.id === props.collection?.user_id ? 'Save Changes' : 'Suggest Changes';
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

    saving.value = true;
    try {
        // Prepare payload - CollectionForm handles internal state, we just need to send it
        const payload = { ...editedCollection.value };

        if (payload.photo_data) {
            payload.photo_path = payload.photo_data;
            delete payload.photo_data;
        }

        // Ensure caves map correctly if needed, though form should produce correct structure
        if (payload.caves) {
            payload.caves = payload.caves.map(c => ({
                id: c.id,
                description: c.playlist_description
            }))
        }

        // Handle photo upload separately if needed, or if form emits a file object
        // CollectionForm stores file in internal state? No, it relies on parent extracting or model binding?
        // Let's check CollectionForm again. It stores photo_data in model. Perfect.

        if (userStore.user?.is_admin || (props.collection && userStore.user?.id === props.collection.user_id)) {
            if (isNew.value) {
                await collectionStore.createCollection(payload);
            } else {
                await collectionStore.updateCollection(payload);
            }
            toast.success(isNew.value ? 'Collection created successfully' : 'Collection updated successfully');
        } else {
            // Suggest Edit or Create
            const response = await fetch('/api/suggested-edits', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    suggestable_type: 'collection',
                    suggestable_id: props.collection?.id || null,
                    suggested_data: payload,
                    original_data: null
                }),
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.message || 'Failed to submit suggestion');
            }
            toast.success('Suggestion submitted for review');
            dialog.value = false;
        }
    } catch (e) {
        let errorMessage = 'Failed to save collection';
        // Check for backend validation errors
        if (e.response && e.response.data) {
            if (e.response.data.errors) {
                // Combine the first error of each field for a concise message
                errorMessage = Object.values(e.response.data.errors).flat()[0];
            } else if (e.response.data.message) {
                errorMessage = e.response.data.message;
            }
        } else if (e.message) {
            errorMessage = e.message;
        }
        console.error(e)
        toast.error(errorMessage);
    } finally {
        saving.value = false;
    }
}
</script>
