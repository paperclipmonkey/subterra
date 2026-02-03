<template>
  <v-container>
    <h2 class="headline mb-4">System Administration</h2>
    <v-row>
      <v-col cols="12" md="6" lg="3">
        <v-card to="/admin/dashboard" link hover height="100%">
          <v-card-title><v-icon left>mdi-chart-line</v-icon> Dashboard</v-card-title>
          <v-card-text>API interactions & trends.</v-card-text>
        </v-card>
      </v-col>
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
        <v-card to="/admin/cave-system-with-cave" link hover height="100%">
          <v-card-title><v-icon left>mdi-map-marker-plus</v-icon> Add Cave</v-card-title>
          <v-card-text>Add System & Entrance.</v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="6" lg="3">
        <v-card to="/admin/pages" link hover height="100%">
          <v-card-title><v-icon left>mdi-file-document-edit</v-icon> Pages</v-card-title>
          <v-card-text>Manage CMS Pages.</v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="6" lg="3">
        <v-card to="/admin/tasks" link hover height="100%">
          <v-card-title><v-icon left color="warning">mdi-clipboard-check</v-icon> Tasks</v-card-title>
          <v-card-text>Approvals & Data Quality.</v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="6" lg="3">
        <v-card to="/admin/catchments" link hover height="100%">
          <v-card-title><v-icon left>mdi-waves</v-icon> Catchments</v-card-title>
          <v-card-text>Manage River Gauges.</v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
export default {
  name: 'SystemAdmin'
}
</script>
