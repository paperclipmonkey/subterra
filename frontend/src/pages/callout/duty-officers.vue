<template>
  <v-container>
    <v-row justify="center">
      <v-col cols="12" md="9">

        <!-- Header -->
        <div class="text-center mb-6">
          <v-icon size="56" color="warning" class="mb-2" :icon="mdiShieldAccount" />
          <h1 class="text-h4 font-weight-bold">Duty Officers &amp; Rota</h1>
          <p class="text-body-1 text-medium-emphasis mt-2">
            Real people, ready to respond if you don't come home.
          </p>
        </div>

        <!-- How it works explanation -->
        <v-card class="mb-6" variant="tonal" color="info">
          <v-card-text>
            <div class="text-subtitle-1 font-weight-bold mb-2">How Duty Officers work</div>
            <p class="text-body-2 mb-3">
              When a callout is triggered, one Duty Officer is <strong>primary</strong> — they are the first to be
              alerted by SMS and email.
              If the primary officer does not take control of the incident within 15 minutes, all Duty Officers are
              alerted with a critical escalation notification.
            </p>
            <p class="text-body-2 mb-0">
              A callout can only be created when a Duty Officer is on-call at your planned return time. This check
              happens automatically when you fill in your return time during callout creation.
            </p>
          </v-card-text>
          <v-card-actions class="pt-0 px-4 pb-3">
            <v-btn
              variant="text"
              color="info"
              size="small"
              :append-icon="mdiOpenInNew"
              href="https://subterra.world/pages/callout-system"
              target="_blank"
              rel="noopener noreferrer"
            >
              Full system details
            </v-btn>
          </v-card-actions>
        </v-card>

        <v-progress-linear v-if="loading" indeterminate color="warning" class="mb-6" />

        <template v-if="!loading">

          <!-- Duty Officers list -->
          <div class="text-h6 font-weight-bold mb-4">
            <v-icon :icon="mdiAccountGroup" color="warning" class="mr-2" />
            Duty Officers
          </div>

          <v-row class="mb-8">
            <v-col v-for="officer in officers" :key="officer.id" cols="12" sm="6" md="4">
              <v-card height="100%" class="d-flex flex-column">
                <div class="d-flex align-center pa-4 pb-2">
                  <v-avatar size="56" class="mr-3 elevation-1" color="primary">
                    <v-img v-if="officer.photo" :src="officer.photo" :alt="officer.name" />
                    <span v-else class="text-h5 text-white font-weight-bold">
                      {{ officer.name.charAt(0) }}
                    </span>
                  </v-avatar>
                  <div>
                    <div class="text-subtitle-1 font-weight-bold">{{ officer.name }}</div>
                    <div v-if="officer.clubs && officer.clubs.length" class="d-flex flex-wrap gap-1 mt-1">
                      <v-chip
                        v-for="club in officer.clubs"
                        :key="club.slug"
                        size="x-small"
                        color="blue-grey"
                        variant="tonal"
                      >
                        {{ club.name }}
                      </v-chip>
                    </div>
                  </div>
                </div>
                <v-card-text v-if="officer.bio" class="pt-1 pb-3 text-body-2 text-medium-emphasis flex-grow-1">
                  {{ officer.bio }}
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>

          <!-- Upcoming Rota -->
          <div class="text-h6 font-weight-bold mb-4">
            <v-icon :icon="mdiCalendarClock" color="warning" class="mr-2" />
            Upcoming Primary Rota
            <span class="text-body-2 text-medium-emphasis font-weight-regular ml-2">(next 7 days)</span>
          </div>

          <v-card v-if="shifts.length === 0" variant="tonal" color="warning" class="mb-6">
            <v-card-text>No shifts are currently scheduled for the next 7 days.</v-card-text>
          </v-card>

          <v-card v-else class="mb-6">
            <v-list lines="two" density="compact">
              <template v-for="(shift, index) in shifts" :key="shift.id">
                <v-divider v-if="index > 0" />
                <v-list-item>
                  <template #prepend>
                    <v-avatar size="36" color="primary" class="mr-2">
                      <v-img v-if="shift.user.photo" :src="shift.user.photo" :alt="shift.user.name" />
                      <span v-else class="text-body-2 text-white font-weight-bold">
                        {{ shift.user.name.charAt(0) }}
                      </span>
                    </v-avatar>
                  </template>
                  <v-list-item-title class="font-weight-medium">{{ shift.user.name }}</v-list-item-title>
                  <v-list-item-subtitle>
                    {{ formatShift(shift.start_at, shift.end_at) }}
                  </v-list-item-subtitle>
                  <template #append>
                    <v-chip
                      v-if="isCurrentShift(shift)"
                      color="success"
                      size="x-small"
                      variant="tonal"
                    >
                      ON CALL NOW
                    </v-chip>
                  </template>
                </v-list-item>
              </template>
            </v-list>
          </v-card>

        </template>

        <!-- Back to callout -->
        <div class="text-center mb-4">
          <v-btn variant="text" color="warning" :prepend-icon="mdiArrowLeft" to="/callout">
            Back to Callout
          </v-btn>
        </div>

      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import {
  mdiShieldAccount,
  mdiAccountGroup,
  mdiCalendarClock,
  mdiOpenInNew,
  mdiArrowLeft,
} from '@mdi/js'
import axios from 'axios'
import moment from 'moment'

export default {
  name: 'CalloutDutyOfficers',
  setup() {
    return {
      mdiShieldAccount,
      mdiAccountGroup,
      mdiCalendarClock,
      mdiOpenInNew,
      mdiArrowLeft,
    }
  },
  data() {
    return {
      loading: true,
      officers: [],
      shifts: [],
    }
  },
  async mounted() {
    try {
      const res = await axios.get('/api/duty-officers/rota')
      this.officers = res.data.data.officers
      this.shifts = res.data.data.shifts
    } catch (e) {
      console.error('Failed to load duty officer rota', e)
    } finally {
      this.loading = false
    }
  },
  methods: {
    isCurrentShift(shift) {
      const now = moment()
      return moment(shift.start_at).isSameOrBefore(now) && moment(shift.end_at).isSameOrAfter(now)
    },
    formatShift(start, end) {
      const s = moment(start)
      const e = moment(end)
      if (s.isSame(e, 'day')) {
        return s.format('ddd D MMM, HH:mm') + ' – ' + e.format('HH:mm')
      }
      return s.format('ddd D MMM HH:mm') + ' – ' + e.format('ddd D MMM HH:mm')
    },
  },
}
</script>
