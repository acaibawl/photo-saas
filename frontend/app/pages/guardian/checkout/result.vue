<script setup lang="ts">
definePageMeta({
  middleware: ['guardian-auth'],
})

type CheckoutStatus = 'success' | 'cancel'
type OrderStatus = 'pending' | 'paid' | 'failed' | 'refunded'

type Order = {
  order_id: string
  status: OrderStatus
}

type OrderPageResponse = {
  data: Order[]
}

const MAX_POLL_ATTEMPTS = 5
const POLL_INTERVAL_MS = 2000

const { $api } = useNuxtApp()
const { normalizeError } = useApiError()
const { logout } = useGuardianAuth()
const route = useRoute()

const status = computed<CheckoutStatus>(() => (route.query.status === 'success' ? 'success' : 'cancel'))
const orderId = computed(() => (typeof route.query.order_id === 'string' ? route.query.order_id : null))

const isChecking = ref(false)
const orderStatus = ref<OrderStatus | null>(null)
const pageError = ref('')

function sleep(ms: number): Promise<void> {
  return new Promise(resolve => setTimeout(resolve, ms))
}

async function unauthorized(): Promise<void> {
  await logout().catch(() => undefined)
  await navigateTo('/guardian/login')
}

async function pollOrderStatus(): Promise<void> {
  if (!orderId.value) return

  isChecking.value = true
  pageError.value = ''

  try {
    for (let attempt = 0; attempt < MAX_POLL_ATTEMPTS; attempt += 1) {
      const response = await $api<OrderPageResponse>('/guardian/orders')
      const order = response.data.find(item => item.order_id === orderId.value)
      orderStatus.value = order?.status ?? null

      if (orderStatus.value === 'paid') return

      if (attempt < MAX_POLL_ATTEMPTS - 1) {
        await sleep(POLL_INTERVAL_MS)
      }
    }
  } catch (error) {
    const normalized = normalizeError(error)
    if (normalized.status === 401) return await unauthorized()
    pageError.value = normalized.message
  } finally {
    isChecking.value = false
  }
}

onMounted(() => {
  if (status.value === 'success') {
    void pollOrderStatus()
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

          <UButton class="w-full justify-center" icon="i-lucide-arrow-left" to="/guardian/photos">
            写真一覧に戻る
          </UButton>
        </template>
      </div>
    </UCard>
  </main>
</template>
