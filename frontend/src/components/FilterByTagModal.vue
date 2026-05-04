<template>
  <v-dialog :model-value="props.isActive" max-width="500" @update:model-value="$emit('close')">
    <template #default>
      <v-card rounded="lg">
        <v-card-title class="d-flex justify-space-between align-center">
          <div class="text-h5 text-medium-emphasis ps-2">
            Filter
          </div>
          <v-btn
            :icon="mdiClose"
            variant="text"
            @click="$emit('close')"
          />
        </v-card-title>

        <v-divider class="mb-4" />

        <v-card-text class="pa-0" style="max-height: 70vh; overflow-y: auto;">
          <template v-for="(groupItems, groupName) in tagsAvailable" :key="groupName">
            <div :ref="el => setCategoryRef(el, groupName)" class="pa-4 pt-2">
              <div class="d-flex align-center justify-space-between mb-2">
                <h2 class="text-h6 tagGroupTitle">{{ groupName }}</h2>
                <v-chip v-if="getSelectedCount(groupName)" size="x-small" color="primary" variant="flat">
                  {{ getSelectedCount(groupName) }} Selected
                </v-chip>
              </div>

              <v-chip-group
                v-model="selectedTags[groupName]"
                column
                :multiple="!isSingleSelect(groupName)"
              >
                <v-chip
                  v-for="tag in groupItems"
                  :key="tag.tag"
                  :text="tag.tag"
                  variant="outlined"
                  filter
                  size="small"
                  :value="tag.tag"
                  :title="tag.description"
                  selected-class="text-primary border-primary"
                />
              </v-chip-group>
              <v-divider v-if="Object.keys(tagsAvailable).indexOf(groupName) < Object.keys(tagsAvailable).length - 1" class="mt-4" />
            </div>
          </template>
        </v-card-text>
        <v-divider class="mt-2" />

        <v-card-actions class="my-2 d-flex justify-end">
          <v-btn
            class="text-none"
            rounded="xl"
            text="Cancel"
            @click="$emit('close')"
          />

          <v-btn
            class="text-none"
            color="primary"
            rounded="xl"
            text="Filter"
            variant="flat"
            @click="emitFilters"
          />
        </v-card-actions>
      </v-card>
    </template>
  </v-dialog>
</template>

<script setup>
import { mdiClose } from '@mdi/js'

import { ref, computed, defineProps, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useTagStore } from '@/stores/tags'

const route = useRoute()
const tagStore = useTagStore()
const emit = defineEmits(['filter', 'close'])

const props = defineProps({
  isActive: Boolean,
  loadedFilters: { type: Array, default: () => [] },
  targetCategory: { type: String, default: null }
})

const tagsAvailable = computed(() => tagStore.tags)
const selectedTags = ref({})
const categoryRefs = ref({})

const setCategoryRef = (el, name) => {
  if (el) categoryRefs.value[name] = el
}

onMounted(async () => {
  await tagStore.fetchTags()
  const pageLoadedTags = route.query.tags ? route.query.tags.split(',') : []

  // Initialize selectedTags with the loaded filters
  for (const group in tagsAvailable.value) {
    const groupTags = tagsAvailable.value[group].filter(tag => pageLoadedTags.includes(tag.tag)).map(tag => tag.tag)
    if (isSingleSelect(group)) {
      selectedTags.value[group] = groupTags[0] || null
    } else {
      selectedTags.value[group] = groupTags
    }
  }
})

watch(() => props.isActive, (active) => {
  if (active) {
    // Sync state from URL whenever modal opens
    const pageLoadedTags = route.query.tags ? route.query.tags.split(',') : []
    for (const group in tagsAvailable.value) {
      const groupTags = tagsAvailable.value[group].filter(tag => pageLoadedTags.includes(tag.tag)).map(tag => tag.tag)
      if (isSingleSelect(group)) {
        selectedTags.value[group] = groupTags[0] || null
      } else {
        selectedTags.value[group] = groupTags
      }
    }

    if (props.targetCategory) {
      // Small timeout to ensure DOM is ready and modal animation finished
      setTimeout(() => {
        const el = categoryRefs.value[props.targetCategory]
        if (el) {
          el.scrollIntoView({ behavior: 'smooth', block: 'start' })
        }
      }, 200)
    }
  }
})

const isSingleSelect = (groupName) => {
  const singleSelectGroups = ['curated', 'region', 'access', 'previously done']
  return singleSelectGroups.includes(groupName.toLowerCase())
}

const emitFilters = () => {
  const filters = Object.values(selectedTags.value).flat().filter(Boolean)
  emit('filter', filters)
}

const getSelectedCount = (groupName) => {
  const selection = selectedTags.value[groupName]
  if (isSingleSelect(groupName)) {
    return selection ? 1 : 0
  }
  return selection?.length || 0
}
</script>

<style scoped>
.tagGroupTitle {
  text-transform: capitalize !important;
  scroll-margin-top: 10px;
}

.border-primary {
  border-color: rgb(var(--v-theme-primary)) !important;
}
</style>