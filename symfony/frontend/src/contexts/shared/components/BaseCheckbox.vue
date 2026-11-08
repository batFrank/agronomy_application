<template>
  <div
    class="BaseCheckbox"
    :class="classes"
  >
    <Icon
      class="icon"
      :icon="innerValue ? ['fas', 'square-check'] : ['far', 'square']"
      @click="updateValue"
    />
    <span> {{ label }} </span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const emit = defineEmits(['update:modelValue', 'change'])
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})
const innerValue = computed(() => !!props.modelValue)
function updateValue() {
  if (props.disabled) {
    return null
  }
  emit('update:modelValue', !innerValue.value)
  emit('change')
}
const classes = computed(() => [innerValue.value ? 'checked' : '', props.disabled ? 'disabled' : ''])
</script>

<style scoped lang="scss">
.BaseCheckbox {
  display: grid;
  grid-template-columns: auto 1fr;
  align-items: center;
  gap: 0.5rem;
  user-select: none;

  .icon {
    color: var(--input-text-color);
    font-size: 1.2rem;
    cursor: pointer;
  }

  &.checked {
    .icon {
      color: var(--input-primary);
    }
  }

  &.disabled .icon {
    color: var(--input-background-disabled);
    cursor: initial;
  }
}
</style>
