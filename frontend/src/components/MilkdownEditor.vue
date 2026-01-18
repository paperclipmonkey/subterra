<template>
  <div>
    <MilkdownProvider>
      <MilkdownInner :modelValue="modelValue" :placeholder="placeholder" @update:modelValue="emit('update:modelValue', $event)"
        @change="emit('change', $event)" />
    </MilkdownProvider>
  </div>
</template>

<script setup>
import { MilkdownProvider } from '@milkdown/vue'
import MilkdownInner from './MilkdownInner.vue'

defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Start writing...'
    }
})

const emit = defineEmits(['update:modelValue', 'change'])
</script>

<style scoped>
:deep(.milkdown) {
    min-height: 200px;
    width: 100%;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 4px;
    padding: 12px;
    background: transparent;
}

:deep(.milkdown:focus-within) {
    border-color: rgb(var(--v-theme-primary));
    border-width: 2px;
}

:deep(.milkdown .editor) {
    padding: 0;
    min-height: 180px;
    outline: none;
}

:deep(.milkdown p) {
    margin: 0 0 8px 0;
}

:deep(.milkdown p:last-child) {
    margin-bottom: 0;
}

:deep(.ProseMirror) {
    outline: none;
}

:deep(.ProseMirror p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    float: left;
    color: rgba(var(--v-theme-on-surface), 0.5);
    pointer-events: none;
    height: 0;
}
</style>
