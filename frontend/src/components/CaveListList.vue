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
    <template v-slot:item.previously_done="{ value, item }">
      <template v-if="value">
        <v-icon color="green" icon="mdi-check"></v-icon>
      </template>
      <template v-else>
        <v-icon color="red" icon="mdi-close" @click="showConfirmModal = true; caveToMark = item"></v-icon>
      </template>
    </template>
  </v-data-table>
  <v-dialog v-model="showConfirmModal" max-width="500">
    <v-card>
      <v-card-title>Confirm</v-card-title>
      <v-card-text>Are you sure you want to mark this cave as done?</v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn text @click="showConfirmModal = false; caveToMark = null">Cancel</v-btn>
        <v-btn text color="primary" @click="markAsDone(caveToMark)">Confirm</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
<script setup>

  import { useCaveStore } from '@/stores/caves';
  import { useAppStore } from '@/stores/app';
  const caveStore = useCaveStore();
  const appStore = useAppStore();
  const showConfirmModal = ref(false);
  const caveToMark = ref(null);

  const headers = ref([
    { title: 'Name', key: 'name' },
    { title: 'Length', key: 'system.length' },
    { title: 'Location', key: 'location' },
    { title: 'Previously Done', key: 'previously_done' },
  ])

  const markAsDone = async (cave) => {
    if (!cave) return;
    const trip = {
      name: 'Marked as Done',
      entrance_cave_id: cave.id,
      exit_cave_id: cave.id,
      participants: [appStore.user.id],
      cave_system_id: cave.system.id,
    }

    const response = await fetch('/api/trips', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(trip)
    })
    if (response.ok) {
      await caveStore.getList() // Refresh the cave list
      showConfirmModal.value = false
      caveToMark.value = null
    } else {
      console.error('failed to save trip')
    }
  }
</script>