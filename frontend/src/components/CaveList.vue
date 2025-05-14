<template>
  <v-card
    flat
  >
    <template v-slot:text>
      <v-text-field
        v-model="search"
        label="Search"
        prepend-inner-icon="mdi-magnify"
        variant="outlined"
        hide-details
        single-line
      >
        <template v-slot:append-inner>
          <v-icon
            @click="showFilterByTagModal = true"
            icon="mdi-filter"
            :color="cachedTags.length ? 'success' : ''"
          >
        </v-icon>
        <template v-if="cachedTags.length">
          {{ cachedTags.length }}
        </template>
        </template>
      </v-text-field>
    </template>

    <v-tabs
      v-model="tab"
      align-tabs="center"
    >
      <v-tab :value="'list'">List</v-tab>
      <v-tab :value="'map'">Map</v-tab>
    </v-tabs>
    <v-tabs-window v-model="tab">
      <v-tabs-window-item
        :key="'list'"
        :value="'list'">
        <CaveListList/>
    </v-tabs-window-item>
    <v-tabs-window-item
      :key="'map'"
      :value="'map'">
        <CaveListMap/>
    </v-tabs-window-item>
  </v-tabs-window>
  <FilterByTagModal @close="showFilterByTagModal=false" @filter="applyFilter" :isActive="showFilterByTagModal"/>
  </v-card>
</template>

<script setup>
  import { useCaveStore } from '@/stores/caves';
  import FilterByTagModal from './FilterByTagModal.vue';
  import { ref, watch, onMounted } from 'vue';
  import { useRoute, useRouter } from 'vue-router';

  const caveStore = useCaveStore()

  const route = useRoute();
  const router = useRouter();

  // Initialize search from query parameter
  const search = ref(route.query.search || '');

  const showFilterByTagModal = ref(false)

  let cachedTags = ref([]);

  const applyFilter = (tags) => {
    cachedTags = tags
    caveStore.applyFilters(tags, search.value)
    // Update URL with tags as a comma-separated string
    router.replace({ query: { ...route.query, tags: tags.join(',') }});
    showFilterByTagModal.value = false
  }

  watch(search, (newSearch) => {
    caveStore.applyFilters(cachedTags, newSearch)
    // Update URL with current search
    router.replace({ query: { ...route.query, search: newSearch }});
  })

  const tab = ref(route.query.view || 'list')

  // Update URL when tab changes
  watch(tab, (newTab) => {
    router.replace({ query: { ...route.query, view: newTab }});
  })

  onMounted(async () => {
    // Ensure search and view parameters are applied on reload
    search.value = route.query.search || '';
    tab.value = route.query.view || 'list';
    const tags = route.query.tags ? route.query.tags.split(',') : [];
    caveStore.applyFilters(tags, search.value);
    cachedTags.value = tags;
    await caveStore.getList();
  })
</script>
