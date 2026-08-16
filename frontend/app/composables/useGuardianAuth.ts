import type { GuardianUser } from '~/stores/auth'

type GuardianLoginResponse = {
  access_token: string
  token_type: string
  expires_in: number
  guardian?: GuardianUser
}

type GuardianChildrenResponse = {
  data: Array<Record<string, unknown>>
}

type GuardianLogoutResponse = {
  revoked_count: number
}

export function useGuardianAuth() {
  const authStore = useAuthStore()
  const { $api, $refreshSingleFlight } = useNuxtApp()

  const login = async (email: string, password: string): Promise<void> => {
    const result = await $api<GuardianLoginResponse>('/guardian/auth/login', {
      method: 'POST',
      body: { email, password },
      skipAuthRetry: true,
    })

    authStore.setGuardianAccessToken(result.access_token)
    authStore.markGuardianSessionRestored()

    if (result.guardian) {
      authStore.setGuardianUser(result.guardian)
    }
  }

  const refresh = async (): Promise<boolean> => {
    return await $refreshSingleFlight('guardian')
  }

  const fetchChildren = async (): Promise<GuardianChildrenResponse> => {
    return await $api<GuardianChildrenResponse>('/guardian/children', {
      method: 'GET',
    })
  }

  const ensureSessionRestored = async (): Promise<void> => {
    if (authStore.guardianSessionRestored) {
      return
    }

    try {
      await refresh()
    } finally {
      authStore.markGuardianSessionRestored()
    }
  }

  const logout = async (): Promise<void> => {
    try {
      await $api<GuardianLogoutResponse>('/guardian/auth/logout', {
        method: 'POST',
      })
    } finally {
      authStore.clearGuardianSession()
      authStore.markGuardianSessionRestored()
    }
  }

  return {
    login,
    refresh,
    fetchChildren,
    ensureSessionRestored,
    logout,
  }
}
