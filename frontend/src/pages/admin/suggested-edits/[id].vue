<template>
  <v-container>
    <v-btn icon class="mb-4" @click="$router.go(-1)">
      <v-icon :icon="mdiArrowLeft" />
    </v-btn>
    
    <div v-if="suggestion">
      <div class="d-flex align-center justify-space-between mb-4">
        <h2 class="text-h4">Review Suggestion #{{ suggestion.id }}</h2>
        <v-btn
          v-if="viewUrl"
          :href="viewUrl"
          target="_blank"
          :prepend-icon="mdiOpenInNew"
          variant="text"
          color="primary"
        >
          View Live
        </v-btn>
      </div>
      <div class="text-subtitle-1 mb-4">
        By <router-link v-if="suggestion.user" :to="`/profile/${suggestion.user.id}`" class="text-decoration-none font-weight-bold text-primary">{{ suggestion.user.name }}</router-link><span v-else>Unknown User</span> for {{ formatType(suggestion.suggestable_type) }} #{{ suggestion.suggestable_id }}
      </div>

      <v-alert
        v-if="suggestion.status !== 'pending'"
        :type="suggestion.status === 'approved' ? 'success' : 'error'"
        class="mb-4"
      >
        This suggestion has been {{ suggestion.status }}.
      </v-alert>

      <v-card v-if="changedFields.length > 0" class="mb-6">
        <v-card-title class="bg-grey-lighten-4 py-3">
          <v-icon start :icon="mdiCompare" />
          Changes
        </v-card-title>
        <v-divider />
        <v-list class="pa-0">
          <template v-for="(field, index) in changedFields" :key="field.key">
            <v-list-item class="pa-4">
              <div class="text-overline text-grey-darken-1 mb-1">{{ field.label }}</div>
                        
              <v-row v-if="field.isImage" no-gutters>
                <v-col cols="12" md="6" class="pa-2">
                  <v-card variant="outlined" class="pa-2">
                    <div class="text-caption font-weight-bold mb-2">ORIGINAL</div>
                    <v-img v-if="field.oldValue" :src="resolveUrl(field.oldValue)" height="200" cover class="rounded bg-grey-lighten-4" />
                    <div v-else class="text-body-2 text-grey-darken-1 font-italic">Empty</div>
                  </v-card>
                </v-col>
                <v-col cols="12" md="6" class="pa-2">
                  <v-card variant="outlined" color="success" class="pa-2">
                    <div class="text-caption font-weight-bold mb-2">SUGGESTED</div>
                    <v-img v-if="field.newValue" :src="resolveUrl(field.newValue)" height="200" cover class="rounded bg-grey-lighten-4" />
                    <div v-else class="text-body-2 text-grey-darken-1 font-italic">Empty</div>
                  </v-card>
                </v-col>
              </v-row>

              <v-row v-else-if="field.isTags" no-gutters>
                <v-col cols="12" md="6" class="pa-2">
                  <v-card variant="outlined" class="pa-2">
                    <div class="text-caption font-weight-bold mb-2">ORIGINAL</div>
                    <v-chip-group v-if="field.oldValue && field.oldValue.length">
                      <v-chip v-for="tag in field.oldValue" :key="tag.tag" size="small">{{ tag.tag }}</v-chip>
                    </v-chip-group>
                    <div v-else class="text-body-2 text-grey-darken-1 font-italic">Empty</div>
                  </v-card>
                </v-col>
                <v-col cols="12" md="6" class="pa-2">
                  <v-card variant="outlined" color="success" class="pa-2">
                    <div class="text-caption font-weight-bold mb-2">SUGGESTED</div>
                    <v-chip-group v-if="field.newValue && field.newValue.length">
                      <v-chip v-for="tag in field.newValue" :key="tag.tag" size="small">{{ tag.tag }}</v-chip>
                    </v-chip-group>
                    <div v-else class="text-body-2 text-grey-darken-1 font-italic">Empty</div>
                  </v-card>
                </v-col>
              </v-row>

              <v-row v-else-if="field.isCaves" no-gutters>
                <v-col cols="12" md="6" class="pa-2">
                  <v-card variant="outlined" class="pa-2">
                    <div class="text-caption font-weight-bold mb-2">ORIGINAL</div>
                    <v-list v-if="field.oldValue && field.oldValue.length" density="compact">
                      <v-list-item v-for="cave in field.oldValue" :key="cave.id" :title="`Cave #${cave.id}`" :subtitle="cave.description?.substring(0, 50) + (cave.description?.length > 50 ? '...' : '')" />
                    </v-list>
                    <div v-else class="text-body-2 text-grey-darken-1 font-italic">Empty</div>
                  </v-card>
                </v-col>
                <v-col cols="12" md="6" class="pa-2">
                  <v-card variant="outlined" color="success" class="pa-2">
                    <div class="text-caption font-weight-bold mb-2">SUGGESTED</div>
                    <v-list v-if="field.newValue && field.newValue.length" density="compact">
                      <v-list-item v-for="cave in field.newValue" :key="cave.id" :title="`Cave #${cave.id}`" :subtitle="cave.description?.substring(0, 50) + (cave.description?.length > 50 ? '...' : '')" />
                    </v-list>
                    <div v-else class="text-body-2 text-grey-darken-1 font-italic">Empty</div>
                  </v-card>
                </v-col>
              </v-row>

              <v-row v-else-if="field.isLongText" no-gutters>
                <v-col cols="12" class="mb-2">
                  <v-card variant="tonal" color="error" class="pa-2 text-body-2">
                    <div class="text-caption font-weight-bold mb-1">ORIGINAL</div>
                    {{ field.oldValue || '(Empty)' }}
                  </v-card>
                </v-col>
                <v-col cols="12">
                  <v-card variant="tonal" color="success" class="pa-2 text-body-2">
                    <div class="text-caption font-weight-bold mb-1">SUGGESTED</div>
                    {{ field.newValue || '(Empty)' }}
                  </v-card>
                </v-col>
              </v-row>
                        
              <v-row v-else no-gutters align="center">
                <v-col cols="5">
                  <span class="text-decoration-line-through text-error">{{ field.oldValue || '(Empty)' }}</span>
                </v-col>
                <v-col cols="2" class="text-center">
                  <v-icon :icon="mdiArrowRight" />
                </v-col>
                <v-col cols="5">
                  <span class="text-success font-weight-bold">{{ field.newValue || '(Empty)' }}</span>
                </v-col>
              </v-row>
            </v-list-item>
            <v-divider v-if="index < changedFields.length - 1" />
          </template>
        </v-list>
      </v-card>

      <v-alert v-else type="info" variant="tonal" class="mb-6">
        This suggestion appears to have no differences from the current data.
      </v-alert>

      <v-card-actions v-if="suggestion.status === 'pending'" class="mt-4">
        <v-spacer />
        <v-btn
          color="error"
          variant="outlined"
          @click="rejectDialog = true"
        >
          Reject
        </v-btn>
        <v-btn
          color="success"
          variant="elevated"
          :loading="processing"
          @click="approve()"
        >
          Approve & Apply
        </v-btn>
      </v-card-actions>
    </div>

    <!-- Reject Dialog -->
    <v-dialog v-model="rejectDialog" max-width="500">
      <v-card title="Reject Suggestion">
        <v-card-text>
          <v-textarea
            v-model="rejectReason"
            label="Reason (Optional)"
            rows="3"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="rejectDialog = false">Cancel</v-btn>
          <v-btn color="error" :loading="processing" @click="reject()">Reject</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { mdiArrowLeft, mdiArrowRight, mdiCompare, mdiOpenInNew } from '@mdi/js'
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/plugins/api.js'
import { useToast } from "vue-toastification"

const route = useRoute()
const toast = useToast()

const suggestion = ref(null)
const loading = ref(true)
const processing = ref(false)
const rejectDialog = ref(false)
const rejectReason = ref('')

const formatType = (type) => {
    return type?.split('\\').pop()
}

const resolveUrl = (val) => {
    if (!val) return ''

    // Handle object wrapper { data: "..." }
    if (typeof val === 'object' && val.data) {
        val = val.data
    }

    if (typeof val !== 'string') return ''

    // Handle JSON string wrapper "{ \"data\": \"...\" }"
    if (val.trim().startsWith('{')) {
        try {
            const parsed = JSON.parse(val)
            if (parsed.data) val = parsed.data
        } catch (e) {
            // Not valid JSON, continue with original value
        }
    }

    if (val.startsWith('data:image')) return val
    if (val.startsWith('http') || val.startsWith('//')) return val
    if (val.startsWith('pending_edits/')) return '/storage/' + val
    return '/storage/' + val
}

const isImageField = (key) => {
    return ['hero_image', 'entrance_image', 'photo_path', 'photo_data'].includes(key)
}

const normalizeTags = (tags) => {
    if (!Array.isArray(tags)) return []
    return tags
        .filter(t => !['previously done'].includes(t.category)) // Filter out system/non-editable tags
        .map(t => ({
            tag: t.tag,
            category: t.category,
            type: t.type || 'cave'
        })).sort((a, b) => a.tag.localeCompare(b.tag))
}

const normalizeCaves = (caves) => {
    if (!Array.isArray(caves)) return []
    return caves.map(c => ({
        id: c.id,
        description: c.playlist_description || c.pivot?.description || (typeof c === 'object' ? c.description : null)
    })).sort((a, b) => a.id - b.id)
}

const changedFields = computed(() => {
    if (!suggestion.value) return []

    // Fallback to current model data if original_data wasn't captured at submission time
    const original = JSON.parse(JSON.stringify(suggestion.value.original_data || suggestion.value.suggestable || {}))
    const suggested = JSON.parse(JSON.stringify(suggestion.value.suggested_data || {}))
    const fields = []

    // Normalize comparison for common relationships
    if (original.tags || suggested.tags) {
        original.tags = normalizeTags(original.tags)
        suggested.tags = normalizeTags(suggested.tags)
    }
    if (original.caves || suggested.caves) {
        original.caves = normalizeCaves(original.caves)
        suggested.caves = normalizeCaves(suggested.caves)
    }

    // Get all unique keys from both objects
    const keys = [...new Set([...Object.keys(original), ...Object.keys(suggested)])]

    // Internal keys to ignore
    const ignoredKeys = [
        'id', 'created_at', 'updated_at', 'deleted_at', 'slug', 'user_id',
        'suggestable_id', 'suggestable_type', 'photo_data',
        'trips', 'visits', 'collections', 'is_ticked', // Non-editable relationships
        'previously_done', 'cave_system_id' // Loaded relationships/counts
    ]

    for (const key of keys) {
        if (ignoredKeys.includes(key)) continue

        const oldVal = original[key]
        const newVal = suggested[key]

        // Check for double empty
        if (isEmpty(oldVal) && isEmpty(newVal)) continue

        // Deep comparison for arrays/objects
        if (JSON.stringify(oldVal) !== JSON.stringify(newVal)) {
            const isImage = isImageField(key)
            const isTags = key === 'tags'
            const isCaves = key === 'caves'
            const isSystem = key === 'cave_system' || key === 'system'

            let displayOld = oldVal
            let displayNew = newVal

            if (isSystem) {
                displayOld = oldVal?.name || oldVal
                displayNew = newVal?.name || newVal
            }

            fields.push({
                key,
                label: key.replace(/_/g, ' ').toUpperCase(),
                oldValue: (isImage || isTags || isCaves) ? oldVal : formatValue(displayOld),
                newValue: (isImage || isTags || isCaves) ? newVal : formatValue(displayNew),
                isImage,
                isTags,
                isCaves,
                isLongText: !isImage && !isTags && !isCaves && !isSystem && (String(oldVal || '').length > 50 || String(newVal || '').length > 50)
            })
        }
    }

    return fields
})

const viewUrl = computed(() => {
    if (!suggestion.value?.suggestable_id) return null

    const type = suggestion.value.suggestable_type
    const id = suggestion.value.suggestable_id
    const slug = suggestion.value.suggestable?.slug

    const map = {
        'App\\Models\\Cave': `/caves/${slug || id}`,
        'App\\Models\\CaveSystem': `/cave-systems/${slug || id}`,
        'App\\Models\\Route': `/routes/${slug || id}`,
        'App\\Models\\Collection': `/collections/${slug || id}`
    }

    return map[type] || null
})

const isEmpty = (val) => {
    if (val === null || val === undefined || val === '') return true
    if (Array.isArray(val) && val.length === 0) return true
    if (typeof val === 'object' && Object.keys(val).length === 0) return true
    return false
}

const formatValue = (val) => {
    if (val === null || val === undefined) return ''
    if (Array.isArray(val) && val.length === 0) return '(Empty)'
    if (typeof val === 'object') {
        if (val.name) return val.name
        return JSON.stringify(val, null, 2)
    }
    return String(val)
}

const fetchSuggestion = async () => {
    loading.value = true
    try {
        const response = await api.get(`/api/admin/suggested-edits/${route.params.id}`)
        suggestion.value = response.data
    } catch (error) {
        console.error("Error fetching suggestion", error)
    } finally {
        loading.value = false
    }
}

const approve = async () => {
    processing.value = true
    try {
        await api.post(`/api/admin/suggested-edits/${suggestion.value.id}/approve`)
        toast.success("Suggestion approved successfully")
        fetchSuggestion()
    } catch (error) {
        toast.error("Failed to approve suggestion")
        console.error(error)
    } finally {
        processing.value = false
    }
}

const reject = async () => {
    processing.value = true
    try {
        await api.post(`/api/admin/suggested-edits/${suggestion.value.id}/reject`, {
            admin_comment: rejectReason.value
        })
        toast.success("Suggestion rejected")
        rejectDialog.value = false
        fetchSuggestion()
    } catch (error) {
        toast.error("Failed to reject suggestion")
        console.error(error)
    } finally {
        processing.value = false
    }
}

onMounted(() => {
    fetchSuggestion()
})
</script>

<style scoped>
pre {
    white-space: pre-wrap;
    font-size: 0.8em;
}
</style>
