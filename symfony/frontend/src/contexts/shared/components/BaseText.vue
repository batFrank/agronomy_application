<template>
  <div
    class="BaseText"
    :class="[`shape--${props.shape}`, props.required ? 'required' : '']"
  >
    <label
      v-if="label.length"
      :for="id"
    >
      {{ label }}
    </label>
    <span v-if="disabled">{{ innerValue }}</span>
    <textarea
      v-else
      :id="id"
      v-model="innerValue"
      class="box"
      :placeholder="placeholder"
    />
    <ErrorMessage :message="error" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import ErrorMessage from '@/contexts/shared/components/ErrorMessage.vue'

const emit = defineEmits(['update:modelValue', 'change'])
const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, default: '' },
  modelValue: { type: [String, Number], default: '' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  resizable: { type: Boolean, default: false },
  error: { type: String, default: '' },
  shape: {
    type: String,
    default: 'rounded',
    validator: (value) => ['rounded', 'square'].includes(value),
  },
})

const id = computed(() => `textarea_${props.name}`)
const innerValue = computed({
  get: () => props.modelValue,
  set: (value) => {
    if (props.disabled) {
      return null
    }
    emit('update:modelValue', value)
    emit('change')
  },
})
</script>

<style scoped lang="scss">
.BaseText {
  display: flex;
  flex-direction: column;
  align-items: start;
  width: 100%;
  position: relative;

  label {
    font-size: 0.9rem;
  }

  textarea,
  span {
    width: 100%;
    height: 10rem;
    padding: 1rem;
    resize: none;
    outline: none;
    background-color: var(--input-background);
    border: 1px solid var(--input-border-color);
    color: var(--input-text-color);
  }

  span {
    color: var(--input-text-disabled);
    background-color: var(--input-background-disabled);
    user-select: none;
  }

  &.shape {
    &--rounded {
      textarea,
      span {
        border-radius: 7px;
      }
    }
    &--square textarea {
      textarea,
      span {
        border-radius: 0;
      }
    }
  }

  &.required label:after {
    content: '*';
    color: var(--color-danger);
    padding-left: 0.25rem;
  }
}
</style>
