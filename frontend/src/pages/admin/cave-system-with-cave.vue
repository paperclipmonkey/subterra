<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.go(-1)">
          <v-icon>mdi-arrow-left</v-icon>
        </v-btn>
        <v-toolbar-title>Add Cave System & Cave</v-toolbar-title>
      </v-col>
    </v-row>
    <v-form @submit.prevent="submit">
      <v-row>
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title>Cave System Details</v-card-title>
            <v-card-text>
              <v-text-field v-model="system.name" label="System Name" required></v-text-field>
              <v-textarea v-model="system.description" label="System Description"></v-textarea>
              <v-text-field v-model="system.length" label="Length (m)" type="number" required></v-text-field>
              <v-text-field v-model="system.vertical_range" label="Vertical Range (m)" type="number" required></v-text-field>
              <v-text-field v-model="system.slug" label="Slug" :placeholder="'e.g. region_cave-name'" :hint="'Lowercase, a-z, 0-9, _ and - only'" persistent-hint></v-text-field>
              <v-textarea v-model="system.references" label="References"></v-textarea>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title>Cave Details</v-card-title>
            <v-card-text>
              <v-text-field v-model="cave.name" label="Cave Name" required></v-text-field>
              <v-textarea v-model="cave.description" label="Cave Description"></v-textarea>
              <v-text-field v-model="cave.location_name" label="Location Name" required></v-text-field>
              <v-text-field v-model="cave.location_country" label="Country" required></v-text-field>
              <v-text-field v-model="cave.location_lat" label="Latitude" type="number" required></v-text-field>
              <v-text-field v-model="cave.location_lng" label="Longitude" type="number" required></v-text-field>
              <v-text-field v-model="cave.location_alt" label="Altitude (m)" type="number"></v-text-field>
              <v-textarea v-model="cave.access_info" label="Access Info"></v-textarea>
              <v-text-field v-model="cave.slug" label="Slug" :placeholder="'e.g. region_cave-name'" :hint="'Lowercase, a-z, 0-9, _ and - only'" persistent-hint></v-text-field>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
      <v-row>
        <v-col cols="12">
          <v-btn type="submit" color="primary">Create System & Cave</v-btn>
        </v-col>
      </v-row>
    </v-form>
  </v-container>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const system = ref({
  name: '',
  description: '',
  length: '',
  vertical_range: '',
  slug: '',
  references: ''
})
const cave = ref({
  name: '',
  description: '',
  location_name: '',
  location_country: '',
  location_lat: '',
  location_lng: '',
  location_alt: '',
  access_info: '',
  slug: ''
})

const slugify = (value) => {
  return value
    .toLowerCase()
    .replace(/[^a-z0-9_-]+/g, '-') // Only allow a-z, 0-9, _ and -
    .replace(/^-+|-+$/g, '') // Remove leading/trailing dashes
    .replace(/--+/g, '-') // Replace multiple dashes with one
}

const submit = async () => {
  // Slugify before submit
  if(system.value.slug) system.value.slug = slugify(system.value.slug)
  if(cave.value.slug) cave.value.slug = slugify(cave.value.slug)

  const response = await fetch('/api/cave_systems_with_cave', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ system: system.value, cave: cave.value })
  })
  if (response.ok) {
    const data = await response.json()
    router.push({ name: '/cave/[id]', params: { id: data.cave.slug } })
  } else {
    alert('Failed to create cave system and cave')
  }
}
</script>
