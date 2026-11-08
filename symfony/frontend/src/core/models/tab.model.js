export default class TabModel {
  constructor({ key = '', title = '', icon = '', view = undefined, props = {}, actions = {} } = {}) {
    this.key = key || ''
    this.title = title || ''
    this.icon = icon || ''
    this.view = view
    this.props = props
    this.actions = actions
  }
}
