<template>
  <v-container class="py-8" style="max-width: 800px;">
    <v-btn variant="text" prepend-icon="mdi-arrow-left" to="/news" class="mb-4 text-none" color="grey-darken-1">
      Back to News
    </v-btn>

    <div v-if="loading" class="d-flex justify-center py-12">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <div v-else-if="error" class="text-center py-12">
      <v-icon icon="mdi-alert-circle-outline" size="48" color="error" class="mb-4" />
      <div class="text-h6 text-medium-emphasis">Article not found</div>
      <v-btn to="/news" color="primary" class="mt-4" variant="tonal">Return to Feed</v-btn>
    </div>

    <article v-else class="news-article">
      <header class="mb-8">
        <div class="text-subtitle-1 text-primary font-weight-bold mb-2">
          {{ moment(article.date).format('MMMM Do, YYYY') }}
        </div>
      </header>

      <v-card class="rounded-xl pa-2 pa-sm-8" border flat>
        <div class="article-content">
          <MarkdownRenderer :source="article.content" />
        </div>
      </v-card>
    </article>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'
import moment from 'moment'

const route = useRoute()
const article = ref(null)
const loading = ref(true)
const error = ref(false)

onMounted(async () => {
  try {
    const res = await fetch(`/api/news/${route.params.id}`)
    if (!res.ok) throw new Error('Failed to fetch')
    article.value = await res.json()
  } catch (e) {
    error.value = true
    console.error(e)
  } finally {
    loading.value = false
  }
})
</script>

<style>
/* Global markdown styles for this component context */
.article-content {
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  line-height: 1.8;
  color: #374151;
}

.article-content h1 {
  font-size: 2rem;
  font-weight: 800;
  margin-bottom: 1.5rem;
  color: #111827;
  line-height: 1.2;
}

.article-content h2 {
  font-size: 1.5rem;
  font-weight: 700;
  margin-top: 2rem;
  margin-bottom: 1rem;
  color: #1f2937;
}

.article-content h3 {
  font-size: 1.25rem;
  font-weight: 600;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
  color: #374151;
}

.article-content p {
  margin-bottom: 1.25rem;
  font-size: 1.05rem;
}

.article-content ul,
.article-content ol {
  margin-bottom: 1.25rem;
  padding-left: 1.5rem;
}

.article-content li {
  margin-bottom: 0.5rem;
}

.article-content a {
  color: rgb(var(--v-theme-primary));
  text-decoration: underline;
  text-underline-offset: 2px;
}

.article-content blockquote {
  border-left: 4px solid rgb(var(--v-theme-primary));
  padding-left: 1rem;
  margin-left: 0;
  margin-bottom: 1.25rem;
  font-style: italic;
  color: #4b5563;
  background: #f9fafb;
  padding: 1rem;
  border-radius: 0 8px 8px 0;
}

.article-content img {
  max-width: 100%;
  border-radius: 12px;
  margin: 1.5rem 0;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.article-content code {
  background-color: #f3f4f6;
  padding: 0.2rem 0.4rem;
  border-radius: 4px;
  font-size: 0.9em;
  font-family: monospace;
  color: #ef4444;
}
</style>
