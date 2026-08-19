<script setup lang="ts">
type VerifyResponse = {
  email_verified_at: string | null
}

useHead({
  title: 'メールアドレス確認',
})

const route = useRoute()
const { $api } = useNuxtApp()

const id = computed(() => (typeof route.params.id === 'string' ? route.params.id : ''))
const hash = computed(() => (typeof route.params.hash === 'string' ? route.params.hash : ''))
const expires = computed(() => (typeof route.query.expires === 'string' ? route.query.expires : ''))
const signature = computed(() => (typeof route.query.signature === 'string' ? route.query.signature : ''))

function resolveFailureStatus(): 'expired' | 'invalid' {
  const expiresTimestamp = Number(expires.value)

  if (Number.isFinite(expiresTimestamp) && expiresTimestamp > 0 && Date.now() > expiresTimestamp * 1000) {
    return 'expired'
  }

  return 'invalid'
}

onMounted(async () => {
  if (id.value === '' || hash.value === '' || expires.value === '' || signature.value === '') {
    await navigateTo({ path: '/guardian/email-verification/result', query: { status: 'invalid' } }, { replace: true })
    return
  }

  try {
    await $api<VerifyResponse>(`/guardian/auth/email/verify/${id.value}/${hash.value}`, {
      method: 'GET',
      query: { expires: expires.value, signature: signature.value },
      skipAuthRetry: true,
    })

    await navigateTo({ path: '/guardian/email-verification/result', query: { status: 'success' } }, { replace: true })
  } catch {
    await navigateTo({ path: '/guardian/email-verification/result', query: { status: resolveFailureStatus() } }, { replace: true })
  }
})
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-emerald-50 px-4 py-10">
    <UCard class="w-full max-w-md border border-emerald-200 bg-white shadow-sm">
      <template #header>
        <div class="space-y-2">
          <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-700">Guardian Portal</p>
          <h1 class="text-2xl font-semibold tracking-tight text-slate-900">メールアドレスを確認しています</h1>
        </div>
      </template>

      <p class="text-sm leading-6 text-slate-600">
        しばらくお待ちください...
      </p>
    </UCard>
  </main>
</template>
