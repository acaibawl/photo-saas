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
