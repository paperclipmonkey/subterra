<template>
  <v-container>
    <h2 class="headline mb-4">Admin Dashboard</h2>
    
    <v-alert v-if="popularError || growthError" type="error" dismissible class="mb-4">
      {{ popularError || growthError }}
    </v-alert>

    <v-row>
      <v-col cols="12">
        <v-card :loading="growthLoading" class="pa-4 mb-4">
          <v-card-title>
            <v-icon left>mdi-trending-up</v-icon>
            Growth Metrics (Last 30 Days)
          </v-card-title>
          
          <v-card-text v-if="!growthLoading && (!growthChartData.datasets || growthChartData.datasets.length === 0)">
            <v-alert type="info">
              No growth data recorded yet.
            </v-alert>
          </v-card-text>

          <div v-else class="chart-container">
            <Line :data="growthChartData" :options="growthChartOptions" />
          </div>
        </v-card>
      </v-col>

      <v-col cols="12">
        <v-card :loading="popularLoading" class="pa-4">
          <v-card-title>
            <v-icon left>mdi-chart-line</v-icon>
            Top 10 Most Popular Records (Last 30 Days)
          </v-card-title>
          
          <v-card-text v-if="!popularLoading && (!popularChartData.datasets || popularChartData.datasets.length === 0)">
            <v-alert type="info">
              No API interactions recorded yet.
            </v-alert>
          </v-card-text>

          <div v-else class="chart-container">
            <Line :data="popularChartData" :options="popularChartOptions" />
          </div>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { mande } from 'mande'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js'
import { Line } from 'vue-chartjs'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend
)

const popularApi = mande('/api/admin/dashboard/popular-records')
const growthApi = mande('/api/admin/dashboard/metrics-overview')

const popularLoading = ref(false)
const growthLoading = ref(false)
const popularError = ref(null)
const growthError = ref(null)

const popularChartData = ref({ labels: [], datasets: [] })
const growthChartData = ref({ labels: [], datasets: [] })

const baseOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: {
    mode: 'index',
    intersect: false,
  },
  plugins: {
    legend: {
      position: 'bottom',
    },
    tooltip: {
      callbacks: {
        label: function (context) {
          let label = context.dataset.label || '';
          if (label) {
            label += ': ';
          }
          if (context.parsed.y !== null) {
            label += context.parsed.y;
          }
          return label;
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        stepSize: 1,
      }
    }
  }
}

const popularChartOptions = {
  ...baseOptions,
  scales: {
    ...baseOptions.scales,
    y: {
      ...baseOptions.scales.y,
      title: {
        display: true,
        text: 'Interactions'
      }
    }
  }
}

const growthChartOptions = {
  ...baseOptions,
  scales: {
    ...baseOptions.scales,
    y: {
      ...baseOptions.scales.y,
      title: {
        display: true,
        text: 'Count'
      }
    }
  }
}

const colors = [
  '#E53935', '#D81B60', '#8E24AA', '#5E35B1', '#3949AB',
  '#1E88E5', '#039BE5', '#00ACC1', '#00897B', '#43A047'
]

const growthColors = {
  'Callouts': '#fb8c00', // orange
  'Trips': '#1e88e5',    // blue
  'Users': '#43a047'     // green
}

const fetchPopularRecords = async () => {
  popularLoading.value = true
  popularError.value = null

  try {
    const response = await popularApi.get()

    const datasets = response.data.map((record, index) => {
      const color = colors[index % colors.length]
      return {
        label: `${record.type}: ${record.name}`,
        data: record.sparkline,
        borderColor: color,
        backgroundColor: color,
        tension: 0.1,
        fill: false
      }
    })

    popularChartData.value = {
      labels: response.labels || [],
      datasets: datasets
    }

  } catch (err) {
    popularError.value = err.response?.data?.message || err.message || 'Failed to load popular records'
    console.error('Error fetching popular records:', err)
  } finally {
    popularLoading.value = false
  }
}

const fetchGrowthMetrics = async () => {
  growthLoading.value = true
  growthError.value = null

  try {
    const response = await growthApi.get()

    const datasets = response.data.map((record) => {
      const color = growthColors[record.label] || '#9e9e9e'
      return {
        label: record.label,
        data: record.sparkline,
        borderColor: color,
        backgroundColor: color,
        tension: 0.1,
        fill: false
      }
    })

    growthChartData.value = {
      labels: response.labels || [],
      datasets: datasets
    }

  } catch (err) {
    growthError.value = err.response?.data?.message || err.message || 'Failed to load growth metrics'
    console.error('Error fetching growth metrics:', err)
  } finally {
    growthLoading.value = false
  }
}

onMounted(() => {
  fetchPopularRecords()
  fetchGrowthMetrics()
})
</script>

<style scoped>
.chart-container {
  height: 400px;
  position: relative;
}
</style>
