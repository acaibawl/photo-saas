import type { StaffUser } from '~/stores/auth'

type StaffLoginResponse = {
  access_token: string
  token_type: string
  expires_in: number
  staff?: StaffUser
}

type StaffMeResponse = StaffUser

type StaffLogoutResponse = {
  revoked_count: number
}

export function useStaffAuth() {
  const authStore = useAuthStore()
  const { $api, $refreshSingleFlight } = useNuxtApp()

  const login = async (email: string, password: string): Promise<void> => {
    const result = await $api<StaffLoginResponse>('/staff/auth/login', {
      method: 'POST',
      body: { email, password },
      skipAuthRetry: true,
    })

    authStore.setStaffAccessToken(result.access_token)
    authStore.markStaffSessionRestored()

    if (result.staff) {
      authStore.setStaffUser(result.staff)
    }
  }

  const refresh = async (): Promise<boolean> => {
    return await $refreshSingleFlight('staff')
  }

  const fetchMe = async (): Promise<StaffUser> => {
    const profile = await $api<StaffMeResponse>('/staff/auth/me', {
      method: 'GET',
    })

    authStore.setStaffUser(profile)

    return profile
  }

  const ensureSessionRestored = async (): Promise<void> => {
    if (authStore.staffSessionRestored) {
      return
    }

    await refresh()
  }

  const logout = async (): Promise<void> => {
    try {
      await $api<StaffLogoutResponse>('/staff/auth/logout', {
        method: 'POST',
        body: { all_sessions: true },
      })
    } finally {
      authStore.clearStaffSession()
      authStore.markStaffSessionRestored()
    }
  }

  return {
    login,
    refresh,
    fetchMe,
    ensureSessionRestored,
    logout,
  }
}
