export class FooterItemModel {
  constructor({ id = 0, title = '', link = '', roles = [], order = 99 }) {
    this.id = id || 0
    this.title = title || ''
    this.link = link || ''
    this.roles = roles || []
    this.order = order || 99
    this.key = `footer_item_${this.id}`
  }
}
