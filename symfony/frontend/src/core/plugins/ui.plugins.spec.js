import { describe, expect, it } from 'vitest'
import { createApp } from 'vue'
import { installUiPlugins } from '@/core/plugins/ui.plugins.js'

describe('[core/plugins/ui.plugins]', () => {
  it('registers every global component and directive a component may rely on', () => {
    const app = createApp({})

    installUiPlugins(app)

    expect(app.component('Icon')).toBeDefined()
    expect(app.component('DateTimePicker')).toBeDefined()
    expect(app.directive('on-click-outside')).toBeDefined()
  })
})
