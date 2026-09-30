<!--
  The reporting entry point, shared by every page that can be reported from.

  Kept deliberately short: a category, an optional note, send. Anything that
  interrogates the person raising a concern is something they abandon halfway.
-->
<template>
  <v-dialog v-model="visible" max-width="520" :persistent="submitting">
    <v-card>
      <v-card-title class="d-flex align-center pa-4" style="gap: 10px;">
        <v-icon color="error" :icon="mdiFlagOutline" />
        <span class="text-h6">Report this {{ targetNoun }}</span>
      </v-card-title>

      <v-divider />

      <v-card-text v-if="sent" class="pa-6 text-center">
        <v-icon color="success" size="48" :icon="mdiCheckCircleOutline" class="mb-3" />
        <p class="text-body-1 mb-1">Thanks — this has been sent to our moderators.</p>
        <p class="text-body-2 text-medium-emphasis mb-0">
          We look at every report. You won't normally hear back unless we need more from you.
        </p>
      </v-card-text>

      <v-card-text v-else class="pa-4">
        <p class="text-body-2 text-medium-emphasis mb-4">
          Tell us what's wrong and a moderator will look at it. If someone is in immediate
          danger, contact the police on 999 rather than waiting for us.
        </p>

        <v-radio-group
          id="report-category"
          v-model="category"
          :error-messages="categoryError"
          hide-details="auto"
          class="mb-4"
        >
          <v-radio
            v-for="option in categories"
            :key="option.value"
            :value="option.value"
            :label="option.label"
            color="error"
            density="comfortable"
          />
        </v-radio-group>

        <v-textarea
          id="report-details"
          v-model="details"
          label="Anything else we should know? (optional)"
          variant="outlined"
          rows="3"
          counter="2000"
          maxlength="2000"
          hide-details="auto"
          :error-messages="detailsError"
        />

        <v-alert
          v-if="errorMessage"
          type="error"
          variant="tonal"
          density="comfortable"
          class="mt-4"
        >
          {{ errorMessage }}
        </v-alert>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" class="text-none" :disabled="submitting" @click="close">
          {{ sent ? 'Close' : 'Cancel' }}
        </v-btn>
        <v-btn
          v-if="!sent"
          color="error"
          variant="flat"
          class="text-none px-6"
          :loading="submitting"
          @click="submit"
        >
          Send report
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { mdiFlagOutline, mdiCheckCircleOutline } from '@mdi/js'
import { api } from '@/plugins/api'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  // Short type name the API allowlists: trip, user, cave, trip_media, cave_media.
  reportableType: { type: String, required: true },
  reportableId: { type: [String, Number], required: true },
})

const emit = defineEmits(['update:modelValue'])

const visible = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

// Wording is plain rather than legal — "involves someone under 18" reads as
// something a person would actually click, "safeguarding concern" does not.
const categories = [
  { value: 'child_safety', label: 'It involves someone under 18' },
  { value: 'harassment', label: 'Harassment, bullying or abuse' },
  { value: 'privacy', label: "It shares someone's private information" },
  { value: 'illegal', label: 'It looks illegal' },
  { value: 'inaccurate', label: "It's wrong or misleading" },
  { value: 'spam', label: 'Spam or advertising' },
  { value: 'other', label: 'Something else' },
]

const targetNouns = {
  trip: 'trip report',
  user: 'member',
  cave: 'cave record',
  trip_media: 'photo',
  cave_media: 'photo',
}

const targetNoun = computed(() => targetNouns[props.reportableType] || 'content')

const category = ref(null)
const details = ref('')
const submitting = ref(false)
const sent = ref(false)
const errorMessage = ref('')
const categoryError = ref('')
const detailsError = ref('')

// Reset on open so a second report never inherits the first one's answers.
watch(visible, isOpen => {
  if (isOpen) {
    category.value = null
    details.value = ''
    sent.value = false
    submitting.value = false
    errorMessage.value = ''
    categoryError.value = ''
    detailsError.value = ''
  }
})

const close = () => {
  visible.value = false
}

const submit = async () => {
  categoryError.value = ''
  detailsError.value = ''
  errorMessage.value = ''

  if (!category.value) {
    categoryError.value = 'Please pick a reason.'
    return
  }

  submitting.value = true
  try {
    await api.post('/api/reports', {
      reportable_type: props.reportableType,
      reportable_id: String(props.reportableId),
      category: category.value,
      details: details.value || null,
    })
    sent.value = true
  } catch (error) {
    const status = error.response?.status
    if (status === 429) {
      errorMessage.value = "You've sent a lot of reports recently. Please try again later."
    } else if (status === 422) {
      const errors = error.response?.data?.errors || {}
      categoryError.value = errors.category?.[0] || ''
      detailsError.value = errors.details?.[0] || ''
      if (!categoryError.value && !detailsError.value) {
        errorMessage.value = 'That report could not be sent. Please check and try again.'
      }
    } else {
      errorMessage.value = error.response?.data?.message || 'That report could not be sent. Please try again.'
    }
  } finally {
    submitting.value = false
  }
}
</script>
