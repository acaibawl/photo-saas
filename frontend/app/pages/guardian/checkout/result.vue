<script setup lang="ts">
import type { GuardianOrder, GuardianOrderStatus } from '~/types/guardian'

definePageMeta({
  middleware: ['guardian-auth'],
})

type CheckoutStatus = 'success' | 'cancel'

const MAX_POLL_ATTEMPTS = 5
const POLL_INTERVAL_MS = 2000

const { $api } = useNuxtApp()
const { normalizeError } = useApiError()
const { logout } = useGuardianAuth()
const route = useRoute()

const status = computed<CheckoutStatus>(() => (route.query.status === 'success' ? 'success' : 'cancel'))
const orderId = computed(() => (typeof route.query.order_id === 'string' ? route.query.order_id : null))
const hasOrderId = computed(() => orderId.value !== null)

const isChecking = ref(false)
const orderStatus = ref<GuardianOrderStatus | null>(null)
const pageError = ref('')
let isDisposed = false
let sleepTimeout: ReturnType<typeof setTimeout> | null = null
let resolveSleep: (() => void) | null = null

function sleep(ms: number): Promise<void> {
  if (isDisposed) return Promise.resolve()

  return new Promise((resolve) => {
    resolveSleep = resolve
    sleepTimeout = setTimeout(() => {
      sleepTimeout = null
      resolveSleep = null
      resolve()
    }, ms)
  })
}

function isRetryableSyncError(statusCode: number | null): boolean {
  return statusCode === null || [502, 503, 504].includes(statusCode)
}

async function unauthorized(): Promise<void> {
  await logout().catch(() => undefined)
  await navigateTo('/guardian/login')
}

async function syncOrder(targetOrderId: string): Promise<GuardianOrder> {
  return await $api<GuardianOrder>(`/guardian/orders/${targetOrderId}/sync`, {
    method: 'POST',
  })
}

async function cancelOrder(targetOrderId: string): Promise<void> {
  isChecking.value = true
  pageError.value = ''

  try {
    const order = await $api<GuardianOrder>(`/guardian/orders/${targetOrderId}/cancel`, {
      method: 'POST',
    })
    orderStatus.value = order.status
  } catch (error) {
    const normalized = normalizeError(error)
    if (normalized.status === 401) {
      await unauthorized()
      return
    }

    pageError.value = normalized.message
  } finally {
    isChecking.value = false
  }
}

async function pollOrderStatus(): Promise<void> {
  const targetOrderId = orderId.value
  if (!targetOrderId || isDisposed) return

  isChecking.value = true
  pageError.value = ''

  try {
    for (let attempt = 0; attempt < MAX_POLL_ATTEMPTS; attempt += 1) {
      if (isDisposed) return

      try {
        const order = await syncOrder(targetOrderId)
        if (isDisposed) return

        orderStatus.value = order.status

        if (orderStatus.value === 'paid' || orderStatus.value === 'failed') return
      } catch (error) {
        if (isDisposed) return

        const normalized = normalizeError(error)
        if (normalized.status === 401) return await unauthorized()

        if (!isRetryableSyncError(normalized.status)) {
          pageError.value = normalized.message
          return
        }
      }

      if (attempt < MAX_POLL_ATTEMPTS - 1) {
        if (isDisposed) return
        await sleep(POLL_INTERVAL_MS)
      }
    }
  } finally {
    if (!isDisposed) {
      isChecking.value = false
    }
  }
}

onScopeDispose(() => {
  isDisposed = true

  if (sleepTimeout !== null) {
    clearTimeout(sleepTimeout)
    sleepTimeout = null
  }

  resolveSleep?.()
  resolveSleep = null
})

onMounted(() => {
  const targetOrderId = orderId.value
  if (!targetOrderId) return

  if (status.value === 'success') {
    void pollOrderStatus()
  } else {
    void cancelOrder(targetOrderId)
  }
})
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10">
    <UCard class="w-full max-w-md border border-slate-200 shadow-sm">
      <template #header>
        <h1 class="text-2xl font-semibold text-slate-900">
          {{ status === 'success' ? '購入結果' : '購入をキャンセルしました' }}
        </h1>
      </template>

      <div class="space-y-5">
        <template v-if="status === 'success'">
          <UAlert
            v-if="orderStatus === 'paid'"
            color="success"
            variant="soft"
            title="購入が完了しました"
            description="購入済み写真からダウンロードできます。"
          />
          <UAlert
            v-else-if="isChecking"
            color="info"
            variant="soft"
            title="購入内容を確認しています"
            description="サーバー側の反映に数秒かかる場合があります。しばらくお待ちください。"
          />
          <UAlert
            v-else-if="!hasOrderId"
            color="warning"
            variant="soft"
            title="注文情報を確認できませんでした"
            description="注文履歴から購入結果をご確認ください。"
          />
          <UAlert
            v-else
            color="warning"
            variant="soft"
            title="購入内容の反映に時間がかかっています"
            description="しばらくしてから注文履歴または購入済み写真をご確認ください。"
          />

          <UAlert v-if="pageError" color="error" variant="soft" :title="pageError" />

          <div class="flex flex-col gap-3 sm:flex-row">
            <UButton class="flex-1 justify-center" icon="i-lucide-images" to="/guardian/purchased-photos">
              購入済み写真へ
            </UButton>
            <UButton class="flex-1 justify-center" color="neutral" variant="outline" icon="i-lucide-receipt" to="/guardian/orders">
              注文履歴を見る
            </UButton>
          </div>
        </template>

        <template v-else>
          <UAlert
            color="neutral"
            variant="soft"
            title="購入をキャンセルしました"
            description="決済は完了していません。引き続き写真をご覧いただけます。"
          />

          <UAlert v-if="pageError" color="error" variant="soft" :title="pageError" />

          <UButton class="w-full justify-center" icon="i-lucide-arrow-left" to="/guardian/photos">
            写真一覧に戻る
          </UButton>
        </template>
      </div>
    </UCard>
  </main>
</template>
