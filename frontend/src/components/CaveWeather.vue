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
              <div class="text-h2 font-weight-light text-white mb-2">
                {{ Math.round(weatherData.currently.temperature) }}°C
              </div>
              <div class="text-h6 text-blue-grey-lighten-3 mb-1">
                {{ weatherData.currently.summary }}
              </div>
              <div class="text-caption text-blue-grey-lighten-2">
                Feels like {{ Math.round(weatherData.currently.apparentTemperature) }}°C
              </div>
            </div>
            <v-icon size="80" class="text-white opacity-80">
              {{ getWeatherIcon(weatherData.currently.icon) }}
            </v-icon>
          </div>
          
          <!-- Additional Details -->
          <v-divider class="my-4 border-opacity-25"></v-divider>
          <v-row dense class="text-blue-grey-lighten-3">
            <v-col cols="4">
              <div class="text-caption">Wind</div>
              <div class="text-body-2">{{ Math.round(weatherData.currently.windSpeed) }} km/h</div>
            </v-col>
            <v-col cols="4">
              <div class="text-caption">Humidity</div>
              <div class="text-body-2">{{ Math.round(weatherData.currently.humidity * 100) }}%</div>
            </v-col>
            <v-col cols="4">
              <div class="text-caption">Pressure</div>
              <div class="text-body-2">{{ Math.round(weatherData.currently.pressure) }} mb</div>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <!-- 24-Hour Forecast -->
      <v-card v-if="hourlyForecast.length > 0" class="mb-4 rounded-lg" elevation="1">
        <v-card-title class="text-subtitle-1 font-weight-bold">Next 24 Hours</v-card-title>
        <v-card-text class="pa-4">
          <div class="hourly-scroll">
            <div class="d-flex" style="gap: 16px;">
              <div 
                v-for="hour in hourlyForecast" 
                :key="hour.time"
                class="hourly-item text-center flex-shrink-0"
              >
                <div class="text-caption text-grey mb-2">{{ formatHour(hour.time) }}</div>
                <v-icon size="32" class="mb-2" :color="getWeatherColor(hour.icon)">
                  {{ getWeatherIcon(hour.icon) }}
                </v-icon>
                <div class="text-body-2 font-weight-medium">{{ Math.round(hour.temperature) }}°</div>
                <div class="text-caption text-grey mt-1" v-if="hour.precipProbability > 0.1">
                  <v-icon size="12" color="blue">mdi-water</v-icon>
                  {{ Math.round(hour.precipProbability * 100) }}%
                </div>
              </div>
            </div>
          </div>
        </v-card-text>
      </v-card>

      <!-- Weekly History -->
      <v-card v-if="historicalData.length > 0" class="mb-4 rounded-lg" elevation="1">
        <v-card-title class="text-subtitle-1 font-weight-bold">Last 7 Days</v-card-title>
        <v-card-text class="pa-4">
          <div v-for="day in historicalData" :key="day.time" class="d-flex align-center py-3 border-b">
            <div class="flex-grow-1">
              <div class="text-body-2 font-weight-medium">{{ formatDate(day.time) }}</div>
              <div class="text-caption text-grey">{{ day.summary }}</div>
            </div>
            <v-icon size="32" class="mx-4" :color="getWeatherColor(day.icon)">
              {{ getWeatherIcon(day.icon) }}
            </v-icon>
            <div class="text-right" style="min-width: 100px;">
              <span class="text-body-2 font-weight-medium">{{ Math.round(day.temperatureHigh) }}°</span>
              <span class="text-grey mx-1">/</span>
              <span class="text-body-2 text-grey">{{ Math.round(day.temperatureLow) }}°</span>
            </div>
          </div>
        </v-card-text>
      </v-card>

      <!-- Daily Forecast -->
      <v-card v-if="dailyForecast.length > 0" class="rounded-lg" elevation="1">
        <v-card-title class="text-subtitle-1 font-weight-bold">Extended Forecast</v-card-title>
        <v-card-text class="pa-4">
          <div v-for="day in dailyForecast" :key="day.time" class="d-flex align-center py-3 border-b">
            <div class="flex-grow-1">
              <div class="text-body-2 font-weight-medium">{{ formatDate(day.time) }}</div>
              <div class="text-caption text-grey">{{ day.summary }}</div>
            </div>
            <v-icon size="32" class="mx-4" :color="getWeatherColor(day.icon)">
              {{ getWeatherIcon(day.icon) }}
            </v-icon>
            <div class="text-right" style="min-width: 100px;">
              <span class="text-body-2 font-weight-medium">{{ Math.round(day.temperatureHigh) }}°</span>
              <span class="text-grey mx-1">/</span>
              <span class="text-body-2 text-grey">{{ Math.round(day.temperatureLow) }}°</span>
            </div>
          </div>
        </v-card-text>
      </v-card>
    </div>

    <v-alert v-else type="info" variant="tonal" class="ma-4">
      No weather data available for this location.
    </v-alert>
  </v-container>
</template>

<style scoped>
.hourly-scroll {
  overflow-x: auto;
  overflow-y: hidden;
  -webkit-overflow-scrolling: touch;
}

.hourly-scroll::-webkit-scrollbar {
  height: 6px;
}

.hourly-scroll::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 3px;
}

.hourly-scroll::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 3px;
}

.hourly-scroll::-webkit-scrollbar-thumb:hover {
  background: #555;
}

.hourly-item {
  min-width: 70px;
}

.border-b:not(:last-child) {
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}
</style>

<script setup>
import { ref, onMounted, computed } from 'vue';

const props = defineProps({
  caveId: {
    type: [String, Number],
    required: true
  }
});

const loading = ref(true);
const error = ref(null);
const weatherData = ref(null);
const historicalData = ref([]);

const hourlyForecast = computed(() => {
  if (!weatherData.value?.hourly?.data) return [];
  return weatherData.value.hourly.data.slice(0, 24);
});

const dailyForecast = computed(() => {
  if (!weatherData.value?.daily?.data) return [];
  return weatherData.value.daily.data.slice(1, 8); // Skip today, show next 7 days
});

const fetchWeatherData = async () => {
  loading.value = true;
  error.value = null;

  try {
    // Fetch current and forecast
    const forecastResponse = await fetch(`/api/caves/${props.caveId}/weather/forecast`);
    if (!forecastResponse.ok) {
      if (forecastResponse.status === 404) {
        error.value = 'Location coordinates not available for this cave';
      } else if (forecastResponse.status === 503) {
        error.value = 'Weather service temporarily unavailable';
      } else {
        throw new Error(`Failed to fetch weather: ${forecastResponse.status}`);
      }
      loading.value = false;
      return;
    }
    const forecastData = await forecastResponse.json();
    weatherData.value = forecastData.data;

    // Fetch historical data
    const historicalResponse = await fetch(`/api/caves/${props.caveId}/weather/historical`);
    if (historicalResponse.ok) {
      const histData = await historicalResponse.json();
      historicalData.value = histData.data || [];
    }
  } catch (err) {
    console.error('Error fetching weather:', err);
    error.value = 'Failed to load weather data';
  } finally {
    loading.value = false;
  }
};

const formatHour = (timestamp) => {
  const date = new Date(timestamp * 1000);
  return date.toLocaleTimeString('en-US', { hour: 'numeric', hour12: true });
};

const formatDate = (timestamp) => {
  const date = new Date(timestamp * 1000);
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);
  
  if (date.toDateString() === today.toDateString()) {
    return 'Today';
  } else if (date.toDateString() === yesterday.toDateString()) {
    return 'Yesterday';
  }
  
  return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
};

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

const getWeatherColor = (icon) => {
  const colorMap = {
    'clear-day': 'orange',
    'clear-night': 'blue-grey',
    'rain': 'blue',
    'snow': 'blue-grey-lighten-2',
    'sleet': 'blue-grey',
    'wind': 'grey',
    'fog': 'grey',
    'cloudy': 'grey',
    'partly-cloudy-day': 'amber',
    'partly-cloudy-night': 'blue-grey',
    'thunderstorm': 'deep-purple',
    'tornado': 'red',
  };
  return colorMap[icon] || 'grey';
};

onMounted(() => {
  fetchWeatherData();
});
</script>
