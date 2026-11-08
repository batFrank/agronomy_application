<template>
  <div
    class="BaseSelect"
    :class="classes"
  >
    <label :for="id">{{ label }}</label>
    <div class="input">
      <Icon
        v-if="icon.length"
        class="icon"
        :icon="icon"
      />
      <VueSelect
        :id="id"
        v-model="innerValue"
        :disabled="disabled"
        :is-clearable="!disabled && isClearable"
        :is-multi="multiselect"
        :options="optionList"
        :placeholder="placeholder"
        :close-on-select="!multiselect"
        @option-selected="(option) => emit('change', option)"
      />
    </div>
    <ErrorMessage :message="error" />
  </div>
</template>

<script setup>
import VueSelect from 'vue3-select-component'
import 'vue3-select-component/dist/styles.css'
import { computed } from 'vue'
import ErrorMessage from '@/contexts/shared/components/ErrorMessage.vue'
const emit = defineEmits(['update:modelValue', 'change'])
const props = defineProps({
  name: { type: String, required: true },
  modelValue: { type: [String, Number, Array], default: '' },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: '' },
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  isClearable: { type: Boolean, default: true },
  multiselect: { type: Boolean, default: false },
  trackBy: { type: String, default: 'value' },
  textBy: { type: String, default: 'label' },
  icon: { type: Array, default: () => [] },
  shape: {
    type: String,
    default: 'rounded',
    validator: (value) => ['underlined', 'rounded', 'square'].includes(value),
  },
  error: { type: String, default: '' },
  required: { type: Boolean, default: false },
  position: { type: String, default: 'bottom', validator: (value) => ['top', 'bottom'].includes(value) },
})
const id = computed(() => `select_${props.name}`)
const innerValue = computed({
  get: () => props.modelValue,
  set: (value) => {
    if (props.disabled) {
      return null
    }
    emit('update:modelValue', value)
  },
})

const optionList = computed(() =>
  props.options.map((option) => ({
    value: option[props.trackBy] || '',
    label: option[props.textBy] || '',
    disabled: option?.disabled || false,
  }))
)
const classes = computed(() => [
  `shape--${props.shape}`,
  props.icon.length ? 'hasIcon' : '',
  props.disabled ? 'disabled' : '',
  props.required ? 'required' : '',
  props.position === 'bottom' ? '' : 'top',
])
</script>

<style scoped lang="scss">
.BaseSelect {
  position: relative;
  width: 100%;
  --vs-background-color: var(--input-background);
  --vs-border: 1px solid var(--input-border-color);
  --vs-multi-value-background-color: var(--input-background);
  &:deep(.multi-value) {
    border: var(--vs-border);
    svg {
      fill: var(--icon-color);
    }
  }

  &:deep(svg) {
    fill: var(--icon-color);
  }

  &:deep(.no-results) {
    background-color: var(--vs-background-color);
  }

  &.shape {
    &--underlined {
      :deep(.control) {
        border-width: 0 0 2px;
        --vs-border-radius: 0;
      }
    }
    &--rounded {
      --vs-border-radius: 7px;
    }
    &--square {
      --vs-border-radius: 0;
    }
  }

  &.hasIcon {
    :deep(.control) {
      padding-left: 2rem;
    }
  }

  .input {
    width: 100%;
    position: relative;
    color: var(--input-text-color);
    --vs-text-color: var(--input-text-color);
    --vs-multi-value-label-text-color: var(--input-text-color);

    .icon {
      color: var(--icon-color);
      position: absolute;
      font-size: 1.4rem;
      top: 0.5rem;
      left: 0.5rem;
      z-index: 1;
    }
  }

  &.disabled {
    .input {
      --vs-background-color: var(--input-background-disabled);
      --vs-text-color: var(--input-text-disabled);
      --vs-multi-value-label-text-color: var(--input-text-disabled);
    }
  }

  &.required label:after {
    content: '*';
    color: var(--color-danger);
    padding-left: 0.25rem;
  }

  &.top {
    &:deep([role='listbox']) {
      bottom: 100%;
    }
  }
}
</style>
