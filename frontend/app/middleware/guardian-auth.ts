export default defineNuxtRouteMiddleware(async () => {
  if (!import.meta.client) {
    return
  }

  const authStore = useAuthStore()
  const guardianAuth = useGuardianAuth()

  if (!authStore.guardianSessionRestored) {
    await guardianAuth.ensureSessionRestored()
  }

  if (!authStore.isGuardianAuthenticated) {
    return navigateTo('/guardian/login')
  }
})