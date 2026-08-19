<script setup lang="ts">
definePageMeta({
  middleware: ['staff-auth'],
})

type SalesAvailability = {
  sales_enabled: boolean
  reason_code: string | null
  reason_message: string | null
}

type StripeConnectStatus = {
  stripe_account_id: string | null
  charges_enabled: boolean
  payouts_enabled: boolean
  onboarding_completed_at: string | null
  requirements_due: string[]
}

const authStore = useAuthStore()
const { fetchMe, logout } = useStaffAuth()
const { normalizeError } = useApiError()
const { $api } = useNuxtApp()

const isLoading = ref(true)
const isLoggingOut = ref(false)
const errorMessage = ref('')
const salesAvailability = ref<SalesAvailability | null>(null)
const stripeStatus = ref<StripeConnectStatus | null>(null)
const salesPermissionDenied = ref(false)

const isOwner = computed(() => authStore.staffUser?.role === 'owner')

async function handleUnauthorized(): Promise<void> {
  await logout().catch(() => undefined)
  await navigateTo('/staff/login')
}

async function loadDashboard(): Promise<void> {
  isLoading.value = true
  errorMessage.value = ''
  salesPermissionDenied.value = false

  try {
    await fetchMe()

    if (!isOwner.value) {
      return
    }

    try {
      const [availability, status] = await Promise.all([
        $api<SalesAvailability>('/staff/sales/availability'),
        $api<StripeConnectStatus>('/staff/stripe/connect/status'),
      ])

      salesAvailability.value = availability
      stripeStatus.value = status
    } catch (error) {
      const normalized = normalizeError(error)

      if (normalized.status === 401) {
        await handleUnauthorized()
        return
      }

      if (normalized.code === 'STAFF_ROLE_FORBIDDEN') {
        salesPermissionDenied.value = true
        salesAvailability.value = null
        stripeStatus.value = null

        try {
          await fetchMe()
        } catch (refreshError) {
          const refreshed = normalizeError(refreshError)

          if (refreshed.status === 401) {
            await handleUnauthorized()
            return
          }

          errorMessage.value = refreshed.message
        }
      } else {
        errorMessage.value = normalized.message
      }
    }
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
    await navigateTo('/staff/login')
  } catch (error) {
    errorMessage.value = normalizeError(error).message
  } finally {
    isLoggingOut.value = false
  }
}

onMounted(loadDashboard)
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-6xl space-y-8">
      <header class="flex flex-col gap-5 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-2">
          <p class="text-sm font-medium text-sky-700">園スタッフ管理</p>
          <h1 class="text-3xl font-semibold text-slate-900">ダッシュボード</h1>
          <p v-if="authStore.staffUser" class="text-sm text-slate-600">{{ authStore.staffUser.name }}さんとしてログインしています</p>
        </div>
        <UButton color="neutral" variant="outline" icon="i-lucide-log-out" :loading="isLoggingOut" @click="handleLogout">ログアウト</UButton>
      </header>

      <UAlert v-if="errorMessage" color="error" variant="soft" :title="errorMessage">
        <template #actions><UButton color="error" variant="ghost" size="sm" @click="loadDashboard">再読み込み</UButton></template>
      </UAlert>

      <section v-if="isLoading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-label="読み込み中">
        <div v-for="index in 3" :key="index" class="h-36 animate-pulse rounded-lg bg-slate-200" />
      </section>

      <template v-else>
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-label="主要メニュー">
          <NuxtLink to="/staff/child-classes" class="group rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-300 hover:shadow-md">
            <UIcon name="i-lucide-school" class="size-6 text-sky-700" />
            <h2 class="mt-4 text-lg font-semibold text-slate-900 group-hover:text-sky-800">組（クラス）管理</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">園内の組を登録・編集・整理します。</p>
          </NuxtLink>
          <NuxtLink to="/staff/children" class="group rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-300 hover:shadow-md">
            <UIcon name="i-lucide-users-round" class="size-6 text-sky-700" />
            <h2 class="mt-4 text-lg font-semibold text-slate-900 group-hover:text-sky-800">園児管理</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">園児の登録、在籍状況、招待を管理します。</p>
          </NuxtLink>
          <NuxtLink to="/staff/photos" class="group rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-300 hover:shadow-md">
            <UIcon name="i-lucide-images" class="size-6 text-sky-700" />
            <h2 class="mt-4 text-lg font-semibold text-slate-900 group-hover:text-sky-800">写真管理</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">アップロード済み写真と販売設定を確認します。</p>
          </NuxtLink>
          <NuxtLink v-if="isOwner" to="/staff/members" class="group rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-300 hover:shadow-md">
            <UIcon name="i-lucide-user-cog" class="size-6 text-sky-700" />
            <h2 class="mt-4 text-lg font-semibold text-slate-900 group-hover:text-sky-800">スタッフ管理</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">スタッフの招待、ロール変更、有効/停止を管理します。</p>
          </NuxtLink>
          <NuxtLink v-if="isOwner" to="/staff/settings/stripe" class="group rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-300 hover:shadow-md">
            <UIcon name="i-lucide-credit-card" class="size-6 text-sky-700" />
            <h2 class="mt-4 text-lg font-semibold text-slate-900 group-hover:text-sky-800">Stripe Connect 設定</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">Stripeオンボーディングと販売可否を確認・開始します。</p>
          </NuxtLink>
        </section>

        <UAlert v-if="salesPermissionDenied" color="error" variant="soft" title="写真販売の表示権限がありません。" />

        <section v-else-if="isOwner" class="grid gap-4 lg:grid-cols-2" aria-label="販売状況">
          <UCard class="border border-slate-200 shadow-sm">
            <template #header>
              <div class="flex items-center justify-between gap-4">
                <div><p class="text-sm font-medium text-slate-600">写真販売</p><h2 class="mt-1 text-lg font-semibold text-slate-900">販売可否</h2></div>
                <UBadge :color="salesAvailability?.sales_enabled ? 'success' : 'warning'" variant="subtle">{{ salesAvailability?.sales_enabled ? '販売可能' : '販売準備中' }}</UBadge>
              </div>
            </template>
            <p v-if="salesAvailability?.sales_enabled" class="text-sm text-slate-700">Stripeの設定が完了しており、写真を販売できます。</p>
            <div v-else class="space-y-3">
              <p class="text-sm text-slate-700">{{ salesAvailability?.reason_message ?? '販売設定を確認しています。' }}</p>
              <UAlert color="warning" variant="soft" title="写真販売を開始するには、Stripeのオンボーディングを完了してください。" />
            </div>
          </UCard>

          <UCard class="border border-slate-200 shadow-sm">
            <template #header>
              <div class="flex items-center justify-between gap-4">
                <div><p class="text-sm font-medium text-slate-600">Stripe Connect</p><h2 class="mt-1 text-lg font-semibold text-slate-900">オンボーディング状況</h2></div>
                <UBadge :color="stripeStatus?.charges_enabled && stripeStatus?.payouts_enabled ? 'success' : 'neutral'" variant="subtle">{{ stripeStatus?.charges_enabled && stripeStatus?.payouts_enabled ? '有効' : '未完了' }}</UBadge>
              </div>
            </template>
            <p class="text-sm text-slate-700">{{ stripeStatus?.charges_enabled && stripeStatus?.payouts_enabled ? 'カード決済と入金が有効です。' : '本人確認・口座情報の設定が必要です。' }}</p>
            <p v-if="stripeStatus?.requirements_due.length" class="mt-3 text-sm text-amber-800">追加対応が必要な項目があります。</p>
          </UCard>
        </section>
      </template>

      <p v-if="authStore.staffUser" class="text-xs text-slate-500">権限: {{ authStore.staffUser.role === 'owner' ? 'オーナー' : 'スタッフ' }}</p>
    </div>
  </main>
</template>
