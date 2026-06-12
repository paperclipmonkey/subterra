<template>
  <v-container class="py-8" style="max-width: 900px;">
    <div class="mb-10 text-center">
      <h1 class="text-h3 font-weight-black mb-2 text-grey-darken-4">Latest News</h1>
      <p class="text-subtitle-1 text-medium-emphasis">Updates from the Subterra team</p>
    </div>

    <div v-if="loading" class="d-flex justify-center py-12">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <div v-else class="d-flex flex-column gap-6">
      <v-card v-for="item in news" :key="item.id" class="news-card rounded-xl overflow-hidden" elevation="0"
              border :to="`/news/${item.id}`" hover>
        <div class="d-flex flex-column flex-md-row">
          <div class="pa-6 pa-md-8 flex-grow-1">
            <div class="d-flex align-center mb-3">
              <v-icon :icon="mdiCalendar" size="small" color="primary" class="mr-2" />
              <span
                class="text-caption font-weight-bold text-uppercase text-medium-emphasis letter-spacing-1">
                {{ moment(item.date).format('MMMM D, YYYY') }}
              </span>
            </div>

            <h2 class="text-h5 font-weight-bold mb-3 text-grey-darken-4 lh-tight">
              {{ item.title }}
            </h2>

            <div class="text-body-1 text-grey-darken-1 mb-4 line-clamp-3">
              {{ getExcerpt(item.content) }}
            </div>

            <div class="d-flex align-center text-primary font-weight-bold text-body-2">
              Read Article <v-icon :icon="mdiArrowRight" class="ml-1" size="small" />
            </div>
          </div>
        </div>
      </v-card>
    </div>
  </v-container>
</template>

<script setup>
import { mdiArrowRight, mdiCalendar } from '@mdi/js'

import { ref, onMounted } from 'vue'
import moment from 'moment'
import { api } from '@/plugins/api'

const news = ref([])
const loading = ref(true)

const getExcerpt = (markdown) => {
  // Simple strip of header # and basic formatting for preview
  let text = markdown || ''
  // Drop a leading heading line — articles repeat their title as the first
  // heading, which would otherwise duplicate the card title in the excerpt.
  text = text.replace(/^\s*#{1,6}\s+.*(?:\r?\n)+/, '')
  // Remove remaining header markers but keep their text
  text = text.replace(/^#+\s+/gm, '')
  // Remove images
  text = text.replace(/!\[.*?\]\(.*?\)/g, '')
  // Remove links but keep text
  text = text.replace(/\[([^\]]+)\]\([^\)]+\)/g, '$1')
  // Remove heavy formatting
  text = text.replace(/(\*\*|__)(.*?)\1/g, '$2')
  text = text.replace(/(\*|_)(.*?)\1/g, '$2')

  // Truncate
  return text.length > 200 ? text.substring(0, 200) + '...' : text
}

onMounted(async () => {
  try {
    const res = await api.get('/api/news')
    news.value = res.data
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.gap-6 {
  gap: 24px;
}

.letter-spacing-1 {
  letter-spacing: 1px;
}

.lh-tight {
  line-height: 1.3;
}

.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.news-card {
  transition: transform 0.2s, box-shadow 0.2s;
}

.news-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}
</style>
