<template>
    <v-dialog v-model="dialog" max-width="800px">
        <template v-slot:activator="{ props }">
            <v-btn color="primary" variant="text" :prepend-icon="isNew ? 'mdi-plus' : 'mdi-pencil'" v-bind="props"
                v-if="canEdit">
                {{ isNew ? 'New Hut' : 'Edit Hut' }}
            </v-btn>
        </template>

        <v-card>
            <v-card-title>
                <span class="text-h5">{{ isNew ? 'New Hut' : 'Edit Hut' }}</span>
            </v-card-title>

            <v-card-text>
                <v-container>
                    <v-form ref="form" v-model="valid">
                        <v-row>
                            <v-col cols="12" md="6">
                                <v-text-field v-model="editedHut.name" label="Name" required></v-text-field>
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-autocomplete v-model="editedHut.club_id" :items="clubs" item-title="name"
                                    item-value="id" label="Club" clearable :loading="loadingClubs"></v-autocomplete>
                            </v-col>
                            <v-col cols="12">
                                <v-autocomplete v-model="editedHut.reciprocal_clubs" :items="clubs" item-title="name"
                                    item-value="id" label="Reciprocal Clubs" multiple chips closable-chips :loading="loadingClubs"></v-autocomplete>
                            </v-col>
                            <v-col cols="12">
                                <div class="text-subtitle-2 mb-2">Description</div>
                                <MilkdownEditor 
                                    v-model="editedHut.description" 
                                    @change="updateDescription"
                                    placeholder="Write description here..." 
                                />
                            </v-col>
                            <v-col cols="12">
                                <v-text-field v-model="editedHut.external_url" label="External URL"></v-text-field>
                            </v-col>
                            <v-col cols="12">
                                <v-textarea v-model="editedHut.booking_info" label="Booking Info" rows="3"></v-textarea>
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field v-model.number="editedHut.location_lat" label="Latitude"
                                    type="number"></v-text-field>
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field v-model.number="editedHut.location_lng" label="Longitude"
                                    type="number"></v-text-field>
                            </v-col>
                            <v-col cols="12">
                                <v-file-input v-model="editedHut.image" label="Hut Image" accept="image/*"
                                    prepend-icon="mdi-camera" show-size truncate-length="50"></v-file-input>
                            </v-col>
                            <v-col cols="12">
                                <v-combobox v-model="editedHut.amenities" label="Amenities" multiple chips
                                    closable-chips hint="Type and press enter to add amenities"
                                    persistent-hint></v-combobox>
                            </v-col>
                        </v-row>
                    </v-form>
                </v-container>
            </v-card-text>

            <v-card-actions>
                <v-btn v-if="!isNew" color="error" variant="text" @click="deleteHut" :loading="deleting">
                    Delete
                </v-btn>
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
import { useRouter } from 'vue-router'
import { useAppStore } from '@/stores/app'
import { useHutStore } from '@/stores/huts'
import { mande } from 'mande'
import { convertFileToBase64 } from '@/utilities.js'
import MilkdownEditor from '@/components/MilkdownEditor.vue'

const props = defineProps({
    hut: {
        type: Object,
        required: false,
        default: null
    },
})

const userStore = useAppStore()
const hutStore = useHutStore()
const router = useRouter()
const clubsApi = mande('/api/admin/clubs')

const dialog = ref(false)
const valid = ref(false)
const clubs = ref([])
const loadingClubs = ref(false)

const defaultHut = {
    name: '',
    description: '',
    external_url: '',
    booking_info: '',
    location_lat: null,
    location_lng: null,
    club_id: null,
    amenities: [],
    reciprocal_clubs: [],
    image: null
}

const editedHut = ref({ ...defaultHut })

const isNew = computed(() => !props.hut)

const canEdit = computed(() => {
    return userStore.user.is_admin
})

onMounted(async () => {
    fetchClubs()
})

const fetchClubs = async () => {
    loadingClubs.value = true
    try {
        const response = await clubsApi.get()
        clubs.value = response.data || response
    } catch (e) {
        console.error('Error fetching clubs', e)
    } finally {
        loadingClubs.value = false
    }
}

watch(() => props.hut, (newVal) => {
    if (newVal) {
        editedHut.value = JSON.parse(JSON.stringify(newVal))
        // Ensure amenities is an array (sometimes it might be null from API)
        if (!editedHut.value.amenities) editedHut.value.amenities = []
        if (newVal.reciprocal_clubs) {
            editedHut.value.reciprocal_clubs = newVal.reciprocal_clubs.map(c => c.id)
        } else {
            editedHut.value.reciprocal_clubs = []
        }
    } else {
        editedHut.value = { ...defaultHut }
    }
}, { immediate: true })

const updateDescription = (event) => {
    if (event && event.markdown) {
        editedHut.value.description = event.markdown;
    }
}

const close = () => {
    dialog.value = false
    if (props.hut) {
        editedHut.value = JSON.parse(JSON.stringify(props.hut))
        if (!editedHut.value.amenities) editedHut.value.amenities = []
        if (props.hut.reciprocal_clubs) {
            editedHut.value.reciprocal_clubs = props.hut.reciprocal_clubs.map(c => c.id)
        } else {
            editedHut.value.reciprocal_clubs = []
        }
    } else {
        editedHut.value = { ...defaultHut }
    }
}

const save = async () => {
    try {
        if (editedHut.value.image instanceof File) {
            editedHut.value.image = await convertFileToBase64(editedHut.value.image)
        } else if (Array.isArray(editedHut.value.image) && editedHut.value.image.length > 0 && editedHut.value.image[0] instanceof File) {
            // Vuetify file input can return an array
            editedHut.value.image = await convertFileToBase64(editedHut.value.image[0])
        }

        if (isNew.value) {
            await hutStore.createHut(editedHut.value)
        } else {
            await hutStore.updateHut(editedHut.value)
        }

        // Refresh list
        await hutStore.fetchHuts()
        if (!isNew.value) {
            await hutStore.fetchHut(editedHut.value.id)
        }

        dialog.value = false
    } catch (e) {
        console.error(e)
        alert('Failed to save: ' + e.message)
    }
}

const deleting = ref(false)

const deleteHut = async () => {
    if (!confirm('Are you certain you want to delete this hut? This action cannot be undone.')) {
        return;
    }

    deleting.value = true;
    try {
        await hutStore.deleteHut(editedHut.value.id);
        dialog.value = false;
        await hutStore.fetchHuts();
        router.push('/huts')
    } catch (e) {
        console.error(e);
        alert('Failed to delete: ' + e.message);
    } finally {
        deleting.value = false;
    }
}
</script>
