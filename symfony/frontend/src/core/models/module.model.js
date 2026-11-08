export default class ModuleModel {
  constructor({ name, routes = {}, menuItems = [], footerItems = [], install = () => {} }) {
    this.name = name || ''
    this.routes = routes || {}
    this.menuItems = menuItems || []
    this.footerItems = footerItems || []
    this.install = install || (() => {})
  }
}
