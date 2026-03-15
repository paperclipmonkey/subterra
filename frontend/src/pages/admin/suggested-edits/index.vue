<template>
  <v-container>
    <div class="d-flex align-center justify-space-between mb-6">
      <h2 class="text-h4">Suggested Edits</h2>
      <v-btn icon variant="text" :loading="loading" @click="fetchItems">
        <v-icon :icon="mdiRefresh" />
      </v-btn>
    </div>

    <v-tabs v-model="activeTab" bg-color="transparent" color="primary" class="mb-6">
      <v-tab value="pending">Pending</v-tab>
      <v-tab value="approved">Approved</v-tab>
      <v-tab value="rejected">Rejected</v-tab>
    </v-tabs>

    <div v-if="loading" class="d-flex justify-center my-8">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <div v-else-if="items.length === 0" class="text-center my-12 text-grey">
      <v-icon size="64" class="mb-4" :icon="mdiFileDocumentOutline" />
      <div class="text-h6">No {{ activeTab }} suggestions found</div>
    </div>

    <v-row v-else>
      <v-col v-for="item in items" :key="item.id" cols="12" md="6">
        <v-card
          :to="`/admin/suggested-edits/${item.id}`"
          hover
          class="h-100 d-flex flex-column"
          variant="elevated"
        >
          <v-card-item class="pb-1">
            <template #prepend>
              <v-avatar color="primary" variant="tonal" rounded size="44">
                <v-icon color="primary" size="22">{{ getTypeIcon(item.suggestable_type) }}</v-icon>
              </v-avatar>
            </template>
            <v-card-title class="text-body-1 font-weight-bold text-truncate">
              {{ getEntityName(item) }}
            </v-card-title>
            <v-card-subtitle class="mt-1">
              <v-chip size="x-small" :color="isNewItem(item) ? 'success' : 'blue-darken-1'" variant="tonal" class="mr-1">
                {{ isNewItem(item) ? '✨ New Item' : formatType(item.suggestable_type) }}
              </v-chip>
            </v-card-subtitle>
            <template #append>
              <v-chip
                size="small"
                :color="item.status === 'approved' ? 'success' : item.status === 'rejected' ? 'error' : 'warning'"
                variant="tonal"
              >
                {{ item.status }}
              </v-chip>
            </template>
          </v-card-item>

          <v-card-text class="flex-grow-1 pt-2">
            <!-- Name change: old → new -->
            <div v-if="getNameChange(item)" class="d-flex align-center mb-3 pa-2 rounded bg-grey-lighten-4">
              <span class="text-body-2 text-decoration-line-through text-error text-truncate" style="max-width: 42%">{{ getNameChange(item).old }}</span>
              <v-icon size="16" class="mx-2 flex-shrink-0 text-grey" :icon="mdiArrowRight" />
              <span class="text-body-2 text-success font-weight-medium text-truncate" style="max-width: 42%">{{ getNameChange(item).new }}</span>
            </div>

            <!-- Description snippet -->
            <p v-if="getDescriptionSnippet(item)" class="text-body-2 text-grey-darken-2 mb-3 description-preview">
              {{ getDescriptionSnippet(item) }}
            </p>

            <!-- Changed field chips -->
            <div v-if="getChangedFields(item).length" class="d-flex flex-wrap gap-1 mb-3">
              <v-chip
                v-for="field in getChangedFields(item).slice(0, 4)"
                :key="field"
                size="x-small"
                color="primary"
                variant="outlined"
              >
                {{ field }}
              </v-chip>
              <v-chip v-if="getChangedFields(item).length > 4" size="x-small" variant="outlined" color="grey-darken-1">
                +{{ getChangedFields(item).length - 4 }} more
              </v-chip>
            </div>

            <!-- Submitter + timestamp -->
            <div class="d-flex align-center">
              <v-avatar size="22" class="mr-2 flex-shrink-0" color="grey-lighten-2">
                <span v-if="item.user?.name" style="font-size: 10px; font-weight: bold;">{{ item.user.name.charAt(0).toUpperCase() }}</span>
                <span v-else style="font-size: 10px;">🤖</span>
              </v-avatar>
              <span class="text-caption text-grey-darken-1">{{ item.user?.name || '🤖 Robot' }}</span>
              <span class="text-caption text-grey mx-1">·</span>
              <span class="text-caption text-grey">{{ new Date(item.created_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) }}</span>
            </div>
          </v-card-text>

          <v-divider />

          <v-card-actions class="py-2">
            <v-spacer />
            <span class="text-caption text-primary font-weight-bold mr-1">
              {{ item.status === 'pending' ? 'REVIEW' : 'VIEW DETAILS' }}
            </span>
            <v-icon color="primary" size="small" :icon="mdiArrowRight" />
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { mdiArrowRight, mdiFileDocument, mdiFileDocumentOutline, mdiFolderMultipleImage, mdiImageFilterHdr, mdiMapMarkerPath, mdiRefresh } from '@mdi/js'
import { ref, onMounted, watch } from 'vue'
import { api } from '@/plugins/api.js'

const loading = ref(false)
const items = ref([])
const activeTab = ref('pending')

const IGNORED_KEYS = new Set([
  'id', 'created_at', 'updated_at', 'deleted_at', 'slug', 'user_id',
  'suggestable_id', 'suggestable_type', 'photo_data', 'name', 'description',
  'trips', 'visits', 'collections', 'is_ticked', 'previously_done', 'cave_system_id',
  'hero_image', 'entrance_image', 'photo_path',
])

const formatType = (type) => {
  return type?.split('\\').pop() || 'Unknown'
}

const getTypeIcon = (type) => {
  const t = formatType(type).toLowerCase()
  if (t.includes('cave')) return mdiImageFilterHdr
  if (t.includes('route')) return mdiMapMarkerPath
  if (t.includes('collection')) return mdiFolderMultipleImage
  return mdiFileDocument
}

const isNewItem = (item) => !item.suggestable_id

const getEntityName = (item) => {
  return item.suggestable?.name
    || item.suggested_data?.name
    || `${formatType(item.suggestable_type)} #${item.suggestable_id || 'New'}`
}

const getNameChange = (item) => {
  const oldName = item.suggestable?.name
  const newName = item.suggested_data?.name
  if (!newName || !oldName || oldName === newName) return null
  return { old: oldName, new: newName }
}

const getDescriptionSnippet = (item) => {
  const suggested = item.suggested_data?.description
  const baseline = item.suggestable?.description || item.original_data?.description
  if (suggested && suggested !== baseline) {
    return suggested.replace(/<[^>]*>/g, '').substring(0, 140).trim() + (suggested.length > 140 ? '…' : '')
  }
  return null
}

const getChangedFields = (item) => {
  const suggested = item.suggested_data || {}
  const originalData = item.original_data || {}
  const suggestable = item.suggestable || {}
  const changed = []
  // Only iterate keys in suggested_data (what's actually being proposed)
  const keys = new Set([...Object.keys(suggested), ...Object.keys(originalData)])
  for (const key of keys) {
    if (IGNORED_KEYS.has(key)) continue
    // Per-key fallback: snapshot first, then live model
    const oldVal = (key in originalData) ? originalData[key] : suggestable[key]
    const newVal = suggested[key]
    if (JSON.stringify(oldVal) !== JSON.stringify(newVal)) {
      changed.push(key.replace(/_/g, ' '))
    }
  }
  return changed
}

const fetchItems = async () => {
  loading.value = true
  items.value = []
  try {
    const response = await api.get('/api/admin/suggested-edits', {
      params: { status: activeTab.value }
    })
    items.value = response.data.data
  } catch (error) {
    console.error('Error fetching suggestions:', error)
  } finally {
    loading.value = false
  }
}

watch(activeTab, () => {
  fetchItems()
})

onMounted(() => {
  fetchItems()
})
</script>

<style scoped>
.description-preview {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
