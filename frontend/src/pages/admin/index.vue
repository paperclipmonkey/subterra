<template>
  <v-container>
    <v-row>
      <v-col>
        <h1>Admin Dashboard</h1>
        <p class="mb-4">Select an administrative task below.</p>
        
        <v-list lines="one">
          <v-list-item
            prepend-icon="mdi-account-group"
            title="User Administration"
            subtitle="Manage user accounts, approvals, and roles"
            to="/admin/users"
            link
          ></v-list-item>
          
          <v-list-item
            prepend-icon="mdi-shield-account"
            title="Club Administration"
            subtitle="Manage club details and memberships"
            to="/admin/clubs"
            link
          ></v-list-item>
          
          <v-list-item
            prepend-icon="mdi-map-marker-plus"
            title="Add Cave System & Cave"
            subtitle="Create a new cave system and its first cave together"
            to="/admin/cave-system-with-cave"
            link
          ></v-list-item>
          
          <!-- Add more admin links here as needed -->
          
        </v-list>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
// No specific script logic needed for this simple navigation page yet
</script>

<style scoped>
/* Add any specific styles if needed */
</style>
