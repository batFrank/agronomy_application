export class MenuItemModel {
  constructor({ id = 0, title = '', icon, link = '', subItems = [], prefix = '', order = 99, roles = [] } = {}) {
    this.id = id || 0
    this.title = title || ''
    this.link = link || ''
    this.icon = icon || ''
    this.order = order || 99
    this.roles = roles || []
    this.subItems = (subItems || []).map(
      (item) =>
        new MenuItemModel({
          id: item.id,
          title: item.title,
          icon: item.icon,
          link: item.link,
          prefix: 'sub_menu_item_',
        })
    )
    this.isFolder = !this.subItems.length
    this.key = `${prefix || 'menu_item_'}${this.id}`
  }
}
