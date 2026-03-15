<template>
  <v-container>
    <v-btn icon class="mb-4" @click="$router.go(-1)">
      <v-icon :icon="mdiArrowLeft" />
    </v-btn>
    
    <div v-if="suggestion">
      <div class="d-flex align-center justify-space-between mb-2">
        <div>
          <div class="d-flex align-center gap-2 mb-1">
            <v-chip size="small" :color="suggestion.suggestable_id ? 'blue-darken-1' : 'success'" variant="tonal">
              {{ suggestion.suggestable_id ? formatType(suggestion.suggestable_type) : '✨ New Item' }}
            </v-chip>
            <v-chip size="small" :color="suggestion.status === 'approved' ? 'success' : suggestion.status === 'rejected' ? 'error' : 'warning'" variant="tonal">
              {{ suggestion.status }}
            </v-chip>
          </div>
          <h2 class="text-h4 font-weight-bold">
            {{ suggestion.suggestable?.name || suggestion.suggested_data?.name || `Suggestion #${suggestion.id}` }}
          </h2>
          <div class="text-subtitle-2 text-grey mt-1">
            Suggestion #{{ suggestion.id }}
            · by <router-link v-if="suggestion.user" :to="`/profile/${suggestion.user.id}`" class="text-decoration-none font-weight-bold text-primary">{{ suggestion.user.name }}</router-link><span v-else>🤖 Robot</span>
          </div>
        </div>
        <v-btn
          v-if="viewUrl"
          :href="viewUrl"
          target="_blank"
          :prepend-icon="mdiOpenInNew"
          variant="tonal"
          color="primary"
        >
          View Live
        </v-btn>
      </div>

      <v-alert
        v-if="suggestion.status !== 'pending'"
        :type="suggestion.status === 'approved' ? 'success' : 'error'"
        class="mt-4 mb-4"
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

    // Build baseline: original_data is a partial snapshot of only the changed fields.
    // For each key, prefer original_data (snapshot at submission time), fall back to
    // the live suggestable so we always have a meaningful before-value.
    const originalData = suggestion.value.original_data || {}
    const suggestable = suggestion.value.suggestable || {}
    const suggested = JSON.parse(JSON.stringify(suggestion.value.suggested_data || {}))
    const fields = []

    // Keys to show = everything proposed in suggested_data, plus anything in original_data
    // that was removed (not present in suggested_data). Ignore pure-metadata keys.
    const ignoredKeys = new Set([
        'id', 'created_at', 'updated_at', 'deleted_at', 'slug', 'user_id',
        'suggestable_id', 'suggestable_type', 'photo_data',
        'trips', 'visits', 'collections', 'is_ticked',
        'previously_done', 'cave_system_id',
    ])
    const keys = [...new Set([...Object.keys(suggested), ...Object.keys(originalData)])]

    for (const key of keys) {
        if (ignoredKeys.has(key)) continue

        const newVal = suggested[key]
        // Per-key fallback: use snapshot if available, else live model
        const oldVal = (key in originalData) ? originalData[key] : suggestable[key]

        // Normalise tags/caves in place before comparing
        if (key === 'tags') {
            const normOld = normalizeTags(oldVal)
            const normNew = normalizeTags(newVal)
            if (JSON.stringify(normOld) !== JSON.stringify(normNew)) {
                fields.push({ key, label: 'TAGS', oldValue: normOld, newValue: normNew, isTags: true })
            }
            continue
        }
        if (key === 'caves') {
            const normOld = normalizeCaves(oldVal)
            const normNew = normalizeCaves(newVal)
            if (JSON.stringify(normOld) !== JSON.stringify(normNew)) {
                fields.push({ key, label: 'CAVES', oldValue: normOld, newValue: normNew, isCaves: true })
            }
            continue
        }

        // Skip if both are empty
        if (isEmpty(oldVal) && isEmpty(newVal)) continue

        // Deep equality check
        if (JSON.stringify(oldVal) === JSON.stringify(newVal)) continue

        const isImage = isImageField(key)
        const isSystem = key === 'cave_system' || key === 'system'

        let displayOld = isSystem ? (oldVal?.name ?? oldVal) : oldVal
        let displayNew = isSystem ? (newVal?.name ?? newVal) : newVal

        fields.push({
            key,
            label: key.replace(/_/g, ' ').toUpperCase(),
            oldValue: isImage ? oldVal : formatValue(displayOld),
            newValue: isImage ? newVal : formatValue(displayNew),
            isImage,
            isTags: false,
            isCaves: false,
            isLongText: !isImage && !isSystem && (String(oldVal ?? '').length > 50 || String(newVal ?? '').length > 50)
        })
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
