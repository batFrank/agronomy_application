let resolveMenu
export const menuState = { isReady: false }

export const menuReady = new Promise((resolve) => {
  resolveMenu = () => {
    menuState.isReady = true
    resolve()
  }
})

export { resolveMenu }
