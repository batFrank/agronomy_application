import { fn } from 'storybook/test'
import BaseButton from '@/contexts/shared/components/BaseButton.vue'

const TYPES = ['primary', 'success', 'warning', 'danger']
const SIZES = ['xs', 's', 'm', 'l']
const SHAPES = ['square', 'rounded', 'circular']

/** @type { import('@storybook/vue3-vite').Meta<typeof BaseButton> } */
const meta = {
  title: 'Shared/BaseButton',
  component: BaseButton,
  argTypes: {
    type: { control: 'select', options: TYPES },
    size: { control: 'select', options: SIZES },
    shape: { control: 'select', options: SHAPES },
    label: { control: 'text', description: 'Default slot content' },
  },
  args: {
    label: 'Save changes',
    type: 'primary',
    size: 'm',
    shape: 'rounded',
    disabled: false,
    onClick: fn(),
  },
  render: (args) => ({
    components: { BaseButton },
    setup: () => ({ args }),
    template: `
      <BaseButton
        :type="args.type"
        :size="args.size"
        :shape="args.shape"
        :disabled="args.disabled"
        @click="args.onClick"
      >
        {{ args.label }}
      </BaseButton>
    `,
  }),
}

export default meta

export const Primary = {}

export const Danger = {
  args: { type: 'danger', label: 'Delete user' },
}

export const Disabled = {
  args: { disabled: true },
}

export const Circular = {
  args: { shape: 'circular', label: '+' },
}

export const AllTypesAndSizes = {
  render: () => ({
    components: { BaseButton },
    setup: () => ({ TYPES, SIZES }),
    template: `
      <div style="display: grid; gap: 1rem">
        <div v-for="type in TYPES" :key="type" style="display: flex; gap: 1rem; align-items: center">
          <BaseButton v-for="size in SIZES" :key="size" :type="type" :size="size">{{ type }} / {{ size }}</BaseButton>
        </div>
      </div>
    `,
  }),
}
