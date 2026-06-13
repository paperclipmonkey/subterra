<template>
  <v-container class="callout-dash">
    <!-- Page Header -->
    <div class="d-flex flex-wrap align-center mb-4" style="gap: 12px;">
      <div class="flex-grow-1" style="min-width: 240px;">
        <h2 class="text-h4 font-weight-bold mb-1">Callout Dashboard</h2>
        <p class="text-subtitle-1 text-medium-emphasis mb-0">
          Real-time monitoring of teams underground. Overdue trips trigger automatic incidents.
        </p>
      </div>
      <v-btn color="primary" variant="tonal" :prepend-icon="mdiPhoneAlert" @click="openTestDialog">
        Test notifications
      </v-btn>
      <v-btn
        v-if="whatsappGroupUrl"
        color="green"
        variant="text"
        :prepend-icon="mdiWhatsapp"
        :href="whatsappGroupUrl"
        target="_blank"
        rel="noopener"
      >
        DO WhatsApp
      </v-btn>
      <v-btn variant="text" :prepend-icon="mdiBookOpenVariant" :append-icon="mdiOpenInNew" to="/pages/duty-officer-guide">
        DO Guide
      </v-btn>
    </div>

    <!-- STATUS HERO — full width, state-driven. Pulses when an incident needs action. -->
    <v-card
      :color="heroColor"
      rounded="lg"
      class="mb-4 overflow-hidden"
      :class="{ 'hero-pulse': heroState === 'critical' }"
    >
      <div class="d-flex flex-wrap align-center pa-4" style="gap: 16px;">
        <v-icon :icon="heroIcon" size="48" />
        <div class="flex-grow-1" style="min-width: 220px;">
          <div class="text-overline" style="opacity: 0.85; line-height: 1.2;">{{ heroKicker }}</div>
          <div class="text-h4 font-weight-bold">{{ heroTitle }}</div>
          <div class="text-body-1" style="opacity: 0.92;">{{ heroMessage }}</div>
        </div>
        <v-btn
          v-if="firstActiveIncident"
          size="large"
          color="white"
          variant="flat"
          class="font-weight-bold"
          :append-icon="mdiArrowRight"
          :to="'/admin/incidents/' + firstActiveIncident.id"
        >
          View incident
        </v-btn>
      </div>
    </v-card>

    <!-- AT-A-GLANCE STAT STRIP -->
    <v-row class="mb-2" dense>
      <v-col v-for="tile in statTiles" :key="tile.label" cols="6" md="3">
        <v-card variant="tonal" :color="tile.color" rounded="lg" class="h-100">
          <div class="d-flex align-center pa-3" style="gap: 12px;">
            <v-icon :icon="tile.icon" size="28" />
            <div class="overflow-hidden">
              <div class="text-h5 font-weight-bold text-truncate">{{ tile.value }}</div>
              <div class="text-caption text-medium-emphasis text-truncate">{{ tile.label }}</div>
            </div>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <v-row>
      <!-- LEFT COLUMN: Main Content (incidents first — so it's top of the page on mobile) -->
      <v-col cols="12" md="8" lg="9">

        <!-- OPEN INCIDENTS (highest priority) -->
        <div v-if="activeIncidents.length > 0" class="mb-6">
          <h3 class="text-subtitle-1 font-weight-bold text-error mb-3 d-flex align-center">
            <v-icon :icon="mdiAlertOctagram" color="error" class="mr-2" /> Open incidents
          </h3>

          <v-card
            v-for="incident in activeIncidents"
            :key="incident.id"
            :to="'/admin/incidents/' + incident.id"
            hover
            rounded="lg"
            class="mb-3 incident-card"
            :style="`border-left: 8px solid ${incident.incident_controller_id ? '#e65100' : '#c62828'};`"
          >
            <div class="pa-4">
              <div class="d-flex flex-wrap align-center" style="gap: 12px;">
                <v-icon
                  :icon="mdiAlertDecagram"
                  :color="incident.incident_controller_id ? 'orange-darken-3' : 'error'"
                  size="40"
                />
                <div class="flex-grow-1" style="min-width: 180px;">
                  <div class="d-flex align-center flex-wrap mb-1" style="gap: 6px;">
                    <span
                      class="text-overline font-weight-black"
                      :class="incident.incident_controller_id ? 'text-orange-darken-3' : 'text-error'"
                    >
                      Incident #{{ incident.id }}
                    </span>
                    <v-chip v-if="!incident.incident_controller_id" color="error" size="x-small" label>
                      UNACKNOWLEDGED
                    </v-chip>
                    <v-chip v-else color="orange-darken-3" size="x-small" label>
                      <v-icon start :icon="mdiAccountStar" /> {{ incident.controller ? incident.controller.name : 'Controller' }}
                    </v-chip>
                  </div>
                  <div class="text-h5 font-weight-bold">
                    {{ incident.callout && incident.callout.cave ? incident.callout.cave.name : 'Unknown Location' }}
                  </div>
                  <div class="text-body-2 text-medium-emphasis mt-1">
                    <v-icon :icon="mdiClockOutline" size="x-small" class="mr-1" />
                    Due {{ formatDate(incident.callout.callout_time) }}
                  </div>
                </div>

                <!-- Overdue ticker -->
                <div class="text-center px-2">
                  <div class="text-overline text-error" style="line-height: 1;">Overdue by</div>
                  <div class="text-h4 font-weight-black text-error tnum">
                    {{ overdueBy(incident.callout.callout_time) }}
                  </div>
                </div>

                <v-btn color="error" size="large" variant="flat" class="font-weight-bold d-none d-sm-flex" tabindex="-1">
                  View incident
                </v-btn>
              </div>
              <v-btn color="error" size="large" variant="flat" block class="font-weight-bold mt-3 d-flex d-sm-none" tabindex="-1">
                View incident
              </v-btn>
            </div>
          </v-card>
        </div>

        <!-- MONITORED CALLOUTS -->
        <div v-if="callouts.length > 0" class="mb-6">
          <div class="d-flex align-center mb-3">
            <h3 class="text-subtitle-1 font-weight-bold text-medium-emphasis d-flex align-center">
              <v-icon :icon="mdiWatch" class="mr-2" /> Monitored callouts
            </h3>
            <v-spacer />
            <v-btn size="small" variant="text" :prepend-icon="mdiMap" @click="showMap = !showMap">
              {{ showMap ? 'Hide map' : 'Show map' }}
            </v-btn>
          </div>

          <v-expand-transition>
            <div v-if="showMap" class="mb-4">
              <ActiveCalloutMap :callouts="callouts" />
            </div>
          </v-expand-transition>

          <v-card v-for="callout in callouts" :key="callout.id" rounded="lg" variant="outlined" class="mb-3">
            <div class="px-4 pt-3 pb-1">
              <div class="d-flex align-center flex-wrap" style="gap: 12px;">
                <v-icon :icon="callout.has_incident ? mdiAlertCircle : mdiRun" :color="callout.has_incident ? 'error' : 'success'" size="32" />

                <div class="flex-grow-1" style="min-width: 200px;">
                  <div class="font-weight-bold text-h6">
                    {{ callout.cave_name }}
                    <span v-if="callout.exit_cave_name" class="text-medium-emphasis">
                      <v-icon :icon="mdiArrowRight" size="small" /> {{ callout.exit_cave_name }}
                    </span>
                  </div>
                  <div class="text-body-2 text-medium-emphasis">
                    <span class="font-weight-bold text-high-emphasis">{{ callout.leader_name }}</span>
                    <span v-if="callout.additional_people > 0"> + {{ callout.additional_people }} other{{ callout.additional_people > 1 ? 's' : '' }}</span>
                    • Due {{ formatDate(callout.callout_time) }}
                  </div>
                  <div v-if="callout.route" class="text-caption text-medium-emphasis mt-1 font-italic text-truncate" style="max-width: 500px;">
                    {{ callout.route }}
                  </div>
                </div>

                <!-- Countdown -->
                <div class="text-right">
                  <div class="text-overline text-medium-emphasis" style="line-height: 1;">
                    {{ isOverdue(callout.callout_time) ? 'Overdue by' : 'Due in' }}
                  </div>
                  <div
                    class="text-h5 font-weight-black tnum"
                    :class="callout.has_incident || isOverdue(callout.callout_time) ? 'text-error' : 'text-primary'"
                  >
                    {{ overdueBy(callout.callout_time) }}
                  </div>
                </div>

                <v-btn v-if="callout.has_incident" color="error" variant="flat" :to="'/admin/incidents/' + callout.incident_id">
                  View incident
                </v-btn>
                <v-chip v-else label color="success" variant="tonal" :prepend-icon="mdiCheckCircle">
                  Monitoring
                </v-chip>
              </div>
            </div>

            <v-progress-linear
              :model-value="getCalloutProgress(callout)"
              :color="getCalloutProgressColor(callout)"
              height="6"
              rounded
              class="mt-2"
            />
          </v-card>
        </div>

        <!-- ALL QUIET (only when nothing is active) -->
        <v-card
          v-if="!loading && callouts.length === 0 && activeIncidents.length === 0"
          variant="outlined"
          rounded="lg"
          class="pa-8 text-center mb-6"
        >
          <v-icon size="72" color="success" :icon="mdiCheckCircleOutline" />
          <h2 class="text-h4 mt-3 font-weight-light">All quiet</h2>
          <p class="text-medium-emphasis text-h6 mb-4">No parties underground and no open incidents.</p>
          <v-chip v-if="loadingOfficer" variant="tonal">Checking cover…</v-chip>
          <v-chip v-else-if="currentOfficer" color="success" variant="tonal" :prepend-icon="mdiPoliceBadge">
            {{ currentOfficer.name }} on call
          </v-chip>
          <v-chip v-else color="error" variant="tonal" :prepend-icon="mdiAlert">
            No duty officer on call
          </v-chip>
        </v-card>

        <!-- Historic / Resolved Incidents -->
        <v-expansion-panels v-if="historicIncidents.length > 0" class="mb-6" flat>
          <v-expansion-panel>
            <v-expansion-panel-title>
              <div class="d-flex align-center">
                <v-icon color="grey" :icon="mdiHistory" class="mr-2" />
                Resolved &amp; historic incidents
                <span class="ml-2 text-medium-emphasis">({{ historicIncidents.length }})</span>
              </div>
            </v-expansion-panel-title>
            <v-expansion-panel-text>
              <v-data-table
                :headers="historicHeaders"
                :items="historicIncidents"
                :items-per-page="5"
                density="compact"
                class="elevation-0"
              >
                <template #item.status="{ item }">
                  <v-chip :color="getIncidentColor(item.status)" size="small" label>
                    {{ item.status.toUpperCase() }}
                  </v-chip>
                </template>
                <template #item.location="{ item }">
                  {{ item.callout.cave ? item.callout.cave.name : 'Unknown Location' }}
                </template>
                <template #item.date="{ item }">
                  {{ formatDate(item.callout.callout_time) }}
                </template>
                <template #item.actions="{ item }">
                  <v-btn icon size="small" variant="text" :to="'/admin/incidents/' + item.id">
                    <v-icon :icon="mdiEye" />
                  </v-btn>
                </template>
              </v-data-table>
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>

      </v-col>

      <!-- RIGHT COLUMN: Status Widgets -->
      <v-col cols="12" md="4" lg="3">

        <!-- Duty Officer Status -->
        <v-card :color="dutyOfficerColor" rounded="lg" class="mb-4">
          <v-card-text>
            <div class="d-flex align-center mb-2" style="gap: 8px;">
              <v-icon :icon="mdiPoliceBadge" size="small" />
              <span class="text-overline" style="line-height: 1;">Duty Officer</span>
            </div>
            <div v-if="loadingOfficer">
              <v-progress-linear indeterminate color="white" />
            </div>
            <template v-else-if="currentOfficer">
              <div class="text-h6 font-weight-bold">{{ currentOfficer.name }}</div>
              <div class="text-caption">On call now</div>
            </template>
            <template v-else>
              <div class="text-h6 font-weight-bold">NO COVERAGE</div>
              <div class="text-caption">System unmonitored</div>
            </template>

            <v-divider v-if="!loadingOfficer" class="my-3" style="opacity: 0.3;" />

            <div v-if="!loadingOfficer">
              <div v-if="nextGapIsSoon" class="d-flex align-center font-weight-bold" style="gap: 6px;">
                <v-icon :icon="mdiAlert" size="small" />
                Gap starts {{ getRelativeTime(nextGapStart) }}
              </div>
              <div v-else class="text-caption">
                Covered until {{ formatDate(nextGapStart) }}
              </div>
            </div>
          </v-card-text>
          <v-card-actions>
            <v-btn variant="text" block to="/admin/rota">Manage rota</v-btn>
          </v-card-actions>
        </v-card>

        <!-- Watchdog Sync Status -->
        <v-card rounded="lg" variant="outlined" class="mb-4">
          <div class="pa-3">
            <div class="d-flex justify-space-between align-center mb-2">
              <span class="text-caption font-weight-bold text-medium-emphasis">BACKUP WATCHDOG</span>
              <v-chip v-if="watchdogCount === -2" color="grey" size="x-small" label>NOT CONFIGURED</v-chip>
              <v-chip v-else-if="watchdogCount === -1" color="error" size="x-small" label>ERROR</v-chip>
              <v-chip v-else-if="isWatchdogOutOfSync" color="warning" size="x-small" label>OUT OF SYNC</v-chip>
              <v-chip v-else color="success" size="x-small" label>SYNCED</v-chip>
            </div>

            <v-row no-gutters class="text-center">
              <v-col cols="6">
                <div class="text-h6 font-weight-bold">{{ systemCount }}</div>
                <div class="text-caption text-medium-emphasis">System</div>
              </v-col>
              <v-col cols="6">
                <div class="text-h6 font-weight-bold" :class="watchdogCount < 0 ? 'text-error' : ''">
                  {{ watchdogCount < 0 ? '??' : watchdogCount }}
                </div>
                <div class="text-caption text-medium-emphasis">Watchdog</div>
              </v-col>
            </v-row>

            <v-alert v-if="watchdogCount === -1" type="error" density="compact" variant="tonal" class="mt-3 py-1 px-2 text-caption" :icon="mdiLanDisconnect">
              Communication error with watchdog service.
            </v-alert>
            <v-alert v-else-if="watchdogCount === -2" type="info" density="compact" variant="tonal" class="mt-3 py-1 px-2 text-caption" :icon="mdiCogOff">
              Watchdog service not configured.
            </v-alert>
            <v-alert v-else-if="isWatchdogOutOfSync" type="warning" density="compact" variant="tonal" class="mt-3 py-1 px-2 text-caption" :icon="mdiAlert">
              Watchdog count differs from system.
            </v-alert>

            <v-btn block size="small" color="primary" variant="outlined" class="mt-3" :prepend-icon="mdiPhoneAlert" @click="openTestDialog">
              Test notifications
            </v-btn>
          </div>
        </v-card>

        <!-- SMS Credit -->
        <v-card rounded="lg" variant="outlined" class="mb-4">
          <div class="pa-3">
            <div class="d-flex justify-space-between align-center mb-2">
              <span class="text-caption font-weight-bold text-medium-emphasis">SMS CREDIT</span>
              <v-chip v-if="lowCredit" color="error" size="x-small" label>LOW CREDIT</v-chip>
              <v-chip v-else color="success" size="x-small" label>OK</v-chip>
            </div>

            <v-row no-gutters class="text-center">
              <v-col v-for="(bal, key) in smsBalances" :key="key" cols="6">
                <div
                  class="text-h6 font-weight-bold"
                  :class="!bal.reachable ? 'text-medium-emphasis' : (bal.ok ? '' : 'text-error')"
                >
                  {{ formatBalance(bal) }}
                </div>
                <div class="text-caption text-medium-emphasis">{{ bal.provider }}{{ key === 'primary' ? ' (primary)' : ' (backup)' }}</div>
              </v-col>
            </v-row>

            <v-alert v-if="lowCredit" type="error" density="compact" variant="tonal" class="mt-3 py-1 px-2 text-caption" :icon="mdiAlert">
              Credit is below the safe minimum — new callouts are blocked until it's topped up.
            </v-alert>
          </div>
        </v-card>

        <!-- Legend -->
        <v-card variant="outlined" rounded="lg" class="mb-4">
          <v-card-text class="text-caption">
            <div class="text-overline mb-1">Legend</div>
            <div class="d-flex align-center mb-1"><v-icon size="small" color="success" class="mr-2" :icon="mdiRun" /> Monitored callout</div>
            <div class="d-flex align-center mb-1"><v-icon size="small" color="error" class="mr-2" :icon="mdiAlertOctagram" /> Open incident</div>
            <div class="d-flex align-center"><v-icon size="small" color="grey" class="mr-2" :icon="mdiHistory" /> Resolved</div>
          </v-card-text>
        </v-card>

        <!-- Duty Officer FAQ -->
        <v-card variant="tonal" rounded="lg" class="mb-16">
          <v-card-title class="text-subtitle-2 d-flex align-center">
            <v-icon :icon="mdiHelpCircleOutline" size="small" class="mr-2" /> Duty Officer FAQ
          </v-card-title>
          <v-card-text class="pa-0">
            <v-expansion-panels flat variant="accordion">

              <v-expansion-panel>
                <v-expansion-panel-title class="pa-2 font-weight-bold">1. How am I alerted?</v-expansion-panel-title>
                <v-expansion-panel-text class="pa-2 pt-0 text-caption">
                  <strong>15 minutes before</strong> a callout is due, both you and the cavers get a heads-up SMS +
                  email so the trip can be marked safe before any alarm. If the party is then <strong>overdue</strong>,
                  an <strong>Incident</strong> is created and you (the on-call DO) get an immediate <strong>SMS and
                    email</strong>, plus a post in Slack <code>#callouts-overdue</code>. If you don't acknowledge you'll
                  get <strong>automated phone calls</strong> (press <strong>1</strong> to acknowledge) — these come to
                  <strong>you for the first 15 minutes</strong>; only after that do alerts and calls widen to every
                  duty officer.
                </v-expansion-panel-text>
              </v-expansion-panel>

              <v-expansion-panel>
                <v-expansion-panel-title class="pa-2 font-weight-bold">2. How do I acknowledge / take control?</v-expansion-panel-title>
                <v-expansion-panel-text class="pa-2 pt-0 text-caption">
                  Any one of: <strong>press 1</strong> on the automated call, <strong>reply ACK</strong> to the alert
                  SMS, or click <strong>Acknowledge</strong> on the incident in this dashboard. This makes you the
                  <strong>Incident Controller</strong> and stops the escalation.
                </v-expansion-panel-text>
              </v-expansion-panel>

              <v-expansion-panel>
                <v-expansion-panel-title class="pa-2 font-weight-bold">3. What do I do once in control?</v-expansion-panel-title>
                <v-expansion-panel-text class="pa-2 pt-0 text-caption">
                  <ol class="pl-3">
                    <li><strong>Open the incident</strong> and review the trip plan, team and vehicle details.</li>
                    <li><strong>Attempt contact</strong> with the party using the numbers provided.</li>
                    <li><strong>Initiate rescue</strong>: if no contact, follow the Rescue Protocol script to call 999.</li>
                    <li><strong>Coordinate &amp; log</strong>: add notes; Cave Rescue will call you back for details.</li>
                    <li><strong>Resolve</strong> the incident once everyone is confirmed safe.</li>
                  </ol>
                </v-expansion-panel-text>
              </v-expansion-panel>

              <v-expansion-panel>
                <v-expansion-panel-title class="pa-2 font-weight-bold">4. What if I miss the first alerts?</v-expansion-panel-title>
                <v-expansion-panel-text class="pa-2 pt-0 text-caption">
                  After 15 minutes with no controller, <strong>every duty officer</strong> is re-alerted (CRITICAL).
                  Independently, a backup "watchdog" hosted elsewhere also messages and calls all DOs via a
                  <strong>different phone provider</strong>, so a single outage can't silence every alert.
                </v-expansion-panel-text>
              </v-expansion-panel>

              <v-expansion-panel>
                <v-expansion-panel-title class="pa-2 font-weight-bold">5. How do I make sure my phone rings?</v-expansion-panel-title>
                <v-expansion-panel-text class="pa-2 pt-0 text-caption">
                  Use <strong>Test notifications</strong> (top of this page) to send yourself a test SMS and call. Then
                  enable an override so alerts break through silent mode — iPhone <em>Emergency Bypass</em>, or star the
                  contact on Android. Step-by-step instructions are in the
                  <router-link to="/pages/duty-officer-guide">Duty Officer Guide</router-link>.
                </v-expansion-panel-text>
              </v-expansion-panel>

              <v-expansion-panel>
                <v-expansion-panel-title class="pa-2 font-weight-bold">6. Coordinating with the team</v-expansion-panel-title>
                <v-expansion-panel-text class="pa-2 pt-0 text-caption">
                  Watch Slack <code>#callouts-open</code> for live trips and <code>#callouts-overdue</code> for alarms,
                  and use the duty officers' <strong>WhatsApp group</strong> for real-time chat during an incident —
                  open it with the <strong>DO WhatsApp</strong> button at the top of this dashboard.
                </v-expansion-panel-text>
              </v-expansion-panel>

            </v-expansion-panels>
            <div class="pa-3">
              <v-btn block color="primary" variant="flat" class="text-none" :prepend-icon="mdiBookOpenVariant" to="/pages/duty-officer-guide">
                Duty Officer Guide
              </v-btn>
            </div>
          </v-card-text>
        </v-card>

      </v-col>
    </v-row>

    <!-- Unified Testing Dialog -->
    <v-dialog v-model="testDialog" max-width="580">
      <v-card>
        <v-card-title class="d-flex align-center pa-4 pb-2">
          <v-icon :icon="mdiPhoneAlert" class="mr-2" /> Test notifications
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-4">
          <p class="text-body-2 text-medium-emphasis mb-4">
            Send a real test SMS <strong>and</strong> place a real phone call to confirm alerts reach
            you — even with your phone on silent or Do&nbsp;Not&nbsp;Disturb.
          </p>

          <v-list v-if="contactNumbers.primary || contactNumbers.backup" density="compact" class="bg-grey-lighten-4 rounded mb-4">
            <v-list-subheader class="text-caption font-weight-bold">Save these alert numbers as contacts</v-list-subheader>
            <v-list-item v-if="contactNumbers.primary" :prepend-icon="mdiCellphoneMessage" title="Primary — SMS &amp; calls (Twilio)" :subtitle="contactNumbers.primary" />
            <v-list-item v-if="contactNumbers.backup" :prepend-icon="mdiShieldCheck" title="Backup — SMS only (TextMagic)" :subtitle="contactNumbers.backup" />
          </v-list>

          <div class="d-flex flex-wrap mb-2" style="gap: 8px;">
            <v-btn color="primary" variant="flat" :loading="testingSelf" :prepend-icon="mdiCellphoneMessage" @click="testSelf">
              Test my phone
            </v-btn>
            <v-btn color="warning" variant="tonal" :loading="testingAll" :prepend-icon="mdiAccountGroup" @click="testBroadcast">
              Test everyone
            </v-btn>
            <v-spacer />
            <v-btn variant="text" :loading="testingWatchdog" :prepend-icon="mdiBugPlay" @click="sendTestCallout">
              Backup watchdog
            </v-btn>
          </div>

          <v-list v-if="testResults.length" density="compact" class="bg-grey-lighten-4 rounded mb-2">
            <v-list-item v-for="(r, i) in testResults" :key="i" :title="r.user">
              <template #append>
                <v-chip size="x-small" :color="r.sms ? 'success' : 'error'" class="mr-1">
                  <v-icon start :icon="r.sms ? mdiCheckCircle : mdiCloseCircle" /> SMS
                </v-chip>
                <v-chip size="x-small" :color="r.call ? 'success' : 'error'">
                  <v-icon start :icon="r.call ? mdiCheckCircle : mdiCloseCircle" /> Call
                </v-chip>
              </template>
            </v-list-item>
          </v-list>

          <v-alert type="info" variant="tonal" density="compact" class="text-caption">
            Set your phone to let callout alerts break through silent mode (iPhone <em>Emergency Bypass</em> /
            Android starred contact). Full steps are in the
            <router-link to="/pages/duty-officer-guide">Duty Officer Guide</router-link>.
          </v-alert>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-3">
          <v-spacer />
          <v-btn variant="text" @click="testDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script>
import { mdiAccountGroup, mdiAccountHardHat, mdiAccountStar, mdiAlert, mdiAlertCircle, mdiAlertDecagram, mdiAlertOctagram, mdiArrowRight, mdiBellRing, mdiBookOpenVariant, mdiBugPlay, mdiCellphoneMessage, mdiCheckCircle, mdiCheckCircleOutline, mdiClockOutline, mdiCloseCircle, mdiCogOff, mdiEye, mdiHelpCircleOutline, mdiHistory, mdiLanDisconnect, mdiMap, mdiOpenInNew, mdiPhoneAlert, mdiPoliceBadge, mdiRun, mdiShieldCheck, mdiWatch, mdiWhatsapp } from '@mdi/js'
import { api } from '@/plugins/api'
import moment from 'moment'
import ActiveCalloutMap from '@/components/ActiveCalloutMap.vue'
import { useNotificationStore } from '@/stores/notifications'

export default {
  components: {
    ActiveCalloutMap
  },
  setup() {
    return {
      notify: useNotificationStore(),
      mdiAccountGroup,
      mdiAccountHardHat,
      mdiAccountStar,
      mdiAlert,
      mdiAlertCircle,
      mdiAlertDecagram,
      mdiAlertOctagram,
      mdiArrowRight,
      mdiBellRing,
      mdiBookOpenVariant,
      mdiBugPlay,
      mdiCellphoneMessage,
      mdiCheckCircle,
      mdiCheckCircleOutline,
      mdiClockOutline,
      mdiCloseCircle,
      mdiCogOff,
      mdiEye,
      mdiHelpCircleOutline,
      mdiHistory,
      mdiLanDisconnect,
      mdiMap,
      mdiOpenInNew,
      mdiPhoneAlert,
      mdiPoliceBadge,
      mdiRun,
      mdiShieldCheck,
      mdiWatch,
      mdiWhatsapp
    }
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
      watchdogCount: 0,
      systemCount: 0,
      isWatchdogOutOfSync: false,
      whatsappGroupUrl: null,
      contactNumbers: { primary: null, backup: null },
      smsBalances: {},
      testDialog: false,
      testingSelf: false,
      testingAll: false,
      testingWatchdog: false,
      testResults: [],
      historicHeaders: [
        { title: 'Status', key: 'status', width: '120px' },
        { title: 'Location', key: 'location' },
        { title: 'Date', key: 'date' },
        { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
      ]
    }
  },
  computed: {
    lowCredit() {
      return Object.values(this.smsBalances).some(b => b && b.reachable && !b.ok)
    },
    activeIncidents() {
      return this.incidents.filter(i => i.status === 'open' || i.status === 'managed')
    },
    historicIncidents() {
      return this.incidents.filter(i => i.status !== 'open' && i.status !== 'managed')
    },
    // The incident the hero CTA jumps to: prioritise an unacknowledged open one.
    firstActiveIncident() {
      if (!this.activeIncidents.length) return null
      return this.activeIncidents.find(i => i.status === 'open' && !i.incident_controller_id) || this.activeIncidents[0]
    },
    controllerName() {
      const managed = this.activeIncidents.find(i => i.incident_controller_id && i.controller)
      return managed ? managed.controller.name : null
    },
    onCallName() {
      return this.currentOfficer ? this.currentOfficer.name : null
    },
    // Drives the state-based hero. Most-urgent state wins.
    heroState() {
      if (this.activeIncidents.some(i => i.status === 'open' && !i.incident_controller_id)) return 'critical'
      if (this.activeIncidents.length > 0) return 'managed'
      if (this.watchdogCount === -1) return 'error'
      if (this.callouts.length > 0) return 'monitoring'
      return 'normal'
    },
    heroColor() {
      // Theme colours (not raw Material shades) so Vuetify applies a contrasting text colour.
      return {
        critical: 'error',
        managed: 'warning',
        error: 'error',
        monitoring: 'info',
        normal: 'success'
      }[this.heroState]
    },
    heroIcon() {
      return {
        critical: mdiAlertDecagram,
        managed: mdiAccountHardHat,
        error: mdiLanDisconnect,
        monitoring: mdiRun,
        normal: mdiShieldCheck
      }[this.heroState]
    },
    heroKicker() {
      return {
        critical: 'Emergency — action required',
        managed: 'Incident',
        error: 'System alert',
        monitoring: 'Monitoring',
        normal: 'System status'
      }[this.heroState]
    },
    heroTitle() {
      switch (this.heroState) {
        case 'critical': return 'Incident needs a controller'
        case 'managed': return 'Incident in progress'
        case 'error': return 'Backup watchdog unreachable'
        case 'monitoring': return `${this.callouts.length} ${this.callouts.length === 1 ? 'party' : 'parties'} underground`
        default: return 'All systems normal'
      }
    },
    heroMessage() {
      switch (this.heroState) {
        case 'critical': return 'An overdue party has not been acknowledged. Open the incident and take control now.'
        case 'managed': return `Being managed by ${this.controllerName || 'a duty officer'}.`
        case 'error': return 'Cannot reach the backup watchdog — verify monitoring is active.'
        case 'monitoring': return `${this.callouts.length} callout${this.callouts.length === 1 ? '' : 's'} monitored. Alerts fire automatically if anyone is overdue.`
        default: return this.currentOfficer ? `${this.currentOfficer.name} is on call. No open callouts.` : 'No duty officer is currently on call.'
      }
    },
    watchdogTile() {
      if (this.watchdogCount === -2) return { label: 'N/A', color: 'grey', icon: mdiCogOff }
      if (this.watchdogCount === -1) return { label: 'Error', color: 'error', icon: mdiLanDisconnect }
      if (this.isWatchdogOutOfSync) return { label: 'Out of sync', color: 'warning', icon: mdiAlert }
      return { label: 'Synced', color: 'success', icon: mdiCheckCircle }
    },
    statTiles() {
      return [
        { label: 'Parties underground', value: this.callouts.length, icon: mdiRun, color: this.callouts.length ? 'primary' : 'grey' },
        { label: 'Open incidents', value: this.activeIncidents.length, icon: mdiAlertOctagram, color: this.activeIncidents.length ? 'error' : 'success' },
        { label: 'On call', value: this.onCallName || 'None', icon: mdiPoliceBadge, color: this.currentOfficer ? 'success' : 'error' },
        { label: 'Backup watchdog', value: this.watchdogTile.label, icon: this.watchdogTile.icon, color: this.watchdogTile.color }
      ]
    },
    dutyOfficerColor() {
      // Theme colours so the card text auto-contrasts (white on error/success).
      if (!this.currentOfficer) return 'error'
      if (this.nextGapIsSoon) return 'warning'
      return 'success'
    },
    nextGapIsSoon() {
      if (!this.nextGapStart) return false
      // Warn if gap is within 24 hours
      return moment(this.nextGapStart).diff(this.now, 'hours') < 24
    }
  },
  async mounted() {
    await Promise.all([this.fetchIncidents(), this.fetchCallouts(), this.fetchDutyOfficer()])
    // Poll every 30s
    this.poll = setInterval(() => {
      this.fetchIncidents()
      this.fetchCallouts()
      this.fetchDutyOfficer()
    }, 30000)
    // Ticker every 1s
    this.ticker = setInterval(() => {
      this.now = moment()
    }, 1000)
  },
  beforeUnmount() {
    clearInterval(this.poll)
    clearInterval(this.ticker)
  },
  methods: {
    formatBalance(bal) {
      if (!bal || !bal.reachable || bal.amount === null) return '—'
      const amount = Number(bal.amount).toFixed(2)
      return bal.currency ? `${amount} ${bal.currency}` : amount
    },
    async fetchIncidents() {
      try {
        const res = await api.get('/api/admin/incidents')
        this.incidents = res.data.data
      } catch (e) {
        console.error(e)
      }
    },
    async fetchCallouts() {
      try {
        const res = await api.get('/api/admin/callouts')
        this.callouts = res.data.data
        this.watchdogCount = res.data.watchdog_count
        this.systemCount = res.data.system_count
        this.isWatchdogOutOfSync = res.data.is_watchdog_out_of_sync
        this.whatsappGroupUrl = res.data.whatsapp_group_url
        this.contactNumbers = res.data.contact_numbers || { primary: null, backup: null }
        this.smsBalances = res.data.sms_balances || {}
      } catch (e) {
        console.error(e)
      } finally {
        this.loading = false
      }
    },
    async fetchDutyOfficer() {
      try {
        const res = await api.get('/api/duty-officers/current')
        const data = res.data.data

        if (data.is_covered) {
          this.currentOfficer = data
          this.nextGapStart = data.next_gap_start
        } else {
          this.currentOfficer = null
          this.nextGapStart = data.next_gap_start || moment()
        }
      } catch (e) {
        console.error("Duty Officer Fetch Error", e)
        this.currentOfficer = null
        this.nextGapStart = moment()
      } finally {
        this.loadingOfficer = false
      }
    },
    formatDate(d) {
      return moment(d).format('HH:mm DD/MM')
    },
    getRelativeTime(d) {
      return moment(d).fromNow()
    },
    isOverdue(time) {
      return moment(time).diff(this.now) < 0
    },
    getCountdown(time) {
      const t = moment(time)
      const diff = t.diff(this.now)
      const duration = moment.duration(Math.abs(diff))

      const hours = Math.floor(duration.asHours())
      const mins = duration.minutes()
      const secs = duration.seconds()

      const str = `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`

      if (diff < 0) return `-${str}`
      return str
    },
    // Absolute HH:MM:SS (no sign) — used with a separate "Overdue by" / "Due in" label.
    overdueBy(time) {
      const c = this.getCountdown(time)
      return c.startsWith('-') ? c.slice(1) : c
    },
    getCalloutProgress(callout) {
      // Visualise "how close to overdue" within a 6-hour window.
      const due = moment(callout.callout_time)
      const hoursLeft = due.diff(this.now, 'hours', true)

      if (hoursLeft < 0) return 100 // Overdue

      const maxWindow = 6
      const progress = Math.max(0, Math.min(100, ((maxWindow - hoursLeft) / maxWindow) * 100))
      return progress
    },
    getCalloutProgressColor(callout) {
      if (callout.has_incident) return 'error'
      const due = moment(callout.callout_time)
      const hoursLeft = due.diff(this.now, 'hours', true)

      if (hoursLeft < 0) return 'purple' // Overdue
      if (hoursLeft < 1) return 'red'
      if (hoursLeft < 2) return 'orange'
      return 'success'
    },
    getIncidentColor(status) {
      if (status === 'open') return 'red'
      if (status === 'managed') return 'orange-darken-3'
      return 'blue-grey'
    },
    getIncidentIcon(status) {
      if (status === 'open') return mdiBellRing
      if (status === 'managed') return mdiAccountHardHat
      return mdiCheckCircle
    },
    openTestDialog() {
      this.testResults = []
      this.testDialog = true
    },
    async testSelf() {
      this.testingSelf = true
      try {
        const res = await api.post('/api/admin/duty-officers/test-self')
        this.testResults = res.data.results || []
        this.notify.showSuccess(res.data.message)
      } catch (e) {
        this.notify.showError(e?.response?.data?.message || 'Failed to send test. Is there a phone number on your profile?')
      } finally {
        this.testingSelf = false
      }
    },
    async testBroadcast() {
      if (!window.confirm('Send a real test SMS and phone call to EVERY duty officer on the rota?')) return
      this.testingAll = true
      try {
        const res = await api.post('/api/admin/duty-officers/test-broadcast')
        this.testResults = res.data.results || []
        this.notify.showSuccess(res.data.message)
      } catch (e) {
        this.notify.showError(e?.response?.data?.message || 'Failed to send broadcast test.')
      } finally {
        this.testingAll = false
      }
    },
    async sendTestCallout() {
      this.testingWatchdog = true
      try {
        await api.post('/api/admin/callouts/test-watchdog')
        this.notify.showSuccess('Test callout sent through the backup watchdog. Check your SMS & email.')
      } catch (e) {
        this.notify.showError('Failed to trigger the watchdog test callout.')
      } finally {
        this.testingWatchdog = false
      }
    }
  }
}
</script>

<style scoped>
/* Tabular figures so the ticking countdowns don't jitter. */
.tnum {
  font-variant-numeric: tabular-nums;
  font-feature-settings: 'tnum';
}

/* Draw attention to an unacknowledged incident without being obnoxious. */
.hero-pulse {
  animation: hero-pulse 1.8s ease-in-out infinite;
}

@keyframes hero-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(198, 40, 40, 0); }
  50% { box-shadow: 0 0 0 6px rgba(198, 40, 40, 0.28); }
}

.incident-card {
  transition: transform 0.12s ease;
}
.incident-card:hover {
  transform: translateY(-1px);
}
</style>
