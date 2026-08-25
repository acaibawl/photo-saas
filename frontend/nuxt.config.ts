// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxt/eslint', '@nuxt/ui', '@vee-validate/nuxt', '@pinia/nuxt'],
  css: ['~/assets/css/main.css'],
  app: {
    head: {
      titleTemplate: 'そだちアルバム | %s',
      link: [
        { rel: 'apple-touch-icon', type: 'image/png', href: '/apple-touch-icon-180x180.png' },
        { rel: 'icon', type: 'image/png', href: '/icon-192x192.png' },
        { rel: 'manifest', href: '/site.webmanifest' },
      ],
    },
  },
  runtimeConfig: {
    public: {
      apiBaseUrl: '',
      contactEmail: '',
    },
  },
  vite: {
    server: {
      allowedHosts: ['frontend.local'],
    },
  },
})