export default defineNuxtPlugin(() => {
  const staffAuth = useStaffAuth()
  const guardianAuth = useGuardianAuth()

  void Promise.all([
    staffAuth.ensureSessionRestored(),
    guardianAuth.ensureSessionRestored(),
  ])
})
