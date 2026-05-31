<template>
  <v-autocomplete
    :model-value="modelValue"
    :label="label"
    :items="items"
    item-title="name"
    item-value="id"
    :variant="variant"
    :placeholder="placeholder"
    :error-messages="errorMessages"
    :rules="rules"
    :loading="loading"
    :name="inputName"
    :hint="hint"
    :persistent-hint="persistentHint"
    autocomplete="off"
    @update:model-value="onSelect"
  >
    <template #item="{ props: itemProps, item }">
      <v-list-item v-bind="itemProps" :title="item.raw.name">
        <template #subtitle>
          <span class="text-caption text-medium-emphasis">
            {{ [item.raw.location_name, item.raw.location_country].filter(Boolean).join(', ') }}
          </span>
        </template>
        <template #append>
          <v-chip
            v-if="item.raw.is_curated"
            size="x-small"
            color="primary"
            variant="tonal"
            class="ml-2"
          >
            Curated
          </v-chip>
        </template>
      </v-list-item>
    </template>
  </v-autocomplete>
</template>

<script setup>
const props = defineProps({
  modelValue: {
    type: [Number, String, null],
    default: null,
  },
  items: {
    type: Array,
    default: () => [],
  },
  label: {
    type: String,
    default: 'Cave Entrance',
  },
  placeholder: {
    type: String,
    default: 'Search for a cave...',
  },
  variant: {
    type: String,
    default: 'outlined',
  },
  errorMessages: {
    type: [String, Array],
    default: () => [],
  },
  rules: {
    type: Array,
    default: () => [],
  },
  inputName: {
    type: String,
    default: 'cave_search_no_autofill',
  },
  loading: {
    type: Boolean,
    default: false,
  },
  hint: {
    type: String,
    default: undefined,
  },
  persistentHint: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'cave-selected'])

const onSelect = (id) => {
  emit('update:modelValue', id)
  const cave = props.items.find(c => c.id === id)
  emit('cave-selected', cave ?? null)
}
</script>
