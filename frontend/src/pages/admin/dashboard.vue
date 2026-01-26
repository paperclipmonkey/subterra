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
            <td>
              <router-link :to="getResourceUrl(record)" class="text-decoration-none font-weight-bold">
                {{ record.name }}
              </router-link>
            </td>
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
    'Page': 'info',
  }
  return colors[type] || 'grey'
}

const getResourceUrl = (record) => {
  const routes = {
    'Cave': '/caves/',
    'Trip': '/trips/',
    'Collection': '/collections/',
    'Page': '/pages/',
  }
  return (routes[record.type] || '/') + record.identifier
}

const generateSparklinePoints = (data) => {
  if (!data || data.length === 0) return ''

  // Use reduce to find min/max for better performance with large arrays
  let max = 1
  let min = 0

  if (data.length > 0) {
    max = data[0]
    min = data[0]
    for (let i = 1; i < data.length; i++) {
      if (data[i] > max) max = data[i]
      if (data[i] < min) min = data[i]
    }
    // Ensure we have at least some range
    if (max === min) max = min + 1
  }

  const range = max - min

  const points = data.map((value, index) => {
    // For single-point datasets, treat the width as 1 to avoid division by zero.
    // The early return above guarantees data.length > 0 here.
    const denominator = data.length > 1 ? (data.length - 1) : 1
    const x = (index / denominator) * (sparklineWidth - 2 * sparklinePadding) + sparklinePadding
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
