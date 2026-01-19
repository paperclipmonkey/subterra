<template>
  <v-container>

    <!-- System Status Banner -->
    <v-alert :type="statusColor" prominent border="left" class="mb-6">
      <h3 class="headline">System Status: {{ systemStatus }}</h3>
      <p>{{ statusMessage }}</p>
    </v-alert>

    <!-- Administrative Links -->
    <v-row class="mb-6">
      <v-col cols="12" md="6" lg="3">
        <v-card to="/admin/users" link hover height="100%">
          <v-card-title><v-icon left>mdi-account-group</v-icon> Users</v-card-title>
          <v-card-text>Manage accounts & approvals.</v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="6" lg="3">
        <v-card to="/admin/clubs" link hover height="100%">
          <v-card-title><v-icon left>mdi-shield-account</v-icon> Clubs</v-card-title>
          <v-card-text>Club details & memberships.</v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="6" lg="3">
        <v-card to="/admin/rota" link hover height="100%">
          <v-card-title><v-icon left>mdi-calendar-clock</v-icon> On-Call Rota</v-card-title>
          <v-card-text>Manage safety coverage.</v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="6" lg="3">
        <v-card to="/admin/cave-system-with-cave" link hover height="100%">
          <v-card-title><v-icon left>mdi-map-marker-plus</v-icon> Add Cave</v-card-title>
          <v-card-text>Add System & Entrance.</v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="6" lg="3">
        <v-card to="/admin/pages" link hover height="100%">
          <v-card-title><v-icon left>mdi-file-document-multiple</v-icon> Pages</v-card-title>
          <v-card-text>Manage content pages.</v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <h2 class="headline mb-4">Active & Recent Incidents</h2>
    <v-row class="mb-6">
      <v-col cols="12" v-if="loading">
        <v-progress-linear indeterminate></v-progress-linear>
      </v-col>

      <v-col cols="12" v-else-if="incidents.length === 0">
        <v-card class="pa-5 text-center">
          <v-icon size="64" color="grey lighten-2">mdi-check-circle-outline</v-icon>
          <p class="grey--text mt-2">All quiet. No active callouts or recent incidents.</p>
        </v-card>
      </v-col>

      <v-col cols="12" md="6" lg="4" v-for="incident in incidents" :key="incident.id">
        <v-card :color="getIncidentColor(incident.status)" dark hover :to="'/admin/incidents/' + incident.id">
          <v-card-title>
            <v-icon left>{{ getIncidentIcon(incident.status) }}</v-icon>
            {{ incident.status.toUpperCase() }}
          </v-card-title>
          <v-card-text>
            <div class="text-h6 mb-2">
              {{ incident.callout.cave ? incident.callout.cave.name : 'Unknown Location' }}
            </div>
            <p><strong>Callout Time:</strong> {{ formatDate(incident.callout.callout_time) }}</p>
            <p v-if="incident.incident_controller_id">
              <v-icon small left>mdi-account-star</v-icon>
              Controller: {{ incident.controller.name }}
            </p>
            <p v-else class="font-weight-bold red--text text--lighten-4">
              <v-icon small left color="red lighten-4">mdi-alert</v-icon>
              UNACKNOWLEDGED
            </p>
          </v-card-text>
          <v-card-actions>
            <v-btn block :color="incident.status === 'open' ? 'red' : 'primary'">
              Manage Incident
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>

    <h2 class="headline mb-4" v-if="callouts.length > 0">Live Operations ({{ callouts.length }})</h2>
    <div v-if="callouts.length > 0" class="mb-6">
      <v-card v-for="callout in callouts" :key="callout.id" class="mb-2" outlined>
        <div class="d-flex align-center py-2 px-4 flex-wrap">
          <!-- Status Indicator -->
          <v-icon :color="callout.has_incident ? 'error' : 'success'" class="mr-3">
            {{ callout.has_incident ? 'mdi-alert-circle' : 'mdi-run' }}
          </v-icon>

          <!-- Location -->
          <div class="mr-6" style="min-width: 200px;">
            <div class="font-weight-bold text-subtitle-1">
              {{ callout.cave_name }}
              <span v-if="callout.exit_cave_name" class="grey--text text--darken-1">
                <v-icon small>mdi-arrow-right</v-icon> {{ callout.exit_cave_name }}
              </span>
            </div>
            <div class="caption grey--text">
              Team: {{ callout.team_size }} • Callout: {{ formatDate(callout.callout_time) }}
            </div>
          </div>

          <v-spacer class="d-none d-md-block"></v-spacer>

          <!-- Countdown -->
          <div class="text-h5 font-weight-black mr-4 text-right"
            :class="callout.has_incident ? 'error--text' : 'primary--text'">
            {{ getCountdown(callout.callout_time) }}
          </div>

          <!-- Actions -->
          <v-btn v-if="callout.has_incident" color="error" small depressed
            :to="'/admin/incidents/' + callout.incident_id">
            View Incident
          </v-btn>
          <v-chip v-else small label color="success" outlined>
            Monitoring
          </v-chip>
        </div>
      </v-card>
    </div>
  </v-container>
</template>

<script>
import axios from 'axios';
import moment from 'moment';

export default {
  data() {
    return {
      loading: true,
      incidents: [],
      callouts: [],
      now: moment()
    };
  },
  computed: {
    systemStatus() {
      if (this.incidents.some(i => i.status === 'open')) return 'CRTICAL';
      if (this.incidents.some(i => i.status === 'managed')) return 'ACTIVE';
      if (this.callouts.length > 0) return 'WATCHDOG ACTIVE';
      return 'NORMAL';
    },
    statusColor() {
      if (this.systemStatus === 'CRTICAL') return 'error';
      if (this.systemStatus === 'ACTIVE') return 'warning';
      if (this.systemStatus === 'WATCHDOG ACTIVE') return 'info';
      return 'success';
    },
    statusMessage() {
      if (this.systemStatus === 'CRTICAL') return 'Unacknowledged incidents require immediate attention.';
      if (this.systemStatus === 'ACTIVE') return 'Incident in progress.';
      if (this.systemStatus === 'WATCHDOG ACTIVE') return this.callouts.length + ' active callouts monitored.';
      return 'Systems operational. No active callouts.';
    }
  },
  async mounted() {
    await Promise.all([this.fetchIncidents(), this.fetchCallouts()]);
    // Poll every 30s
    this.poll = setInterval(() => {
      this.fetchIncidents();
      this.fetchCallouts();
    }, 30000);
    // Ticker every 1s
    this.ticker = setInterval(() => {
      this.now = moment();
    }, 1000);
  },
  beforeUnmount() {
    clearInterval(this.poll);
    clearInterval(this.ticker);
  },
  methods: {
    async fetchIncidents() {
      try {
        const res = await axios.get('/api/admin/incidents');
        this.incidents = res.data.data;
      } catch (e) {
        console.error(e);
      }
    },
    async fetchCallouts() {
      try {
        const res = await axios.get('/api/admin/callouts');
        this.callouts = res.data.data;
      } catch (e) {
        console.error(e);
      } finally {
        this.loading = false;
      }
    },
    formatDate(d) {
      return moment(d).format('HH:mm DD/MM');
    },
    getCountdown(time) {
      const t = moment(time);
      const diff = t.diff(this.now);
      const duration = moment.duration(Math.abs(diff));

      const hours = Math.floor(duration.asHours());
      const mins = duration.minutes();
      const secs = duration.seconds();

      const str = `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;

      if (diff < 0) return `-${str}`;
      return str;
    },
    getIncidentColor(status) {
      if (status === 'open') return 'red';
      if (status === 'managed') return 'orange darken-3';
      return 'grey darken-1';
    },
    getIncidentIcon(status) {
      if (status === 'open') return 'mdi-bell-ring';
      if (status === 'managed') return 'mdi-account-hard-hat';
      return 'mdi-check-circle';
    }
  }
};
</script>
