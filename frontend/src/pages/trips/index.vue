<script setup>
import { mdiPlus } from '@mdi/js'
</script>
<template>
  <TripList />
  <v-fab color="primary" to="/create-trip" class="fab" :icon="mdiPlus" />
</template>

<style scoped>
.fab {
  position: fixed;
  right: 36px;
  bottom: 90px;
}
</style>