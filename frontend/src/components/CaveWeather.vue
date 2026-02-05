<template>
  <v-container fluid class="pa-0">
    <v-progress-circular v-if="loading" indeterminate color="primary" class="ma-4"></v-progress-circular>
    
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
          <v-divider class="my-4 border-opacity-25"></v-divider>
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
            <v-card class="mb-4 rounded-lg" elevation="1" v-if="historicData">
                <v-card-title class="text-subtitle-1 font-weight-bold">Historic Precipitation (Last 7 Days)</v-card-title>
                <v-card-text>
                   <Line
                        id="historic-rain-chart"
                        :options="historicChartOptions"
                        :data="historicChartData"
                    />
                </v-card-text>
            </v-card>
            <v-skeleton-loader v-else class="mb-4 rounded-lg" type="card"></v-skeleton-loader>
        </v-col>

        <v-col cols="12">
            <v-card class="mb-4 rounded-lg" elevation="1">
                <v-card-title class="text-subtitle-1 font-weight-bold">External Resources</v-card-title>
                <v-list density="compact">
                    <v-list-item 
                        prepend-icon="mdi-weather-windy" 
                        title="Windy.com - Rain Accumulation"
                        subtitle="View detailed rain accumulation maps"
                        :href="`https://www.windy.com/-Rain-accumulation-rainAccu?rainAccu,${location.lat},${location.lng},8`"
                        target="_blank"
                    ></v-list-item>
                     <v-list-item 
                        prepend-icon="mdi-weather-cloudy-clock" 
                        title="Met Office"
                        subtitle="UK Weather Forecast"
                        :href="`https://www.metoffice.gov.uk/`"
                        target="_blank"
                    ></v-list-item>
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
import { ref, onMounted, computed, defineProps } from 'vue';
import { Bar, Line } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement, Filler } from 'chart.js'
import moment from 'moment';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement, Filler)

const props = defineProps({
  caveId: {
    type: [String, Number],
    required: true
  }
});

const loading = ref(true);
const error = ref(null);
const weatherData = ref(null);
const historicData = ref(null);
const location = ref({ lat: 0, lng: 0 });
const caveName = ref('');

const historicChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: function (context) {
          return context.parsed.y + ' mm';
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
  if (!historicData.value) return { labels: [], datasets: [] };

  // historicData is an object keyed by date string
  // Sort keys just in case
  const dates = Object.keys(historicData.value).sort();

  // Calculate total daily rainfall from hourly data
  const dailyRain = dates.map(date => {
    const day = historicData.value[date];
    if (!day.hourly) return 0;

    // Sum precipIntensity (mm/hour) for each hour. 
    // Assuming data is hourly, summing intensity gives approx total mm.
    return day.hourly.reduce((sum, hour) => sum + (hour.precipIntensity || 0), 0);
  });

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
  if (!weatherData.value?.hourly?.data) return { labels: [], datasets: [] };

  const next48Hours = weatherData.value.hourly.data.slice(0, 48);

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

const fetchWeatherData = async () => {
  loading.value = true;
  error.value = null;

  try {
    // Fetch current and forecast
    const forecastResponse = await fetch(`/api/caves/${props.caveId}/weather/forecast`);
    if (!forecastResponse.ok) {
      // ... error handling
      throw new Error(`Failed to fetch weather`);
    }
    const forecastJson = await forecastResponse.json();
    weatherData.value = forecastJson.data;

    if (weatherData.value) {
      location.value = {
        lat: weatherData.value.latitude,
        lng: weatherData.value.longitude
      }
      caveName.value = weatherData.value.cave_name;
    }

    fetchHistoricData();

  } catch (err) {
    console.error('Error fetching weather:', err);
    error.value = 'Failed to load weather data';
  } finally {
    loading.value = false;
  }
};

const fetchHistoricData = async () => {
  try {
    const response = await fetch(`/api/caves/${props.caveId}/weather/historic`);
    if (response.ok) {
      const json = await response.json();
      historicData.value = json.data;
    }
  } catch (e) {
    console.error("Failed to fetch historic weather", e);
  }
}


const getWeatherIcon = (icon) => {
  const iconMap = {
    'clear-day': 'mdi-weather-sunny',
    'clear-night': 'mdi-weather-night',
    'rain': 'mdi-weather-rainy',
    'snow': 'mdi-weather-snowy',
    'sleet': 'mdi-weather-snowy-rainy',
    'wind': 'mdi-weather-windy',
    'fog': 'mdi-weather-fog',
    'cloudy': 'mdi-weather-cloudy',
    'partly-cloudy-day': 'mdi-weather-partly-cloudy',
    'partly-cloudy-night': 'mdi-weather-night-partly-cloudy',
    'thunderstorm': 'mdi-weather-lightning',
    'tornado': 'mdi-weather-tornado',
  };
  return iconMap[icon] || 'mdi-weather-cloudy';
};

onMounted(() => {
  fetchWeatherData();
});
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
