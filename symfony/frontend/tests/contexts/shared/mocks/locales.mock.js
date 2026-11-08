export default window.$settings.LOCALE_LIST_URL
  ? [
      {
        url: window.$settings.LOCALE_LIST_URL,
        callback: () => ({
          status: 200,
          error: false,
          data: {
            en: 'English',
            pt: 'Português',
            es: 'Español',
            fr: 'Français',
          },
        }),
        method: 'get',
      },
    ]
  : []
