<template>
  <v-container>
    <div class="mb-8">
      <h2 class="text-h3 font-weight-bold mb-1">Administration</h2>
      <p class="text-subtitle-1 text-grey-darken-1">Manage platform operations, users, and content.</p>
    </div>

    <!-- Operations & Safety -->
    <section v-if="isDutyOfficer || isPlatformAdmin" class="mb-10">
      <div class="d-flex align-center mb-4">
        <v-icon color="error" class="mr-3" size="32" :icon="mdiShieldAlert" />
        <h3 class="text-h5 font-weight-bold">Operations & Safety</h3>
      </div>
      <v-row>
        <v-col v-if="isDutyOfficer" cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/callout" link hover class="admin-card">
            <v-card-item title="Callout Admin">
              <template #prepend>
                <v-icon color="error" :icon="mdiMonitorDashboard" />
              </template>
              <v-card-subtitle>Live dashboard & incidents</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
        <v-col v-if="isDutyOfficer" cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/rota" link hover class="admin-card">
            <v-card-item title="Duty Rota">
              <template #prepend>
                <v-icon color="deep-purple" :icon="mdiCalendarClock" />
              </template>
              <v-card-subtitle>Manage on-call shifts</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
      </v-row>
    </section>

    <!-- User Management -->
    <section v-if="isPlatformAdmin" class="mb-10">
      <div class="d-flex align-center mb-4">
        <v-icon color="primary" class="mr-3" size="32" :icon="mdiAccountCog" />
        <h3 class="text-h5 font-weight-bold">User Management</h3>
      </div>
      <v-row>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/users" link hover class="admin-card">
            <v-card-item title="Users">
              <template #prepend>
                <v-icon color="primary" :icon="mdiAccountGroup" />
              </template>
              <v-card-subtitle>Manage accounts & roles</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/clubs" link hover class="admin-card">
            <v-card-item title="Clubs">
              <template #prepend>
                <v-icon color="secondary" :icon="mdiShieldHome" />
              </template>
              <v-card-subtitle>Club details & members</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/communications" link hover class="admin-card">
            <v-card-item title="Mass Comms">
              <template #prepend>
                <v-icon color="info" :icon="mdiEmailMultiple" />
              </template>
              <v-card-subtitle>Send platform announcements</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/pip-feedback" link hover class="admin-card">
            <v-card-item title="Pip Feedback">
              <template #prepend>
                <v-icon color="deep-purple" :icon="mdiRobotOutline" />
              </template>
              <v-card-subtitle>Review flagged Pip conversations</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
      </v-row>
    </section>

    <!-- Content & Data -->
    <section v-if="isPlatformAdmin || isDataAdmin" class="mb-10">
      <div class="d-flex align-center mb-4">
        <v-icon color="success" class="mr-3" size="32" :icon="mdiDatabaseEdit" />
        <h3 class="text-h5 font-weight-bold">Content & Data</h3>
      </div>
      <v-row>
        <v-col v-if="isPlatformAdmin || isDataAdmin" cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/pages" link hover class="admin-card">
            <v-card-item title="Pages">
              <template #prepend>
                <v-icon color="success" :icon="mdiFileEdit" />
              </template>
              <v-card-subtitle>Manage CMS content</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
        <v-col v-if="isPlatformAdmin || isDataAdmin" cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/suggested-edits" link hover class="admin-card">
            <v-card-item title="Suggestions">
              <template #prepend>
                <v-icon color="orange" :icon="mdiFileCompare" />
              </template>
              <v-card-subtitle>Review community edits</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
        <v-col v-if="isPlatformAdmin || isDataAdmin" cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/tasks" link hover class="admin-card">
            <v-card-item title="Data Quality">
              <template #prepend>
                <v-icon color="warning" :icon="mdiClipboardCheck" />
              </template>
              <v-card-subtitle>Missing data & validation</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
        <v-col v-if="isDataAdmin" cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/cave-system-with-cave" link hover class="admin-card">
            <v-card-item title="Add Cave">
              <template #prepend>
                <v-icon color="teal" :icon="mdiMapMarkerPlus" />
              </template>
              <v-card-subtitle>System & entrance wizard</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
        <v-col v-if="isDataAdmin" cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/catchments" link hover class="admin-card">
            <v-card-item title="Catchments">
              <template #prepend>
                <v-icon color="blue" :icon="mdiWaves" />
              </template>
              <v-card-subtitle>River gauge monitoring</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
      </v-row>
    </section>

    <!-- Analytics -->
    <section v-if="isPlatformAdmin" class="mb-10">
      <div class="d-flex align-center mb-4">
        <v-icon color="grey-darken-2" class="mr-3" size="32" :icon="mdiChartBar" />
        <h3 class="text-h5 font-weight-bold">Analytics</h3>
      </div>
      <v-row>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/dashboard" link hover class="admin-card">
            <v-card-item title="Traffic Dashboard">
              <template #prepend>
                <v-icon color="grey-darken-2" :icon="mdiChartLine" />
              </template>
              <v-card-subtitle>API trends & popular records</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
      </v-row>
    </section>

    <!-- Access & Permits -->
    <section v-if="isAccessOfficer || isPlatformAdmin" class="mb-10">
      <div class="d-flex align-center mb-4">
        <v-icon color="amber-darken-2" class="mr-3" size="32" :icon="mdiKeyVariant" />
        <h3 class="text-h5 font-weight-bold">Access & Permits</h3>
      </div>
      <v-row>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/permits" link hover class="admin-card">
            <v-card-item title="Permits">
              <template #prepend>
                <v-icon color="amber-darken-2" :icon="mdiFileDocumentEdit" />
              </template>
              <v-card-subtitle>Manage permit schemes</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/bookings" link hover class="admin-card">
            <v-card-item title="Bookings">
              <template #prepend>
                <v-icon color="amber-darken-3" :icon="mdiCalendarCheck" />
              </template>
              <v-card-subtitle>Review & manage applications</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
      </v-row>
    </section>
  </v-container>
</template>

<script setup>
import { mdiAccountCog, mdiAccountGroup, mdiCalendarCheck, mdiCalendarClock, mdiChartBar, mdiChartLine, mdiClipboardCheck, mdiDatabaseEdit, mdiEmailMultiple, mdiFileCompare, mdiFileDocumentEdit, mdiFileEdit, mdiKeyVariant, mdiMapMarkerPlus, mdiMonitorDashboard, mdiRobotOutline, mdiShieldAlert, mdiShieldHome, mdiWaves } from '@mdi/js'
import { useAppStore } from '@/stores/app'
import { computed } from 'vue'

defineOptions({
  name: 'AdminDashboard'
})

const appStore = useAppStore()

const hasRole = (role) => {
  return appStore.user?.roles?.some(r => r.slug === role)
}

const isPlatformAdmin = computed(() => hasRole('platform_admin'))
const isDutyOfficer = computed(() => hasRole('duty_officer')) // Duty Officers see their specific tools
const isDataAdmin = computed(() => hasRole('data_admin'))
const isAccessOfficer = computed(() => hasRole('access_officer'))
</script>

<style scoped>
.admin-card {
  transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
  border-radius: 12px;
  border: 1px solid rgba(0, 0, 0, 0.05);
}

.admin-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1) !important;
}

.admin-card :deep(.v-card-item__prepend) {
  padding-inline-end: 16px;
}

.admin-card :deep(.v-card-title) {
  font-size: 1.1rem;
  font-weight: 600;
  margin-bottom: 2px;
}

.admin-card :deep(.v-card-subtitle) {
  opacity: 0.7;
}

section {
  position: relative;
}

section:not(:last-child)::after {
  content: '';
  position: absolute;
  bottom: -40px;
  left: 0;
  right: 0;
  height: 1px;
  background: linear-gradient(to right, rgba(0, 0, 0, 0.05), transparent);
}
</style>
