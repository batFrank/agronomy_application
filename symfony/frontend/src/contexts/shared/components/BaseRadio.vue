<template>
  <div
    class="BaseRadio"
    :class="{ disabled, inline: type === 'inline' }"
  >
    <div
      v-for="item in optionList"
      :key="item.key"
      class="options"
    >
      <Icon
        class="icon"
        :icon="item.key === modelValue ? ['fas', 'dot-circle'] : ['far', 'circle']"
        @click="updateSelected(item.key)"
      />
      <span>{{ item.label }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const emit = defineEmits(['update:modelValue', 'change'])
const props = defineProps({
  name: { type: String, required: true },
  options: { type: Array, default: () => [] },
  trackBy: { type: String, default: 'name' },
  textBy: { type: String, default: 'label' },
  modelValue: { type: [String, Number], default: '' },
  disabled: { type: Boolean, default: false },
  type: {
    type: String,
    default: 'list',
    validator: (value) => ['list', 'inline'].includes(value),
  },
})

const optionList = computed(() =>
  props.options.map((option) => ({
    key: option[props.trackBy] || '',
    label: option[props.textBy] || '',
  }))
)

function updateSelected(key = '') {
  if (props.disabled) {
    return null
  }
  emit('update:modelValue', key)
  emit('change')
}
</script>

<style scoped lang="scss">
.BaseRadio {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;

  .options {
    display: grid;
    grid-template-columns: auto 1fr;
    align-items: center;
    gap: 0.5rem;

    .icon {
      font-size: 1.2rem;
      cursor: pointer;
      color: var(--input-text-color);
    }

    :deep(.fa-circle-dot) {
      color: var(--input-primary);
    }
  }

  &.disabled {
    .icon {
      color: var(--input-text-disabled);
      cursor: initial;
    }
  }

  &.inline {
    flex-direction: row;
  }
}
</style>
