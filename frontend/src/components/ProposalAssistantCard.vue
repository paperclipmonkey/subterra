<template>
  <v-card class="proposal-card" variant="outlined" :to="reviewRoute" link>
    <v-card-text class="pa-3">
      <div class="d-flex align-center mb-1">
        <v-icon :icon="icon" size="16" class="mr-2" color="primary" />
        <span class="proposal-card-type">{{ typeLabel }}</span>
        <v-spacer />
        <v-chip size="x-small" color="warning" variant="tonal">Pending review</v-chip>
      </div>
      <div class="proposal-card-target">{{ proposal.target || 'Multiple records' }}</div>
      <div class="proposal-card-hint">
        {{ proposal.count > 1 ? `${proposal.count} suggested edits` : '1 suggested edit' }}
        — click to review &amp; approve
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { computed } from 'vue'
import { mdiTagMultipleOutline, mdiSetMerge, mdiPencilOutline } from '@mdi/js'

const props = defineProps({
  proposal: { type: Object, required: true },
})

const typeLabel = computed(() => ({
  bulk_tag: 'Bulk tag proposal',
  merge: 'System merge proposal',
  field_fix: 'Data fix proposal',
}[props.proposal.type] || 'Proposal'))

const icon = computed(() => ({
  bulk_tag: mdiTagMultipleOutline,
  merge: mdiSetMerge,
  field_fix: mdiPencilOutline,
}[props.proposal.type] || mdiPencilOutline))

const reviewRoute = computed(() => props.proposal.review_url || '/admin/suggested-edits')
</script>

<style scoped>
.proposal-card {
  min-width: 230px;
  max-width: 300px;
  border-radius: 12px;
  background: #fff;
}
.proposal-card-type {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
}
.proposal-card-target {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.proposal-card-hint {
  font-size: 11px;
  color: #6b7280;
  margin-top: 2px;
}
</style>
