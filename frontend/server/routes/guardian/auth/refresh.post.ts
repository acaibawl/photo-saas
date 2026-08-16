import { defineEventHandler, proxyRequest } from 'h3'

export default defineEventHandler((event) => {
  const config = useRuntimeConfig()
  const target = new URL('/guardian/auth/refresh', config.public.apiBaseUrl).toString()

  return proxyRequest(event, target)
})
