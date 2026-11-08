<template>
  <div class="BaseInputFile">
    <label
      class="inputFileBox"
      :class="{ disabled }"
    >
      <input
        type="file"
        :accept="accept"
        :disabled="disabled"
        v-bind="$attrs"
        @change="change"
      />

      <div class="textContainer">
        <Icon
          v-if="icon.length"
          class="icon"
          :icon="icon"
        />

        <span
          v-if="fileNames"
          class="infoText files-name"
          >{{ fileNames }}</span
        >

        <span
          v-else
          class="infoText"
          >{{ label }}</span
        >
        <span
          v-if="maxSize"
          class="maxSizeText"
        >
          Max: {{ bytesToText(maxSize) }}
        </span>
      </div>
    </label>
    <ErrorMessage :message="error" />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import ErrorMessage from '@/contexts/shared/components/ErrorMessage.vue'
import { bytesToText } from '../utils/misc.utils.js'

const emit = defineEmits(['update:modelValue'])
const props = defineProps({
  label: { type: String, default: 'No hay archivos adjuntos' },
  accept: { type: String, default: 'application/pdf,image/*,video/*' },
  maxSize: { type: Number, default: 0 },
  modelValue: { type: Array, default: () => [] },
  icon: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const innerValue = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

const fileNames = ref('')
function change({ target }) {
  if (props.disabled) {
    return null
  }
  const files = [...target.files].filter(
    (file) =>
      new RegExp(props.accept.replaceAll('/', '\/').replaceAll(',', '|'), 'ig').test(file.type) &&
      (props.maxSize ? file.size <= props.maxSize : true)
  )
  fileNames.value = files.map((f) => f.name).join('; ')
  innerValue.value = files
}
</script>

<style scoped lang="scss">
.BaseInputFile {
  display: flex;
  width: 100%;
  flex-direction: column;
  .inputFileBox {
    position: relative;
    border: 1px dashed var(--input-border-color);
    background-color: var(--input-background);
    color: var(--input-text-color);
    padding: 2rem 1rem;
    border-radius: 3px;
    cursor: pointer;
    width: 100%;

    &:has(.files-name) {
      border: 1px solid var(--input-primary);
      background-color: var(--input-primary-bg);

      .maxSizeText {
        display: none;
      }
    }

    .icon {
      color: var(--icon-color);
    }

    input {
      display: none;
    }

    .infoText {
      display: block;
      text-align: center;
      font-size: 0.8rem;
      color: var(--input-text-color);
      max-width: 80%;
      white-space: nowrap;
      text-overflow: ellipsis;
      overflow: hidden;
    }

    &.disabled {
      cursor: initial;
      border: 1px solid var(--input-border-color);
      background-color: var(--input-background-disabled);

      .infoText {
        color: var(--input-text-disabled);
      }
    }

    .textContainer {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .maxSizeText {
      position: absolute;
      font-size: 0.65rem;
      bottom: 0;
      right: 0;
      padding: 0 0.25rem;
      border: dashed var(--input-border-color);
      border-width: 1px 0 0 1px;
    }
  }
}
</style>
