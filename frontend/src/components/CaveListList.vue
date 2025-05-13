<template>
  <v-data-table
    :headers="headers"
    :items="caveStore.caves"
    :items-per-page="10000"
    :loading="caveStore.loading"
    hide-default-footer
  >
    <template v-slot:loading>
      <v-skeleton-loader type="table-row@10"></v-skeleton-loader>
    </template>
    <template v-slot:item.system.length="{ value }">
      <span :title="`${value} m`">{{ Math.round((value / 1000)*10)/10 }} km</span>
    </template>
    <template v-slot:item.name="{ item, value }">
      <router-link :to="{name: '/cave/[id]', params: {id: item.slug}}">
        {{ value }}
      </router-link>
    </template>
    <template v-slot:item.location="{ item }">
        {{ item.location_name }}, {{ item.location_country }}
    </template>
    <template v-slot:item.tags="{ value }">
      <v-chip v-for="tag in value" :key="tag.tag" class="ma-1">
        <span :title="tag.description">{{ tag.tag }}</span>
      </v-chip>
    </template>
    <template v-slot:item.previously_done="{ value }">
      <v-icon :color="value ? 'green' : 'red'">{{ value ? 'mdi-check' : 'mdi-close' }}</v-icon>
    </template>
  </v-data-table>
</template>
<script setup>
  import { useCaveStore } from '@/stores/caves';
  const caveStore = useCaveStore()

  const headers = ref([
    { title: 'Name', key: 'name' },
    { title: 'Length', key: 'system.length' },
    { title: 'Location', key: 'location' },
    { title: 'Previously Done', key: 'previously_done' },
  ])
</script>