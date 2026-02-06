<template>
  <v-container>
    <v-form ref="form" @submit.prevent="save">
      <v-text-field
        v-model="route.name"
        label="Route Name"
        :rules="[v => !!v || 'Name is required']"
        required
      ></v-text-field>

      <div class="mb-4">
        <label class="v-label">Hero Image</label>
        <v-file-input
            accept="image/*"
            label="Upload Hero Image"
            prepend-icon="mdi-camera"
            @change="handleHeroImageUpload"
            show-size
            density="compact"
        ></v-file-input>
        <v-img
            v-if="route.hero_image"
            :src="route.hero_image"
            max-height="200"
            cover
            class="rounded mt-2 bg-grey-lighten-2"
        ></v-img>
      </div>

      <v-autocomplete
        v-model="route.entrance_id"
        :items="caves"
        item-title="name"
        item-value="id"
        label="Entrance Cave"
        clearable
      ></v-autocomplete>

      <v-autocomplete
        v-model="route.exit_id"
        :items="caves"
        item-title="name"
        item-value="id"
        label="Exit Cave"
        clearable
      ></v-autocomplete>

      <v-select
        v-model="route.grade"
        :items="[
            { value: 1, title: 'Grade 1', props: { subtitle: 'Easy walking, no tackle' } },
            { value: 2, title: 'Grade 2', props: { subtitle: 'Easy caving, some crawling' } },
            { value: 3, title: 'Grade 3', props: { subtitle: 'Moderate, vertical/water possible' } },
            { value: 4, title: 'Grade 4', props: { subtitle: 'Difficult, significant vertical/water' } },
            { value: 5, title: 'Grade 5', props: { subtitle: 'Severe, expert only' } }
        ]"
        label="Grade"
        clearable
      ></v-select>

      <v-combobox
        v-model="route.duration"
        :items="['1-2 hours', '2-4 hours', '4-6 hours', 'Full Day']"
        label="Duration"
      ></v-combobox>

      <div class="text-caption mb-2 text-medium-emphasis">Description (Markdown)</div>
      <MilkdownEditor 
        v-model="route.description" 
        placeholder="Describe the route..."
        class="mb-4"
      />

      <h3 class="text-h6 mt-4 mb-2">Tackle</h3>
      <div v-for="(tackle, index) in route.tackle" :key="index" class="d-flex align-top gap-2 mb-4 border rounded pa-2">
        <v-row dense>
             <v-col cols="12" md="3">
                <v-select
                    v-model="tackle.type"
                    :items="[
                        { title: 'SRT Rope', value: 'srt_rope' }, 
                        { title: 'Handline', value: 'handline' }, 
                        { title: 'Lifeline', value: 'lifeline' },
                        { title: 'Ladder', value: 'ladder' }, 
                        { title: 'Sling', value: 'sling' }, 
                        { title: 'Karabiner', value: 'karabiner' },
                        { title: 'Rope Protector', value: 'rope_protector' }
                    ]"
                    label="Type"
                    density="compact"
                    :rules="[v => !!v || 'Type is required']"
                ></v-select>
             </v-col>
             <v-col cols="12" md="4">
                <v-text-field
                    v-model="tackle.description"
                    label="Description"
                    density="compact"
                    :rules="[v => !!v || 'Description is required']"
                ></v-text-field>
             </v-col>
             <v-col cols="6" md="2">
                <v-text-field
                    v-model.number="tackle.length"
                    label="Length (m)"
                    type="number"
                    density="compact"
                ></v-text-field>
             </v-col>
             <v-col cols="6" md="2">
                 <v-checkbox
                    v-model="tackle.optional"
                    label="Optional"
                    density="compact"
                 ></v-checkbox>
             </v-col>
             <v-col cols="1" class="d-flex align-center">
                 <v-btn icon="mdi-delete" size="small" color="error" variant="text" @click="removeTackle(index)"></v-btn>
             </v-col>
        </v-row>
      </div>
      <v-btn prepend-icon="mdi-plus" variant="tonal" size="small" @click="addTackle" class="mb-4">Add Tackle</v-btn>

      <v-divider class="my-4"></v-divider>

      <h3 class="text-h6 mb-2">Media</h3>
      <div class="mb-4">
          <v-file-input
            label="Upload Photos/PDFs"
            multiple
            chips
            prepend-icon="mdi-camera"
            @change="handleMediaUpload"
            show-size
          ></v-file-input>
          
          <v-list v-if="newMedia.length > 0">
              <v-list-item v-for="(item, i) in newMedia" :key="i">
                  <template v-slot:prepend>
                      <v-icon v-if="item.type === 'pdf'">mdi-file-pdf-box</v-icon>
                      <v-img v-else :src="item.data" width="40" height="40" cover class="mr-2 rounded"></v-img>
                  </template>
                  <v-list-item-title>
                      <v-text-field 
                        v-model="item.caption" 
                        label="Caption" 
                        density="compact" 
                        hide-details
                        variant="underlined"
                      ></v-text-field>
                  </v-list-item-title>
                  <template v-slot:append>
                      <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="newMedia.splice(i, 1)"></v-btn>
                  </template>
              </v-list-item>
          </v-list>

          <div v-if="route.media && route.media.length > 0" class="mt-4">
              <h4 class="text-subtitle-2 mb-2">Existing Media</h4>
              <v-row dense>
                  <v-col v-for="(media, index) in route.media" :key="media.id" cols="12" sm="4" md="3">
                      <v-card variant="outlined">
                          <v-img :src="media.path" height="100" cover v-if="media.type !== 'pdf'"></v-img>
                          <div v-else class="d-flex align-center justify-center bg-grey-lighten-3" style="height: 100px;">
                               <v-icon size="large">mdi-file-pdf-box</v-icon>
                          </div>
                          <v-card-text class="pa-2 text-caption text-truncate d-flex justify-space-between align-center">
                              <span>{{ media.caption || 'No caption' }}</span>
                              <v-btn 
                                icon="mdi-delete" 
                                size="x-small" 
                                color="error" 
                                variant="text"
                                @click="markMediaForDeletion(index, media.id)"
                              ></v-btn>
                          </v-card-text>
                      </v-card>
                  </v-col>
              </v-row>
          </div>
      </div>

      <v-divider class="my-4"></v-divider>
      
      <v-btn type="submit" color="primary" block :loading="loading">Save Route</v-btn>
    </v-form>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import MilkdownEditor from '@/components/MilkdownEditor.vue'
import { convertFileToBase64 } from '@/utilities.js'

const props = defineProps({
    initialRoute: {
        type: Object,
        default: () => ({
            name: '',
            entrance_id: null,
            exit_id: null,
            grade: null,
            duration: '',
            description: '',
            tackle: []
        })
    },
    caveSystemId: {
        type: [String, Number],
        required: true
    }
})

const emit = defineEmits(['saved'])

const form = ref(null)
const loading = ref(false)
const route = ref({ ...props.initialRoute })
const caves = ref([])
const newMedia = ref([])
const deletedMediaIds = ref([])

const handleHeroImageUpload = async (event) => {
    const file = event.target.files[0]
    if (file) {
        try {
            const result = await convertFileToBase64(file)
            route.value.hero_image = result.data
        } catch (error) {
            console.error('Error converting file to base64:', error)
        }
    }
}

const handleMediaUpload = async (event) => {
    const files = Array.from(event.target.files)
    for (const file of files) {
        try {
            const result = await convertFileToBase64(file)
            // simple type detection based on extension or mime if available in result (it is not, strictly)
            // let's infer type from file.type
            const type = file.type === 'application/pdf' ? 'pdf' : 'photo'
            newMedia.value.push({
                data: result.data,
                caption: '',
                type: type,
                file_name: file.name
            })
        } catch (error) {
            console.error('Error processing media file:', error)
        }
    }
    // Clear input to allow re-selecting same files if needed? 
    // event.target.value = '' 
}

onMounted(async () => {
    // ... items
    try {
        const response = await fetch(`/api/cave_systems/${props.caveSystemId}`)
        if (response.ok) {
            const system = await response.json()
            caves.value = system.data.caves || []
        }
    } catch (e) {
        console.error(e)
    }
})

const addTackle = () => {
    if (!route.value.tackle) route.value.tackle = []
    route.value.tackle.push({
        type: 'srt_rope',
        description: '',
        length: null,
        optional: false,
        quantity: 1
    })
}

const removeTackle = (index) => {
    route.value.tackle.splice(index, 1)
}

const markMediaForDeletion = (index, id) => {
    route.value.media.splice(index, 1)
    deletedMediaIds.value.push(id)
}

const save = async () => {
    const { valid } = await form.value.validate()
    if (!valid) return

    loading.value = true
    try {
        const url = route.value.id
            ? `/api/routes/${route.value.id}`
            : `/api/cave_systems/${props.caveSystemId}/routes`

        const method = route.value.id ? 'PUT' : 'POST'

        const payload = {
            ...route.value,
            media: newMedia.value, // Add new media to payload
            deleted_media: deletedMediaIds.value // Send deleted media IDs
        }
        
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })

        if (response.ok) {
            emit('saved')
        } else {
            console.error('Failed to save')
        }
    } catch (error) {
        console.error(error)
    } finally {
        loading.value = false
    }
}
</script>
