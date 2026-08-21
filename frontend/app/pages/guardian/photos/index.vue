<script setup lang="ts">
import { storeToRefs } from 'pinia'
import type { GuardianLinkedChild } from '~/composables/useGuardianAuth'

definePageMeta({
  middleware: ['guardian-auth'],
})

type GuardianPhotoAlbum = {
  album_id: string
  title: string
  event_date: string
}

type GuardianPhoto = {
  photo_id: string
  album_id: string | null
  price: number | null
  purchased: boolean
  preview_url: string | null
  event_date: string | null
  tagged_child_ids: string[]
}

type GuardianPhotoPageResponse = {
  data: GuardianPhoto[]
  meta: {
    current_page: number
    total: number
  }
}

type CheckoutSessionResponse = {
  order_id: string
  checkout_session_id: string
  checkout_url: string
  total_amount: number
  currency: string
}

const pageSize = 20

const { $api } = useNuxtApp()
const { normalizeError } = useApiError()
const { logout, fetchChildren } = useGuardianAuth()
const photoBasket = useGuardianPhotoBasketStore()
const { selectedPhotos, selectedPhotoCount, selectedTotalAmount } = storeToRefs(photoBasket)
const route = useRoute()
const router = useRouter()

function queryValue(value: unknown): string {
  return Array.isArray(value) ? String(value[0] ?? '') : String(value ?? '')
}

const children = ref<GuardianLinkedChild[]>([])
const albums = ref<GuardianPhotoAlbum[]>([])
const photos = ref<GuardianPhoto[]>([])
const total = ref(0)
const currentPage = ref(Number(route.query.page) > 0 ? Number(route.query.page) : 1)
const isLoading = ref(true)
const isPurchasing = ref(false)
const pageError = ref('')
const filterError = ref('')
const purchaseError = ref('')
const previewRetriedPhotoIds = new Set<string>()

const filters = reactive({
  child_id: queryValue(route.query.child_id) || 'all',
  album_id: queryValue(route.query.album_id) || 'all',
  event_date_from: queryValue(route.query.event_date_from),
  event_date_to: queryValue(route.query.event_date_to),
})

const childOptions = computed(() => [
  { label: 'すべて', value: 'all' },
  ...children.value.map((child) => ({ label: child.child_name, value: child.child_id })),
])
const albumOptions = computed(() => [
  { label: 'すべて', value: 'all' },
  ...albums.value.map((album) => ({ label: album.title, value: album.album_id })),
])
const hasPagination = computed(() => total.value > pageSize)

async function unauthorized(): Promise<void> {
  await logout().catch(() => undefined)
  await navigateTo('/guardian/login')
}

async function loadChildren(): Promise<void> {
  filterError.value = ''

  try {
    const response = await fetchChildren()
    children.value = response.data
  } catch (error) {
    const normalized = normalizeError(error)
    if (normalized.status === 401) return await unauthorized()
    filterError.value = normalized.message
  }
}

async function loadAlbums(): Promise<void> {
  filterError.value = ''

  try {
    const response = await $api<{ data: GuardianPhotoAlbum[] }>('/guardian/albums', {
      query: { child_id: filters.child_id === 'all' ? undefined : filters.child_id },
    })
    albums.value = response.data
  } catch (error) {
    const normalized = normalizeError(error)
    if (normalized.status === 401) return await unauthorized()
    filterError.value = normalized.message
  }
}

async function loadFilterOptions(): Promise<void> {
  await Promise.all([loadChildren(), loadAlbums()])
}

let photosRequestId = 0

async function loadPhotos(): Promise<void> {
  const requestId = ++photosRequestId
  isLoading.value = true
  pageError.value = ''

  try {
    const response = await $api<GuardianPhotoPageResponse>('/guardian/photos', {
      query: {
        child_id: filters.child_id === 'all' ? undefined : filters.child_id,
        album_id: filters.album_id === 'all' ? undefined : filters.album_id,
        event_date_from: filters.event_date_from || undefined,
        event_date_to: filters.event_date_to || undefined,
        page: currentPage.value,
        per_page: pageSize,
      },
    })
    if (requestId !== photosRequestId) return

    photos.value = response.data
    total.value = response.meta.total
    currentPage.value = response.meta.current_page
    removeUnavailablePhotosFromSelection(response.data)
  } catch (error) {
    if (requestId !== photosRequestId) return

    const normalized = normalizeError(error)
    if (normalized.status === 401) return await unauthorized()
    pageError.value = normalized.message
  } finally {
    if (requestId === photosRequestId) {
      isLoading.value = false
    }
  }
}

function buildQuery(): Record<string, string | undefined> {
  return {
    child_id: filters.child_id === 'all' ? undefined : filters.child_id,
    album_id: filters.album_id === 'all' ? undefined : filters.album_id,
    event_date_from: filters.event_date_from || undefined,
    event_date_to: filters.event_date_to || undefined,
    page: currentPage.value > 1 ? String(currentPage.value) : undefined,
  }
}

let lastChildId = filters.child_id

async function syncAlbumsForChildChange(): Promise<void> {
  if (filters.child_id === lastChildId) return
  lastChildId = filters.child_id
  filters.album_id = 'all'
  await loadAlbums()
}

async function applyFilters(): Promise<void> {
  currentPage.value = 1
  clearSelection()
  await syncAlbumsForChildChange()
  await router.push({ query: buildQuery() })
}

async function goToPage(page: number): Promise<void> {
  currentPage.value = page
  await router.push({ query: buildQuery() })
}

async function refreshPreview(photo: GuardianPhoto): Promise<void> {
  if (previewRetriedPhotoIds.has(photo.photo_id)) return
  previewRetriedPhotoIds.add(photo.photo_id)

  try {
    const response = await $api<{ preview_url: string | null }>(`/guardian/photos/${photo.photo_id}/preview-url`, {
      method: 'POST',
    })
    photo.preview_url = response.preview_url
  } catch {
    // 再取得に失敗した場合はプレースホルダー表示のままにする
  }
}

function isSelected(photo: GuardianPhoto): boolean {
  return photoBasket.isSelected(photo.photo_id)
}

function canSelectPhoto(photo: GuardianPhoto): boolean {
  return photo.price !== null && !photo.purchased
}

function removeUnavailablePhotosFromSelection(loadedPhotos: GuardianPhoto[]): void {
  const unavailablePhotoIds = new Set(
    loadedPhotos
      .filter(photo => photo.purchased || photo.price === null)
      .map(photo => photo.photo_id),
  )

  if (!unavailablePhotoIds.size) return
  photoBasket.removePhotoIds([...unavailablePhotoIds])
}

function togglePhotoSelection(photo: GuardianPhoto): void {
  if (photo.price === null || photo.purchased) return

  purchaseError.value = ''
  photoBasket.toggle({
    photo_id: photo.photo_id,
    price: photo.price,
  })
}

function clearSelection(): void {
  photoBasket.clear()
  purchaseError.value = ''
}

async function purchaseSelectedPhotos(): Promise<void> {
  if (!selectedPhotoCount.value) return

  purchaseError.value = ''
  isPurchasing.value = true

  try {
    const origin = window.location.origin
    const response = await $api<CheckoutSessionResponse>('/guardian/purchases/checkout-session', {
      method: 'POST',
      body: {
        photo_ids: selectedPhotos.value.map(photo => photo.photo_id),
        checkout_amount: selectedTotalAmount.value,
        success_url: `${origin}/guardian/checkout/result?status=success`,
        cancel_url: `${origin}/guardian/checkout/result?status=cancel`,
      },
    })

    window.location.href = response.checkout_url
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.status === 401) {
      await unauthorized()
      return
    }

    if (normalized.code === 'PHOTO_PURCHASE_NOT_ALLOWED') {
      purchaseError.value = '選択した写真の中に購入できない写真があります。'
    } else if (normalized.code === 'SALES_DISABLED_FOR_KINDERGARTEN') {
      purchaseError.value = '園の販売設定が停止されているため、現在購入できません。'
    } else if (normalized.code === 'ORDER_ALREADY_PAID_OR_CLOSED') {
      purchaseError.value = '選択した写真の中に購入済み、または注文処理中の写真があります。'
    } else if (normalized.code === 'CHECKOUT_AMOUNT_MISMATCH') {
      purchaseError.value = '価格が更新されたため、最新情報を再取得しました。もう一度選択して購入してください。'
      clearSelection()
      await loadPhotos()
    } else {
      purchaseError.value = normalized.message
    }
  } finally {
    isPurchasing.value = false
  }
}

function formatPrice(price: number | null): string {
  return price === null ? '価格未設定' : `${price.toLocaleString('ja-JP')}円`
}

watch(
  () => route.query,
  async (query) => {
    filters.child_id = queryValue(query.child_id) || 'all'
    filters.album_id = queryValue(query.album_id) || 'all'
    filters.event_date_from = queryValue(query.event_date_from)
    filters.event_date_to = queryValue(query.event_date_to)
    currentPage.value = Number(query.page) > 0 ? Number(query.page) : 1

    await syncAlbumsForChildChange()
    void loadPhotos()
  },
  { deep: true },
)

onMounted(async () => {
  await loadFilterOptions()
  await loadPhotos()
})
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-6xl space-y-8">
      <header class="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-2">
          <NuxtLink to="/guardian" class="inline-flex items-center gap-1 text-sm font-medium text-emerald-700">
            <UIcon name="i-lucide-arrow-left" class="size-4" />
            保護者ホーム
          </NuxtLink>
          <h1 class="text-3xl font-semibold text-slate-900">写真一覧</h1>
          <p class="text-sm text-slate-600">条件を絞り込んで、閲覧可能な写真を確認できます。</p>
        </div>
      </header>

      <UAlert v-if="filterError" color="error" variant="soft" :title="filterError">
        <template #actions>
          <UButton color="error" variant="ghost" size="sm" @click="loadFilterOptions">再読み込み</UButton>
        </template>
      </UAlert>

      <UAlert v-if="pageError" color="error" variant="soft" :title="pageError">
        <template #actions>
          <UButton color="error" variant="ghost" size="sm" @click="loadPhotos">再読み込み</UButton>
        </template>
      </UAlert>

      <UAlert v-if="purchaseError" color="error" variant="soft" :title="purchaseError" />

      <UCard class="border border-slate-200 shadow-sm">
        <form class="grid gap-4 md:grid-cols-5 md:items-end" @submit.prevent="applyFilters">
          <UFormField label="園児">
            <USelect v-model="filters.child_id" :items="childOptions" value-key="value" />
          </UFormField>
          <UFormField label="アルバム">
            <USelect v-model="filters.album_id" :items="albumOptions" value-key="value" />
          </UFormField>
          <UFormField label="撮影日（開始）">
            <UInput v-model="filters.event_date_from" type="date" />
          </UFormField>
          <UFormField label="撮影日（終了）">
            <UInput v-model="filters.event_date_to" type="date" />
          </UFormField>
          <UButton type="submit" icon="i-lucide-list-filter">絞り込む</UButton>
        </form>
      </UCard>

      <section>
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <h2 class="text-lg font-semibold text-slate-900">写真</h2>
          <div class="flex flex-wrap items-center gap-3">
            <span v-if="!isLoading" class="text-sm text-slate-500">{{ total }}件</span>
            <UButton
              v-if="selectedPhotoCount"
              color="neutral"
              variant="ghost"
              size="sm"
              icon="i-lucide-x"
              @click="clearSelection"
            >
              選択解除
            </UButton>
          </div>
        </div>

        <div v-if="isLoading" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
          <USkeleton v-for="index in 8" :key="index" class="aspect-square" />
        </div>
        <div
          v-else-if="!photos.length"
          class="rounded-lg border border-dashed border-slate-300 bg-white px-5 py-16 text-center text-sm text-slate-500"
        >
          条件に一致する写真がありません。
        </div>
        <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
          <UCard
            v-for="photo in photos"
            :key="photo.photo_id"
            class="overflow-hidden p-0"
            :class="isSelected(photo) ? 'ring-2 ring-emerald-500' : ''"
          >
            <div class="relative">
              <UButton
                v-if="!photo.purchased"
                :icon="isSelected(photo) ? 'i-lucide-check' : 'i-lucide-plus'"
                :color="isSelected(photo) ? 'primary' : 'neutral'"
                :variant="isSelected(photo) ? 'solid' : 'soft'"
                size="sm"
                square
                class="absolute right-2 top-2 z-10 shadow-sm"
                :disabled="!canSelectPhoto(photo)"
                :aria-label="isSelected(photo) ? '選択を解除' : '購入対象に追加'"
                @click="togglePhotoSelection(photo)"
              />
              <UBadge
                v-else
                color="neutral"
                variant="solid"
                class="absolute right-2 top-2 z-10 shadow-sm"
              >
                購入済み
              </UBadge>
            </div>
            <NuxtLink :to="`/guardian/photos/${photo.photo_id}`" class="block">
              <div class="aspect-square bg-slate-100">
                <img
                  v-if="photo.preview_url"
                  :src="photo.preview_url"
                  :alt="`写真 ${photo.photo_id}`"
                  loading="lazy"
                  class="size-full object-cover"
                  @error="refreshPreview(photo)"
                >
                <div v-else class="flex size-full items-center justify-center">
                  <UIcon name="i-lucide-image" class="size-8 text-slate-400" />
                </div>
              </div>
              <div class="space-y-1 p-3">
                <p class="truncate text-sm font-medium text-slate-700">{{ formatPrice(photo.price) }}</p>
                <p v-if="photo.event_date" class="text-xs text-slate-500">{{ photo.event_date }}</p>
              </div>
            </NuxtLink>
          </UCard>
        </div>

        <div v-if="!isLoading && hasPagination" class="flex justify-center pt-6">
          <UPagination :page="currentPage" :items-per-page="pageSize" :total="total" @update:page="goToPage" />
        </div>
      </section>

      <div
        v-if="selectedPhotoCount"
        class="sticky bottom-4 z-20 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-lg"
      >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-sm font-medium text-slate-900">{{ selectedPhotoCount }}枚を選択中</p>
            <p class="text-lg font-semibold text-slate-900">合計 {{ formatPrice(selectedTotalAmount) }}</p>
          </div>
          <UButton
            icon="i-lucide-shopping-cart"
            :loading="isPurchasing"
            :disabled="!selectedPhotoCount"
            @click="purchaseSelectedPhotos"
          >
            まとめて購入
          </UButton>
        </div>
      </div>
    </div>
  </main>
</template>
