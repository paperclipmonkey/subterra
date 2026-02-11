<template>
  <v-dialog :model-value="props.isActive" @update:model-value="$emit('close')" max-width="500">
    <template v-slot:default>
      <v-card rounded="lg">
        <v-card-title class="d-flex justify-space-between align-center">
          <div class="text-h5 text-medium-emphasis ps-2">
            Add trip participants
          </div>

          <v-btn
            icon="mdi-close"
            variant="text"
            @click="$emit('close')"
          ></v-btn>
        </v-card-title>

        <v-divider class="mb-4"></v-divider>

        <v-card-text>
          <div class="text-medium-emphasis mb-4">
            Add the trip participant
          </div>

          <v-alert
            v-if="props.error"
            type="error"
            variant="tonal"
            class="mb-4"
            closable
          >
            {{ props.error }}
          </v-alert>

          <v-text-field
            label="Name"
            type="text"
            v-model="name"
            :rules="nameRules"
          ></v-text-field>

          <v-text-field
            label="Email address"
            type="email"
            :rules="emailRules"
            v-model="email"
          ></v-text-field>
        </v-card-text>

        <v-divider class="mt-2"></v-divider>

        <v-card-actions class="my-2 d-flex justify-end">
          <v-btn
            class="text-none"
            rounded="xl"
            text="Cancel"
            @click="$emit('close')"
            :disabled="props.loading"
          ></v-btn>

          <v-btn
            class="text-none"
            color="primary"
            rounded="xl"
            text="Add"
            variant="flat"
            @click="$emit('add', {name, email,})"
            :loading="props.loading"
            :disabled="!isValid"
          ></v-btn>
        </v-card-actions>
      </v-card>
    </template>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps(['isActive', 'loading', 'error'])
const name = ref('')
const email = ref('')

// Reset fields when dialog opens
watch(() => props.isActive, (newVal) => {
  if (newVal) {
    name.value = ''
    email.value = ''
  }
})

const nameRules = [
  value => {
    if (value) return true
    return 'Name is required.'
  }
]

const emailRules = [
  value => {
    // Email is optional for manual participants unless we want to enforce it? 
    // The prompt says "if I don't add an email address. If I do...".
    // Let's make it optional but valid if present, OR required if that's the desired behavior.
    // The user said: "The form submits but resets if I don't add an email address."
    // implying they might expect it to work without one, or maybe they want it to fail gracefully.
    // Let's assume name is required, email is optional but must be valid if provided.
    // Actually, backend `create` method requires email: 'email' => 'required|string|email...'.
    // So we MUST require email.
    if (value) return true
    return 'E-mail is required.'
  },
  value => {
    if (/.+@.+\..+/.test(value)) return true
    return 'E-mail must be valid.'
  },
]

const isValid = computed(() => {
  return name.value && email.value && /.+@.+\..+/.test(email.value)
})
</script>