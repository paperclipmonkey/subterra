<template>
  <v-container>
    <!-- Page Header -->
    <div class="mb-6">
         <h2 class="display-1 font-weight-bold grey--text text--darken-3 mb-1">
            Callout Dashboard
         </h2>
         <p class="subtitle-1 grey--text text--darken-1">
            Real-time monitoring of teams underground. Overdue trips trigger automatic incidents.
         </p>
    </div>

    <v-row>
      <!-- LEFT COLUMN: Main Content -->
      <v-col cols="12" md="8" lg="9">

        <!-- LIVE ACTIONS SECTION -->
        <div v-if="callouts.length > 0 || activeIncidents.length > 0">
            <div class="d-flex align-center justify-space-between mb-4">
                <v-btn v-if="callouts.length > 0" small text @click="showMap = !showMap" class="ml-auto">
                    <v-icon left>mdi-map</v-icon> {{ showMap ? 'Hide Map' : 'Show Map' }}
                </v-btn>
            </div>
            
            <!-- Optional Map View -->
            <v-expand-transition>
                <div v-if="showMap && callouts.length > 0" class="mb-6">
                    <ActiveCalloutMap :callouts="callouts" />
                </div>
            </v-expand-transition>
            
            <!-- Active Incidents (Priority) -->
            <div v-if="activeIncidents.length > 0" class="mb-8">
                 <h3 class="title error--text mb-3 d-flex align-center">
                    <v-icon color="error" class="mr-2">mdi-alert-octagram</v-icon> 
                    Open Incidents
                 </h3>
                 <v-row>
                    <v-col cols="12" v-for="incident in activeIncidents" :key="incident.id">
                        <v-card hover :to="'/admin/incidents/' + incident.id" class="elevation-3"
                            style="border-left: 8px solid #d32f2f; overflow: hidden;">
                            <div class="pa-4 d-flex align-center">
                                <v-icon color="error" size="48" class="mr-4">mdi-alert-decagram</v-icon>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-center mb-1">
                                        <span class="text-overline font-weight-black error--text mr-2">OPEN INCIDENT
                                            #{{ incident.id }}</span>
                                        <v-chip v-if="!incident.incident_controller_id" color="error" x-small label
                                            class="px-2 white--text shadow-none">UNACKNOWLEDGED</v-chip>
                                    </div>
                                    <div class="text-h5 font-weight-bold mb-1 black--text">
                                        {{ incident.callout.cave ? incident.callout.cave.name : 'Unknown Location' }}
                                    </div>
                                    <div class="text-body-2 grey--text text--darken-2">
                                        <v-icon small left>mdi-clock-outline</v-icon>
                                        <strong>Due:</strong> {{ formatDate(incident.callout.callout_time) }}
                                        <span v-if="incident.incident_controller_id" class="ml-4 text-uppercase">
                                            <v-icon small left>mdi-account-star</v-icon>
                                            Controller: {{ incident.controller.name }}
                                        </span>
                                    </div>
                                </div>
                                <v-btn color="error" depressed large class="font-weight-bold ml-4 d-none d-sm-flex">
                                    VIEW INCIDENT
                                </v-btn>
                            </div>
                            <!-- Mobile only button -->
                            <v-btn color="error" block tile large class="font-weight-bold d-flex d-sm-none">
                                VIEW INCIDENT
                            </v-btn>
                        </v-card>
                    </v-col>
                 </v-row>
            </div>
            
            <!-- Live Callouts -->
            <div v-if="callouts.length > 0" class="mb-6">
                <h3 class="title grey--text text--darken-1 mb-3 d-flex align-center">
                    <v-icon class="mr-2">mdi-watch</v-icon>
                    Monitored Callouts
                </h3>
                <v-card v-for="callout in callouts" :key="callout.id" class="mb-4 elevation-1" outlined>
                    <div class="px-4 pt-3 pb-1">
                        <div class="d-flex align-center flex-wrap">
                            <!-- Status Indicator -->
                            <v-icon :color="callout.has_incident ? 'error' : 'success'" class="mr-3" size="32">
                                {{ callout.has_incident ? 'mdi-alert-circle' : 'mdi-run' }}
                            </v-icon>

                            <!-- Location -->
                            <div class="mr-6 flex-grow-1" style="min-width: 200px;">
                                <div class="font-weight-bold text-h6">
                                {{ callout.cave_name }}
                                <span v-if="callout.exit_cave_name" class="grey--text text--darken-1">
                                    <v-icon small>mdi-arrow-right</v-icon> {{ callout.exit_cave_name }}
                                </span>
                                </div>
                                <div class="subtitle-2 grey--text">
                                Team: {{ callout.team_size }} • Due: {{ formatDate(callout.callout_time) }}
                                </div>
                            </div>

                            <!-- Countdown -->
                            <div class="text-h4 font-weight-black mr-6 text-right"
                                :class="callout.has_incident ? 'error--text' : 'primary--text'">
                                {{ getCountdown(callout.callout_time) }}
                            </div>

                            <!-- Actions -->
                            <v-btn v-if="callout.has_incident" color="error" large depressed
                                :to="'/admin/incidents/' + callout.incident_id">
                                View Incident
                            </v-btn>
                            <v-chip v-else label color="success" outlined>
                                Monitoring
                            </v-chip>
                        </div>
                    </div>

                    <!-- Progress Bar / Timeline -->
                    <v-progress-linear
                        :value="getCalloutProgress(callout)"
                        :color="getCalloutProgressColor(callout)"
                        height="6"
                        rounded
                        class="mt-2"
                    ></v-progress-linear>
                </v-card>
            </div>


        </div>
        
        <div v-else-if="!loading" class="mb-8">
            <v-card class="pa-10 text-center rounded-xl" outlined>
              <v-icon size="80" color="success lighten-4">mdi-check-circle-outline</v-icon>
              <h2 class="text-h4 mt-4 font-weight-thin grey--text text--darken-2">All Quiet</h2>
              <p class="grey--text mt-2 text-h6">No open operations.</p>
            </v-card>
        </div>

        <v-divider class="mb-6"></v-divider>

        <!-- Historic / Resolved Incidents -->
        <v-expansion-panels v-if="historicIncidents.length > 0" class="mb-6" flat>
            <v-expansion-panel>
                <v-expansion-panel-title>
                    <div class="d-flex align-center">
                        <v-icon left color="grey">mdi-history</v-icon>
                        Resolved & Historic Incidents
                        <span class="ml-2 grey--text">({{ historicIncidents.length }})</span>
                    </div>
                </v-expansion-panel-title>
                <v-expansion-panel-text>
                    <v-data-table
                        :headers="historicHeaders"
                        :items="historicIncidents"
                        :items-per-page="5"
                        density="compact"
                        class="elevation-0"
                        :show-select="false"
                        :disable-sort="false"
                    >
                        <template v-slot:item.status="{ item }">
                             <v-chip :color="getIncidentColor(item.status)" dark small label>
                                {{ item.status.toUpperCase() }}
                             </v-chip>
                        </template>
                        <template v-slot:item.location="{ item }">
                            {{ item.callout.cave ? item.callout.cave.name : 'Unknown Location' }}
                        </template>
                        <template v-slot:item.date="{ item }">
                            {{ formatDate(item.callout.callout_time) }}
                        </template>
                        <template v-slot:item.actions="{ item }">
                             <v-btn icon small :to="'/admin/incidents/' + item.id">
                                <v-icon>mdi-eye</v-icon>
                             </v-btn>
                        </template>
                    </v-data-table>
                </v-expansion-panel-text>
            </v-expansion-panel>
        </v-expansion-panels>
        
      </v-col>

      <!-- RIGHT COLUMN: Status Widgets -->
      <v-col cols="12" md="4" lg="3">
         <!-- System Status Banner (moved to card) -->
         <v-card class="mb-4" outlined>
            <v-alert :type="statusColor" tile class="mb-0">
                <h3 class="headline mb-1">Status: {{ systemStatus }}</h3>
                <div class="caption">{{ statusMessage }}</div>
            </v-alert>
         </v-card>

         <!-- Duty Officer Status -->
         <v-card class="d-flex flex-column mb-4" :color="dutyOfficerColor" dark>
           <v-card-title class="pb-1 subtitle-1 font-weight-bold">
             <v-icon left small>mdi-police-badge</v-icon> Duty Officer
           </v-card-title>
           <v-card-text class="flex-grow-1">
             <div v-if="loadingOfficer">
               <v-progress-linear indeterminate color="white"></v-progress-linear>
             </div>
             <div v-else-if="currentOfficer">
               <div class="text-h6 font-weight-bold">{{ currentOfficer.name }}</div>
               <div class="caption">On Call Now</div>
             </div>
             <div v-else>
                <div class="text-h6 font-weight-bold">NO COVERAGE</div>
                <div class="caption">System Unmonitored</div>
             </div>
 
             <v-divider class="my-3" v-if="!loadingOfficer"></v-divider>
 
             <div v-if="!loadingOfficer">
                <div v-if="nextGapIsSoon" class="d-flex align-center font-weight-bold yellow--text text--lighten-4">
                   <v-icon small left color="yellow lighten-4">mdi-alert</v-icon>
                   Gap starts {{ getRelativeTime(nextGapStart) }}
                </div>
                <div v-else class="caption">
                   Covered until {{ formatDate(nextGapStart) }}
                </div>
             </div>
           </v-card-text>
           <v-card-actions>
             <v-btn text block small to="/admin/rota">Manage Rota</v-btn>
           </v-card-actions>
         </v-card>

         <!-- Info / Legend Card -->
         <v-card outlined class="bg-grey-lighten-4">
            <v-card-title class="subtitle-2">Legend</v-card-title>
            <v-card-text class="caption">
                <div class="d-flex align-center mb-1"><v-icon small color="success" class="mr-2">mdi-run</v-icon> Active Callout</div>
                <div class="d-flex align-center mb-1"><v-icon small color="error" class="mr-2">mdi-alert-octagram</v-icon> Active Incident</div>
                <div class="d-flex align-center"><v-icon small color="grey" class="mr-2">mdi-history</v-icon> Resolved</div>
            </v-card-text>
         </v-card>

         <!-- Duty Officer FAQ -->
         <v-card outlined class="bg-grey-lighten-4 mt-4 mb-16">
             <v-card-title class="subtitle-2 grey--text text--darken-2">
                 Duty Officer FAQ
             </v-card-title>
             <v-card-text class="pa-0">
                 <v-expansion-panels flat variant="accordion">
                 
                <v-expansion-panel>
                     <v-expansion-panel-title class="pa-2 font-weight-bold">
                         1. How am I contacted?
                     </v-expansion-panel-title>
                     <v-expansion-panel-text class="pa-2 pt-0 caption grey--text text--darken-1">
                         When a callout becomes overdue, the system automatically triggers an <strong>Incident</strong>. You will receive an immediate SMS to your registered mobile number an email alert, and a post in Slack #callouts-overdue.
                     </v-expansion-panel-text>
                 </v-expansion-panel>

                 <v-expansion-panel>
                     <v-expansion-panel-title class="pa-2 font-weight-bold">
                         2. What should I do?
                     </v-expansion-panel-title>
                     <v-expansion-panel-text class="pa-2 pt-0 caption grey--text text--darken-1">
                         <ol class="pl-3">
                             <li><strong>Login</strong> to this dashboard immediately.</li>
                             <li><strong>Acknowledge</strong> the incident to stop escalations.</li>
                             <li><strong>Check Details</strong>: Review the trip plan and team info.</li>
                             <li><strong>Attempt Contact</strong>: Call the contact details provided in the callout. This could be for multiple participants.</li>
                             <li><strong>Initiate Rescue</strong>: If no contact, use the "Rescue Protocol" script to call 999.</li>
                             <li><strong>Wait for Cave Rescue</strong>: They will call you back, asking for more details about the trip.</li>
                         </ol>
                     </v-expansion-panel-text>
                 </v-expansion-panel>

                 <v-expansion-panel>
                     <v-expansion-panel-title class="pa-2 font-weight-bold">
                         3. Notification Channels?
                     </v-expansion-panel-title>
                     <v-expansion-panel-text class="pa-2 pt-0 caption grey--text text--darken-1">
                         We use Slack for general monitoring. Join the <code>#callouts-open</code> channel to see live trips being created and <code>#callouts-overdue</code> for urgent overdue alarms.
                     </v-expansion-panel-text>
                 </v-expansion-panel>

                 <v-expansion-panel>
                     <v-expansion-panel-title class="pa-2 font-weight-bold">
                         4. What if I miss it?
                     </v-expansion-panel-title>
                     <v-expansion-panel-text class="pa-2 pt-0 caption grey--text text--darken-1">
                         If you do not acknowledge the incident within 15 minutes, the system escalates to <strong>every Duty Officer</strong>. The first person to click "Acknowledge and take control" then becomes the incident controller.
                     </v-expansion-panel-text>
                 </v-expansion-panel>

                 <v-expansion-panel>
                     <v-expansion-panel-title class="pa-2 font-weight-bold">
                         5. Platform Stability?
                     </v-expansion-panel-title>
                     <v-expansion-panel-text class="pa-2 pt-0 caption grey--text text--darken-1">
                         We use a secondary Watchdog that operates independently of this website. If the site goes down, the Watchdog will still trigger SMS alerts to every Duty Officer directly with the full callout details. Please use Slack or another method to coordinate with your team.
                     </v-expansion-panel-text>
                 </v-expansion-panel>

             </v-expansion-panels>
             </v-card-text>
         </v-card>

      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import axios from 'axios';
import moment from 'moment';
import ActiveCalloutMap from '@/components/ActiveCalloutMap.vue';

export default {
  components: {
    ActiveCalloutMap
  },
  data() {
    return {
      loading: true,
      loadingOfficer: true,
      showMap: false,
      incidents: [],
      callouts: [],
      currentOfficer: null,
      nextGapStart: null,
      now: moment(),
      historicHeaders: [
        { text: 'Status', value: 'status', width: '120px' },
        { text: 'Location', value: 'location' },
        { text: 'Date', value: 'date' },
        { text: 'Actions', value: 'actions', sortable: false, align: 'end' },
      ]
    };
  },
  computed: {
    activeIncidents() {
      return this.incidents.filter(i => i.status === 'open' || i.status === 'managed');
    },
    historicIncidents() {
      return this.incidents.filter(i => i.status !== 'open' && i.status !== 'managed');
    },
    systemStatus() {
      if (this.activeIncidents.length > 0) return 'CRITICAL';
      if (this.incidents.some(i => i.status === 'managed')) return 'ACTIVE';
      if (this.callouts.length > 0) return 'WATCHDOG ACTIVE';
      return 'NORMAL';
    },
    statusColor() {
      if (this.systemStatus === 'CRITICAL') return 'error';
      if (this.systemStatus === 'ACTIVE') return 'warning';
      if (this.systemStatus === 'WATCHDOG ACTIVE') return 'info';
      return 'success';
    },
    statusMessage() {
      if (this.systemStatus === 'CRITICAL') return 'Unacknowledged incidents require immediate attention.';
      if (this.systemStatus === 'ACTIVE') return 'Incident in progress.';
      if (this.systemStatus === 'WATCHDOG ACTIVE') return this.callouts.length + ' open callouts monitored.';
      return 'Systems operational. No open callouts.';
    },
    dutyOfficerColor() {
      if (!this.currentOfficer) return 'red darken-3';
      if (this.nextGapIsSoon) return 'orange darken-3';
      return 'success darken-2';
    },
    nextGapIsSoon() {
      if (!this.nextGapStart) return false;
      // Warn if gap is within 24 hours
      return moment(this.nextGapStart).diff(this.now, 'hours') < 24;
    }
  },
  async mounted() {
    await Promise.all([this.fetchIncidents(), this.fetchCallouts(), this.fetchDutyOfficer()]);
    // Poll every 30s
    this.poll = setInterval(() => {
      this.fetchIncidents();
      this.fetchCallouts();
      this.fetchDutyOfficer();
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
    async fetchDutyOfficer() {
      try {
        const res = await axios.get('/api/duty-officers/current');
        const data = res.data.data;

        if (data.is_covered) {
          this.currentOfficer = data;
          this.nextGapStart = data.next_gap_start;
        } else {
          this.currentOfficer = null;
          this.nextGapStart = data.next_gap_start || moment();
        }
      } catch (e) {
        console.error("Duty Officer Fetch Error", e);
        this.currentOfficer = null;
        this.nextGapStart = moment();
      } finally {
        this.loadingOfficer = false;
      }
    },
    formatDate(d) {
      return moment(d).format('HH:mm DD/MM');
    },
    getRelativeTime(d) {
      return moment(d).fromNow();
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
    getCalloutProgress(callout) {
      // Assume callout started 12 hours ago max? Or use created_at if available. 
      // Lacking created_at in API response, maybe hardcode a window or just use relative urgency.
      // Let's deduce "start" as "callout time - trip length" but we don't know trip length.
      // Let's simpler: progress from "now" relative to "callout_time" within a 4 hour window?
      // Actually, just visualising "how close to overdue" is good.
      // Let's assume a standard 6 hour trip for visualisation if created_at missing?
      // Wait, just showing "Time elapsed" vs "Time Remaining" might simply be the countdown number.
      // A bar helps show "percent complete".
      // Let's assume start was created_at or 4 hours ago.

      // Better: Calculate percentage of time elapsed?
      // Use a fixed window: e.g. last 2 hours = 100%? No.

      // Let's just create a visual "Urgency" bar.
      // 0 to 1 hour left: Red.
      // 1 to 2 hours left: Orange.
      // > 2 hours: Green.
      // Bar checks "Time Until Overdue" against a max window of say 6 hours.
      const due = moment(callout.callout_time);
      const hoursLeft = due.diff(this.now, 'hours', true);

      if (hoursLeft < 0) return 100; // Overdue

      // If 6 hours left, 0% bar. If 0 hours left, 100% bar.
      const maxWindow = 6;
      const progress = Math.max(0, Math.min(100, ((maxWindow - hoursLeft) / maxWindow) * 100));
      return progress;
    },
    getCalloutProgressColor(callout) {
      if (callout.has_incident) return 'error';
      const due = moment(callout.callout_time);
      const hoursLeft = due.diff(this.now, 'hours', true);

      if (hoursLeft < 0) return 'purple'; // Overdue 
      if (hoursLeft < 1) return 'red';
      if (hoursLeft < 2) return 'orange';
      return 'success';
    },
    getIncidentColor(status) {
      if (status === 'open') return 'red';
      if (status === 'managed') return 'orange darken-3';
      return 'blue-grey';
    },
    getIncidentIcon(status) {
      if (status === 'open') return 'mdi-bell-ring';
      if (status === 'managed') return 'mdi-account-hard-hat';
      return 'mdi-check-circle';
    }
  }
};
</script>
