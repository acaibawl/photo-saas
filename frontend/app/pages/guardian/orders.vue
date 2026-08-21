<script setup lang="ts">
definePageMeta({
  middleware: ['guardian-auth'],
})

type OrderStatus = 'pending' | 'paid' | 'failed' | 'refunded'

type OrderItem = {
  order_item_id: string
  photo_id: string
  price: number
}

type Order = {
  order_id: string
  status: OrderStatus
  total_amount: number
  created_at: string | null
  items: OrderItem[]
}

type OrderPageResponse = {
  data: Order[]
  meta: {
    current_page: number
    total: number
  }
}

const PER_PAGE = 20

const statusOptions = [
  { label: 'すべて', value: '' },
  { label: '保留中', value: 'pending' },
  { label: '支払い済み', value: 'paid' },
  { label: '失敗', value: 'failed' },
  { label: '返金済み', value: 'refunded' },
]

const { $api } = useNuxtApp()
const { normalizeError } = useApiError()
const { logout } = useGuardianAuth()

const orders = ref<Order[]>([])
const filters = reactive({ status: '' })
const currentPage = ref(1)
const total = ref(0)
const isLoading = ref(true)
const pageError = ref('')

const hasPagination = computed(() => total.value > PER_PAGE)

function statusLabel(status: OrderStatus): string {
  return statusOptions.find(option => option.value === status)?.label ?? status
}

function statusColor(status: OrderStatus): 'success' | 'error' | 'warning' | 'neutral' {
  if (status === 'paid') return 'success'
  if (status === 'failed') return 'error'
  if (status === 'refunded') return 'neutral'
  return 'warning'
}

function formatAmount(amount: number): string {
  return `${amount.toLocaleString('ja-JP')}円`
}

function formatDateTime(value: string | null): string {
  return value ? new Date(value).toLocaleString('ja-JP') : '-'
}

async function unauthorized(): Promise<void> {
  await logout().catch(() => undefined)
  await navigateTo('/guardian/login')
}

async function loadOrders(): Promise<void> {
  isLoading.value = true
  pageError.value = ''

  try {
    const response = await $api<OrderPageResponse>('/guardian/orders', {
      query: {
        status: filters.status || undefined,
        page: currentPage.value,
        per_page: PER_PAGE,
      },
    })
    orders.value = response.data
    total.value = response.meta.total
    currentPage.value = response.meta.current_page
  } catch (error) {
    const normalized = normalizeError(error)
    if (normalized.status === 401) return await unauthorized()
    pageError.value = normalized.message
  } finally {
    isLoading.value = false
  }
}

function applyFilters(): void {
  currentPage.value = 1
  void loadOrders()
}

function goToPage(page: number): void {
  currentPage.value = page
  void loadOrders()
}

onMounted(loadOrders)
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl space-y-8">
      <header class="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-2">
          <NuxtLink to="/guardian" class="inline-flex items-center gap-1 text-sm font-medium text-emerald-700">
            <UIcon name="i-lucide-arrow-left" class="size-4" />
            保護者ホーム
          </NuxtLink>
          <h1 class="text-3xl font-semibold text-slate-900">注文履歴</h1>
          <p class="text-sm text-slate-600">過去の注文状態を確認できます。</p>
        </div>
      </header>

      <UCard class="border border-slate-200 shadow-sm">
        <form class="grid gap-4 md:grid-cols-[1fr_auto] md:items-end" @submit.prevent="applyFilters">
          <UFormField label="ステータス">
            <USelect v-model="filters.status" :items="statusOptions.filter((option) => option.value)" value-key="value" placeholder="すべて" />
          </UFormField>
          <UButton type="submit" icon="i-lucide-list-filter">絞り込む</UButton>
        </form>
      </UCard>

      <UAlert v-if="pageError" color="error" variant="soft" :title="pageError">
        <template #actions>
          <UButton color="error" variant="ghost" size="sm" @click="loadOrders">再読み込み</UButton>
        </template>
      </UAlert>

      <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
          <h2 class="text-lg font-semibold text-slate-900">注文一覧</h2>
          <span v-if="!isLoading" class="text-sm text-slate-500">{{ total }}件</span>
        </div>

        <div v-if="isLoading" class="space-y-3 p-5" aria-label="読み込み中">
          <div v-for="index in 4" :key="index" class="h-16 animate-pulse rounded bg-slate-100" />
        </div>

        <div v-else-if="!orders.length" class="px-5 py-14 text-center">
          <UIcon name="i-lucide-receipt" class="mx-auto size-8 text-slate-400" />
          <p class="mt-3 font-medium text-slate-700">該当する注文がありません。</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead>
              <tr>
                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">注文ID</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">ステータス</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">写真件数</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">合計金額</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">注文日時</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="order in orders" :key="order.order_id">
                <td class="px-3 py-3 font-mono text-xs text-slate-600">{{ order.order_id }}</td>
                <td class="px-3 py-3">
                  <UBadge :color="statusColor(order.status)" variant="subtle">{{ statusLabel(order.status) }}</UBadge>
                </td>
                <td class="px-3 py-3 text-sm text-slate-700">{{ order.items.length }}件</td>
                <td class="px-3 py-3 text-sm font-medium text-slate-900">{{ formatAmount(order.total_amount) }}</td>
                <td class="px-3 py-3 text-sm text-slate-500">{{ formatDateTime(order.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="!isLoading && hasPagination" class="flex justify-center border-t border-slate-200 px-5 py-4">
          <UPagination :page="currentPage" :items-per-page="PER_PAGE" :total="total" @update:page="goToPage" />
        </div>
      </section>
    </div>
  </main>
</template>
