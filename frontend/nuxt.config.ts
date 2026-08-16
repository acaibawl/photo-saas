// https://nuxt.com/docs/api/configuration/nuxt-config
const apiBaseUrl = process.env.NUXT_PUBLIC_API_BASE_URL || 'https://backend.local'

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxt/eslint', '@nuxt/ui', '@vee-validate/nuxt', '@pinia/nuxt'],
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
    public: {
      // Overridden by NUXT_PUBLIC_API_BASE_URL from .env at runtime.
      apiBaseUrl,
    },
  },
  vite: {
    server: {
      allowedHosts: ['frontend.local'],
    },
  },
})