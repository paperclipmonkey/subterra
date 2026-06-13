<template>
  <v-dialog :model-value="modelValue" max-width="440" @update:model-value="$emit('update:modelValue', $event)">
    <v-card>
      <v-card-title class="d-flex align-center pa-4 pb-2">
        <v-icon :icon="mdiCellphoneCheck" class="mr-2" /> Verify your phone
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-4">
        <p class="text-body-2 text-medium-emphasis mb-4">
          We've sent a 6-digit code by text to <strong>{{ phone }}</strong>. Enter it below to confirm
          callout alerts can reach you.
        </p>

        <v-text-field
          v-model="code"
          label="6-digit code"
          inputmode="numeric"
          maxlength="6"
          :disabled="verifying"
          autofocus
          @keyup.enter="verify"
        />

        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-3 text-caption">
          {{ error }}
        </v-alert>
        <v-alert v-else-if="info" type="info" variant="tonal" density="compact" class="mt-3 text-caption">
          {{ info }}
        </v-alert>
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-btn variant="text" :disabled="sending || resendIn > 0" :loading="sending" @click="sendCode">
          {{ resendIn > 0 ? `Resend in ${resendIn}s` : 'Resend code' }}
        </v-btn>
        <v-spacer />
        <v-btn variant="text" @click="$emit('update:modelValue', false)">Cancel</v-btn>
        <v-btn color="primary" variant="flat" :loading="verifying" :disabled="code.length !== 6" @click="verify">
          Verify
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
import { mdiCellphoneCheck } from '@mdi/js'
import { api } from '@/plugins/api'

export default {
  name: 'PhoneVerify',
  props: {
    modelValue: { type: Boolean, default: false },
    phone: { type: String, default: '' },
  },
  emits: ['update:modelValue', 'verified'],
  setup() {
    return { mdiCellphoneCheck }
  },
  data() {
    return { code: '', sending: false, verifying: false, error: null, info: null, resendIn: 0, timer: null }
  },
  watch: {
    modelValue(open) {
      if (open) {
        this.code = ''
        this.error = null
        this.info = null
        // Auto-send a code when the dialog opens (unless we just sent one).
        if (this.resendIn === 0) this.sendCode()
      }
    },
  },
  beforeUnmount() {
    if (this.timer) clearInterval(this.timer)
  },
  methods: {
    async sendCode() {
      this.sending = true
      this.error = null
      try {
        const res = await api.post('/api/users/me/phone/send-code', {}, { suppressErrorNotification: true })
        this.info = res.data.message || 'Code sent.'
        this.startResendCooldown()
      } catch (e) {
        this.error = e.response?.data?.message || 'Could not send a code. Please try again shortly.'
      } finally {
        this.sending = false
      }
    },
    async verify() {
      if (this.code.length !== 6 || this.verifying) return
      this.verifying = true
      this.error = null
      try {
        await api.post('/api/users/me/phone/verify', { code: this.code }, { suppressErrorNotification: true })
        this.$emit('verified') // parent refreshes the user/store
        this.$emit('update:modelValue', false)
      } catch (e) {
        this.error = e.response?.data?.message || 'That code is incorrect or has expired.'
        this.code = ''
      } finally {
        this.verifying = false
      }
    },
    startResendCooldown() {
      this.resendIn = 30
      if (this.timer) clearInterval(this.timer)
      this.timer = setInterval(() => {
        this.resendIn -= 1
        if (this.resendIn <= 0) clearInterval(this.timer)
      }, 1000)
    },
  },
}
</script>
