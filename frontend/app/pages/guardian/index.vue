<script setup lang="ts">
import type { GuardianLinkedChild } from '~/composables/useGuardianAuth'

definePageMeta({
  middleware: ['guardian-auth'],
})

const authStore = useAuthStore()
const { logout, fetchChildren } = useGuardianAuth()
const { normalizeError } = useApiError()

const children = ref<GuardianLinkedChild[]>([])
const isLoading = ref(true)
const isLoggingOut = ref(false)
const errorMessage = ref('')

async function handleUnauthorized(): Promise<void> {
  await logout().catch(() => undefined)
  await navigateTo('/guardian/login')
}

async function loadChildren(): Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await fetchChildren()
    children.value = response.data
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.status === 401) {
      await handleUnauthorized()
      return
    }

    errorMessage.value = normalized.message
  } finally {
    isLoading.value = false
  }
}

async function handleLogout(): Promise<void> {
  isLoggingOut.value = true
  errorMessage.value = ''

  try {
    await logout()
  } catch (error) {
    errorMessage.value = normalizeError(error).message
  } finally {
    isLoggingOut.value = false
    await navigateTo('/guardian/login')
  }
}

onMounted(loadChildren)
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-4xl space-y-8">
      <header class="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-2">
          <p class="text-sm font-medium text-emerald-700">保護者ポータル</p>
          <h1 class="text-3xl font-semibold text-slate-900">保護者ホーム</h1>
          <p v-if="authStore.guardianUser" class="text-sm text-slate-600">{{ authStore.guardianUser.name }}さんとしてログインしています</p>
        </div>
        <UButton color="neutral" variant="outline" icon="i-lucide-log-out" :loading="isLoggingOut" @click="handleLogout">ログアウト</UButton>
      </header>

      <UAlert v-if="errorMessage" color="error" variant="soft" :title="errorMessage">
        <template #actions>
          <UButton color="error" variant="ghost" size="sm" @click="loadChildren">再読み込み</UButton>
        </template>
      </UAlert>

      <section aria-label="紐づけ園児一覧" class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">紐づいている園児</h2>
          <span v-if="!isLoading" class="text-sm text-slate-500">{{ children.length }}件</span>
        </div>

        <div v-if="isLoading" class="grid gap-4 sm:grid-cols-2" aria-label="読み込み中">
          <div v-for="index in 2" :key="index" class="h-32 animate-pulse rounded-lg bg-slate-200" />
        </div>

        <UCard v-else-if="!children.length && !errorMessage" class="border border-slate-200 shadow-sm">
          <div class="py-10 text-center">
            <UIcon name="i-lucide-users-round" class="mx-auto size-8 text-slate-400" />
            <p class="mt-3 font-medium text-slate-700">紐づいている園児はいません。</p>
            <p class="mt-1 text-sm text-slate-500">園から共有された招待QR・URLにアクセスして園児を追加してください。</p>
          </div>
        </UCard>

        <div v-else-if="children.length" class="grid gap-4 sm:grid-cols-2">
          <UCard v-for="child in children" :key="child.child_id" class="border border-slate-200 shadow-sm">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="text-base font-semibold text-slate-900">{{ child.child_name }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ child.kindergarten_name }} / {{ child.class_name }}</p>
              </div>
              <UBadge v-if="child.label" color="neutral" variant="subtle">{{ child.label }}</UBadge>
            </div>
            <UButton class="mt-4 w-full justify-center" icon="i-lucide-image" :to="`/guardian/photos?child_id=${child.child_id}`">写真を見る</UButton>
          </UCard>
        </div>
      </section>

      <UCard class="border border-slate-200 shadow-sm">
        <template #header>
          <h2 class="text-lg font-semibold text-slate-900">兄弟姉妹を追加する</h2>
        </template>
        <p class="text-sm leading-6 text-slate-700">園から発行された招待QRコードを読み取るか、招待URLにアクセスすると、別の園児をこのアカウントに追加できます。</p>
      </UCard>
    </div>
  </main>
</template>
