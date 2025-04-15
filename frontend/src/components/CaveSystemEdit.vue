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
              <v-text-field v-model="cavesystem.length" label="Length (m)" required type="number"></v-text-field>
              <v-text-field v-model="cavesystem.vertical_range" label="Vertical Range (m)" type="number"></v-text-field>
              <v-text-field v-model="cavesystem.slug" label="Slug" required></v-text-field>
              <v-textarea v-model="cavesystem.references" label="References" required></v-textarea>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Files Section -->
      <v-row>
        <v-col cols="12">
          <v-card>
            <v-card-title>Files</v-card-title>
            <v-card-text>
              <!-- Display Existing Files -->
              <v-list lines="one" v-if="cavesystem.files && cavesystem.files.length > 0">
                <v-list-item
                  v-for="file in cavesystem.files"
                  :key="file.id"
                  :title="file.original_filename || file.filename"
                  :subtitle="`${(file.size / 1024).toFixed(2)} KB`"
                  :class="{ 'file-marked-for-deletion': filesToDelete.includes(file.id) }"
                >
                  <template v-slot:append>
                    <v-btn
                      color="red"
                      icon="mdi-delete"
                      variant="text"
                      size="small"
                      @click="toggleFileDeletion(file.id)"
                    ></v-btn>
                  </template>
                </v-list-item>
              </v-list>
              <p v-else>No files uploaded yet.</p>

              <v-divider class="my-4"></v-divider>

              <!-- Upload New Files -->
              <v-file-input
                v-model="newFiles"
                label="Upload New Files"
                multiple
                chips
                show-size
                counter
                prepend-icon="mdi-paperclip"
              ></v-file-input>
              <!-- Optional: Add inputs for details per file if needed -->

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

<style scoped>
.file-marked-for-deletion {
  opacity: 0.6;
  text-decoration: line-through;
}
</style>

<script setup>
import { ref, watch, onMounted } from "vue"
import { useRoute, useRouter } from "vue-router"

const router = useRouter()
const route = useRoute()

const selectedTags = ref({})
const filesToDelete = ref([]) // Track IDs of files to delete
const newFiles = ref([]) // Track new files to upload

const cavesystem = ref({
  name: '',
  description: '',
  length: '',
  vertical_range: '',
  slug: '',
  tags: [],
  caves: [],
  references: "",
  files: [] // Initialize files array
})

const tagsAvailable = ref({})

const load = async () => {
  try {
    const response = await fetch(`/api/cave_systems/${route.params.id}`)
    if (!response.ok) throw new Error('Failed to load cave system')
    cavesystem.value = (await response.json()).data
    // Ensure files array exists even if null from API
    cavesystem.value.files = cavesystem.value.files || []
    filesToDelete.value = [] // Reset deletion list on load
    newFiles.value = [] // Reset new files list on load

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

  } catch (error) {
    console.error("Error loading cave system data:", error)
    // Handle error display to user if necessary
  }
}

const toggleFileDeletion = (fileId) => {
  const index = filesToDelete.value.indexOf(fileId)
  if (index > -1) {
    filesToDelete.value.splice(index, 1) // Unmark
  } else {
    filesToDelete.value.push(fileId) // Mark
  }
}

const save = async () => {
  // Use FormData for multipart/form-data request (needed for files)
  const formData = new FormData()

  // Append standard fields
  formData.append('_method', 'PUT') // Method override for Laravel
  formData.append('name', cavesystem.value.name || '')
  formData.append('description', cavesystem.value.description || '')
  formData.append('length', cavesystem.value.length || '')
  formData.append('vertical_range', cavesystem.value.vertical_range || '')
  formData.append('slug', cavesystem.value.slug || '')
  formData.append('references', cavesystem.value.references || '')

  // Append tags (if uncommented and used)
  // const tagsToSend = Object.entries(selectedTags.value).reduce((acc, [category, tags]) => {
  //   return acc.concat(tags.map(tag => ({ category, tag })))
  // }, [])
  // formData.append('tags', JSON.stringify(tagsToSend)); // Send tags as JSON string if needed by backend

  // Append files marked for deletion
  filesToDelete.value.forEach(fileId => {
    formData.append('deleted_files[]', fileId)
  })

  // Append new files
  newFiles.value.forEach((file) => {
    formData.append('new_files[]', file)
    // If you add details per file, append them here, e.g.:
    // formData.append('new_file_details[]', JSON.stringify({ description: 'My new survey' }));
  })

  try {
    const response = await fetch(`/api/cave_systems/${route.params.id}`, {
      method: 'POST', // <-- Corrected: Use POST for FormData with _method spoofing
      // No 'Content-Type' header - browser sets it for FormData
      body: formData,
      headers: {
        // Add Authorization header if needed
        'Accept': 'application/json', // Expect JSON response
      }
    })

    if (response.ok) {
      router.go(-1) // Go back on success
    } else {
      // Handle errors (e.g., validation errors)
      const errors = await response.json()
      console.error('Save failed:', errors)
      // Display errors to the user
      alert(`Save failed: ${JSON.stringify(errors)}`)
    }
  } catch (error) {
    console.error('Error saving cave system:', error)
    alert(`An error occurred: ${error.message}`)
  }
}

onMounted(load)

watch(
  () => route.fullPath,
  (newPath, oldPath) => {
    // Reload only if the ID changes, prevent reload on hash changes etc.
    if (route.params.id && newPath.includes(`/cave-systems/${route.params.id}`)) {
       load()
    }
  }
)
</script>
