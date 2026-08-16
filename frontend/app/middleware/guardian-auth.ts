export default defineNuxtRouteMiddleware(async () => {
  const authStore = useAuthStore()
  const guardianAuth = useGuardianAuth()

  if (!authStore.guardianSessionRestored) {
    await guardianAuth.ensureSessionRestored()
  }

  if (!authStore.isGuardianAuthenticated) {
    return navigateTo('/guardian/login')
  }
})
