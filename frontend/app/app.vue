<template>
  <UApp>
    <NuxtRouteAnnouncer />
    <div
      v-if="portalBrand"
      class="px-4 py-1.5 text-center text-[11px] font-semibold tracking-[0.18em] text-white"
      :class="portalBrand.bgClass"
    >
      そだちアルバム
    </div>
    <NuxtPage />
  </UApp>
</template>

<script setup lang="ts">
const route = useRoute()
const requestUrl = useRequestURL()
const { public: { siteUrl } } = useRuntimeConfig()

const shouldNoIndex = computed(() => {
  const isStaffAuthenticatedArea = route.path.startsWith('/staff') && route.path !== '/staff/login'
  const isGuardianAuthenticatedArea = route.path.startsWith('/guardian') && route.path !== '/guardian/login'
  const isInvitationArea = route.path.startsWith('/invitations/') || route.path.startsWith('/staff/invitations/')

  return isStaffAuthenticatedArea || isGuardianAuthenticatedArea || isInvitationArea
})

const canonicalHref = computed(() => {
  const baseUrl = (siteUrl || `${requestUrl.protocol}//${requestUrl.host}`).replace(/\/+$/, '')
  return `${baseUrl}${route.path}`
})

useHead(() => ({
  link: [
    {
      rel: 'canonical',
      href: canonicalHref.value,
    },
  ],
  meta: [
    {
      name: 'robots',
      content: shouldNoIndex.value ? 'noindex, nofollow' : 'index, follow',
    },
  ],
}))

const portalBrand = computed<null | { bgClass: string }>(() => {
  if (route.path.startsWith('/guardian')) {
    return { bgClass: 'bg-emerald-600' }
  }

  if (route.path.startsWith('/staff')) {
    return { bgClass: 'bg-sky-700' }
  }

  return null
})
</script>
