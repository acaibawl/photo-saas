<script setup lang="ts">
definePageMeta({
  middleware: ['staff-auth'],
})

type StripeConnectStatus = {
  stripe_account_id: string | null
  charges_enabled: boolean
  payouts_enabled: boolean
  onboarding_completed_at: string | null
  requirements_due: string[]
}

type SalesAvailability = {
  sales_enabled: boolean
  reason_code: string | null
  reason_message: string | null
}

type OnboardingLinkResponse = {
  onboarding_url: string
  stripe_account_id: string
  expires_at: string
}

const { fetchMe, logout } = useStaffAuth()
const { normalizeError } = useApiError()
const { $api } = useNuxtApp()

const isCheckingAccess = ref(true)
const accessDenied = ref(false)
const accessErrorMessage = ref('')

const isLoading = ref(true)
const loadErrorMessage = ref('')

const isStartingOnboarding = ref(false)
const onboardingErrorMessage = ref('')

const stripeStatus = ref<StripeConnectStatus | null>(null)
const salesAvailability = ref<SalesAvailability | null>(null)

const isFullyEnabled = computed(() => {
  return Boolean(stripeStatus.value?.charges_enabled && stripeStatus.value?.payouts_enabled)
})

async function unauthorized(): Promise<void> {
  await logout().catch(() => undefined)
  await navigateTo('/staff/login')
}

function currentPageUrl(): string {
  return `${window.location.origin}/staff/settings/stripe`
}

async function checkAccess(): Promise<boolean> {
  try {
    const profile = await fetchMe()

    if (profile.role !== 'owner') {
      await navigateTo('/staff')
      return false
    }

    return true
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.status === 401) {
      await unauthorized()
      return false
    }

    accessDenied.value = true
    accessErrorMessage.value = normalized.message
    return false
  }
}

async function loadStatus(): Promise<void> {
  isLoading.value = true
  loadErrorMessage.value = ''

  try {
    const [status, availability] = await Promise.all([
      $api<StripeConnectStatus>('/staff/stripe/connect/status'),
      $api<SalesAvailability>('/staff/sales/availability'),
    ])

    stripeStatus.value = status
    salesAvailability.value = availability
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.status === 401) {
      await unauthorized()
      return
    }

    if (normalized.code === 'STAFF_ROLE_FORBIDDEN') {
      await navigateTo('/staff')
      return
    }

    if (normalized.code === 'STRIPE_API_ERROR') {
      loadErrorMessage.value = 'Stripeとの通信でエラーが発生しました。時間をおいて再試行してください。'
    } else {
      loadErrorMessage.value = normalized.message
    }
  } finally {
    isLoading.value = false
  }
}

async function initialize(): Promise<void> {
  isCheckingAccess.value = true
  accessDenied.value = false
  accessErrorMessage.value = ''

  const canAccess = await checkAccess()
  isCheckingAccess.value = false

  if (!canAccess) {
    return
  }

  await loadStatus()
}

async function startOnboarding(): Promise<void> {
  onboardingErrorMessage.value = ''
  isStartingOnboarding.value = true

  try {
    const pageUrl = currentPageUrl()

    const response = await $api<OnboardingLinkResponse>('/staff/stripe/connect/onboarding-link', {
      method: 'POST',
      body: {
        return_url: pageUrl,
        refresh_url: pageUrl,
      },
    })

    window.location.href = response.onboarding_url
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.status === 401) {
      await unauthorized()
      return
    }

    if (normalized.code === 'STAFF_ROLE_FORBIDDEN') {
      await navigateTo('/staff')
      return
    }

    if (normalized.code === 'STRIPE_API_ERROR') {
      onboardingErrorMessage.value = 'Stripeとの通信でエラーが発生しました。時間をおいて再試行してください。'
    } else {
      onboardingErrorMessage.value = normalized.message
    }
  } finally {
    isStartingOnboarding.value = false
  }
}

onMounted(initialize)
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-4xl space-y-8">
      <header class="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-2">
          <NuxtLink to="/staff" class="inline-flex items-center gap-1 text-sm font-medium text-sky-700 hover:text-sky-900">
            <UIcon name="i-lucide-arrow-left" class="size-4" /> ダッシュボード
          </NuxtLink>
          <h1 class="text-3xl font-semibold text-slate-900">Stripe Connect 設定</h1>
          <p class="text-sm text-slate-600">写真販売のためのStripeオンボーディング状況を確認・開始します。</p>
        </div>
      </header>

      <section v-if="isCheckingAccess" class="space-y-3" aria-label="読み込み中">
        <div v-for="index in 3" :key="index" class="h-24 animate-pulse rounded-lg bg-slate-100" />
      </section>

      <UAlert
        v-else-if="accessDenied"
        color="error"
        variant="soft"
        title="Stripe Connect設定を読み込めませんでした。"
        :description="accessErrorMessage || 'Stripe Connect設定を読み込めませんでした。'"
      >
        <template #actions>
          <UButton color="error" variant="ghost" size="sm" @click="initialize">再読み込み</UButton>
        </template>
      </UAlert>

      <template v-else>
        <section v-if="isLoading" class="space-y-4" aria-label="読み込み中">
          <div v-for="index in 2" :key="index" class="h-32 animate-pulse rounded-lg bg-slate-100" />
        </section>

        <UAlert v-else-if="loadErrorMessage" color="error" variant="soft" :title="loadErrorMessage">
          <template #actions>
            <UButton color="error" variant="ghost" size="sm" @click="loadStatus">再読み込み</UButton>
          </template>
        </UAlert>

        <template v-else>
          <UAlert
            :color="salesAvailability?.sales_enabled ? 'success' : 'warning'"
            variant="soft"
            :title="salesAvailability?.sales_enabled ? '写真販売が有効です。' : '写真販売はまだ有効になっていません。'"
            :description="salesAvailability?.sales_enabled ? undefined : (salesAvailability?.reason_message ?? 'Stripeのオンボーディングを完了してください。')"
          />

          <UCard class="border border-slate-200 shadow-sm">
            <template #header>
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-sm font-medium text-slate-600">Stripe Connect</p>
                  <h2 class="mt-1 text-lg font-semibold text-slate-900">接続状態</h2>
                </div>
                <UBadge :color="isFullyEnabled ? 'success' : 'neutral'" variant="subtle">
                  {{ isFullyEnabled ? '有効' : '未完了' }}
                </UBadge>
              </div>
            </template>

            <div class="space-y-4">
              <div class="grid gap-3 sm:grid-cols-2">
                <div class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3">
                  <span class="text-sm text-slate-700">カード決済（charges）</span>
                  <UBadge :color="stripeStatus?.charges_enabled ? 'success' : 'neutral'" variant="subtle">
                    {{ stripeStatus?.charges_enabled ? '有効' : '無効' }}
                  </UBadge>
                </div>
                <div class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3">
                  <span class="text-sm text-slate-700">入金（payouts）</span>
                  <UBadge :color="stripeStatus?.payouts_enabled ? 'success' : 'neutral'" variant="subtle">
                    {{ stripeStatus?.payouts_enabled ? '有効' : '無効' }}
                  </UBadge>
                </div>
              </div>

              <p v-if="stripeStatus?.onboarding_completed_at" class="text-sm text-slate-600">
                オンボーディング完了日時: {{ new Date(stripeStatus.onboarding_completed_at).toLocaleString('ja-JP') }}
              </p>
              <p v-else class="text-sm text-slate-600">
                オンボーディングはまだ完了していません。
              </p>

              <div v-if="stripeStatus?.requirements_due.length" class="space-y-2">
                <p class="text-sm font-medium text-amber-800">未充足の項目があります</p>
                <ul class="space-y-1 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                  <li
                    v-for="requirement in stripeStatus.requirements_due"
                    :key="requirement"
                    class="flex items-center gap-2 text-sm text-amber-800"
                  >
                    <UIcon name="i-lucide-triangle-alert" class="size-4 shrink-0" />
                    {{ requirement }}
                  </li>
                </ul>
              </div>
            </div>

            <template #footer>
              <div class="flex flex-col gap-3">
                <UAlert v-if="onboardingErrorMessage" color="error" variant="soft" :title="onboardingErrorMessage" />

                <UButton
                  icon="i-lucide-external-link"
                  :loading="isStartingOnboarding"
                  @click="startOnboarding"
                >
                  {{ isFullyEnabled ? 'Stripeの設定を再開する' : 'Stripeオンボーディングを開始する' }}
                </UButton>
              </div>
            </template>
          </UCard>
        </template>
      </template>
    </div>
  </main>
</template>
