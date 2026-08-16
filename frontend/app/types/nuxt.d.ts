export {}

type AuthRealm = 'staff' | 'guardian'

type ApiFetchOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: BodyInit | Record<string, unknown> | null
  query?: Record<string, string | number | boolean | undefined>
  headers?: HeadersInit
  skipAuthRetry?: boolean
}

declare module '#app' {
  interface NuxtApp {
    $api: <T>(path: string, options?: ApiFetchOptions) => Promise<T>
    $refreshSingleFlight: (realm: AuthRealm) => Promise<boolean>
  }
}

declare module 'vue' {
  interface ComponentCustomProperties {
    $api: <T>(path: string, options?: ApiFetchOptions) => Promise<T>
    $refreshSingleFlight: (realm: AuthRealm) => Promise<boolean>
  }
}
