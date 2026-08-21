<script setup lang="ts">
definePageMeta({
  middleware: ['guardian-auth'],
})

type GuardianPhotoAlbum = {
  album_id: string
  title: string
  event_date: string
}

type PurchasedPhoto = {
  photo_id: string
  album_id: string | null
  downloadable: boolean
  purchased_at: string | null
  event_date: string | null
  preview_url: string | null
}

type PurchasedPhotoPageResponse = {
  data: PurchasedPhoto[]
  meta: {
    current_page: number
    total: number
  }
}

const PER_PAGE = 20

const { $api } = useNuxtApp()
const { normalizeError } = useApiError()
const { logout } = useGuardianAuth()
const route = useRoute()
const router = useRouter()

function queryValue(value: unknown): string {
  return Array.isArray(value) ? String(value[0] ?? '') : String(value ?? '')
}

const albums = ref<GuardianPhotoAlbum[]>([])
const photos = ref<PurchasedPhoto[]>([])
const total = ref(0)
const currentPage = ref(Number(route.query.page) > 0 ? Number(route.query.page) : 1)
const isLoading = ref(true)
const pageError = ref('')
const filterError = ref('')
const downloadError = ref('')
const downloadingPhotoId = ref('')
const previewRetriedPhotoIds = new Set<string>()

const filters = reactive({
  album_id: queryValue(route.query.album_id) || 'all',
  event_date_from: queryValue(route.query.event_date_from),
  event_date_to: queryValue(route.query.event_date_to),
})

const albumOptions = computed(() => [
  { label: 'すべて', value: 'all' },
  ...albums.value.map(album => ({ label: album.title, value: album.album_id })),
])
const hasPagination = computed(() => total.value > PER_PAGE)

async function unauthorized(): Promise<void> {
  await logout().catch(() => undefined)
  await navigateTo('/guardian/login')
}

async function loadAlbums(): Promise<void> {
  filterError.value = ''

  try {
    const response = await $api<{ data: GuardianPhotoAlbum[] }>('/guardian/albums')
    albums.value = response.data
  } catch (error) {
    const normalized = normalizeError(error)
    if (normalized.status === 401) return await unauthorized()
    filterError.value = normalized.message
  }
}

let photosRequestId = 0

async function loadPhotos(): Promise<void> {
  const requestId = ++photosRequestId
  isLoading.value = true
  pageError.value = ''

  try {
    const response = await $api<PurchasedPhotoPageResponse>('/guardian/purchased-photos', {
      query: {
        album_id: filters.album_id === 'all' ? undefined : filters.album_id,
        event_date_from: filters.event_date_from || undefined,
        event_date_to: filters.event_date_to || undefined,
        page: currentPage.value,
        per_page: PER_PAGE,
      },
    })
    if (requestId !== photosRequestId) return

    photos.value = response.data
    total.value = response.meta.total
    currentPage.value = response.meta.current_page
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

async function refreshPreview(photo: PurchasedPhoto): Promise<void> {
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

async function download(photo: PurchasedPhoto): Promise<void> {
  downloadError.value = ''
  downloadingPhotoId.value = photo.photo_id

  try {
    const response = await $api<{ download_url: string, expires_at: string }>(`/guardian/photos/${photo.photo_id}/download-url`, {
      method: 'POST',
    })
    window.location.href = response.download_url
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.status === 401) {
      await unauthorized()
      return
    }

    if (normalized.code === 'ENTITLEMENT_NOT_FOUND') {
      downloadError.value = 'この写真はダウンロードできません。'
    } else {
      downloadError.value = normalized.message
    }
  } finally {
    downloadingPhotoId.value = ''
  }
}

watch(
  () => route.query,
  async (query) => {
    filters.album_id = queryValue(query.album_id) || 'all'
    filters.event_date_from = queryValue(query.event_date_from)
    filters.event_date_to = queryValue(query.event_date_to)
    currentPage.value = Number(query.page) > 0 ? Number(query.page) : 1

    void loadPhotos()
  },
  { deep: true },
)

onMounted(async () => {
  await loadAlbums()
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
          <h1 class="text-3xl font-semibold text-slate-900">購入済み写真</h1>
          <p class="text-sm text-slate-600">購入した写真の確認とダウンロードができます。</p>
        </div>
      </header>

      <UAlert v-if="filterError" color="error" variant="soft" :title="filterError">
        <template #actions>
          <UButton color="error" variant="ghost" size="sm" @click="loadAlbums">再読み込み</UButton>
        </template>
      </UAlert>

      <UAlert v-if="pageError" color="error" variant="soft" :title="pageError">
        <template #actions>
          <UButton color="error" variant="ghost" size="sm" @click="loadPhotos">再読み込み</UButton>
        </template>
      </UAlert>

      <UAlert v-if="downloadError" color="error" variant="soft" :title="downloadError" />

      <UCard class="border border-slate-200 shadow-sm">
        <form class="grid gap-4 md:grid-cols-4 md:items-end" @submit.prevent="applyFilters">
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
          購入済み写真はまだありません。
        </div>
        <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
          <UCard v-for="photo in photos" :key="photo.photo_id" class="overflow-hidden p-0">
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
            <div class="space-y-2 p-3">
              <p v-if="photo.event_date" class="text-xs text-slate-500">撮影日: {{ photo.event_date }}</p>
              <p v-if="photo.purchased_at" class="text-xs text-slate-500">
                購入日時: {{ new Date(photo.purchased_at).toLocaleString('ja-JP') }}
              </p>
              <UButton
                block
                size="sm"
                icon="i-lucide-download"
                :loading="downloadingPhotoId === photo.photo_id"
                @click="download(photo)"
              >
                ダウンロード
              </UButton>
            </div>
          </UCard>
        </div>

        <div v-if="!isLoading && hasPagination" class="flex justify-center pt-6">
          <UPagination :page="currentPage" :items-per-page="PER_PAGE" :total="total" @update:page="goToPage" />
        </div>
      </section>
    </div>
  </main>
</template>
