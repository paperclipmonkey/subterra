<template>
  <v-container>
    <v-row>
      <v-col>
        <div class="d-flex justify-space-between align-center mb-4">
          <h1>Manage Pages</h1>
          <v-btn color="primary" to="/admin/pages/edit" prepend-icon="mdi-plus">Create Page</v-btn>
        </div>

        <v-text-field
          v-model="search"
          append-inner-icon="mdi-magnify"
          label="Search Pages"
          single-line
          hide-details
          class="mb-4"
        ></v-text-field>

        <v-data-table
          :headers="headers"
          :items="pages"
          :loading="loading"
          :search="search"
          class="elevation-1"
        >
          <template v-slot:item.actions="{ item }">
            <v-btn icon="mdi-pencil" variant="text" size="small" :to="'/admin/pages/edit?id=' + item.id"></v-btn>
            <v-btn icon="mdi-open-in-new" variant="text" size="small" :to="'/pages/' + item.slug" target="_blank"></v-btn>
            <v-btn icon="mdi-delete" variant="text" size="small" color="error" @click="deletePage(item)"></v-btn>
          </template>
        </v-data-table>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { mande } from 'mande';

const pagesApi = mande('/api/admin/pages');
const pages = ref([]);
const loading = ref(false);
const search = ref('');

const headers = [
  { title: 'ID', key: 'id', sortable: true },
  { title: 'Title', key: 'title', sortable: true },
  { title: 'Slug', key: 'slug', sortable: true },
  { title: 'Views', key: 'access_count', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
];

const fetchPages = async () => {
  loading.value = true;
  try {
    pages.value = (await pagesApi.get()).data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

const deletePage = async (page) => {
  if (!confirm('Are you sure you want to delete this page?')) return;
  try {
    await pagesApi.delete(page.id);
    await fetchPages();
  } catch (e) {
    console.error(e);
    alert('Failed to delete page');
  }
};

onMounted(fetchPages);
</script>
