// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxt/eslint', '@nuxt/ui', '@vee-validate/nuxt', '@pinia/nuxt'],
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
    public: {
      // Overridden by NUXT_PUBLIC_API_BASE_URL from .env at runtime.
      apiBaseUrl: 'https://backend.local',
    },
  },
  vite: {
    server: {
      allowedHosts: ['frontend.local'],
    },
  },
})