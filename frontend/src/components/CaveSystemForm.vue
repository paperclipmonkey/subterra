<template>
  <v-card class="mb-4">
    <v-card-title>
      <v-text-field
        v-model="internalSystem.name"
        label="Cave System Name"
        :rules="[v => !!v || 'Name is required']"
        required
      />
    </v-card-title>
    <v-card-text>
      <div class="text-subtitle-2 mt-4 mb-1">System description (Optional)</div>
      <MilkdownEditor
        v-model="internalSystem.description"
        placeholder="Detailed description of the cave system..."
        class="mb-4"
      />
      <v-row>
        <v-col cols="12" md="6">
          <v-text-field
            v-model.number="internalSystem.length"
            label="Length (m)"
            type="number"
            :rules="[v => !!v || 'Length is required']"
            required
          />
        </v-col>
        <v-col cols="12" md="6">
          <v-text-field
            v-model.number="internalSystem.vertical_range"
            label="Vertical Range (m)"
            type="number"
            :rules="[v => v !== null && v !== undefined || 'Vertical range is required']"
            required
          />
        </v-col>
      </v-row>
      <v-text-field
        v-model="internalSystem.slug"
        label="URL Slug"
        :rules="[v => !!v || 'Slug is required']"
        required
        :hint="'Lowercase, a-z, 0-9, _ and - only'"
        persistent-hint
      />

      <v-select
        v-model="internalSystem.catchment_id"
        :items="catchments"
        item-title="name"
        item-value="id"
        label="Catchment (River Levels)"
        clearable
        class="mt-4"
      />
      <div class="text-subtitle-2 mt-4 mb-1">References</div>
      <MilkdownEditor
        v-model="internalSystem.references"
        placeholder="References, sources, etc."
        class="mb-4"
      />
    </v-card-text>

    <!-- Files Section (Optional, only show if files exist or are being added) -->
    <v-card-text v-if="showFiles">
      <h3 class="text-h6 mb-2">Files</h3>
      <v-list v-if="internalSystem.files && internalSystem.files.length > 0" lines="one">
        <v-list-item
          v-for="file in internalSystem.files"
          :key="file.id"
          :title="file.original_filename || file.filename"
          :subtitle="`${(file.size / 1024).toFixed(2)} KB`"
          :class="{ 'file-marked-for-deletion': filesToDelete.includes(file.id) }"
        >
          <template #append>
            <v-btn
              color="red"
              icon="mdi-delete"
              variant="text"
              size="small"
              @click="toggleFileDeletion(file.id)"
            />
          </template>
        </v-list-item>
      </v-list>
      <v-file-input
        v-model="newFiles"
        label="Upload New Files"
        multiple
        chips
        show-size
        counter
        prepend-icon="mdi-paperclip"
      />

      <v-alert
        type="info"
        variant="tonal"
        density="compact"
        class="mt-4 text-caption"
      >
        By submitting this data, you confirm that you have the right to share this information and media, and that it does not infringe on any third-party rights.
      </v-alert>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import MilkdownEditor from '@/components/MilkdownEditor.vue'

const props = defineProps({
  modelValue: {
    type: Object,
    required: true
  },
  showFiles: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'update:filesToDelete', 'update:newFiles'])

const internalSystem = ref({ ...props.modelValue })
const filesToDelete = ref([])
const newFiles = ref([])

watch(() => props.modelValue, (newVal) => {
  if (JSON.stringify(newVal) !== JSON.stringify(internalSystem.value)) {
    internalSystem.value = { ...newVal }
  }
}, { deep: true })

watch(internalSystem, (newVal) => {
  if (JSON.stringify(newVal) !== JSON.stringify(props.modelValue)) {
    emit('update:modelValue', newVal)
  }
}, { deep: true })

const toggleFileDeletion = (fileId) => {
  const index = filesToDelete.value.indexOf(fileId)
  if (index > -1) {
    filesToDelete.value.splice(index, 1)
  } else {
    filesToDelete.value.push(fileId)
  }
  emit('update:filesToDelete', filesToDelete.value)
}

watch(newFiles, (newVal) => {
  emit('update:newFiles', newVal)
})

const slugify = (value) => {
  if (!value) return ''
  return value
    .toLowerCase()
    .replace(/[^a-z0-9_-]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .replace(/--+/g, '-')
}

// Auto-slugify name if slug is empty
watch(() => internalSystem.value.name, (newName) => {
  if (!internalSystem.value.slug && newName) {
    internalSystem.value.slug = slugify(newName)
  }
})

// Watch slug to ensure valid format
watch(() => internalSystem.value.slug, (newSlug) => {
  if (newSlug) {
    internalSystem.value.slug = slugify(newSlug)
  }
})

const catchments = ref([])

onMounted(async () => {
  try {
    const response = await fetch('/api/admin/catchments')
    if (response.ok) {
      const json = await response.json()
      catchments.value = json.data
    }
  } catch (e) {
    console.error('Failed to fetch catchments', e)
  }
})
</script>

<style scoped>
.file-marked-for-deletion {
  opacity: 0.6;
  text-decoration: line-through;
}
</style>
