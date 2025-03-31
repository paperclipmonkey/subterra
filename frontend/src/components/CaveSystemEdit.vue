<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.go(-1)">
          <v-icon>mdi-arrow-left</v-icon>
        </v-btn>
        <v-toolbar-title>{{ cavesystem.name }}</v-toolbar-title>
      </v-col>
    </v-row>
    <v-form @submit.prevent="save">
    <v-row>
      <v-col cols="12">
          <v-card>
            <v-card-title>
              <v-text-field v-model="cavesystem.name" label="Cave System Name" required></v-text-field>
            </v-card-title>
            <v-card-text>
              <v-textarea v-model="cavesystem.description" label="System description" required></v-textarea>
              <v-text-field v-model="cavesystem.length" label="Length (m)" required></v-text-field>
              <v-text-field v-model="cavesystem.vertical_range" label="Vertical Range (m)"></v-text-field>
              <v-text-field v-model="cavesystem.slug" label="Slug" required></v-text-field>
              <v-textarea v-model="cavesystem.references" label="References" required></v-textarea>
            </v-card-text>
          </v-card>
      </v-col>
    </v-row>

    <!-- <v-row>
      <v-col>
        <v-card>
            <v-card-title>Tags</v-card-title>
            <v-card-text>
            <template v-for="(groupItems, groupName) in tagsAvailable">
            <h2 class="text-h6 mb-2 tagGroupTitle">{{groupName}}</h2>

            <v-chip-group
              v-model="selectedTags[groupName]"
              column
              :multiple="true"
            >
              <v-chip
                v-for="tag in groupItems"
                :text="tag.tag"
                variant="outlined"
                :value="tag.tag"
                filter
              ></v-chip>
            </v-chip-group>
            </template>
          </v-card-text>
        <v-divider class="mt-2"></v-divider>
        </v-card>
      </v-col>
    </v-row> -->
    <v-row>
      <v-col>
        <v-card-text>
          <v-btn type="submit" color="primary">Save</v-btn>
        </v-card-text>
      </v-col>
    </v-row>
  </v-form>

  </v-container>
</template>

<script setup>
import { watch } from "vue"
import { useRoute, useRouter } from "vue-router"

const router = useRouter()
const route = useRoute()

const selectedTags = ref({})

  const cavesystem = ref({
    name: '',
    description: '',
    length: '',
    vertical_range: '',
    slug: '',
    tags: [],
    caves: [],
    references: "",
  })

  const tagsAvailable = ref({})

  const load = async () => {
    const response = await fetch(`/api/cave_systems/${route.params.id}`)
    cavesystem.value = (await response.json()).data

    selectedTags.value = cavesystem.value.tags.reduce((acc, tag) => {
      if(tag.type !== 'cavesystem') { // Only show cave tags
        return acc
      }
      if (!acc[tag.category]) {
        acc[tag.category] = []
      }
      acc[tag.category].push(tag.tag)
      return acc
    }, {})

    const tagsresponse = await fetch('/api/tags')
    tagsAvailable.value = (await tagsresponse.json())
    
    // TODO: remove tags that aren't related to a cave
  }

  const save = async () => {
    // Re-add tags to cave
    cavesystem.value.tags = Object.entries(selectedTags.value).reduce((acc, [category, tags]) => {
      return acc.concat(tags.map(tag => ({ category, tag })))
    }, [])
    const response = await fetch(`/api/cave_systems/${route.params.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(cavesystem.value),
    })
    if (response.ok) {
      router.go(-1)
    }
  }

  onMounted(load)

  watch(
    () => route.fullPath,
    load
  )
</script>
