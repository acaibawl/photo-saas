<script setup lang="ts">
type VerifyResponse = {
  email_verified_at: string | null
}

useHead({
  title: 'メールアドレス確認',
})

const route = useRoute()
const { $api } = useNuxtApp()
const { normalizeError } = useApiError()

const id = computed(() => (typeof route.params.id === 'string' ? route.params.id : ''))
const hash = computed(() => (typeof route.params.hash === 'string' ? route.params.hash : ''))
const expires = computed(() => (typeof route.query.expires === 'string' ? route.query.expires : ''))
const signature = computed(() => (typeof route.query.signature === 'string' ? route.query.signature : ''))

const isVerifying = ref(false)
const requestErrorMessage = ref('')

function resolveFailureStatus(): 'expired' | 'invalid' {
  const expiresTimestamp = Number(expires.value)

  if (Number.isFinite(expiresTimestamp) && expiresTimestamp > 0 && Date.now() > expiresTimestamp * 1000) {
    return 'expired'
  }

  return 'invalid'
}

async function verify(): Promise<void> {
  requestErrorMessage.value = ''
  isVerifying.value = true

  try {
    await $api<VerifyResponse>(`/guardian/auth/email/verify/${id.value}/${hash.value}`, {
      method: 'GET',
      query: { expires: expires.value, signature: signature.value },
      skipAuthRetry: true,
    })

    await navigateTo({ path: '/guardian/email-verification/result', query: { status: 'success' } }, { replace: true })
  } catch (error) {
    const normalized = normalizeError(error)

    // 403は署名検証(期限切れ/不正リンク)の失敗を表す。それ以外はネットワーク障害やサーバーエラーであり、
    // 誤って「リンクが無効」と案内せず再試行させる。
    if (normalized.status === 403) {
      await navigateTo({ path: '/guardian/email-verification/result', query: { status: resolveFailureStatus() } }, { replace: true })
      return
    }

    requestErrorMessage.value = '確認処理に失敗しました。しばらくしてから再試行してください。'
  } finally {
    isVerifying.value = false
  }
}

onMounted(() => {
  if (id.value === '' || hash.value === '' || expires.value === '' || signature.value === '') {
    navigateTo({ path: '/guardian/email-verification/result', query: { status: 'invalid' } }, { replace: true })
    return
  }

  verify()
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

      <div class="space-y-5">
        <p v-if="!requestErrorMessage" class="text-sm leading-6 text-slate-600">
          しばらくお待ちください...
        </p>

        <template v-else>
          <UAlert color="error" variant="soft" :title="requestErrorMessage" />
          <UButton size="lg" class="w-full justify-center" :loading="isVerifying" @click="verify">
            再試行する
          </UButton>
        </template>
      </div>
    </UCard>
  </main>
</template>
