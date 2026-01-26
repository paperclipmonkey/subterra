<template>
  <v-container>
    <h2 class="headline mb-4">API Interactions Dashboard</h2>
    
    <v-alert v-if="error" type="error" dismissible class="mb-4">
      {{ error }}
    </v-alert>

    <v-card :loading="loading">
      <v-card-title>
        <v-icon left>mdi-chart-line</v-icon>
        Top 10 Most Popular Records (Last 30 Days)
      </v-card-title>
      
      <v-card-text v-if="!loading && popularRecords.length === 0">
        <v-alert type="info">
          No API interactions recorded yet.
        </v-alert>
      </v-card-text>

      <v-table v-else>
        <thead>
          <tr>
            <th class="text-left">Type</th>
            <th class="text-left">Name</th>
            <th class="text-right">Total Views</th>
            <th class="text-center">Trend (30 Days)</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="record in popularRecords" :key="`${record.type}-${record.id}`">
            <td>
              <v-chip :color="getTypeColor(record.type)" size="small">
                {{ record.type }}
              </v-chip>
            </td>
            <td>{{ record.name }}</td>
            <td class="text-right">{{ record.total_interactions }}</td>
            <td class="text-center">
              <div class="sparkline-container">
                <svg :width="sparklineWidth" :height="sparklineHeight" class="sparkline">
                  <polyline
                    :points="generateSparklinePoints(record.sparkline)"
                    fill="none"
                    :stroke="getTypeColor(record.type)"
                    stroke-width="2"
                  />
                </svg>
              </div>
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { mande } from 'mande'

const api = mande('/api/admin/dashboard/popular-records')
const loading = ref(false)
const error = ref(null)
const popularRecords = ref([])

const sparklineWidth = 150
const sparklineHeight = 40
const sparklinePadding = 4

const getTypeColor = (type) => {
  const colors = {
    'Cave': 'primary',
    'Trip': 'success',
    'Collection': 'secondary',
  }
  return colors[type] || 'grey'
}

const generateSparklinePoints = (data) => {
  if (!data || data.length === 0) return ''
  
  const max = Math.max(...data, 1) // Avoid division by zero
  const min = Math.min(...data, 0)
  const range = max - min || 1 // Avoid division by zero
  
  const points = data.map((value, index) => {
    const x = (index / (data.length - 1 || 1)) * (sparklineWidth - 2 * sparklinePadding) + sparklinePadding
    const y = sparklineHeight - sparklinePadding - ((value - min) / range) * (sparklineHeight - 2 * sparklinePadding)
    return `${x},${y}`
  })
  
  return points.join(' ')
}

const fetchPopularRecords = async () => {
  loading.value = true
  error.value = null
  
  try {
    const response = await api.get()
    popularRecords.value = response.data
  } catch (err) {
    error.value = err.response?.data?.message || err.message || 'Failed to load popular records'
    console.error('Error fetching popular records:', err)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchPopularRecords()
})
</script>

<style scoped>
.sparkline-container {
  display: flex;
  justify-content: center;
  align-items: center;
}

.sparkline {
  display: block;
}
</style>
