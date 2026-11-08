<template>
  <div
    class="BaseSwitch"
    :class="{ disabled }"
  >
    <input
      v-model="innerValue"
      type="checkbox"
      :disabled="disabled"
    />

    <label @click="updateValue" />
  </div>
</template>

<script setup>
import { computed } from 'vue'

const emit = defineEmits(['update:modelValue', 'change'])
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const innerValue = computed(() => props.modelValue)
function updateValue() {
  if (props.disabled) {
    return null
  }
  emit('update:modelValue', !innerValue.value)
  emit('change')
}
</script>

<style scoped lang="scss">
.BaseSwitch {
  width: fit-content;
  display: flex;
  align-items: center;

  input[type='checkbox'] {
    height: 0;
    width: 0;
    visibility: hidden;
    position: absolute;
  }

  label {
    cursor: pointer;
    text-indent: -9999px;
    width: 50px;
    height: 26px;
    background: var(--input-primary-bg);
    border: 1px solid var(--input-border-color);
    display: block;
    border-radius: 100px;
    position: relative;
    margin: 0;
  }

  label:after {
    content: '';
    position: absolute;
    top: 1px;
    left: 2px;
    width: 20px;
    height: 20px;
    background: var(--color-neutral-lightest);
    border: 1px solid var(--input-border-color);
    border-radius: 90px;
    transition: 0.3s;
  }

  input:checked + label {
    background: var(--input-primary);
  }

  input:checked + label:after {
    left: calc(100% - 2px);
    transform: translateX(-100%);
  }

  label:active:after {
    width: 33px;
  }

  &.disabled {
    label {
      cursor: initial;
      background: var(--input-background-disabled);
      &:after {
        background: var(--color-neutral-mid-dark);
      }
    }
    input:checked + label {
      background: var(--input-background-disabled);
      &:after {
        background: var(--color-neutral-mid-dark);
      }
    }
  }
}
</style>
