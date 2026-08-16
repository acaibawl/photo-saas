export default defineNuxtRouteMiddleware(async () => {
  if (!import.meta.client) {
    return
  }

  const authStore = useAuthStore()
  const staffAuth = useStaffAuth()

  if (!authStore.staffSessionRestored) {
    await staffAuth.ensureSessionRestored()
  }

  if (!authStore.isStaffAuthenticated) {
    return navigateTo('/staff/login')
  }
})
