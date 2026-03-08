<template>
  <div class="callout-time-picker">
    <v-card variant="outlined" class="pa-4">
      <div class="text-caption text-grey mb-2">Current Time</div>
      <div class="text-h6 mb-4">{{ currentTimeDisplay }}</div>

      <v-divider class="my-4" />

      <div class="text-subtitle-1 font-weight-bold mb-4">Callout Alarm Time</div>

      <!-- Clock Time Display -->
      <div class="time-display-card mb-4">
        <div class="d-flex align-center justify-center">
          <v-btn icon variant="text" size="large" :disabled="!canDecrease" @click="adjustTime(-15)">
            <v-icon size="32" :icon="mdiMinusCircle" />
          </v-btn>
          <div class="mx-6 text-center">
            <div class="text-h3 font-weight-bold">{{ calloutTimeDisplay }}</div>
            <div class="text-caption text-grey">{{ calloutDateDisplay }}</div>
          </div>
          <v-btn icon variant="text" size="large" @click="adjustTime(15)">
            <v-icon size="32" :icon="mdiPlusCircle" />
          </v-btn>
        </div>
      </div>

      <!-- Duration Display -->
      <v-alert type="info" variant="tonal" density="compact" class="mb-4">
        <div class="text-center">
          <div class="text-body-1 font-weight-medium">{{ durationDisplay }}</div>
        </div>
      </v-alert>

      <!-- Alternative: Hours and Minutes Adjustment -->
      <v-divider class="my-4" />
      <div class="text-caption text-grey mb-3">Or adjust by duration:</div>
            
      <v-row dense>
        <v-col cols="6">
          <div class="duration-adjuster">
            <div class="text-caption text-grey mb-1">Hours</div>
            <div class="d-flex align-center justify-center">
              <v-btn icon size="small" variant="text" :disabled="!canDecrease" @click="adjustHours(-1)">
                <v-icon :icon="mdiMinus" />
              </v-btn>
              <div class="mx-3 text-h5 font-weight-medium">{{ hoursFromNow }}</div>
              <v-btn icon size="small" variant="text" @click="adjustHours(1)">
                <v-icon :icon="mdiPlus" />
              </v-btn>
            </div>
          </div>
        </v-col>
        <v-col cols="6">
          <div class="duration-adjuster">
            <div class="text-caption text-grey mb-1">Minutes</div>
            <div class="d-flex align-center justify-center">
              <v-btn icon size="small" variant="text" :disabled="!canDecrease" @click="adjustMinutes(-15)">
                <v-icon :icon="mdiMinus" />
              </v-btn>
              <div class="mx-3 text-h5 font-weight-medium">{{ minutesFromNow }}</div>
              <v-btn icon size="small" variant="text" @click="adjustMinutes(15)">
                <v-icon :icon="mdiPlus" />
              </v-btn>
            </div>
          </div>
        </v-col>
      </v-row>

      <!-- Warning if time is in the past -->
      <v-alert v-if="isPastTime" type="error" density="compact" class="mt-4">
        This time is in the past! Please select a future time.
      </v-alert>
    </v-card>

    <!-- Help Text -->
    <v-card variant="outlined" color="info" class="mt-4 pa-4">
      <div class="d-flex align-start">
        <v-icon color="info" class="mr-3 mt-1" :icon="mdiInformation" />
        <div>
          <div class="text-subtitle-2 font-weight-bold mb-2">What is Callout Time?</div>
          <div class="text-body-2 mb-3">
            This is when we'll call 999 if you haven't checked in. <strong>Always add extra time as a safety buffer</strong> 
            – it's better to check in early than have emergency services called unnecessarily.
          </div>
                    
          <div class="text-subtitle-2 font-weight-bold mb-2">Consider adding time for:</div>
          <ul class="text-body-2 mb-3">
            <li>Unexpected delays or route changes</li>
            <li>Getting lost or taking wrong turns</li>
            <li>Equipment issues or slower progress than planned</li>
            <li>Weather deterioration</li>
          </ul>
        </div>
      </div>
    </v-card>
  </div>
</template>

<script>
import { mdiInformation, mdiMinus, mdiMinusCircle, mdiPlus, mdiPlusCircle } from '@mdi/js'
import moment from 'moment'

export default {
  name: 'CalloutTimePicker',
  props: {
    modelValue: {
      type: String,
      default: null
    }
  },
  emits: ['update:modelValue'],
  setup() {
    return { mdiInformation, mdiMinus, mdiMinusCircle, mdiPlus, mdiPlusCircle }
  },
  data() {
    return {
      currentTime: moment(),
      internalValue: null,
      clockInterval: null
    }
  },
  computed: {
    currentTimeDisplay() {
      return this.currentTime.format('HH:mm')
    },
    calloutTime() {
      if (this.internalValue) {
        return moment(this.internalValue)
      }
      return moment().add(5, 'hours')
    },
    calloutTimeDisplay() {
      return this.calloutTime.format('HH:mm')
    },
    calloutDateDisplay() {
      const now = moment()
      const callout = this.calloutTime

      if (callout.isSame(now, 'day')) {
        return 'Today'
      } else if (callout.isSame(now.clone().add(1, 'day'), 'day')) {
        return 'Tomorrow'
      } else {
        return callout.format('ddd, MMM D')
      }
    },
    durationDisplay() {
      const now = moment()
      const callout = this.calloutTime
      const duration = moment.duration(callout.diff(now))

      const hours = Math.floor(duration.asHours())
      const minutes = duration.minutes()

      let text = 'That is '
      if (hours > 0) {
        text += `${hours} hour${hours !== 1 ? 's' : ''}`
      }
      if (minutes > 0) {
        if (hours > 0) text += ' and '
        text += `${minutes} minute${minutes !== 1 ? 's' : ''}`
      }
      text += ' from now'

      return text
    },
    hoursFromNow() {
      const now = moment()
      const callout = this.calloutTime
      return Math.floor(moment.duration(callout.diff(now)).asHours())
    },
    minutesFromNow() {
      const now = moment()
      const callout = this.calloutTime
      const duration = moment.duration(callout.diff(now))
      return duration.minutes()
    },
    isPastTime() {
      return this.calloutTime.isBefore(moment())
    },
    canDecrease() {
      // Can't decrease if it would put us in the past
      const testTime = this.calloutTime.clone().subtract(15, 'minutes')
      return testTime.isAfter(moment())
    }
  },
  watch: {
    modelValue: {
      immediate: true,
      handler(val) {
        if (val) {
          this.internalValue = val
        } else {
          // Default to 5 hours from now, snapped to 15 min boundary
          const defaultTime = moment().add(5, 'hours')
          // Use ISO 8601 format to preserve timezone information
          this.internalValue = this.snapTo15Minutes(defaultTime).toISOString()
          this.$emit('update:modelValue', this.internalValue)
        }
      }
    },
    internalValue(val) {
      this.$emit('update:modelValue', val)
    }
  },
  mounted() {
    // Update current time every minute
    this.clockInterval = setInterval(() => {
      this.currentTime = moment()
    }, 60000)
  },
  beforeUnmount() {
    if (this.clockInterval) {
      clearInterval(this.clockInterval)
    }
  },
  methods: {
    snapTo15Minutes(time) {
      const minutes = time.minutes()
      const remainder = minutes % 15
      if (remainder === 0) return time

      // Round to nearest 15 minute boundary
      const roundedMinutes = Math.round(minutes / 15) * 15
      return time.clone().minutes(roundedMinutes).seconds(0).milliseconds(0)
    },
    adjustTime(minutes) {
      const newTime = this.calloutTime.clone().add(minutes, 'minutes')
      const snapped = this.snapTo15Minutes(newTime)
      // Use ISO 8601 format to preserve timezone information
      this.internalValue = snapped.toISOString()
    },
    adjustHours(hours) {
      this.adjustTime(hours * 60)
    },
    adjustMinutes(minutes) {
      this.adjustTime(minutes)
    }
  }
}
</script>

<style scoped>
.time-display-card {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  padding: 24px;
  color: white;
}

.time-display-card .text-h3 {
  color: white;
}

.time-display-card .text-caption {
  color: rgba(255, 255, 255, 0.9);
}

.duration-adjuster {
  background: #f5f5f5;
  border-radius: 8px;
  padding: 12px;
}
</style>
