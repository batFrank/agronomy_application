const StylesPlugin = {
  async install() {
    let hasErrors = false
    if (window.$settings.FOLDER_NAME && window.$settings.FOLDER_NAME !== 'default')
      await import(`@/clients/${window.$settings.FOLDER_NAME}/shared/assets/styles/globals.scss`).catch(() => {
        console.log('Client Not Found')
        hasErrors = true
      })
    if (window.$settings.FOLDER_NAME === 'default' || hasErrors)
      await import('@/contexts/shared/assets/styles/globals.scss')
  },
}

export default StylesPlugin
