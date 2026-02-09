<template>
  <v-container>
    <h2 class="headline mb-4">API Interactions Dashboard</h2>
    
    <v-alert v-if="error" type="error" dismissible class="mb-4">
      {{ error }}
    </v-alert>

    <v-card :loading="loading" class="pa-4">
      <v-card-title>
        <v-icon left>mdi-chart-line</v-icon>
        Top 10 Most Popular Records (Last 30 Days)
      </v-card-title>
      
      <v-card-text v-if="!loading && (!chartData.datasets || chartData.datasets.length === 0)">
        <v-alert type="info">
          No API interactions recorded yet.
        </v-alert>
      </v-card-text>

      <div v-else class="chart-container">
        <Line :data="chartData" :options="chartOptions" />
      </div>
    </v-card>
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

const api = mande('/api/admin/dashboard/popular-records')
const loading = ref(false)
const error = ref(null)
const chartData = ref({ labels: [], datasets: [] })

const chartOptions = {
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
      title: {
        display: true,
        text: 'Interactions'
      }
    }
  }
}

const colors = [
  '#E53935', '#D81B60', '#8E24AA', '#5E35B1', '#3949AB',
  '#1E88E5', '#039BE5', '#00ACC1', '#00897B', '#43A047'
]

const fetchPopularRecords = async () => {
  loading.value = true
  error.value = null

  try {
    const response = await api.get()

    // Transform data for Chart.js
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

    chartData.value = {
      labels: response.labels || [], // Backend should now provide labels
      datasets: datasets
    }

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
.chart-container {
  height: 500px;
  position: relative;
}
</style>
