// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxt/eslint', '@nuxt/ui', '@vee-validate/nuxt', '@pinia/nuxt'],
  css: ['~/assets/css/main.css'],
  app: {
    head: {
      titleTemplate: 'そだちアルバム | %s',
      meta: [
        { name: 'description', content: '保育園・幼稚園向けの写真販売サービス「そだちアルバム」' },
        { property: 'og:site_name', content: 'そだちアルバム' },
        { property: 'og:type', content: 'website' },
        { property: 'og:title', content: 'そだちアルバム' },
        { property: 'og:description', content: '保育園・幼稚園向けの写真販売サービス「そだちアルバム」' },
        { property: 'og:image', content: '/landing_top.png' },
        { name: 'twitter:card', content: 'summary_large_image' },
        { name: 'twitter:title', content: 'そだちアルバム' },
        { name: 'twitter:description', content: '保育園・幼稚園向けの写真販売サービス「そだちアルバム」' },
        { name: 'twitter:image', content: '/landing_top.png' },
      ],
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
      siteUrl: '',
    },
  },
  vite: {
    server: {
      allowedHosts: ['frontend.local'],
    },
  },
})