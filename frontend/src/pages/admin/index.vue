<template>
  <v-container>
    <div class="mb-8">
      <h2 class="text-h3 font-weight-bold mb-1">Administration</h2>
      <p class="text-subtitle-1 text-grey-darken-1">Manage platform operations, users, and content.</p>
    </div>

    <!-- Operations & Safety -->
    <section class="mb-10">
      <div class="d-flex align-center mb-4">
        <v-icon color="error" class="mr-3" size="32">mdi-shield-alert</v-icon>
        <h3 class="text-h5 font-weight-bold">Operations & Safety</h3>
      </div>
      <v-row>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/callout" link hover class="admin-card">
            <v-card-item title="Callout Admin">
              <template v-slot:prepend>
                <v-icon color="error">mdi-monitor-dashboard</v-icon>
              </template>
              <v-card-subtitle>Live dashboard & incidents</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/rota" link hover class="admin-card">
            <v-card-item title="Duty Rota">
              <template v-slot:prepend>
                <v-icon color="deep-purple">mdi-calendar-clock</v-icon>
              </template>
              <v-card-subtitle>Manage on-call shifts</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
      </v-row>
    </section>

    <!-- User Management -->
    <section class="mb-10">
      <div class="d-flex align-center mb-4">
        <v-icon color="primary" class="mr-3" size="32">mdi-account-cog</v-icon>
        <h3 class="text-h5 font-weight-bold">User Management</h3>
      </div>
      <v-row>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/users" link hover class="admin-card">
            <v-card-item title="Users">
              <template v-slot:prepend>
                <v-icon color="primary">mdi-account-group</v-icon>
              </template>
              <v-card-subtitle>Manage accounts & roles</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/clubs" link hover class="admin-card">
            <v-card-item title="Clubs">
              <template v-slot:prepend>
                <v-icon color="secondary">mdi-shield-home</v-icon>
              </template>
              <v-card-subtitle>Club details & members</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/communications" link hover class="admin-card">
            <v-card-item title="Mass Comms">
              <template v-slot:prepend>
                <v-icon color="info">mdi-email-multiple</v-icon>
              </template>
              <v-card-subtitle>Send platform announcements</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
      </v-row>
    </section>

    <!-- Content & Data -->
    <section class="mb-10">
      <div class="d-flex align-center mb-4">
        <v-icon color="success" class="mr-3" size="32">mdi-database-edit</v-icon>
        <h3 class="text-h5 font-weight-bold">Content & Data</h3>
      </div>
      <v-row>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/pages" link hover class="admin-card">
            <v-card-item title="Pages">
              <template v-slot:prepend>
                <v-icon color="success">mdi-file-edit</v-icon>
              </template>
              <v-card-subtitle>Manage CMS content</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/suggested-edits" link hover class="admin-card">
            <v-card-item title="Suggestions">
              <template v-slot:prepend>
                <v-icon color="orange">mdi-file-compare</v-icon>
              </template>
              <v-card-subtitle>Review community edits</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/tasks" link hover class="admin-card">
            <v-card-item title="Data Quality">
              <template v-slot:prepend>
                <v-icon color="warning">mdi-clipboard-check</v-icon>
              </template>
              <v-card-subtitle>Missing data & validation</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/cave-system-with-cave" link hover class="admin-card">
            <v-card-item title="Add Cave">
              <template v-slot:prepend>
                <v-icon color="teal">mdi-map-marker-plus</v-icon>
              </template>
              <v-card-subtitle>System & entrance wizard</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/catchments" link hover class="admin-card">
            <v-card-item title="Catchments">
              <template v-slot:prepend>
                <v-icon color="blue">mdi-waves</v-icon>
              </template>
              <v-card-subtitle>River gauge monitoring</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
      </v-row>
    </section>

    <!-- Analytics -->
    <section class="mb-10">
      <div class="d-flex align-center mb-4">
        <v-icon color="grey-darken-2" class="mr-3" size="32">mdi-chart-bar</v-icon>
        <h3 class="text-h5 font-weight-bold">Analytics</h3>
      </div>
      <v-row>
        <v-col cols="12" sm="6" md="4" lg="3">
          <v-card to="/admin/dashboard" link hover class="admin-card">
            <v-card-item title="Traffic Dashboard">
              <template v-slot:prepend>
                <v-icon color="grey-darken-2">mdi-chart-line</v-icon>
              </template>
              <v-card-subtitle>API trends & popular records</v-card-subtitle>
            </v-card-item>
          </v-card>
        </v-col>
      </v-row>
    </section>
  </v-container>
</template>

<script setup>
defineOptions({
  name: 'AdminDashboard'
})
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
