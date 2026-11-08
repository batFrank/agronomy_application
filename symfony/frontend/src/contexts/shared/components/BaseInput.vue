<template>
  <div
    class="BaseInput"
    :class="classes"
  >
    <label
      v-if="label.length"
      :for="id"
      >{{ label }}</label
    >
    <div class="input">
      <Icon
        v-if="icon.length"
        class="icon"
        :icon="icon"
      />
      <span
        v-if="disabled"
        class="box"
        >{{ innerValue }}</span
      >
      <input
        v-else
        :id="id"
        v-model="innerValue"
        class="box"
        :placeholder="placeholder"
        :required="required"
        :type="type"
      />
    </div>
    <ErrorMessage :message="error" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import ErrorMessage from '@/contexts/shared/components/ErrorMessage.vue'

const emit = defineEmits(['update:modelValue', 'change'])
const props = defineProps({
  name: { type: String, required: true },
  type: { type: String, default: 'text' },
  label: { type: String, default: '' },
  modelValue: { type: [String, Number], default: '' },
  placeholder: { type: String, default: '' },
  icon: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  error: { type: String, default: '' },
  shape: {
    type: String,
    default: 'rounded',
    validator: (value) => ['underlined', 'rounded', 'square'].includes(value),
  },
})

const id = computed(() => `input_${props.name}`)
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
const classes = computed(() => [
  `shape--${props.shape}`,
  props.icon.length ? 'hasIcon' : '',
  props.required ? 'required' : '',
])
</script>

<style scoped lang="scss">
.BaseInput {
  display: flex;
  flex-direction: column;
  align-items: start;
  position: relative;
  width: 100%;

  label {
    font-size: 0.9rem;
  }

  .input {
    width: 100%;
    position: relative;
    display: flex;
    align-items: center;

    span {
      display: block;
      height: 2rem;
      padding: 0.25rem 1rem;
      text-align: left;
      background-color: var(--input-background-disabled);
      user-select: none;
      color: var(--input-text-disabled);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    input {
      padding: 0.25rem 1rem;
      background-color: var(--input-background);
      color: var(--input-text-color);
    }

    .box {
      outline: none;
      width: 100%;
      border: 1px solid var(--input-border-color);
    }

    .icon {
      color: var(--icon-color);
      position: absolute;
      font-size: 1rem;
      left: 0.35rem;
    }
  }

  &.shape {
    &--underlined .box {
      border: solid var(--input-border-color);
      border-width: 0 0 2px;
    }
    &--rounded .box {
      border-radius: 7px;
    }
    &--square .box {
      border-radius: 0;
    }
  }

  &.hasIcon {
    .box {
      padding-left: 2rem;
    }
  }

  &.required label:after {
    content: '*';
    color: var(--color-danger);
    padding-left: 0.25rem;
  }
}
</style>
