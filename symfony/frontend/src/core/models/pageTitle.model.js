export class PageTitleModel {
  constructor({ id = 0, title = '', name = '', params = {} }) {
    this.title = title || '_'
    this.name = name || ''
    this.params = params || {}
    this.key = `pageLink_${id}`
  }
}
