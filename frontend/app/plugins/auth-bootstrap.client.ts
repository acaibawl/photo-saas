export default defineNuxtPlugin(async () => {
  const staffAuth = useStaffAuth()
  const guardianAuth = useGuardianAuth()

  await Promise.all([
    staffAuth.ensureSessionRestored(),
    guardianAuth.ensureSessionRestored(),
  ])
})
