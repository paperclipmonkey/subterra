<template>
  <v-container>
    <v-form ref="form" @submit.prevent="save">
      <v-text-field
        v-model="route.name"
        label="Route Name"
        :rules="[v => !!v || 'Name is required']"
        required
      />

      <div class="mb-4">
        <label class="v-label">Hero Image</label>
        <v-file-input
          accept="image/*"
          label="Upload Hero Image"
          :prepend-icon="mdiCamera"
          show-size
          density="compact"
          @change="handleHeroImageUpload"
        />
        <v-img
          v-if="route.hero_image"
          :src="route.hero_image"
          max-height="200"
          cover
          class="rounded mt-2 bg-grey-lighten-2"
        />
      </div>

      <v-autocomplete
        v-if="caves.length > 1"
        v-model="route.entrance_id"
        :items="caves"
        item-title="name"
        item-value="id"
        label="Entrance Cave"
        clearable
        autocomplete="off"
      />

      <v-autocomplete
        v-if="caves.length > 1"
        v-model="route.exit_id"
        :items="caves"
        item-title="name"
        item-value="id"
        label="Exit Cave"
        clearable
        autocomplete="off"
      />

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
      />

      <v-combobox
        v-model="route.duration"
        :items="['1-2 hours', '2-4 hours', '4-6 hours', 'Full Day']"
        label="Duration"
        autocomplete="off"
      />

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
            />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="tackle.description"
              label="Description"
              density="compact"
              :rules="[v => !!v || 'Description is required']"
            />
          </v-col>
          <v-col cols="6" md="2">
            <v-text-field
              v-model.number="tackle.length"
              label="Length (m)"
              type="number"
              density="compact"
            />
          </v-col>
          <v-col cols="6" md="2">
            <v-checkbox
              v-model="tackle.optional"
              label="Optional"
              density="compact"
            />
          </v-col>
          <v-col cols="1" class="d-flex align-center">
            <v-btn :icon="mdiDelete" size="small" color="error" variant="text" @click="removeTackle(index)" />
          </v-col>
        </v-row>
      </div>
      <v-btn :prepend-icon="mdiPlus" variant="tonal" size="small" class="mb-4" @click="addTackle">Add Tackle</v-btn>

      <v-divider class="my-4" />

      <h3 class="text-h6 mb-2">Media</h3>
      <div class="mb-4">
        <v-file-input
          label="Upload Photos/PDFs"
          multiple
          chips
          :prepend-icon="mdiCamera"
          show-size
          @change="handleMediaUpload"
        />
          
        <v-list v-if="newMedia.length > 0">
          <v-list-item v-for="(item, i) in newMedia" :key="i">
            <template #prepend>
              <v-icon v-if="item.type === 'pdf'" :icon="mdiFilePdfBox" />
              <v-img v-else :src="item.data" width="40" height="40" cover class="mr-2 rounded" />
            </template>
            <v-list-item-title>
              <v-text-field 
                v-model="item.caption" 
                label="Caption" 
                density="compact" 
                hide-details
                variant="underlined"
              />
            </v-list-item-title>
            <template #append>
              <v-btn :icon="mdiDelete" size="small" variant="text" color="error" @click="newMedia.splice(i, 1)" />
            </template>
          </v-list-item>
        </v-list>

        <div v-if="route.media && route.media.length > 0" class="mt-4">
          <h4 class="text-subtitle-2 mb-2">Existing Media</h4>
          <v-row dense>
            <v-col v-for="(media, index) in route.media" :key="media.id" cols="12" sm="4" md="3">
              <v-card variant="outlined">
                <v-img v-if="media.type !== 'pdf'" :src="media.path" height="100" cover />
                <div v-else class="d-flex align-center justify-center bg-grey-lighten-3" style="height: 100px;">
                  <v-icon size="large" :icon="mdiFilePdfBox" />
                </div>
                <v-card-text class="pa-2 text-caption text-truncate d-flex justify-space-between align-center">
                  <span>{{ media.caption || 'No caption' }}</span>
                  <v-btn 
                    :icon="mdiDelete" 
                    size="x-small" 
                    color="error" 
                    variant="text"
                    @click="markMediaForDeletion(index, media.id)"
                  />
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
        </div>
      </div>

      <v-alert
        type="info"
        variant="tonal"
        density="compact"
        class="mb-4 text-caption"
      >
        By submitting this data, you confirm that you have the right to share this information and media, and that it does not infringe on any third-party rights.
      </v-alert>

      <v-divider class="my-4" />
      
      <v-btn type="submit" color="primary" block :loading="loading">
        {{ appStore.user?.is_admin ? 'Save Route' : 'Suggest Changes' }}
      </v-btn>
    </v-form>
  </v-container>
</template>

<script setup>
import { mdiCamera, mdiDelete, mdiFilePdfBox, mdiPlus } from '@mdi/js'
import { ref, onMounted, computed, watch } from 'vue'
import MilkdownEditor from '@/components/MilkdownEditor.vue'
import { convertFileToBase64 } from '@/utilities'
import { useAppStore } from '@/stores/app'
import { useNotificationStore } from '@/stores/notifications'
import { onBeforeRouteLeave } from 'vue-router'
import { api } from '@/plugins/api'

const notifications = useNotificationStore()
const appStore = useAppStore()

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
  },
  preventSubmit: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['saved', 'submit'])

const form = ref(null)
const loading = ref(false)
const route = ref({ ...props.initialRoute })
const caves = ref([])
const newMedia = ref([])
const deletedMediaIds = ref([])
const isSaved = ref(false)

const isDirty = computed(() => {
  if (isSaved.value) return false
  return JSON.stringify(route.value) !== JSON.stringify(props.initialRoute) ||
    newMedia.value.length > 0 ||
    deletedMediaIds.value.length > 0
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
    const response = await api.get(`/api/cave_systems/${props.caveSystemId}`)
    caves.value = response.data.data.caves || []

    // Auto-prefill if only one cave
    if (caves.value.length === 1) {
      route.value.entrance_id = caves.value[0].id
      route.value.exit_id = caves.value[0].id
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
    const payload = {
      ...route.value,
      media: newMedia.value, // Add new media to payload
      deleted_media: deletedMediaIds.value // Send deleted media IDs
    }

    if (props.preventSubmit) {
      emit('submit', payload)
      loading.value = false
      return
    }

    if (appStore.user?.is_admin) {
      const url = route.value.id
        ? `/api/routes/${route.value.slug}`
        : `/api/cave_systems/${props.caveSystemId}/routes`

      const method = route.value.id ? 'put' : 'post'

      await api[method](url, payload)

      isSaved.value = true
      emit('saved')
    } else {
      // Suggest Edit or Create
      await api.post('/api/suggested-edits', {
        suggestable_type: 'route',
        suggestable_id: route.value.id || null, // null means creation
        suggested_data: { ...payload, cave_system_id: props.caveSystemId },
        original_data: null
      })

      notifications.showSuccess('Thank you! Your suggestion has been submitted for review.')
      isSaved.value = true
      emit('saved')
    }
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
}
</script>
