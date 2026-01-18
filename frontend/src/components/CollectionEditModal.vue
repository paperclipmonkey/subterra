<template>
    <v-dialog v-model="dialog" max-width="800px">
        <template v-slot:activator="{ props }">
            <v-btn color="primary" variant="text" :prepend-icon="isNew ? 'mdi-plus' : 'mdi-pencil'" v-bind="props"
                v-if="canEdit">
                {{ isNew ? 'New Collection' : 'Edit Collection' }}
            </v-btn>
        </template>

        <v-card>
            <v-card-title>
                <span class="text-h5">{{ isNew ? 'New Collection' : 'Edit Collection' }}</span>
            </v-card-title>

            <v-card-text>
                <v-container>
                    <v-form ref="form" v-model="valid">
                        <v-row>
                            <v-col cols="12">
                                <v-text-field v-model="editedCollection.name" label="Name" required></v-text-field>
                            </v-col>
                            <v-col cols="12">
                                <v-textarea v-model="editedCollection.description" label="Description"></v-textarea>
                            </v-col>
                            <v-col cols="12">
                                <v-text-field v-model="editedCollection.photo_path" label="Photo URL"></v-text-field>
                            </v-col>
                            <v-col cols="12" v-if="userStore.user.is_admin">
                                <v-switch v-model="editedCollection.is_official" label="Official Collection"
                                    color="primary"></v-switch>
                            </v-col>
                        </v-row>

                        <v-divider class="my-4"></v-divider>
                        <div class="text-h6 mb-2">Manage Caves</div>

                        <v-autocomplete v-model="selectedCaveToAdd" :items="allCaves" item-title="name" item-value="id"
                            label="Add a Cave" placeholder="Search for a cave..." return-object hide-details
                            class="mb-4" @update:model-value="addCave"></v-autocomplete>

                        <v-list density="compact" class="bg-grey-lighten-4 rounded">
                            <v-list-item v-for="cave in editedCollection.caves" :key="cave.id" :title="cave.name"
                                :subtitle="cave.location_name">
                                <template v-slot:append>
                                    <v-btn icon="mdi-delete" size="small" color="error" variant="text"
                                        @click="removeCave(cave)"></v-btn>
                                </template>
                            </v-list-item>
                            <v-list-item v-if="!editedCollection.caves || editedCollection.caves.length === 0">
                                <div class="text-center w-100 text-grey font-italic">No caves in this collection</div>
                            </v-list-item>
                        </v-list>
                    </v-form>
                </v-container>
            </v-card-text>

            <v-card-actions>
                <v-spacer></v-spacer>
                <v-btn color="blue-darken-1" variant="text" @click="close">
                    Cancel
                </v-btn>
                <v-btn color="blue-darken-1" variant="text" @click="save">
                    Save
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAppStore } from '@/stores/app'
import { useCollectionStore } from '@/stores/collections'
import { useCaveStore } from '@/stores/caves'

const props = defineProps({
    collection: {
        type: Object,
        required: false,
        default: null
    },
})

const userStore = useAppStore()
const collectionStore = useCollectionStore()
const caveStore = useCaveStore()

const dialog = ref(false)
const valid = ref(false)
// Initialize with defaults if creating new
const defaultCollection = {
    name: '',
    description: '',
    photo_path: '',
    is_official: false,
    caves: []
}
const editedCollection = ref({ ...defaultCollection })
const selectedCaveToAdd = ref(null)

// Track changes locally
const addedCaves = ref([])
const removedCaves = ref([])

const isNew = computed(() => !props.collection)

const canEdit = computed(() => {
    if (isNew.value) return userStore.user.is_admin;
    return userStore.user.is_admin || userStore.user.id === props.collection.user_id
})

// Fetch all caves for autocomplete
const allCaves = computed(() => caveStore.caves)

onMounted(() => {
    if (caveStore.caves.length === 0) {
        caveStore.getList()
    }
})

watch(() => props.collection, (newVal) => {
    if (newVal) {
        editedCollection.value = JSON.parse(JSON.stringify(newVal))
    } else {
        editedCollection.value = { ...defaultCollection }
    }
    // Reset change trackers
    addedCaves.value = []
    removedCaves.value = []
}, { immediate: true })

const close = () => {
    dialog.value = false
    if (props.collection) {
        editedCollection.value = JSON.parse(JSON.stringify(props.collection))
    } else {
        editedCollection.value = { ...defaultCollection }
    }
    addedCaves.value = []
    removedCaves.value = []
}

const save = async () => {
    try {
        let collectionId = null;
        let collectionSlug = null;

        if (isNew.value) {
            // Create
            const response = await collectionStore.createCollection(editedCollection.value);
            // Assuming response contains id/slug. If standard Store pattern, it might return the object.
            // Check collection store createCollection return.
            // Adjust based on typical mande response. usually returns the JSON body.
            collectionId = response.id; // or slug
            collectionSlug = response.slug;
        } else {
            // Update metadata
            await collectionStore.updateCollection(editedCollection.value);
            collectionSlug = props.collection.slug; // Use original slug or if updated one? 
            // Usually update uses ID in URL.
        }

        const targetSlug = collectionSlug || collectionId;

        // Process additions
        for (const cave of addedCaves.value) {
            await collectionStore.addCaveToCollection(targetSlug, cave.id);
        }

        // Process removals
        // Only trigger removal if we are NOT in create mode OR if we added it then removed it (which is handled by local state, but let's be safe)
        // Actually if we just created, the initial list is empty, so only addedCaves matter.
        // If we are editing, removedCaves matters.
        if (!isNew.value) {
            for (const cave of removedCaves.value) {
                await collectionStore.removeCaveFromCollection(targetSlug, cave.id);
            }
        }

        // Refresh 
        if (!isNew.value) {
            await collectionStore.fetchCollection(targetSlug);
        } else {
            // If new, maybe redirect or reload list?
            await collectionStore.fetchCollections();
        }

        dialog.value = false
    } catch (e) {
        console.error(e)
        alert('Failed to save: ' + e.message)
    }
}

const addCave = (cave) => {
    if (!cave) return
    // Check if already in list
    if (editedCollection.value.caves && editedCollection.value.caves.find(c => c.id === cave.id)) return;

    if (!editedCollection.value.caves) editedCollection.value.caves = [];

    // Add to UI list
    editedCollection.value.caves.push(cave);

    // Track operation
    // If it was in removed list, remove from there (undo remove)
    const removedIndex = removedCaves.value.findIndex(c => c.id === cave.id);
    if (removedIndex > -1) {
        removedCaves.value.splice(removedIndex, 1);
    } else {
        // Else add to added list
        addedCaves.value.push(cave);
    }

    selectedCaveToAdd.value = null;
}

const removeCave = (cave) => {
    // Remove from UI list
    editedCollection.value.caves = editedCollection.value.caves.filter(c => c.id !== cave.id);

    // Track operation
    // If it was in added list, remove from there (undo add)
    const addedIndex = addedCaves.value.findIndex(c => c.id === cave.id);
    if (addedIndex > -1) {
        addedCaves.value.splice(addedIndex, 1);
    } else {
        // Else add to removed list (if it existed originally)
        // If new collection, everything is "added", so if we remove it, it just disappears from added list.
        // But if editing, we need track removal.
        if (!isNew.value) {
            removedCaves.value.push(cave);
        }
    }
}
</script>
