import { ref, watch } from 'vue'
import { fn } from 'storybook/test'
import BaseInput from '@/contexts/shared/components/BaseInput.vue'

const SHAPES = ['underlined', 'rounded', 'square']

/** @type { import('@storybook/vue3-vite').Meta<typeof BaseInput> } */
const meta = {
  title: 'Shared/BaseInput',
  component: BaseInput,
  argTypes: {
    shape: { control: 'select', options: SHAPES },
    type: { control: 'select', options: ['text', 'email', 'password', 'number'] },
  },
  args: {
    name: 'email',
    type: 'text',
    label: 'Email address',
    modelValue: '',
    placeholder: 'name@example.com',
    icon: [],
    disabled: false,
    required: false,
    error: '',
    shape: 'rounded',
    onChange: fn(),
    'onUpdate:modelValue': fn(),
  },
  render: (args) => ({
    components: { BaseInput },
    setup: () => {
      // The control panel owns the initial value; typing in the story owns it afterwards.
      const value = ref(args.modelValue)
      watch(
        () => args.modelValue,
        (nextValue) => (value.value = nextValue)
      )
      return { args, value }
    },
    template: `
      <BaseInput
        v-bind="args"
        v-model="value"
        style="max-width: 320px"
      />
    `,
  }),
}

export default meta

export const Default = {}

export const WithIcon = {
  args: { icon: ['fas', 'bars'], placeholder: 'Search' },
}

export const Required = {
  args: { required: true },
}

export const WithError = {
  args: { modelValue: 'not-an-email', error: 'Please enter a valid email address' },
}

export const Disabled = {
  args: { modelValue: 'read only value', disabled: true },
}

export const Underlined = {
  args: { shape: 'underlined' },
}
