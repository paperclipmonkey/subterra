<template>
  <div class="weather-card">
    <div class="weather-header">
      <div class="weather-title">
        <v-icon :icon="mdiWeatherCloudy" size="18" class="mr-2" />
        {{ data.cave_name }} — Weather & Conditions
      </div>
      <router-link :to="weatherLink" class="weather-link">
        View Details <v-icon :icon="mdiArrowRight" size="14" class="ml-1" />
      </router-link>
    </div>

    <!-- Current Conditions -->
    <div v-if="data.currently" class="current-conditions">
      <div class="conditions-main">
        <v-icon :icon="getWeatherIcon(data.currently.icon)" size="36" class="conditions-icon" />
        <div class="conditions-text">
          <div class="conditions-summary">{{ data.currently.summary }}</div>
        </div>
      </div>
      <div class="conditions-details">
        <span v-if="data.currently.windSpeed !== null"><v-icon :icon="mdiWeatherWindy" size="12" class="mr-1" />{{ data.currently.windSpeed }} km/h</span>
        <span v-if="data.currently.humidity !== null"><v-icon :icon="mdiWaterPercent" size="12" class="mr-1" />{{ data.currently.humidity }}%</span>
        <span v-if="data.antecedent_rain_7d_mm !== null && data.antecedent_rain_7d_mm !== undefined"><v-icon :icon="mdiWater" size="12" class="mr-1" />{{ data.antecedent_rain_7d_mm }}mm last 7d</span>
      </div>
    </div>

    <!-- Data Sections (antecedent rain + rain gauges) -->
    <div
      v-if="(!data.currently && data.antecedent_rain_7d_mm !== null && data.antecedent_rain_7d_mm !== undefined) || (data.rain_gauges && data.rain_gauges.length)"
      class="weather-data"
    >
      <!-- Antecedent Rainfall (only shown when no currently block displayed it) -->
      <div v-if="!data.currently && data.antecedent_rain_7d_mm !== null && data.antecedent_rain_7d_mm !== undefined" class="data-card">
        <div class="data-label">
          <v-icon :icon="mdiWater" size="13" />
          Past 7 Days
        </div>
        <div class="data-value">{{ data.antecedent_rain_7d_mm }}<span class="unit">mm</span></div>
      </div>

      <!-- Rain Gauges -->
      <div v-if="data.rain_gauges && data.rain_gauges.length" class="data-card">
        <div class="data-label">
          <v-icon :icon="mdiWater" size="13" />
          Current Rain
        </div>
        <div v-for="gauge in data.rain_gauges" :key="gauge.name" class="gauge-item">
          <span class="gauge-name">{{ gauge.name }}</span>
          <span class="gauge-value">{{ gauge.readings_24h_mm }}<span class="unit">mm/24h</span></span>
        </div>
      </div>
    </div>

    <!-- River Gauge Sparklines -->
    <div v-if="data.river_gauges && data.river_gauges.length" class="river-section">
      <div
        v-for="(gauge, index) in data.river_gauges"
        :key="gauge.name"
        class="river-gauge-card"
        :class="gaugeClass(gauge.state)"
      >
        <div class="river-gauge-header">
          <div class="river-gauge-left">
            <v-icon :icon="mdiWaves" size="13" class="mr-1" />
            <span class="gauge-name">{{ gauge.name }}</span>
          </div>
          <div class="gauge-right">
            <span class="gauge-value">{{ gauge.latest_value }}m</span>
            <v-icon
              v-if="gauge.trend"
              :icon="trendIcon(gauge.trend)"
              :color="trendColor(gauge.trend)"
              size="14"
              class="ml-1"
            />
          </div>
        </div>
        <div v-if="gauge.readings && gauge.readings.length" style="position: relative; height: 70px; margin-top: 4px;">
          <canvas :ref="el => { if (el) riverChartRefs[index] = el }" />
        </div>
      </div>
    </div>

    <!-- Forecast Chart -->
    <div v-if="data.daily_forecast && data.daily_forecast.length" class="forecast-section">
      <div class="forecast-label">
        <v-icon :icon="mdiCalendarCheckOutline" size="13" />
        7-Day Forecast
      </div>
      <div style="position: relative; height: 140px;">
        <canvas ref="forecastChart" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import {
  mdiWeatherCloudy,
  mdiWeatherSunny,
  mdiWeatherNight,
  mdiWeatherRainy,
  mdiWeatherSnowy,
  mdiWeatherSnowyRainy,
  mdiWeatherWindy,
  mdiWeatherFog,
  mdiWeatherPartlyCloudy,
  mdiWeatherNightPartlyCloudy,
  mdiWeatherLightning,
  mdiCalendarCheckOutline,
  mdiWater,
  mdiWaterPercent,
  mdiWaves,
  mdiArrowRight,
  mdiTrendingUp,
  mdiTrendingDown,
  mdiMinus,
} from '@mdi/js'
import { Chart as ChartJS, CategoryScale, LinearScale, BarController, BarElement, LineController, LineElement, PointElement, Legend, Tooltip } from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarController, BarElement, LineController, LineElement, PointElement, Legend, Tooltip)

const props = defineProps({
  data: { type: Object, required: true },
})

const forecastChart = ref(null)
const riverChartRefs = []

const weatherLink = computed(() => {
  if (props.data.cave_slug) {
    return `/caves/${props.data.cave_slug}?tab=weather`
  }
  return '/'
})

function gaugeClass(state) {
  if (!state) return ''
  const lower = state.toLowerCase()
  if (lower.includes('very high')) return 'gauge-severe'
  if (lower.includes('high')) return 'gauge-warning'
  return ''
}

function trendIcon(trend) {
  const t = trend?.toLowerCase()
  if (t === 'rising') return mdiTrendingUp
  if (t === 'falling') return mdiTrendingDown
  return mdiMinus
}

function trendColor(trend) {
  const t = trend?.toLowerCase()
  if (t === 'rising') return '#f59e0b'
  if (t === 'falling') return '#10b981'
  return '#6b7280'
}

function getWeatherIcon(icon) {
  const map = {
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
  }
  return map[icon] || mdiWeatherCloudy
}

onMounted(() => {
  // --- Forecast chart: precipitation probability (bars) + max temperature (line) ---
  if (forecastChart.value && props.data.daily_forecast && props.data.daily_forecast.length) {
    try {
      const dates = props.data.daily_forecast.map(d => {
        const date = new Date(d.date)
        return date.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric' })
      })
      const precipProb = props.data.daily_forecast.map(d => d.precip_prob ?? 0)
      const precipMm = props.data.daily_forecast.map(d => d.precip_mm ?? 0)

      new ChartJS(forecastChart.value, {
        type: 'bar',
        data: {
          labels: dates,
          datasets: [
            {
              type: 'bar',
              label: 'Chance (%)',
              data: precipProb,
              backgroundColor: 'rgba(144, 202, 249, 0.45)',
              borderColor: '#90caf9',
              borderWidth: 0,
              borderRadius: 2,
              yAxisID: 'y',
            },
            {
              type: 'line',
              label: 'Rain (mm)',
              data: precipMm,
              borderColor: '#1e88e5',
              backgroundColor: 'transparent',
              borderWidth: 2,
              borderDash: [5, 4],
              pointRadius: 2,
              pointBackgroundColor: '#1e88e5',
              tension: 0.3,
              yAxisID: 'y1',
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: true,
              position: 'top',
              labels: { font: { size: 9 }, usePointStyle: true, boxWidth: 8, padding: 8 },
            },
            tooltip: {
              mode: 'index',
              intersect: false,
              callbacks: {
                label: ctx => ctx.datasetIndex === 0
                  ? `Chance: ${ctx.parsed.y}%`
                  : `Rain: ${ctx.parsed.y} mm`,
              },
            },
          },
          scales: {
            y: {
              type: 'linear',
              position: 'left',
              min: 0,
              max: 100,
              ticks: { font: { size: 9 }, color: '#90caf9', stepSize: 50 },
              grid: { color: '#f3f4f6' },
            },
            y1: {
              type: 'linear',
              position: 'right',
              beginAtZero: true,
              ticks: { font: { size: 9 }, color: '#1e88e5' },
              grid: { drawOnChartArea: false },
            },
            x: {
              ticks: { font: { size: 9 }, color: '#6b7280' },
              grid: { display: false },
            },
          },
        },
      })
    } catch (e) {
      console.error('Failed to render forecast chart:', e)
    }
  }

  // --- River gauge sparklines ---
  if (props.data.river_gauges && props.data.river_gauges.length) {
    props.data.river_gauges.forEach((gauge, index) => {
      const canvas = riverChartRefs[index]
      if (!canvas || !gauge.readings || !gauge.readings.length) return
      try {
        const labels = gauge.readings.map(r => {
          const d = new Date(r.t)
          return d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0')
        })
        const values = gauge.readings.map(r => r.v)

        const stateClass = gauge.state?.toLowerCase()
        const lineColor = stateClass?.includes('high') ? '#ef4444'
          : stateClass === 'low' ? '#f97316'
          : '#3b82f6'

        const datasets = [
          {
            data: values,
            borderColor: lineColor,
            backgroundColor: 'transparent',
            borderWidth: 2,
            pointRadius: 0,
            tension: 0.2,
          },
        ]

        if (gauge.typical_range_high != null) {
          datasets.push({
            data: values.map(() => gauge.typical_range_high),
            borderColor: 'rgba(107, 114, 128, 0.45)',
            backgroundColor: 'transparent',
            borderWidth: 1,
            borderDash: [5, 4],
            pointRadius: 0,
          })
        }

        new ChartJS(canvas, {
          type: 'line',
          data: { labels, datasets },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                mode: 'nearest',
                intersect: false,
                callbacks: { label: ctx => `${ctx.parsed.y.toFixed(3)} m` },
              },
            },
            scales: {
              x: {
                ticks: { maxTicksLimit: 6, font: { size: 8 }, color: '#9ca3af' },
                grid: { display: false },
              },
              y: {
                ticks: { maxTicksLimit: 4, font: { size: 8 }, color: '#9ca3af' },
                grid: { color: '#f3f4f6' },
                beginAtZero: false,
              },
            },
          },
        })
      } catch (e) {
        console.error('Failed to render river sparkline:', e)
      }
    })
  }
})
</script>

<style scoped>
.current-conditions {
  padding: 12px 14px;
  border-bottom: 1px solid #e5e7eb;
  background: linear-gradient(135deg, #1e3a5f 0%, #1e293b 100%);
}

.conditions-main {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
}

.conditions-icon {
  color: #93c5fd;
  flex-shrink: 0;
}

.conditions-summary {
  font-size: 11px;
  color: #94a3b8;
  margin-top: 1px;
}

.conditions-details {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  font-size: 11px;
  color: #64748b;
}

.conditions-details span {
  display: flex;
  align-items: center;
  color: #94a3b8;
}

.weather-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.weather-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  background: linear-gradient(135deg, #f0f9ff 0%, #f8fafc 100%);
  border-bottom: 1px solid #e5e7eb;
}

.weather-title {
  display: flex;
  align-items: center;
  font-size: 13px;
  font-weight: 600;
  color: #1e293b;
}

.weather-link {
  display: flex;
  align-items: center;
  font-size: 11px;
  font-weight: 500;
  color: #3b82f6;
  text-decoration: none;
  padding: 4px 8px;
  border-radius: 4px;
  transition: all 0.15s;
  flex-shrink: 0;
}

.weather-link:hover {
  background: #eff6ff;
  color: #1d4ed8;
}

.weather-data {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 10px;
  padding: 12px;
  border-bottom: 1px solid #e5e7eb;
}

.data-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px;
  background: #f9fafb;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.data-label {
  display: flex;
  align-items: center;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  color: #6b7280;
  letter-spacing: 0.03em;
  gap: 4px;
}

.data-value {
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
}

.gauge-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 0;
  font-size: 11px;
  border-bottom: 1px solid #e5e7eb;
}

.gauge-item:last-child {
  border-bottom: none;
}

.gauge-name {
  font-weight: 500;
  color: #374151;
}

.gauge-value {
  font-weight: 600;
  color: #111827;
}

.gauge-right {
  display: flex;
  align-items: center;
  gap: 4px;
}

.gauge-item.gauge-warning .gauge-name {
  color: #b45309;
}

.gauge-item.gauge-warning .gauge-value {
  color: #d97706;
}

.gauge-item.gauge-severe .gauge-name {
  color: #7f1d1d;
}

.gauge-item.gauge-severe .gauge-value {
  color: #dc2626;
}

.unit {
  font-size: 10px;
  font-weight: 500;
  color: #6b7280;
  margin-left: 2px;
}

.river-section {
  border-top: 1px solid #e5e7eb;
}

.river-gauge-card {
  padding: 10px 12px;
  border-bottom: 1px solid #f3f4f6;
}

.river-gauge-card:last-child {
  border-bottom: none;
}

.river-gauge-card.gauge-warning {
  background: #fffbeb;
}

.river-gauge-card.gauge-severe {
  background: #fef2f2;
}

.river-gauge-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.river-gauge-left {
  display: flex;
  align-items: center;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  color: #6b7280;
  letter-spacing: 0.03em;
}

.forecast-section {
  padding: 12px;
  border-top: 1px solid #e5e7eb;
}

.forecast-label {
  display: flex;
  align-items: center;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  color: #6b7280;
  letter-spacing: 0.03em;
  gap: 4px;
  margin-bottom: 8px;
}
</style>
