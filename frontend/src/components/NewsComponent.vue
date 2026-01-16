<template>
  <v-container>
    <v-row>
      <v-col cols="12" v-for="(value, key) in news">
        <v-card>
          <v-card-title>{{ key }}</v-card-title>
          <v-card-text>
            <vue-markdown class="vue-markdown" :source="value" />      
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
<style lang="css">
.vue-markdown {
  font-family: Roboto, sans-serif;
  font-size: 16px;
  line-height: 1.6;
  color: rgba(0, 0, 0, 0.87);
}

.vue-markdown h1,
.vue-markdown h2,
.vue-markdown h3 {
  font-weight: 500;
  margin-bottom: 16px;
  color: #1a1a1a;
}

.vue-markdown p {
  margin-bottom: 16px;
  color: rgba(0, 0, 0, 0.87);
}

.vue-markdown a {
  color: #1976D2;
  text-decoration: none;
}

.vue-markdown a:hover {
  text-decoration: underline;
}

.vue-markdown ul,
.vue-markdown ol {
  padding-left: 24px;
  margin-bottom: 16px;
  color: rgba(0, 0, 0, 0.87);
}

.vue-markdown li {
  margin-bottom: 8px;
}

.vue-markdown code {
  background-color: #f5f5f5;
  padding: 2px 4px;
  border-radius: 4px;
  font-family: 'Courier New', monospace;
  color: #d32f2f;
}

.vue-markdown pre {
  background-color: #f5f5f5;
  padding: 16px;
  border-radius: 4px;
  overflow-x: auto;
  color: #333;
}
</style>