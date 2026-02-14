<template>
  <v-dialog v-model="dialog" persistent max-width="600px">
    <v-card>
      <v-card-title>
        <span class="text-h5">Create New Club</span>
      </v-card-title>
      <v-card-text>
        <v-container>
          <v-row>
            <v-col cols="12">
              <v-text-field v-model="clubName" label="Club Name*" required />
            </v-col>
            <v-col cols="12">
              <v-text-field v-model="clubSlug" label="Club Slug*" required />
            </v-col>
            <v-col cols="12">
              <div class="text-subtitle-2 mb-1">Description</div>
              <MilkdownEditor v-model="clubDescription" placeholder="Markdown supported..." />
            </v-col>
          </v-row>
        </v-container>
        <small>*indicates required field</small>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn color="blue darken-1" text @click="closeDialog">
          Cancel
        </v-btn>
        <v-btn color="blue darken-1" text :loading="loading" @click="saveClub">
          Save
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import { mande } from 'mande'
import MilkdownEditor from '@/components/MilkdownEditor.vue'

const props = defineProps({
  modelValue: Boolean,
})

const emit = defineEmits(['update:modelValue', 'clubCreated'])

const dialog = ref(false)
const clubName = ref('')
const clubSlug = ref('')
const clubDescription = ref('')
const loading = ref(false)

// API instance for clubs
const clubsApi = mande('/api/admin/clubs')

watch(() => props.modelValue, (newValue) => {
  dialog.value = newValue
  if (newValue) {
    // Reset form when dialog opens
    clubName.value = ''
    clubSlug.value = ''
    clubDescription.value = ''
  }
})

function closeDialog() {
  dialog.value = false
  emit('update:modelValue', false)
}

async function saveClub() {
  if (!clubName.value || !clubSlug.value) {
    alert('Club name and slug are required.')
    return
  }
  loading.value = true
  try {
    const payload = { name: clubName.value, slug: clubSlug.value }
    if (clubDescription.value) {
      payload.description = clubDescription.value
    }
    const response = await clubsApi.post(payload)
    emit('clubCreated', response)
    closeDialog()
  } catch (error) {
    console.error('Error creating club:', error)
    // Handle error display to the user, e.g., using a snackbar
    alert('Failed to create club. Please try again.')
  } finally {
    loading.value = false
  }
}
</script>
