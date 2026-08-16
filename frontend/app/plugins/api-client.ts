import { $fetch } from 'ofetch'
import type { FetchError } from 'ofetch'

type AuthRealm = 'staff' | 'guardian'

type ApiFetchOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: BodyInit | Record<string, unknown> | null
  query?: Record<string, string | number | boolean | undefined>
  headers?: HeadersInit
  skipAuthRetry?: boolean
}

function resolveRealm(path: string): AuthRealm | null {
  if (path.startsWith('/staff/')) {
    return 'staff'
  }

  if (path.startsWith('/guardian/')) {
    return 'guardian'
  }

  return null
}

function isRefreshPath(path: string): boolean {
  return path === '/staff/auth/refresh' || path === '/guardian/auth/refresh'
}

function isLoginPath(path: string): boolean {
  return path === '/staff/auth/login' || path === '/guardian/auth/login'
}

export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  const authStore = useAuthStore()

  const refreshPromises: Partial<Record<AuthRealm, Promise<boolean>>> = {}

  const applyAuthHeader = (path: string, incoming: HeadersInit | undefined): Headers => {
    const headers = new Headers(incoming)
    const realm = resolveRealm(path)

    if (realm === 'staff' && authStore.staffAccessToken) {
      headers.set('Authorization', `Bearer ${authStore.staffAccessToken}`)
    }

    if (realm === 'guardian' && authStore.guardianAccessToken) {
      headers.set('Authorization', `Bearer ${authStore.guardianAccessToken}`)
    }

    return headers
  }

  const performRefresh = async (realm: AuthRealm): Promise<boolean> => {
    const endpoint = realm === 'staff' ? '/staff/auth/refresh' : '/guardian/auth/refresh'

    try {
      const response = await $fetch<{ access_token: string }>(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
      })

      if (!response.access_token) {
        if (realm === 'staff') {
          authStore.clearStaffSession()
          authStore.markStaffSessionRestored()
        } else {
          authStore.clearGuardianSession()
          authStore.markGuardianSessionRestored()
        }

        return false
      }

      if (realm === 'staff') {
        authStore.setStaffAccessToken(response.access_token)
        authStore.markStaffSessionRestored()
      } else {
        authStore.setGuardianAccessToken(response.access_token)
        authStore.markGuardianSessionRestored()
      }

      return true
    } catch (error) {
      const fetchError = error as FetchError
      const status = fetchError.response?.status

      // 422 はリフレッシュトークン未保持(未ログイン)時に返される想定内のレスポンス
      if (status !== 401 && status !== 403 && status !== 422) {
        throw error
      }

      if (realm === 'staff') {
        authStore.clearStaffSession()
        authStore.markStaffSessionRestored()
      } else {
        authStore.clearGuardianSession()
        authStore.markGuardianSessionRestored()
      }

      return false
    }
  }

  const refreshSingleFlight = async (realm: AuthRealm): Promise<boolean> => {
    const active = refreshPromises[realm]
    if (active) {
      return active
    }

    const running = performRefresh(realm).finally(() => {
      refreshPromises[realm] = undefined
    })

    refreshPromises[realm] = running

    return running
  }

  const api = async <T>(path: string, options: ApiFetchOptions = {}): Promise<T> => {
    const headers = applyAuthHeader(path, options.headers)
    const isRefreshRequest = isRefreshPath(path)

    try {
      return await $fetch<T>(path, {
        baseURL: isRefreshRequest ? undefined : config.public.apiBaseUrl,
        method: options.method,
        body: options.body,
        query: options.query,
        headers,
        credentials: isRefreshRequest ? 'same-origin' : 'omit',
      })
    } catch (error) {
      const fetchError = error as FetchError
      const status = fetchError.response?.status
      const realm = resolveRealm(path)
      const shouldTryRefresh =
        status === 401 &&
        !options.skipAuthRetry &&
        realm !== null &&
        !isRefreshPath(path) &&
        !isLoginPath(path)

      if (!shouldTryRefresh || realm === null) {
        throw error
      }

      const refreshed = await refreshSingleFlight(realm)
      if (!refreshed) {
        throw error
      }

      const retryHeaders = applyAuthHeader(path, options.headers)

      return await $fetch<T>(path, {
        baseURL: config.public.apiBaseUrl,
        method: options.method,
        body: options.body,
        query: options.query,
        headers: retryHeaders,
        credentials: 'omit',
      })
    }
  }

  return {
    provide: {
      api,
      refreshSingleFlight,
    },
  }
})
