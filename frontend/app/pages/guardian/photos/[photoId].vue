<script setup lang="ts">
definePageMeta({
  middleware: ['guardian-auth'],
})

type GuardianPhotoDetailChild = {
  child_id: string
  name: string
  class_name: string
}

type GuardianPhotoDetail = {
  photo_id: string
  album: {
    title: string | null
    event_date: string | null
  }
  price: number | null
  preview_url: string | null
  tagged_children: GuardianPhotoDetailChild[]
}

type CheckoutSessionResponse = {
  order_id: string
  checkout_session_id: string
  checkout_url: string
  total_amount: number
  currency: string
}

const { $api } = useNuxtApp()
const { normalizeError } = useApiError()
const { logout } = useGuardianAuth()
const route = useRoute()

const photoId = computed(() => String(route.params.photoId))

const photo = ref<GuardianPhotoDetail | null>(null)
const isLoading = ref(true)
const isPurchasing = ref(false)
const pageError = ref('')
const purchaseError = ref('')
let previewRetried = false

async function unauthorized(): Promise<void> {
  await logout().catch(() => undefined)
  await navigateTo('/guardian/login')
}

async function loadPhoto(): Promise<void> {
  isLoading.value = true
  pageError.value = ''
  previewRetried = false

  try {
    photo.value = await $api<GuardianPhotoDetail>(`/guardian/photos/${photoId.value}`)
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.status === 401) {
      await unauthorized()
      return
    }

    if (normalized.status === 404) {
      pageError.value = '写真が見つかりませんでした。'
      return
    }

    if (normalized.status === 403) {
      pageError.value = 'この写真を閲覧する権限がありません。'
      return
    }

    pageError.value = normalized.message
  } finally {
    isLoading.value = false
  }
}

async function refreshPreview(): Promise<void> {
  if (previewRetried) {
    if (photo.value) {
      photo.value.preview_url = null
    }
    return
  }
  previewRetried = true

  try {
    const response = await $api<{ preview_url: string | null }>(`/guardian/photos/${photoId.value}/preview-url`, {
      method: 'POST',
    })

    if (photo.value) {
      photo.value.preview_url = response.preview_url
    }
  } catch {
    if (photo.value) {
      photo.value.preview_url = null
    }
  }
}

async function purchase(): Promise<void> {
  if (!photo.value || photo.value.price === null) return

  purchaseError.value = ''
  isPurchasing.value = true

  try {
    const origin = window.location.origin
    const response = await $api<CheckoutSessionResponse>('/guardian/purchases/checkout-session', {
      method: 'POST',
      body: {
        photo_ids: [photo.value.photo_id],
        checkout_amount: photo.value.price,
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
      purchaseError.value = 'この写真は購入できません。'
    } else if (normalized.code === 'SALES_DISABLED_FOR_KINDERGARTEN') {
      purchaseError.value = '園の販売設定が停止されているため、現在購入できません。'
    } else if (normalized.code === 'ORDER_ALREADY_PAID_OR_CLOSED') {
      purchaseError.value = 'この写真はすでに購入済み、または注文処理中です。'
    } else if (normalized.code === 'CHECKOUT_AMOUNT_MISMATCH') {
      purchaseError.value = '価格が更新されたため、最新情報を再取得しました。もう一度購入をお試しください。'
      await loadPhoto()
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

onMounted(loadPhoto)
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl space-y-8">
      <header class="border-b border-slate-200 pb-6">
        <NuxtLink to="/guardian/photos" class="inline-flex items-center gap-1 text-sm font-medium text-emerald-700">
          <UIcon name="i-lucide-arrow-left" class="size-4" />
          写真一覧に戻る
        </NuxtLink>
        <h1 class="mt-2 text-3xl font-semibold text-slate-900">写真詳細</h1>
      </header>

      <UAlert v-if="pageError" color="error" variant="soft" :title="pageError">
        <template #actions>
          <UButton color="error" variant="ghost" size="sm" @click="loadPhoto">再読み込み</UButton>
        </template>
      </UAlert>

      <div v-if="isLoading" class="space-y-4">
        <USkeleton class="aspect-square w-full" />
        <USkeleton class="h-6 w-2/3" />
        <USkeleton class="h-6 w-1/3" />
      </div>

      <UCard v-else-if="photo" class="border border-slate-200 shadow-sm">
        <div class="space-y-6">
          <div class="aspect-square overflow-hidden rounded-lg bg-slate-100">
            <img
              v-if="photo.preview_url"
              :src="photo.preview_url"
              :alt="`写真 ${photo.photo_id}`"
              class="size-full object-cover"
              @error="refreshPreview"
            >
            <div v-else class="flex size-full items-center justify-center">
              <UIcon name="i-lucide-image" class="size-8 text-slate-400" />
            </div>
          </div>

          <div class="space-y-2">
            <p class="text-lg font-semibold text-slate-900">{{ photo.album.title ?? 'アルバム未設定' }}</p>
            <p v-if="photo.album.event_date" class="text-sm text-slate-600">撮影日: {{ photo.album.event_date }}</p>
            <div v-if="photo.tagged_children.length" class="flex flex-wrap gap-2">
              <UBadge v-for="child in photo.tagged_children" :key="child.child_id" color="neutral" variant="subtle">
                {{ child.name }}（{{ child.class_name }}）
              </UBadge>
            </div>
          </div>

          <div class="flex items-center justify-between border-t border-slate-200 pt-4">
            <p class="text-2xl font-semibold text-slate-900">{{ formatPrice(photo.price) }}</p>
            <UButton
              icon="i-lucide-shopping-cart"
              :disabled="photo.price === null"
              :loading="isPurchasing"
              @click="purchase"
            >
              購入する
            </UButton>
          </div>

          <UAlert v-if="purchaseError" color="error" variant="soft" :title="purchaseError" />
        </div>
      </UCard>
    </div>
  </main>
</template>
