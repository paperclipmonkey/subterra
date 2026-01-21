<template>
  <v-container>
    <h2 class="headline mb-4">System Administration</h2>
    <v-row>
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
    </v-row>
  </v-container>
</template>

<script>
export default {
  name: 'SystemAdmin'
}
</script>
