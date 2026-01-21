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
                                <v-file-input v-model="photoFile" label="Photo" accept="image/*" prepend-icon="mdi-camera"
                                    show-size></v-file-input>
                                <div v-if="editedCollection.photo_path && !photoFile" class="text-caption">
                                    Current photo: {{ editedCollection.photo_path }}
                                </div>
                            </v-col>
                        </v-row>

                        <v-divider class="my-4"></v-divider>
                        <div class="text-h6 mb-2">Manage Caves</div>

                        <v-autocomplete v-model="selectedCaveToAdd" :items="allCaves" item-title="name" item-value="id"
                            label="Add a Cave" placeholder="Search for a cave..." return-object hide-details
                            class="mb-4" @update:model-value="addCave"></v-autocomplete>

                        <v-list density="compact" class="bg-grey-lighten-4 rounded">
                            <template v-for="(cave, index) in editedCollection.caves" :key="cave.id">
                                <v-list-item>
                                    <template v-slot:prepend>
                                        <div class="d-flex flex-column">
                                            <v-btn icon="mdi-chevron-up" variant="text" size="x-small"
                                                :disabled="index === 0" @click="moveCave(index, -1)"></v-btn>
                                            <v-btn icon="mdi-chevron-down" variant="text" size="x-small"
                                                :disabled="index === editedCollection.caves.length - 1"
                                                @click="moveCave(index, 1)"></v-btn>
                                        </div>
                                    </template>
                                    
                                    <v-list-item-title class="font-weight-bold">{{ cave.name }}</v-list-item-title>
                                    <v-list-item-subtitle>{{ cave.location_name }}</v-list-item-subtitle>
                                    
                                    <v-textarea v-model="cave.playlist_description" label="Note (Markdown supported)" rows="1" auto-grow
                                        hide-details density="compact" variant="underlined" class="mt-2"></v-textarea>

                                    <template v-slot:append>
                                        <v-btn icon="mdi-delete" size="small" color="error" variant="text"
                                            @click="removeCave(index)"></v-btn>
                                    </template>
                                </v-list-item>
                                <v-divider v-if="index < editedCollection.caves.length - 1"></v-divider>
                            </template>
                            
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
                <v-btn color="blue-darken-1" variant="text" @click="save" :loading="saving">
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
const saving = ref(false)

// Initialize with defaults if creating new
const defaultCollection = {
    name: '',
    description: '',
    photo_path: '',
    caves: []
}
const editedCollection = ref({ ...defaultCollection })
const photoFile = ref(null)
const selectedCaveToAdd = ref(null)

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

const initForm = (newVal) => {
    if (newVal) {
        editedCollection.value = JSON.parse(JSON.stringify(newVal))
        // Map pivot description to editable property if exists, or use existing pivot data
        if (editedCollection.value.caves) {
            editedCollection.value.caves.forEach(c => {
                // If pivot exists, use it. Backend creates pivot structure.
                if (c.pivot) {
                    c.playlist_description = c.pivot.description;
                }
            });
        }
    } else {
        editedCollection.value = { ...defaultCollection }
    }
    photoFile.value = null;
}

watch(() => props.collection, (newVal) => {
    initForm(newVal)
}, { immediate: true })

const close = () => {
    dialog.value = false
    initForm(props.collection)
}

const save = async () => {
    saving.value = true;
    try {
        // Prepare payload
        const payload = {
            ...editedCollection.value,
            caves: editedCollection.value.caves.map(c => ({
                id: c.id,
                description: c.playlist_description
            }))
        };

        // Add photo file if exists
        if (photoFile.value) {
            payload.photo = photoFile.value;
        }

        if (isNew.value) {
            await collectionStore.createCollection(payload);
        } else {
            await collectionStore.updateCollection(payload);
        }

        dialog.value = false
    } catch (e) {
        console.error(e)
        alert('Failed to save: ' + e.message)
    } finally {
        saving.value = false;
    }
}

const addCave = (cave) => {
    if (!cave) return
    // Check if already in list
    if (editedCollection.value.caves && editedCollection.value.caves.find(c => c.id === cave.id)) {
        selectedCaveToAdd.value = null; // reset
        return;
    }

    if (!editedCollection.value.caves) editedCollection.value.caves = [];

    // Add to UI list with empty description
    // Clone to avoid reference issues
    const newCave = { ...cave, playlist_description: '' };
    editedCollection.value.caves.push(newCave);

    selectedCaveToAdd.value = null;
}

const removeCave = (index) => {
    editedCollection.value.caves.splice(index, 1);
}

const moveCave = (index, direction) => {
    const newIndex = index + direction;
    if (newIndex >= 0 && newIndex < editedCollection.value.caves.length) {
        const item = editedCollection.value.caves.splice(index, 1)[0];
        editedCollection.value.caves.splice(newIndex, 0, item);
    }
}
</script>
