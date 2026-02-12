<template>
  <v-form ref="form" v-model="valid">
    <v-row>
      <v-col cols="12">
        <v-text-field v-model="internalCollection.name" label="Name" required :rules="[v => !!v || 'Name is required']" />
      </v-col>
      <v-col cols="12">
        <div class="text-subtitle-2 mb-1">Description</div>
        <MilkdownEditor v-model="internalCollection.description" placeholder="About this collection..." />
      </v-col>
      <v-col cols="12">
        <v-file-input v-model="photoFile" label="Photo" accept="image/*" prepend-icon="mdi-camera"
                      show-size @change="onPhotoChange" />
        <div v-if="internalCollection.photo_path && !photoFile" class="text-caption">
          Current photo: {{ internalCollection.photo_path }}
        </div>
      </v-col>
    </v-row>

    <v-divider class="my-4" />
    <div class="text-h6 mb-2">Manage Caves</div>

    <v-autocomplete v-model="selectedCaveToAdd" :items="allCaves" item-title="name" item-value="id"
                    label="Add a Cave" placeholder="Search for a cave..." return-object hide-details
                    class="mb-4" @update:model-value="addCave" />

    <v-list density="compact" class="bg-grey-lighten-4 rounded">
      <template v-for="(cave, index) in internalCollection.caves" :key="cave.id">
        <v-list-item>
          <template #prepend>
            <div class="d-flex flex-column">
              <v-btn icon="mdi-chevron-up" variant="text" size="x-small"
                     :disabled="index === 0" @click="moveCave(index, -1)" />
              <v-btn icon="mdi-chevron-down" variant="text" size="x-small"
                     :disabled="index === internalCollection.caves.length - 1"
                     @click="moveCave(index, 1)" />
            </div>
          </template>
                    
          <v-list-item-title class="font-weight-bold">{{ cave.name }}</v-list-item-title>
          <v-list-item-subtitle>{{ cave.location_name }}</v-list-item-subtitle>
                    
          <div class="text-subtitle-2 mt-4 mb-1">Note</div>
          <MilkdownEditor v-model="cave.playlist_description" 
                          placeholder="Add a note about this cave in the collection..." />

          <template #append>
            <v-btn icon="mdi-delete" size="small" color="error" variant="text"
                   @click="removeCave(index)" />
          </template>
        </v-list-item>
        <v-divider v-if="index < internalCollection.caves.length - 1" />
      </template>
            
      <v-list-item v-if="!internalCollection.caves || internalCollection.caves.length === 0">
        <div class="text-center w-100 text-grey font-italic">No caves in this collection</div>
      </v-list-item>
    </v-list>
  </v-form>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import { useCaveStore } from '@/stores/caves'
import MilkdownEditor from '@/components/MilkdownEditor.vue'
import { convertFileToBase64 } from '@/utilities.js'

const props = defineProps({
    modelValue: {
        type: Object,
        required: true
    }
})

const emit = defineEmits(['update:modelValue'])

const caveStore = useCaveStore()
const form = ref(null)
const valid = ref(false)
const internalCollection = ref(JSON.parse(JSON.stringify(props.modelValue)))
const photoFile = ref(null)
const selectedCaveToAdd = ref(null)

// Fetch all caves for autocomplete
const allCaves = computed(() => caveStore.caves)

onMounted(() => {
    if (caveStore.caves.length === 0) {
        caveStore.getList()
    }
})

// Helper to ensure data structure is correct
const ensureDataStructure = (collection) => {
    if (!collection.caves) {
        collection.caves = []
    }

    // Map pivot description if needed
    collection.caves.forEach(c => {
        if (c.pivot && !c.playlist_description) {
            c.playlist_description = c.pivot.description;
        }
    });
    return collection;
}

// Initialize immediately
ensureDataStructure(internalCollection.value)

// Deep watch for two-way binding
watch(() => props.modelValue, (newVal) => {
    if (JSON.stringify(newVal) !== JSON.stringify(internalCollection.value)) {
        internalCollection.value = ensureDataStructure(JSON.parse(JSON.stringify(newVal)))
    }
}, { deep: true })

watch(internalCollection, (newVal) => {
    emit('update:modelValue', newVal)
}, { deep: true })

const onPhotoChange = async (event) => {
    const file = event.target.files[0]
    if (file) {
        try {
            const result = await convertFileToBase64(file)
            internalCollection.value.photo_data = result.data // Store base64 for submission
            photoFile.value = file // Keep file reference if needed locally
        } catch (error) {
            console.error('Error converting file to base64:', error)
        }
    }
}

const addCave = (cave) => {
    if (!cave) return
    if (internalCollection.value.caves && internalCollection.value.caves.find(c => c.id === cave.id)) {
        selectedCaveToAdd.value = null;
        return;
    }

    if (!internalCollection.value.caves) internalCollection.value.caves = [];

    const newCave = { ...cave, playlist_description: '' };
    internalCollection.value.caves.push(newCave);
    selectedCaveToAdd.value = null;
}

const removeCave = (index) => {
    internalCollection.value.caves.splice(index, 1);
}

const moveCave = (index, direction) => {
    const newIndex = index + direction;
    if (newIndex >= 0 && newIndex < internalCollection.value.caves.length) {
        const item = internalCollection.value.caves.splice(index, 1)[0];
        internalCollection.value.caves.splice(newIndex, 0, item);
    }
}

// Expose validate method
defineExpose({
    validate: () => form.value.validate()
})
</script>
