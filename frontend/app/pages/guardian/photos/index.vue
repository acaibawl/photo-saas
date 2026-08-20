<script setup lang="ts">
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

const pageSize = 20

const { $api } = useNuxtApp()
const { normalizeError } = useApiError()
const { logout, fetchChildren } = useGuardianAuth()
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
const pageError = ref('')
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
  try {
    const response = await fetchChildren()
    children.value = response.data
  } catch (error) {
    const normalized = normalizeError(error)
    if (normalized.status === 401) return await unauthorized()
    pageError.value = normalized.message
  }
}

async function loadAlbums(): Promise<void> {
  try {
    const response = await $api<{ data: GuardianPhotoAlbum[] }>('/guardian/albums', {
      query: { child_id: filters.child_id === 'all' ? undefined : filters.child_id },
    })
    albums.value = response.data
  } catch (error) {
    const normalized = normalizeError(error)
    if (normalized.status === 401) return await unauthorized()
    pageError.value = normalized.message
  }
}

async function loadPhotos(): Promise<void> {
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
    photos.value = response.data
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

function buildQuery(): Record<string, string | undefined> {
  return {
    child_id: filters.child_id === 'all' ? undefined : filters.child_id,
    album_id: filters.album_id === 'all' ? undefined : filters.album_id,
    event_date_from: filters.event_date_from || undefined,
    event_date_to: filters.event_date_to || undefined,
    page: currentPage.value > 1 ? String(currentPage.value) : undefined,
  }
}

async function applyFilters(): Promise<void> {
  currentPage.value = 1
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

function formatPrice(price: number | null): string {
  return price === null ? '価格未設定' : `${price.toLocaleString('ja-JP')}円`
}

watch(
  () => route.query,
  (query) => {
    const previousChildId = filters.child_id
    filters.child_id = queryValue(query.child_id) || 'all'
    filters.album_id = queryValue(query.album_id) || 'all'
    filters.event_date_from = queryValue(query.event_date_from)
    filters.event_date_to = queryValue(query.event_date_to)
    currentPage.value = Number(query.page) > 0 ? Number(query.page) : 1

    if (filters.child_id !== previousChildId) {
      void loadAlbums()
    }

    void loadPhotos()
  },
  { deep: true },
)

onMounted(async () => {
  await Promise.all([loadChildren(), loadAlbums()])
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

      <UAlert v-if="pageError" color="error" variant="soft" :title="pageError">
        <template #actions>
          <UButton color="error" variant="ghost" size="sm" @click="loadPhotos">再読み込み</UButton>
        </template>
      </UAlert>

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
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">写真</h2>
          <span v-if="!isLoading" class="text-sm text-slate-500">{{ total }}件</span>
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
          <UCard v-for="photo in photos" :key="photo.photo_id" class="overflow-hidden p-0">
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
    </div>
  </main>
</template>
