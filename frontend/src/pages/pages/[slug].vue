<template>
  <v-container>
    <div v-if="loading" class="d-flex justify-center mt-12">
       <v-progress-circular indeterminate></v-progress-circular>
    </div>
    <div v-else-if="page">
      <h1 class="text-h3 mb-6">{{ page.title }}</h1>
      <div class="text-caption mb-4 text-medium-emphasis">
        Last updated: {{ formatDate(page.updated_at) }}
      </div>
      <v-sheet class="pa-6" rounded border>
        <div class="vue-markdown">
            <VueMarkdownRender :source="page.content" />
        </div>
      </v-sheet>
    </div>
    <div v-else class="text-center mt-12">
        <h2 class="text-h4">Page not found</h2>
        <v-btn to="/" class="mt-4" color="primary">Go Home</v-btn>
    </div>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import VueMarkdownRender from 'vue-markdown-render';
import moment from 'moment';

const route = useRoute();
const page = ref(null);
const loading = ref(true);

const fetchPage = async () => {
    try {
        const res = await fetch(`/api/pages/${route.params.slug}`);
        if (res.ok) {
            page.value = (await res.json()).data;
        }
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

const formatDate = (date) => moment(date).format('MMMM Do YYYY, h:mm a');

onMounted(fetchPage);
</script>

<style scoped>
:deep(.vue-markdown) {
    font-family: Roboto, sans-serif;
    font-size: 16px;
    line-height: 1.6;
    color: rgba(0, 0, 0, 0.87);
}

:deep(.vue-markdown h1),
:deep(.vue-markdown h2),
:deep(.vue-markdown h3) {
    font-weight: 500;
    margin-bottom: 16px;
    color: #1a1a1a;
}

:deep(.vue-markdown p) {
    margin-bottom: 16px;
    color: rgba(0, 0, 0, 0.87);
}

:deep(.vue-markdown a) {
    color: #1976D2;
    text-decoration: none;
}

:deep(.vue-markdown a:hover) {
    text-decoration: underline;
}

:deep(.vue-markdown ul),
:deep(.vue-markdown ol) {
    padding-left: 24px;
    margin-bottom: 16px;
    color: rgba(0, 0, 0, 0.87);
}

:deep(.vue-markdown li) {
    margin-bottom: 8px;
}

:deep(.vue-markdown code) {
    background-color: #f5f5f5;
    padding: 2px 4px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    color: #d32f2f;
}

:deep(.vue-markdown pre) {
    background-color: #f5f5f5;
    padding: 16px;
    border-radius: 4px;
    overflow-x: auto;
    color: #333;
}
</style>
