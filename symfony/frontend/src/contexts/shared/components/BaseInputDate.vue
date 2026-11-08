<template>
  <div
    class="BaseInputDate"
    :class="{ disabled }"
  >
    <label v-if="label">{{ label }}</label>
    <div class="input">
      <DateTimePicker
        v-model="innerValue"
        prevent-min-max-navigation
        auto-apply
        :enable-time-picker="type === 'datetime'"
        :time-picker-inline="type === 'datetime'"
        :clearable="false"
        :disabled="disabled"
        :format="customFormat"
        :dark="false"
        v-bind="$attrs"
      />
      <ErrorMessage :message="error" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import ErrorMessage from '@/contexts/shared/components/ErrorMessage.vue'

const emit = defineEmits(['update:modelValue'])
const props = defineProps({
  type: {
    type: String,
    default: 'date',
    validator: (value) => ['date', 'datetime'].includes(value),
  },
  modelValue: { type: [String, Date], default: null },
  disabled: { type: Boolean, default: false },
  label: { type: String, default: '' },
  format: { type: String, default: '' },
  error: { type: String, default: '' },
})

const innerValue = computed({
  get: () => props.modelValue,
  set: (value) => {
    if (props.disabled) {
      return null
    }
    emit('update:modelValue', value)
  },
})
const customFormat = computed(() => {
  if (props.format) return props.format
  if (props.type === 'datetime') return 'MM/dd/yyyy HH:mm'
  return 'MM/dd/yyyy'
})
</script>

<style scoped lang="scss">
.BaseInputDate {
  label {
    font-size: 0.9rem;
  }

  .input {
    width: 100%;
    position: relative;
  }

  .dp__theme_light {
    --dp-text-color: var(--input-text-color);
    --dp-background-color: var(--input-background);
    --dp-border-color: var(--input-border-color);
    --dp-disabled-color: var(--input-background-disabled);
    --dp-icon-color: var(--icon-color);
  }

  &.disabled .dp__theme_light {
    --dp-text-color: var(--input-text-disabled);
  }
}
</style>
