import ClickOutside from '@/core/directives/clickOutside.js'

export function addDirectives(app) {
  app.directive('on-click-outside', ClickOutside)
}
