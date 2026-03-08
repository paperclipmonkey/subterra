<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.go(-1)">
          <v-icon :icon="mdiArrowLeft" />
        </v-btn>
        <v-toolbar-title>Suggest Edit for {{ originalCollection.name }}</v-toolbar-title>
      </v-col>
    </v-row>
    <v-form ref="form" @submit.prevent="submitSuggestion">
      <v-row>
        <v-col cols="12">
          <v-alert type="info" class="mb-4">
            Thank you for helping improve our data! Your suggestions will be reviewed by an admin.
          </v-alert>
          <CollectionForm ref="collectionForm" v-model="collection" />
        </v-col>
      </v-row>

      <v-row>
        <v-col>
          <v-card-text>
            <v-btn type="submit" color="primary" block size="large" :loading="loading">Submit Suggestion</v-btn>
          </v-card-text>
        </v-col>
      </v-row>
    </v-form>

    <v-snackbar v-model="successSnackbar" color="success">
      Suggestion submitted! Redirecting...
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { mdiArrowLeft } from '@mdi/js'

import { ref, onMounted } from "vue"
import { useRoute, useRouter } from "vue-router"
import CollectionForm from '@/components/CollectionForm.vue'
import { api } from '@/plugins/api.js'

const router = useRouter()
const route = useRoute()
const collectionForm = ref(null)
const loading = ref(false)
const successSnackbar = ref(false)

const originalCollection = ref({})
const collection = ref({
    name: '',
    description: '',
    photo_path: '',
    caves: []
})

const fetchCollection = async () => {
    const response = await api.get(`/api/collections/${route.params.slug}`)
    const data = response.data.data // Verify data structure
    originalCollection.value = JSON.parse(JSON.stringify(data))

    // Transform original data if needed to match form structure
    // CollectionForm expects 'caves' array. API returns 'caves' with pivot data.
    // The CollectionForm logic handles mapping: "if (c.pivot) c.playlist_description = c.pivot.description"

    collection.value = JSON.parse(JSON.stringify(data))
}

const submitSuggestion = async () => {
    const { valid } = await collectionForm.value.validate()
    if (!valid) return

    loading.value = true
    try {
        await api.post('/api/suggested-edits', {
            suggestable_type: 'collection',
            suggestable_id: originalCollection.value.id,
            original_data: originalCollection.value,
            suggested_data: collection.value
        })

        successSnackbar.value = true
        setTimeout(() => {
            router.back()
        }, 1500)

    } catch (error) {
        console.error(error)
    } finally {
        loading.value = false
    }
}

onMounted(fetchCollection)
</script>
