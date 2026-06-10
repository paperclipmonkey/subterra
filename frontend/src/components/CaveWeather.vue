<template>
  <v-container fluid class="pa-0">
    <v-progress-circular v-if="loading" indeterminate color="primary" class="ma-4" />
    
    <v-alert v-else-if="error" type="error" variant="tonal" class="ma-4">
      {{ error }}
    </v-alert>

    <div v-else-if="weatherData">
      <!-- Current Weather -->
      <v-card v-if="weatherData.currently" class="mb-4 rounded-lg" elevation="0" color="blue-grey-darken-4">
        <v-card-text class="pa-6">
          <div class="d-flex align-center">
            <div class="flex-grow-1">
              <div class="text-h4 font-weight-light text-white mb-1">
                {{ Math.round(weatherData.currently.temperature) }}°C
              </div>
              <div class="text-subtitle-1 text-blue-grey-lighten-3 mb-0">
                {{ weatherData.currently.summary }}
              </div>
              <div class="text-caption text-blue-grey-lighten-2">
                Feels like {{ Math.round(weatherData.currently.apparentTemperature) }}°C
              </div>
            </div>
            <v-icon size="64" class="text-white opacity-80">
              {{ getWeatherIcon(weatherData.currently.icon) }}
            </v-icon>
          </div>
          
          <!-- Additional Details -->
          <v-divider class="my-4 border-opacity-25" />
          <v-row dense class="text-blue-grey-lighten-3">
            <v-col cols="6">
              <div class="text-caption">Wind</div>
              <div class="text-body-2">{{ Math.round(weatherData.currently.windSpeed) }} km/h</div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption">Humidity</div>
              <div class="text-body-2">{{ Math.round(weatherData.currently.humidity * 100) }}%</div>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <v-row>
        <v-col cols="12">
          <!-- Forecast Rain Chart -->
          <v-card class="mb-4 rounded-lg" elevation="1">
            <v-card-title class="text-subtitle-1 font-weight-bold">Predicted Precipitation (Next 48h)</v-card-title>
            <v-card-text>
              <Line
                id="forecast-rain-chart"
                :options="forecastChartOptions"
                :data="forecastChartData"
              />
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12">
          <!-- Historic Rain Chart -->
          <v-card v-if="historicData" class="mb-4 rounded-lg" elevation="1">
            <v-card-title class="text-subtitle-1 font-weight-bold">Historic Precipitation (Last 7 Days)</v-card-title>
            <v-card-text>
              <Line
                id="historic-rain-chart"
                :options="historicChartOptions"
                :data="historicChartData"
              />
            </v-card-text>
          </v-card>
          <v-skeleton-loader v-else class="mb-4 rounded-lg" type="card" />
        </v-col>

        <v-col v-if="weatherData.river_levels && weatherData.river_levels.length > 0" cols="12">
          <!-- River Level Charts (One per gauge) -->
          <v-card v-for="(gauge, index) in weatherData.river_levels" :key="`river-${gauge.rloi_id}`" class="mb-4 rounded-lg" elevation="1">
            <v-card-title class="text-subtitle-1 font-weight-bold d-flex justify-space-between align-center">
              <div>
                {{ gauge.name }}
                <span class="text-caption text-medium-emphasis ml-2">Last 24h</span>
              </div>
              <v-chip size="small" :color="getStateColor(gauge.state)" label>
                {{ gauge.state }}
              </v-chip>
            </v-card-title>
                
            <v-card-text>
              <div class="d-flex align-center mb-2">
                <div class="text-h4 font-weight-light mr-4">
                  {{ gauge.latest_value?.toFixed(2) }}m
                </div>
                <div class="d-flex flex-column">
                  <span class="text-caption text-medium-emphasis">Current Level</span>
                  <div class="d-flex align-center">
                    <v-icon :icon="getTrendIcon(gauge.trend)" size="small" :color="getTrendColor(gauge.trend)" class="mr-1" />
                    <span class="text-body-2" :class="`text-${getTrendColor(gauge.trend)}`">{{ gauge.trend }}</span>
                  </div>
                </div>
              </div>

              <div style="height: 250px;">
                <Line
                  :id="`river-level-chart-${index}`"
                  :options="riverLevelChartOptions"
                  :data="getRiverLevelChartData(gauge, index)"
                />
              </div>
            </v-card-text>
            <v-card-actions>
              <v-btn block variant="tonal" :href="`https://check-for-flooding.service.gov.uk/station/${gauge.rloi_id}`" target="_blank" :prepend-icon="mdiOpenInNew">
                View Official Gauge Data
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-col>

        <v-col v-if="weatherData.rain_gauges && weatherData.rain_gauges.length > 0" cols="12">
          <!-- Rain Gauge Charts (One per gauge) -->
          <v-card v-for="(gauge, index) in weatherData.rain_gauges" :key="`rain-${gauge.station_id}`" class="mb-4 rounded-lg" elevation="1">
            <v-card-title class="text-subtitle-1 font-weight-bold d-flex justify-space-between align-center">
              <div>
                {{ gauge.name }}
                <span class="text-caption text-medium-emphasis ml-2">Last 24h</span>
              </div>
            </v-card-title>
            <v-card-text>
              <div style="height: 250px;">
                <Bar
                  :id="`rain-gauge-chart-${index}`"
                  :options="rainGaugeChartOptions"
                  :data="getRainGaugeChartData(gauge, index)"
                />
              </div>
            </v-card-text>
            <v-card-actions>
              <v-btn block variant="tonal" :href="`https://check-for-flooding.service.gov.uk/rainfall-station/${gauge.station_id}`" target="_blank" :prepend-icon="mdiOpenInNew">
                View Official Gauge Data
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-col>

        <v-col cols="12">
          <v-card class="mb-4 rounded-lg" elevation="1">
            <v-card-title class="text-subtitle-1 font-weight-bold">External Resources</v-card-title>
            <v-list density="compact">
              <v-list-item 
                :prepend-icon="mdiWeatherWindy" 
                title="Windy.com - Rain Accumulation"
                subtitle="View detailed rain accumulation maps"
                :href="`https://www.windy.com/-Rain-accumulation-rainAccu?rainAccu,${location.lat},${location.lng},8`"
                target="_blank"
              />
              <v-list-item 
                :prepend-icon="mdiWeatherCloudyClock" 
                title="Met Office"
                subtitle="UK Weather Forecast"
                :href="`https://www.metoffice.gov.uk/`"
                target="_blank"
              />
            </v-list>
          </v-card>
        </v-col>
      </v-row>
    </div>

    <v-alert v-else type="info" variant="tonal" class="ma-4">
      No weather data available for this location.
    </v-alert>
  </v-container>
</template>

<script setup>
import { mdiArrowBottomRight, mdiArrowTopRight, mdiMinus, mdiOpenInNew, mdiWeatherCloudy, mdiWeatherCloudyClock, mdiWeatherFog, mdiWeatherLightning, mdiWeatherNight, mdiWeatherNightPartlyCloudy, mdiWeatherPartlyCloudy, mdiWeatherRainy, mdiWeatherSnowy, mdiWeatherSnowyRainy, mdiWeatherSunny, mdiWeatherTornado, mdiWeatherWindy } from '@mdi/js'

import { ref, onMounted, computed } from 'vue'
import { Bar, Line } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement, Filler, TimeScale } from 'chart.js'
import 'chartjs-adapter-moment'
import moment from 'moment'
import { api } from '@/plugins/api'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement, Filler, TimeScale)

const props = defineProps({
  caveId: {
    type: [String, Number],
    required: true
  }
})

const loading = ref(true)
const error = ref(null)
const weatherData = ref(null)
const historicData = ref(null)
const location = ref({ lat: 0, lng: 0 })
const caveName = ref('')

const historicChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: function (context) {
          return context.parsed.y + ' mm'
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      title: { display: true, text: 'Precipitation (mm)' }
    }
  }
}

const forecastChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: true, position: 'bottom' },
    tooltip: {
      mode: 'index',
      intersect: false,
    }
  },
  scales: {
    y: {
      type: 'linear',
      display: true,
      position: 'left',
      title: { display: true, text: 'Probability (%)' },
      min: 0,
      max: 100,
    },
    y1: {
      type: 'linear',
      display: true,
      position: 'right',
      title: { display: true, text: 'Intensity (mm/h)' },
      grid: {
        drawOnChartArea: false,
      },
      beginAtZero: true
    }
  },
  interaction: {
    mode: 'nearest',
    axis: 'x',
    intersect: false
  }
}

const historicChartData = computed(() => {
  if (!historicData.value) return { labels: [], datasets: [] }

  // historicData is an object keyed by date string
  // Sort keys just in case
  const dates = Object.keys(historicData.value).sort()

  // Calculate total daily rainfall from hourly data
  const dailyRain = dates.map(date => {
    const day = historicData.value[date]
    if (!day.hourly) return 0

    // Sum precipIntensity (mm/hour) for each hour. 
    // Assuming data is hourly, summing intensity gives approx total mm.
    return day.hourly.reduce((sum, hour) => sum + (hour.precipIntensity || 0), 0)
  })

  return {
    labels: dates.map(d => moment(d).format('ddd Do')),
    datasets: [{
      label: 'Precipitation (mm)',
      borderColor: '#42A5F5',
      backgroundColor: 'rgba(66, 165, 245, 0.2)',
      fill: true,
      tension: 0.4,
      data: dailyRain
    }]
  }
})

const forecastChartData = computed(() => {
  if (!weatherData.value?.hourly?.data) return { labels: [], datasets: [] }

  const next48Hours = weatherData.value.hourly.data.slice(0, 48)

  return {
    labels: next48Hours.map(h => moment.unix(h.time).format('ddd HH:mm')),
    datasets: [
      {
        label: 'Precip Probability (%)',
        borderColor: '#90CAF9',
        backgroundColor: 'rgba(144, 202, 249, 0.2)',
        fill: true,
        data: next48Hours.map(h => (h.precipProbability * 100)),
        yAxisID: 'y',
        tension: 0.4
      },
      {
        label: 'Precip Intensity (mm/h)',
        borderColor: '#1E88E5',
        backgroundColor: 'rgba(30, 136, 229, 0.5)',
        borderDash: [5, 5],
        data: next48Hours.map(h => h.precipIntensity),
        yAxisID: 'y1',
        tension: 0.4
      }
    ]
  }
})


const riverLevelChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false }, // Hide legend as title serves this purpose
    tooltip: {
      mode: 'index',
      intersect: false,
    }
  },
  scales: {
    x: {
      type: 'time',
      time: {
        unit: 'hour',
        displayFormats: {
          hour: 'HH:mm'
        }
      },
      title: {
        display: false,
      }
    },
    y: {
      beginAtZero: false,
      title: { display: true, text: 'Level (m)' }
    }
  },
  interaction: {
    mode: 'nearest',
    axis: 'x',
    intersect: false
  }
}

const rainGaugeChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      mode: 'index',
      intersect: false,
    }
  },
  scales: {
    x: {
      type: 'time',
      time: {
        unit: 'hour',
        displayFormats: {
          hour: 'HH:mm'
        }
      },
      title: {
        display: false,
      }
    },
    y: {
      beginAtZero: true,
      title: { display: true, text: 'Rainfall (mm)' }
    }
  },
  interaction: {
    mode: 'nearest',
    axis: 'x',
    intersect: false
  }
}

const getRainGaugeChartData = (gauge, index) => {
  const sortedReadings = [...gauge.readings].sort((a, b) => new Date(a.dateTime) - new Date(b.dateTime))
  const color = '#2196F3' // Blue for rain

  return {
    datasets: [{
      label: 'Rainfall',
      backgroundColor: color,
      borderColor: color,
      borderWidth: 1,
      data: sortedReadings.map(r => ({ x: r.dateTime, y: r.value })),
    }]
  }
}

const getRiverLevelChartData = (gauge, index) => {
  const sortedReadings = [...gauge.reading].sort((a, b) => new Date(a.dateTime) - new Date(b.dateTime))
  const color = ['#4CAF50', '#FF9800', '#F44336'][index % 3]
  const datasets = []

  // Main Gauge Line
  datasets.push({
    label: 'Level',
    borderColor: color,
    backgroundColor: color,
    data: sortedReadings.map(r => ({ x: r.dateTime, y: r.value })),
    pointRadius: 0,
    borderWidth: 2,
    tension: 0.1,
    order: 1 // Draw on top
  })

  // Normal Range (if metadata exists)
  if (gauge.metadata?.typicalRangeHigh && gauge.metadata?.typicalRangeLow) {
    if (sortedReadings.length > 0) {
      const start = sortedReadings[0].dateTime
      const end = sortedReadings[sortedReadings.length - 1].dateTime

      const rangeDataHigh = [
        { x: start, y: gauge.metadata.typicalRangeHigh },
        { x: end, y: gauge.metadata.typicalRangeHigh }
      ]
      const rangeDataLow = [
        { x: start, y: gauge.metadata.typicalRangeLow },
        { x: end, y: gauge.metadata.typicalRangeLow }
      ]

      // Low Line (Invisible)
      datasets.push({
        label: 'Normal Low',
        data: rangeDataLow,
        borderColor: 'transparent',
        pointRadius: 0,
        borderWidth: 0,
        fill: false,
        order: 2
      })

      // High Line (Fills to Low)
      datasets.push({
        label: 'Normal Range',
        data: rangeDataHigh,
        borderColor: color,
        borderDash: [5, 5],
        borderWidth: 1,
        backgroundColor: hexToRgba(color, 0.1),
        pointRadius: 0,
        fill: '-1',
        order: 3
      })
    }
  }

  return { datasets }
}

const hexToRgba = (hex, alpha) => {
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

const fetchWeatherData = async () => {
  loading.value = true
  error.value = null

  try {
    // Fetch current and forecast
    const forecastResponse = await api.get(`/api/caves/${props.caveId}/weather/forecast`)
    weatherData.value = forecastResponse.data.data

    if (weatherData.value) {
      location.value = {
        lat: weatherData.value.latitude,
        lng: weatherData.value.longitude
      }
      caveName.value = weatherData.value.cave_name
    }

    fetchHistoricData()

  } catch (err) {
    console.error('Error fetching weather:', err)
    error.value = 'Failed to load weather data'
  } finally {
    loading.value = false
  }
}

const fetchHistoricData = async () => {
  try {
    const response = await api.get(`/api/caves/${props.caveId}/weather/historic`)
    historicData.value = response.data.data
  } catch (e) {
    console.error("Failed to fetch historic weather", e)
  }
}


const getWeatherIcon = (icon) => {
  const iconMap = {
    'clear-day': mdiWeatherSunny,
    'clear-night': mdiWeatherNight,
    'rain': mdiWeatherRainy,
    'snow': mdiWeatherSnowy,
    'sleet': mdiWeatherSnowyRainy,
    'wind': mdiWeatherWindy,
    'fog': mdiWeatherFog,
    'cloudy': mdiWeatherCloudy,
    'partly-cloudy-day': mdiWeatherPartlyCloudy,
    'partly-cloudy-night': mdiWeatherNightPartlyCloudy,
    'thunderstorm': mdiWeatherLightning,
    'tornado': mdiWeatherTornado,
  }
  return iconMap[icon] || mdiWeatherCloudy
}

onMounted(() => {
  fetchWeatherData()
})

const getStateColor = (state) => {
  switch (state) {
    case 'Low': return 'orange'
    case 'High': return 'red'
    default: return 'success'
  }
}

const getTrendIcon = (trend) => {
  switch (trend) {
    case 'Rising': return mdiArrowTopRight
    case 'Falling': return mdiArrowBottomRight
    default: return mdiMinus
  }
}

const getTrendColor = (trend) => {
  switch (trend) {
    case 'Rising': return 'red'
    case 'Falling': return 'green'
    default: return 'grey'
  }
}
</script>

<style scoped>
/* Chart heights */
#historic-rain-chart {
  height: 200px;
}

#forecast-rain-chart {
  height: 300px;
}
</style>
