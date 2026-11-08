<template>
  <button
    type="button"
    class="BaseButton"
    :class="classes"
    v-bind="attrs"
    @click="emitAction"
  >
    <span class="content">
      <slot />
    </span>
  </button>
</template>

<script setup>
import { computed, useAttrs } from 'vue'
const attrs = useAttrs()
const emit = defineEmits(['click'])
const props = defineProps({
  type: {
    type: String,
    default: 'primary',
    validator: (value) => ['primary', 'success', 'warning', 'danger'].includes(value),
  },
  size: {
    type: String,
    default: 'm',
    validator: (value) => ['xs', 's', 'm', 'l'].includes(value),
  },
  shape: {
    type: String,
    default: 'rounded',
    validator: (value) => ['square', 'rounded', 'circular'].includes(value),
  },
  disabled: { type: Boolean, default: false },
})

const classes = computed(() => [
  `type--${props.type}`,
  `size--${props.size}`,
  `shape--${props.shape}`,
  props.disabled ? `disabled` : '',
])

function emitAction() {
  if (props.disabled) {
    return null
  }
  emit('click')
}
</script>

<style scoped lang="scss">
.BaseButton {
  display: inline-grid;
  place-content: center;
  border: none;
  cursor: pointer;
  outline: none;
  user-select: none;
  border-radius: var(--border-radius);
  color: var(--button-color);
  background: var(--button-background-color);
  padding: var(--button-padding);
  font-size: var(--button-font-size);

  &:not(.disabled):hover {
    background-color: var(--button-background-color-hover);
  }

  &.type {
    &--primary {
      --button-color: var(--color-neutral-lightest);
      --button-background-color: var(--color-primary);
      --button-background-color-hover: var(--color-primary-hover);
    }
    &--success {
      --button-color: var(--color-neutral-lightest);
      --button-background-color: var(--color-success);
      --button-background-color-hover: var(--color-success-hover);
    }
    &--warning {
      --button-color: var(--color-neutral-dark);
      --button-background-color: var(--color-warning);
      --button-background-color-hover: var(--color-warning-hover);
    }
    &--danger {
      --button-color: var(--color-neutral-lightest);
      --button-background-color: var(--color-danger);
      --button-background-color-hover: var(--color-danger-hover);
    }
  }

  &.size {
    &--xs {
      --button-padding: 0.25rem 1rem;
      --button-font-size: 0.6rem;
    }
    &--s {
      --button-padding: 0.35rem 1rem;
      --button-font-size: 0.75rem;
    }
    &--m {
      --button-padding: 0.5rem 1rem;
      --button-font-size: 1rem;
    }
    &--l {
      --button-padding: 0.5rem 2rem;
      --button-font-size: 1.125rem;
    }
  }

  &.shape {
    &--square {
      --border-radius: 0;
    }
    &--rounded {
      --border-radius: 3px;
    }
    &--circular {
      aspect-ratio: 1;
      --border-radius: 99999px;
    }
  }

  &.disabled {
    --button-color: var(--input-text-disabled);
    --button-background-color: var(--input-background-disabled);
    cursor: initial;
  }
}
</style>
