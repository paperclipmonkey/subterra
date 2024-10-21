<template>
  <v-container>
    <v-row>
      <v-col cols="12" v-for="(value, key) in news">
        <v-card>
          <v-card-title>{{ key }}</v-card-title>
          <v-card-text>
            <vue-markdown :source="value" />      
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import VueMarkdown from 'vue-markdown-render'

const news = ref({})

const fetchNews = async () => {
  const response = await fetch(`/api/news`)
  news.value = (await response.json())
}

onMounted(fetchNews)
</script>
