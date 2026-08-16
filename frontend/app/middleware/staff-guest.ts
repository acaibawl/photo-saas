export default defineNuxtRouteMiddleware(async () => {
  const authStore = useAuthStore()
  const staffAuth = useStaffAuth()

  if (!authStore.staffSessionRestored) {
    await staffAuth.ensureSessionRestored()
  }

  if (authStore.isStaffAuthenticated) {
    return navigateTo('/staff')
  }
})
