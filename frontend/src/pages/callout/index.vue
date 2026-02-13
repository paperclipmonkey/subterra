<template>
  <v-container>
    <v-row justify="center">
      <v-col cols="12" md="8">
        <!-- Header -->
        <div class="text-center mb-6">
          <v-icon size="64" color="warning" class="mb-2">mdi-alert-octagram</v-icon>
          <h1 class="text-h3 font-weight-bold">Safety Callout</h1>
          <p class="text-subtitle-1 grey--text text--darken-1 mt-2">
            Tell us where you're going. We'll watch your back.
          </p>
        </div>

        <!-- Callout Stats & Map -->
        <v-card v-if="activeCallouts.length > 0" class="mb-6 elevation-2 overflow-hidden">
          <v-alert icon="mdi-account-group" border="start" variant="tonal" color="info" density="compact" class="mb-0 rounded-b-0">
            <strong>{{ activeCallouts.length }} Open Trips</strong> currently underground.
          </v-alert>
          <ActiveCalloutMap :callouts="activeCallouts" />
        </v-card>
        <v-alert v-else type="success" variant="tonal" border="start" class="mb-6">
          <div class="text-h6">All Quiet</div>
          <div>No open callouts at the moment. You'll be the first one down!</div>
        </v-alert>

        <!-- Duty Officer Status -->
        <v-card class="mb-6" outlined :color="dutyOfficerColor">
          <v-card-text>
            <div class="d-flex align-center">
              <v-avatar color="white" size="48" class="mr-4 elevation-1">
                <v-img v-if="onCallOfficer && onCallOfficer.photo" :src="onCallOfficer.photo" />
                <span v-else-if="onCallOfficer" class="text-h5 primary--text font-weight-bold">{{ onCallOfficer.name.charAt(0) }}</span>
                <v-icon v-else large color="grey">mdi-account-off</v-icon>
              </v-avatar>
              <div>
                <div class="text-caption font-weight-bold text-uppercase" style="letter-spacing: 1px;">Duty Officer Status</div>
                <div v-if="onCallOfficer" class="text-h6 font-weight-bold">
                  Online: {{ onCallOfficer.name }}
                </div>
                <div v-else class="text-h6 error--text">
                  No Officer On Call
                </div>
                <div v-if="onCallOfficer" class="text-body-2">
                  Monitoring open callouts until {{ formatFullDate(onCallOfficer.next_gap_start) }}
                </div>
                <div v-else class="text-body-2">
                  Callouts cannot be created at this time. Please leave details with a trusted friend.
                </div>
              </div>
            </div>
          </v-card-text>
        </v-card>

        <!-- Process Info -->
        <v-expansion-panels class="mb-8" flat>
          <v-expansion-panel>
            <v-expansion-panel-title>
              <v-icon left color="primary" class="mr-2">mdi-information-outline</v-icon>
              How it works
            </v-expansion-panel-title>
            <v-expansion-panel-text>
              <ol class="pl-4 mt-2 mb-2">
                <li class="mb-2"><strong>Create Callout:</strong> Tell us your cave, team, and expected exit time.</li>
                <li class="mb-2"><strong>Track:</strong> We monitor your time. If you are overdue, our automated system alerts the Duty Officer.</li>
                <li class="mb-2"><strong>Rescue:</strong> If we can't contact you, we initiate a Cave Rescue response.</li>
              </ol>
              <div class="caption grey--text">
                Note: This is an automated safety backup. Always check weather and conditions yourself.
              </div>
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>

        <!-- Action Button -->
        <div class="text-center">
          <div v-if="appStore.user && appStore.user.active_callout">
            <v-alert type="error" variant="tonal" class="mb-4">
              <div class="text-h6">You have an open callout!</div>
              <div>Please manage your current trip before starting a new one.</div>
            </v-alert>
            <v-btn x-large color="error" size="x-large" block to="/callout/active" class="mb-4 font-weight-bold elevation-4">
              MANAGE OPEN CALLOUT
              <v-icon right class="ml-2">mdi-alert</v-icon>
            </v-btn>
          </div>
                    
          <div v-else>
            <v-alert v-if="!onCallOfficer" type="warning" variant="tonal" class="mb-4" border="start">
              <div class="text-h6">Callouts Not Available</div>
              <div>There is no Duty Officer on call at this time. Please ensure you leave callout details with a trusted friend before heading underground.</div>
            </v-alert>
            <v-btn x-large color="warning" size="x-large" block to="/callout/create" class="mb-4 font-weight-bold elevation-4" :disabled="!onCallOfficer">
              START CALLOUT
              <v-icon right class="ml-2">mdi-arrow-right</v-icon>
            </v-btn>
          </div>
        </div>

      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import axios from 'axios';
import moment from 'moment';
import ActiveCalloutMap from '@/components/ActiveCalloutMap.vue';
import { useAppStore } from '@/stores/app';

export default {
  name: 'CalloutLanding',
  components: {
    ActiveCalloutMap
  },
  setup() {
    const appStore = useAppStore();
    return { appStore };
  },
  data() {
    return {
      activeCallouts: [],
      onCallOfficer: null,
      loading: true
    }
  },
  computed: {
    dutyOfficerColor() {
      if (this.onCallOfficer) return 'success-lighten-5';
      return 'red-lighten-5';
    }
  },
  async mounted() {
    await Promise.all([
      this.fetchActiveCallouts(),
      this.fetchDutyOfficer(),
      this.appStore.getUser() // Ensure we have the latest user state with active_callout
    ]);
    this.loading = false;
  },
  methods: {
    async fetchActiveCallouts() {
      try {
        const res = await axios.get('/api/callouts/active');
        this.activeCallouts = res.data.data;
      } catch (e) {
        console.error("Failed to fetch open callouts", e);
      }
    },
    async fetchDutyOfficer() {
      try {
        const res = await axios.get('/api/duty-officers/current');
        const data = res.data.data;

        if (data.is_covered) {
          this.onCallOfficer = data;
        } else {
          this.onCallOfficer = null;
        }
      } catch (e) {
        console.error("Failed to fetch duty officer", e);
        this.onCallOfficer = null;
      }
    },
    formatDate(d) {
      if (!d) return '';
      return moment(d).format('HH:mm');
    },
    formatFullDate(d) {
      if (!d) return '';
      // If it's today, show time, else show date and time
      if (moment(d).isSame(moment(), 'day')) {
        return 'today at ' + moment(d).format('HH:mm');
      }
      return moment(d).format('MMM Do, HH:mm');
    }
  }
}
</script>

<style scoped>
.success-lighten-5 {
  background-color: #E8F5E9;
  /* Green 50 */
  border-color: #A5D6A7;
}

.red-lighten-5 {
  background-color: #FFEBEE;
  /* Red 50 */
  border-color: #EF9A9A;
}
</style>
