<script setup lang="ts">
type VerificationStatus = 'success' | 'expired' | 'invalid'

useHead({
  title: 'メールアドレス確認結果',
})

const route = useRoute()
const authStore = useAuthStore()

const status = computed<VerificationStatus>(() => {
  const value = route.query.status

  if (value === 'success' || value === 'expired' || value === 'invalid') {
    return value
  }

  return 'invalid'
})

const content = computed(() => {
  if (status.value === 'success') {
    return {
      color: 'success' as const,
      title: 'メール確認が完了しました。',
      description: 'ご登録のメールアドレスの確認が完了しました。',
    }
  }

  if (status.value === 'expired') {
    return {
      color: 'warning' as const,
      title: '確認リンクの有効期限が切れています。',
      description: 'お手数ですが、マイページから確認メールを再送してください。',
    }
  }

  return {
    color: 'error' as const,
    title: '確認リンクが無効です。',
    description: 'お手数ですが、マイページから確認メールを再送してください。',
  }
})

const homeLink = computed(() => (authStore.isGuardianAuthenticated ? '/guardian' : '/guardian/login'))
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-emerald-50 px-4 py-10">
    <UCard class="w-full max-w-md border border-emerald-200 bg-white shadow-sm">
      <template #header>
        <div class="space-y-2">
          <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-700">Guardian Portal</p>
          <h1 class="text-2xl font-semibold tracking-tight text-slate-900">メールアドレス確認</h1>
        </div>
      </template>

      <div class="space-y-5">
        <UAlert :color="content.color" variant="soft" :title="content.title" :description="content.description" />

        <UButton size="lg" class="w-full justify-center" @click="navigateTo(homeLink)">
          {{ status === 'success' ? 'ホームへ進む' : 'ホームに戻る' }}
        </UButton>
      </div>
    </UCard>
  </main>
</template>
