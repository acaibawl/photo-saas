<script setup lang="ts">
definePageMeta({
  middleware: ['staff-auth'],
})

type ChildClass = {
  id: string
  kindergarten_id: string
  name: string
  created_at: string
  updated_at: string
}

type ChildClassListResponse = {
  data: ChildClass[]
  meta: {
    current_page: number
    per_page: number
    total: number
  }
}

const { $api } = useNuxtApp()
const { normalizeError } = useApiError()
const { logout } = useStaffAuth()
const route = useRoute()
const router = useRouter()

function parsePageFromQuery(value: unknown): number {
  const raw = Array.isArray(value) ? value[0] : value
  const parsed = Number(raw)
  return Number.isInteger(parsed) && parsed > 0 ? parsed : 1
}

const childClasses = ref<ChildClass[]>([])
const currentPage = ref(parsePageFromQuery(route.query.page))
const perPage = ref(20)
const totalCount = ref(0)
const isLoading = ref(true)
const isSaving = ref(false)
const isDeleting = ref(false)
const pageError = ref('')
const formError = ref('')
const nameError = ref('')
const createName = ref('')
const editingClass = ref<ChildClass | null>(null)
const deletingClass = ref<ChildClass | null>(null)
const editName = ref('')

const hasClasses = computed(() => childClasses.value.length > 0)
const hasPagination = computed(() => totalCount.value > perPage.value)

async function handleUnauthorized(): Promise<void> {
  await logout().catch(() => undefined)
  await navigateTo('/staff/login')
}

function validateName(name: string): boolean {
  const trimmedName = name.trim()

  if (!trimmedName) {
    nameError.value = '組名を入力してください。'
    return false
  }

  if (trimmedName.length > 50) {
    nameError.value = '組名は50文字以内で入力してください。'
    return false
  }

  nameError.value = ''
  return true
}

function applyFormError(error: unknown, fallback: string): void {
  const normalized = normalizeError(error)

  if (normalized.code === 'CHILD_CLASS_NAME_ALREADY_EXISTS') {
    formError.value = '同じ名前の組がすでに登録されています。'
  } else if (normalized.code === 'CHILD_CLASS_IN_USE') {
    formError.value = '所属園児を先に移動してください。'
  } else if (normalized.code === 'VALIDATION_ERROR') {
    nameError.value = normalized.fieldErrors.name?.[0] ?? '入力内容を確認してください。'
  } else {
    formError.value = normalized.message || fallback
  }
}

async function loadChildClasses(page = currentPage.value): Promise<void> {
  isLoading.value = true
  pageError.value = ''

  try {
    const response = await $api<ChildClassListResponse>('/staff/child-classes', {
      query: { page, per_page: perPage.value },
    })
    childClasses.value = response.data
    currentPage.value = response.meta.current_page
    perPage.value = response.meta.per_page
    totalCount.value = response.meta.total
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.status === 401) {
      await handleUnauthorized()
      return
    }

    pageError.value = normalized.message
  } finally {
    isLoading.value = false
  }
}

async function goToPage(page: number): Promise<void> {
  if (page === currentPage.value) {
    await loadChildClasses(page)
    return
  }

  await router.push({ query: { ...route.query, page: page > 1 ? String(page) : undefined } })
}

watch(() => route.query.page, () => {
  const page = parsePageFromQuery(route.query.page)
  const rawPage = Array.isArray(route.query.page) ? route.query.page[0] : route.query.page

  if (rawPage !== undefined && rawPage !== String(page)) {
    void router.replace({ query: { ...route.query, page: page > 1 ? String(page) : undefined } })
    return
  }

  void loadChildClasses(page)
})

async function createChildClass(): Promise<void> {
  formError.value = ''

  if (!validateName(createName.value)) {
    return
  }

  isSaving.value = true

  try {
    await $api<ChildClass>('/staff/child-classes', {
      method: 'POST',
      body: { name: createName.value.trim() },
    })
    createName.value = ''
    await goToPage(1)
  } catch (error) {
    if (normalizeError(error).status === 401) {
      await handleUnauthorized()
      return
    }

    applyFormError(error, '組を作成できませんでした。')
  } finally {
    isSaving.value = false
  }
}

function openEditModal(childClass: ChildClass): void {
  editingClass.value = childClass
  editName.value = childClass.name
  formError.value = ''
  nameError.value = ''
}

function closeEditModal(): void {
  editingClass.value = null
  editName.value = ''
  formError.value = ''
  nameError.value = ''
}

function openDeleteModal(childClass: ChildClass): void {
  deletingClass.value = childClass
  formError.value = ''
}

function closeDeleteModal(): void {
  deletingClass.value = null
  formError.value = ''
}

const isEditModalOpen = computed({
  get: () => editingClass.value !== null,
  set: (value: boolean) => {
    if (!value) {
      closeEditModal()
    }
  },
})

const isDeleteModalOpen = computed({
  get: () => deletingClass.value !== null,
  set: (value: boolean) => {
    if (!value) {
      closeDeleteModal()
    }
  },
})

async function updateChildClass(): Promise<void> {
  if (!editingClass.value || !validateName(editName.value)) {
    return
  }

  formError.value = ''
  isSaving.value = true

  try {
    await $api<ChildClass>(`/staff/child-classes/${editingClass.value.id}`, {
      method: 'PATCH',
      body: { name: editName.value.trim() },
    })
    closeEditModal()
    await loadChildClasses()
  } catch (error) {
    if (normalizeError(error).status === 401) {
      await handleUnauthorized()
      return
    }

    applyFormError(error, '組名を更新できませんでした。')
  } finally {
    isSaving.value = false
  }
}

async function deleteChildClass(): Promise<void> {
  if (!deletingClass.value) {
    return
  }

  formError.value = ''
  isDeleting.value = true

  try {
    await $api<{ deleted: boolean, id: string }>(`/staff/child-classes/${deletingClass.value.id}`, {
      method: 'DELETE',
    })
    deletingClass.value = null
    const isLastItemOnPage = childClasses.value.length === 1 && currentPage.value > 1
    await goToPage(isLastItemOnPage ? currentPage.value - 1 : currentPage.value)
  } catch (error) {
    if (normalizeError(error).status === 401) {
      await handleUnauthorized()
      return
    }

    applyFormError(error, '組を削除できませんでした。')
  } finally {
    isDeleting.value = false
  }
}

onMounted(loadChildClasses)
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl space-y-8">
      <header class="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-2">
          <NuxtLink to="/staff" class="inline-flex items-center gap-1 text-sm font-medium text-sky-700 hover:text-sky-900">
            <UIcon name="i-lucide-arrow-left" class="size-4" /> ダッシュボード
          </NuxtLink>
          <h1 class="text-3xl font-semibold text-slate-900">組（クラス）管理</h1>
          <p class="text-sm text-slate-600">園児登録で使用する組を管理します。</p>
        </div>
      </header>

      <UCard class="border border-slate-200 shadow-sm">
        <template #header>
          <div>
            <h2 class="text-lg font-semibold text-slate-900">新しい組を追加</h2>
            <p class="mt-1 text-sm text-slate-600">同じ名前の組は登録できません。</p>
          </div>
        </template>

        <form class="flex flex-col gap-3 sm:flex-row sm:items-start" @submit.prevent="createChildClass">
          <div class="flex-1">
            <UInput v-model="createName" maxlength="50" placeholder="例: ひよこ組" :disabled="isSaving" />
            <p v-if="nameError && !editingClass" class="mt-1 text-sm text-red-700">{{ nameError }}</p>
          </div>
          <UButton type="submit" icon="i-lucide-plus" :loading="isSaving">組を追加</UButton>
        </form>
        <UAlert v-if="formError && !editingClass && !deletingClass" class="mt-4" color="error" variant="soft" :title="formError" />
      </UCard>

      <UAlert v-if="pageError" color="error" variant="soft" :title="pageError">
        <template #actions><UButton color="error" variant="ghost" size="sm" @click="goToPage(currentPage)">再読み込み</UButton></template>
      </UAlert>

      <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
          <h2 class="text-lg font-semibold text-slate-900">登録済みの組</h2>
          <span v-if="!isLoading" class="text-sm text-slate-500">{{ totalCount }}件</span>
        </div>

        <div v-if="isLoading" class="space-y-3 p-5" aria-label="読み込み中">
          <div v-for="index in 4" :key="index" class="h-12 animate-pulse rounded bg-slate-100" />
        </div>

        <div v-else-if="!hasClasses" class="px-5 py-14 text-center">
          <UIcon name="i-lucide-school" class="mx-auto size-8 text-slate-400" />
          <p class="mt-3 font-medium text-slate-700">登録済みの組はありません。</p>
          <p class="mt-1 text-sm text-slate-500">上のフォームから最初の組を追加してください。</p>
        </div>

        <div v-else class="divide-y divide-slate-100">
          <article v-for="childClass in childClasses" :key="childClass.id" class="flex items-center justify-between gap-4 px-5 py-4">
            <div class="min-w-0">
              <h3 class="truncate font-medium text-slate-900">{{ childClass.name }}</h3>
              <p class="mt-1 text-xs text-slate-500">更新日: {{ new Date(childClass.updated_at).toLocaleDateString('ja-JP') }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-1">
              <UButton color="neutral" variant="ghost" icon="i-lucide-pencil" :aria-label="`${childClass.name}を編集`" @click="openEditModal(childClass)" />
              <UButton color="error" variant="ghost" icon="i-lucide-trash-2" :aria-label="`${childClass.name}を削除`" @click="openDeleteModal(childClass)" />
            </div>
          </article>
        </div>

        <div v-if="!isLoading && hasPagination" class="flex justify-center border-t border-slate-200 px-5 py-4">
          <UPagination
            :page="currentPage"
            :items-per-page="perPage"
            :total="totalCount"
            @update:page="(page) => goToPage(page)"
          />
        </div>
      </section>
    </div>

    <UModal
      v-model:open="isEditModalOpen"
      title="組名を編集"
      description="園児の所属はそのまま維持されます。"
      :dismissible="!isSaving"
      :close="{ disabled: isSaving }"
    >
      <template #body>
        <form id="edit-class-form" class="space-y-4" @submit.prevent="updateChildClass">
          <div>
            <label for="edit-class-name" class="mb-2 block text-sm font-medium text-slate-800">組名</label>
            <UInput id="edit-class-name" v-model="editName" maxlength="50" :disabled="isSaving" autofocus />
            <p v-if="nameError" class="mt-1 text-sm text-red-700">{{ nameError }}</p>
          </div>
          <UAlert v-if="formError" color="error" variant="soft" :title="formError" />
        </form>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-3">
          <UButton color="neutral" variant="outline" :disabled="isSaving" @click="closeEditModal">キャンセル</UButton>
          <UButton type="submit" form="edit-class-form" :loading="isSaving">保存</UButton>
        </div>
      </template>
    </UModal>

    <UModal
      v-model:open="isDeleteModalOpen"
      title="組を削除しますか？"
      :dismissible="!isDeleting"
      :close="{ disabled: isDeleting }"
    >
      <template #body>
        <p class="text-sm leading-6 text-slate-700">「{{ deletingClass?.name }}」を削除します。所属する園児がいる組は削除できません。</p>
        <UAlert v-if="formError" class="mt-4" color="error" variant="soft" :title="formError" />
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-3">
          <UButton color="neutral" variant="outline" :disabled="isDeleting" @click="closeDeleteModal">キャンセル</UButton>
          <UButton color="error" icon="i-lucide-trash-2" :loading="isDeleting" @click="deleteChildClass">削除する</UButton>
        </div>
      </template>
    </UModal>
  </main>
</template>