<template>
  <v-container>
    <div class="d-flex align-center justify-space-between mb-6">
      <h2 class="text-h4">Suggested Edits</h2>
      <v-btn icon variant="text" @click="fetchItems" :loading="loading">
        <v-icon>mdi-refresh</v-icon>
      </v-btn>
    </div>
    
    <v-tabs v-model="activeTab" bg-color="transparent" color="primary" class="mb-6">
      <v-tab value="pending">Pending</v-tab>
      <v-tab value="approved">Approved</v-tab>
      <v-tab value="rejected">Rejected</v-tab>
    </v-tabs>

    <div v-if="loading" class="d-flex justify-center my-8">
        <v-progress-circular indeterminate color="primary"></v-progress-circular>
    </div>

    <div v-else-if="items.length === 0" class="text-center my-12 text-grey">
        <v-icon size="64" class="mb-4">mdi-file-document-outline</v-icon>
        <div class="text-h6">No {{ activeTab }} suggestions found</div>
    </div>

    <v-row v-else>
        <v-col v-for="item in items" :key="item.id" cols="12" md="6" lg="4">
            <v-card 
                :to="`/admin/suggested-edits/${item.id}`" 
                hover 
                class="h-100 d-flex flex-column"
                variant="elevated"
            >
                <v-card-item>
                    <template v-slot:prepend>
                        <v-avatar color="primary" variant="tonal" rounded>
                            <v-icon color="primary">{{ getTypeIcon(item.suggestable_type) }}</v-icon>
                        </v-avatar>
                    </template>
                    <v-card-title>
                        {{ formatType(item.suggestable_type) }}
                    </v-card-title>
                    <v-card-subtitle>
                        ID: #{{ item.suggestable_id || 'New' }}
                    </v-card-subtitle>
                </v-card-item>

                <v-card-text class="flex-grow-1">
                    <div class="d-flex align-center mb-2">
                        <v-avatar size="24" class="mr-2" color="grey-lighten-2">
                            <span v-if="item.user?.name" class="text-caption">{{ item.user.name.charAt(0) }}</span>
                            <v-icon v-else size="16">mdi-account</v-icon>
                        </v-avatar>
                        <span class="text-body-2">{{ item.user?.name || 'Unknown User' }}</span>
                    </div>
                    <div class="text-caption text-grey">
                        Suggested on {{ new Date(item.created_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) }}
                    </div>
                </v-card-text>

                <v-divider></v-divider>

                <v-card-actions>
                    <v-spacer></v-spacer>
                    <span class="text-caption text-primary font-weight-bold mr-2">
                        {{ item.status === 'pending' ? 'REVIEW SUGGESTION' : 'VIEW DETAILS' }}
                    </span>
                    <v-icon color="primary" size="small">mdi-arrow-right</v-icon>
                </v-card-actions>
            </v-card>
        </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { api } from '@/plugins/api.js'

const loading = ref(false)
const items = ref([])
const activeTab = ref('pending')

const formatType = (type) => {
  return type?.split('\\').pop() || 'Unknown'
}

const getTypeIcon = (type) => {
  const t = formatType(type).toLowerCase()
  if (t.includes('cave')) return 'mdi-image-filter-hdr'
  if (t.includes('route')) return 'mdi-map-marker-path'
  if (t.includes('collection')) return 'mdi-folder-multiple-image'
  return 'mdi-file-document'
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
