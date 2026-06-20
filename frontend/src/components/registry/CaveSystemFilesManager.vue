<template>
  <div>
    <v-list v-if="files.length" density="compact" class="mb-3 border rounded">
      <v-list-item v-for="f in files" :key="f.id">
        <template #prepend>
          <v-avatar v-if="f.thumbnail_url" rounded size="40" class="mr-2">
            <v-img :src="f.thumbnail_url" cover />
          </v-avatar>
          <v-icon v-else class="mr-2" :icon="f.is_image ? mdiImage : mdiFileDocument" />
        </template>
        <v-list-item-title>{{ f.title || f.original_filename }}</v-list-item-title>
        <v-list-item-subtitle>
          {{ f.kind }} · {{ f.photographer || f.details || 'no credit' }}
        </v-list-item-subtitle>
        <template #append>
          <v-chip
            :color="f.visibility === 'private' ? 'deep-orange' : 'green'"
            size="x-small"
            variant="tonal"
            label
            class="mr-2"
          >
            {{ f.visibility }}
          </v-chip>
          <v-btn :icon="mdiDelete" size="x-small" variant="text" color="error" @click="remove(f)" />
        </template>
      </v-list-item>
    </v-list>
    <p v-else class="text-caption text-grey mb-3">No files yet.</p>

    <v-sheet class="pa-3 border rounded" color="grey-lighten-5">
      <div class="text-caption font-weight-medium mb-2">Add a file (survey, historic photo, document)</div>
      <v-file-input
        v-model="uploadFile"
        label="PDF or image"
        density="compact"
        variant="outlined"
        hide-details
        :prepend-icon="mdiUpload"
        class="mb-2"
      />
      <v-row dense>
        <v-col cols="6">
          <v-select v-model="uploadKind" :items="kindOptions" label="Kind" density="compact" variant="outlined" hide-details />
        </v-col>
        <v-col cols="6">
          <v-select v-model="uploadVisibility" :items="['public', 'private']" label="Visibility" density="compact" variant="outlined" hide-details />
        </v-col>
        <v-col cols="12">
          <v-text-field v-model="uploadTitle" label="Title / description" density="compact" variant="outlined" hide-details />
        </v-col>
        <v-col cols="6">
          <v-text-field v-model="uploadPhotographer" label="Photographer / credit" density="compact" variant="outlined" hide-details />
        </v-col>
        <v-col cols="6">
          <v-text-field v-model="uploadCopyright" label="Copyright" density="compact" variant="outlined" hide-details />
        </v-col>
      </v-row>
      <div class="d-flex justify-end mt-2">
        <v-btn
          color="indigo"
          size="small"
          :prepend-icon="mdiUpload"
          :disabled="!uploadFile"
          :loading="uploading"
          @click="upload"
        >
          Upload
        </v-btn>
      </div>
    </v-sheet>
  </div>
</template>

<script setup>
import { mdiDelete, mdiFileDocument, mdiImage, mdiUpload } from '@mdi/js'
import { onMounted, ref, watch } from 'vue'
import { api } from '@/plugins/api'

const props = defineProps({
  systemId: { type: [Number, String], default: null },
})

const kindOptions = ['photo', 'survey', 'document', 'historic', 'other']

const files = ref([])
const uploadFile = ref(null)
const uploadKind = ref('survey')
const uploadVisibility = ref('public')
const uploadTitle = ref('')
const uploadPhotographer = ref('')
const uploadCopyright = ref('')
const uploading = ref(false)

async function load() {
  if (!props.systemId) return
  const res = await api.get(`/api/cave_systems/${props.systemId}/files`)
  files.value = res.data.data
}

async function upload() {
  if (!uploadFile.value || !props.systemId) return
  uploading.value = true
  try {
    const form = new FormData()
    const file = Array.isArray(uploadFile.value) ? uploadFile.value[0] : uploadFile.value
    form.append('file', file)
    form.append('kind', uploadKind.value)
    form.append('visibility', uploadVisibility.value)
    if (uploadTitle.value) form.append('title', uploadTitle.value)
    if (uploadPhotographer.value) form.append('photographer', uploadPhotographer.value)
    if (uploadCopyright.value) form.append('copyright', uploadCopyright.value)
    // The shared axios instance defaults to application/json; multipart must be
    // set explicitly or the file field never reaches the server.
    await api.post(`/api/cave_systems/${props.systemId}/files`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    uploadFile.value = null
    uploadTitle.value = ''
    uploadPhotographer.value = ''
    uploadCopyright.value = ''
    await load()
  } finally {
    uploading.value = false
  }
}

async function remove(f) {
  await api.delete(`/api/cave_systems/${props.systemId}/files/${f.id}`)
  await load()
}

watch(() => props.systemId, load)
onMounted(load)
</script>
