import { fn } from 'storybook/test'
import BaseModal from '@/contexts/shared/components/BaseModal.vue'

const SIZES = ['s', 'm', 'l']

/** @type { import('@storybook/vue3-vite').Meta<typeof BaseModal> } */
const meta = {
  title: 'Shared/BaseModal',
  component: BaseModal,
  // The modal is a fixed full-viewport overlay, so it is rendered in its own frame in docs.
  parameters: {
    layout: 'fullscreen',
    docs: { story: { inline: false, iframeHeight: 420 } },
  },
  argTypes: {
    size: { control: 'select', options: SIZES },
    body: { control: 'text', description: 'Default slot content' },
  },
  args: {
    title: 'Edit user',
    showCloseButton: true,
    size: 'm',
    body: 'Modal body content goes in the default slot.',
    onClose: fn(),
  },
  render: (args) => ({
    components: { BaseModal },
    setup: () => ({ args }),
    template: `
      <BaseModal
        :title="args.title"
        :show-close-button="args.showCloseButton"
        :size="args.size"
        @close="args.onClose"
      >
        <p>{{ args.body }}</p>
      </BaseModal>
    `,
  }),
}

export default meta

export const Default = {}

export const Small = {
  args: { size: 's', title: 'Confirm' },
}

export const Large = {
  args: { size: 'l', title: 'Report' },
}

export const WithoutCloseButton = {
  args: { showCloseButton: false },
}
