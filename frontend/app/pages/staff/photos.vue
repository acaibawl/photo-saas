<script setup lang="ts">
import type {
  Album,
  BatchStatus,
  Child,
  OptionsResponse,
  PageResponse,
  Photo,
  PhotoDetail,
  SelectOption,
} from '~/types/shared'

definePageMeta({ middleware: ["staff-auth"] });

const { $api } = useNuxtApp();
const { normalizeError } = useApiError();
const { logout } = useStaffAuth();
const photos = ref<Photo[]>([]);
const children = ref<Child[]>([]);
const albums = ref<Album[]>([]);
const priceOptions = ref<SelectOption[]>([{ label: "すべて", value: "all" }]);
const previewOptions = ref<SelectOption[]>([{ label: "すべて", value: "all" }]);
const previewStatusLabelMap = computed(() =>
  Object.fromEntries(
    previewOptions.value
      .filter((option) => option.value !== "all")
      .map((option) => [option.value, option.label]),
  ),
);
const total = ref(0);
const currentPage = ref(1);
const pageSize = 24;
const isLoading = ref(true);
const pageError = ref("");
const filters = reactive({
  album_id: "all",
  child_id: "all",
  price_status: "all",
  preview_status: "all",
});
const isUploadOpen = ref(false);
const isEditOpen = ref(false);
const isSaving = ref(false);
const uploadError = ref("");
const editError = ref("");
const selectedFiles = ref<File[]>([]);
const selectedPhoto = ref<PhotoDetail | null>(null);
const uploadForm = reactive({ album_id: "", price: "", child_ids: [] as string[] });
const editForm = reactive({ album_id: "", price: "", child_ids: [] as string[] });
const batch = ref<BatchStatus | null>(null);
let pollTimer: ReturnType<typeof setTimeout> | undefined;
let activePollBatchId: string | null = null;
let pollGeneration = 0;

const albumOptions = computed(() => {
  return albums.value.map((album) => ({ label: album.title, value: album.id }));
});
const childOptions = computed(() => children.value.map((child) => ({
  label: `${child.name}（${child.class_name}）`,
  value: child.id,
})));
const albumFilterOptions = computed(() => [
  { label: "すべて", value: "all" },
  ...albumOptions.value,
]);
const childFilterOptions = computed(() => [
  { label: "すべて", value: "all" },
  ...childOptions.value,
]);
const photoQueryFilters = computed(() => ({
  album_id: filters.album_id === "all" ? undefined : filters.album_id,
  child_id: filters.child_id === "all" ? undefined : filters.child_id,
  price_status: filters.price_status === "all" ? undefined : filters.price_status,
  preview_status:
    filters.preview_status === "all" ? undefined : filters.preview_status,
}));
const batchProgress = computed(() =>
  batch.value && batch.value.total_files
    ? Math.round((batch.value.accepted_count / batch.value.total_files) * 100)
    : 0,
);

async function unauthorized(): Promise<void> {
  await logout().catch(() => undefined);
  await navigateTo("/staff/login");
}
async function loadData(page = currentPage.value): Promise<void> {
  isLoading.value = true;
  pageError.value = "";
  try {
    const [
      photoResponse,
      childResponse,
      albumResponse,
      priceStatusResponse,
      previewStatusResponse,
    ] = await Promise.all([
      $api<PageResponse<Photo>>("/staff/photos", {
        query: {
          page,
          per_page: pageSize,
          ...photoQueryFilters.value,
        },
      }),
      $api<PageResponse<Child>>("/staff/children", {
        query: { page: 1, per_page: 100 },
      }),
      $api<PageResponse<Album>>("/staff/albums", {
        query: { page: 1, per_page: 100 },
      }),
      $api<OptionsResponse>("/staff/photos/price-statuses"),
      $api<OptionsResponse>("/staff/photos/preview-statuses"),
    ]);
    photos.value = photoResponse.data;
    total.value = photoResponse.meta.total;
    currentPage.value = photoResponse.meta.current_page;
    children.value = childResponse.data;
    albums.value = albumResponse.data;
    priceOptions.value = [{ label: "すべて", value: "all" }, ...priceStatusResponse.data];
    previewOptions.value = [{ label: "すべて", value: "all" }, ...previewStatusResponse.data];
  } catch (error) {
    const normalized = normalizeError(error);
    if (normalized.status === 401) return await unauthorized();
    pageError.value = normalized.message;
  } finally {
    isLoading.value = false;
  }
}

async function goToPage(page: number): Promise<void> {
  const nextPage = Math.max(1, Number(page) || 1);
  currentPage.value = nextPage;
  await loadData(nextPage);
}

function resetUpload(): void {
  uploadForm.album_id = "";
  uploadForm.price = "";
  uploadForm.child_ids = [];
  selectedFiles.value = [];
  uploadError.value = "";
  batch.value = null;
}
function handleFiles(event: Event): void {
  const input = event.target as HTMLInputElement;
  selectedFiles.value = Array.from(input.files ?? []);
}

async function createAlbum(): Promise<void> {
  // アルバム名の簡易入力はダイアログでの対話入力が必要なため許容する。
  // eslint-disable-next-line no-alert
  const title = window.prompt("アルバム名を入力してください");
  if (!title?.trim()) return;

  try {
    const album = await $api<Album>("/staff/albums", {
      method: "POST",
      body: {
        title: title.trim(),
        event_date: new Date().toISOString().slice(0, 10),
      },
    });
    albums.value = [album, ...albums.value];
    uploadForm.album_id = album.id;
  } catch (error) {
    uploadError.value = normalizeError(error).message;
  }
}

async function uploadBatch(): Promise<void> {
  uploadError.value = "";
  if (!selectedFiles.value.length) {
    uploadError.value = "写真を1枚以上選択してください。";
    return;
  }

  const body = new FormData();
  if (uploadForm.album_id) body.append("album_id", uploadForm.album_id);
  if (uploadForm.price) body.append("price", uploadForm.price);
  uploadForm.child_ids.forEach((id) => body.append("child_ids[]", id));
  selectedFiles.value.forEach((file) => body.append("files[]", file));
  isSaving.value = true;

  try {
    batch.value = await $api<BatchStatus>("/staff/photos/upload-batch", {
      method: "POST",
      body,
    });
    pollBatch(batch.value.batch_id);
  } catch (error) {
    const normalized = normalizeError(error);
    if (normalized.status === 401) return await unauthorized();
    uploadError.value = normalized.message;
  } finally {
    isSaving.value = false;
  }
}

function stopPolling(): void {
  pollGeneration += 1;
  activePollBatchId = null;
  if (pollTimer) clearTimeout(pollTimer);
  pollTimer = undefined;
}

function pollBatch(id: string): void {
  stopPolling();
  activePollBatchId = id;
  const generation = pollGeneration;

  pollTimer = setTimeout(async () => {
    pollTimer = undefined;
    try {
      const nextBatch = await $api<BatchStatus>(`/staff/photos/upload-batch/${id}`);
      if (generation !== pollGeneration || activePollBatchId !== id) return;

      batch.value = nextBatch;
      if (["completed", "failed"].includes(nextBatch.status)) {
        stopPolling();
        await loadData();
        return;
      }

      if (generation !== pollGeneration || activePollBatchId !== id) return;
      pollTimer = setTimeout(() => pollBatch(id), 1500);
    } catch (error) {
      if (generation !== pollGeneration || activePollBatchId !== id) return;
      uploadError.value = normalizeError(error).message;
    }
  }, 1500);
}

async function openEditor(photo: Photo): Promise<void> {
  editError.value = "";
  try {
    selectedPhoto.value = await $api<PhotoDetail>(`/staff/photos/${photo.photo_id}`);
    editForm.album_id = selectedPhoto.value.album_id ?? "";
    editForm.price = selectedPhoto.value.price?.toString() ?? "";
    editForm.child_ids = selectedPhoto.value.tagged_children.map((child) => child.child_id);
    isEditOpen.value = true;
  } catch (error) {
    editError.value = normalizeError(error).message;
  }
}

async function savePhoto(): Promise<void> {
  if (!selectedPhoto.value) return;
  isSaving.value = true;
  editError.value = "";

  try {
    await $api(`/staff/photos/${selectedPhoto.value.photo_id}`, {
      method: "PATCH",
      body: {
        album_id: editForm.album_id || null,
        price: editForm.price ? Number(editForm.price) : null,
        child_ids: editForm.child_ids,
      },
    });
    isEditOpen.value = false;
    await loadData();
  } catch (error) {
    const normalized = normalizeError(error);
    if (
      normalized.code === "PHOTO_NOT_READY_FOR_UPDATE" ||
      normalized.code === "PHOTO_PREVIEW_PROCESSING"
    ) {
      editError.value = "プレビュー処理中のため、まだ編集できません。";
    } else {
      editError.value = normalized.message;
    }
  } finally {
    isSaving.value = false;
  }
}

function formatPrice(price: number | null): string {
  return price === null ? "価格未設定" : `${price.toLocaleString("ja-JP")}円`;
}

function statusLabel(status: Photo["preview_status"]): string {
  return previewStatusLabelMap.value[status] ?? status;
}

onMounted(() => void loadData());
onBeforeUnmount(stopPolling);
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-8">
      <header
        class="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between"
      >
        <div class="space-y-2">
          <NuxtLink
            to="/staff"
            class="inline-flex items-center gap-1 text-sm font-medium text-sky-700"
          >
            <UIcon name="i-lucide-arrow-left" class="size-4" />
            ダッシュボード
          </NuxtLink>
          <h1 class="text-3xl font-semibold text-slate-900">写真管理</h1>
          <p class="text-sm text-slate-600">
            写真のアップロード、価格、アルバム、園児タグを管理します。
          </p>
        </div>
        <UButton
          icon="i-lucide-upload"
          @click="resetUpload(); isUploadOpen = true"
        >
          写真をアップロード
        </UButton>
      </header>

      <UAlert v-if="pageError" color="error" variant="soft" :title="pageError">
        <template #actions>
          <UButton color="error" variant="ghost" size="sm" @click="() => loadData()">
            再読み込み
          </UButton>
        </template>
      </UAlert>

      <UCard>
        <form
          class="grid gap-4 md:grid-cols-4 md:items-end"
          @submit.prevent="currentPage = 1; loadData()"
        >
          <UFormField label="アルバム">
            <USelect
              v-model="filters.album_id"
              :items="albumFilterOptions"
              value-key="value"
            />
          </UFormField>
          <UFormField label="園児">
            <USelect
              v-model="filters.child_id"
              :items="childFilterOptions"
              value-key="value"
            />
          </UFormField>
          <UFormField label="価格状態">
            <USelect v-model="filters.price_status" :items="priceOptions" value-key="value" />
          </UFormField>
          <UFormField label="プレビュー状態">
            <USelect
              v-model="filters.preview_status"
              :items="previewOptions"
              value-key="value"
            />
          </UFormField>
          <UButton
            class="md:col-span-4 md:justify-self-end"
            type="submit"
            icon="i-lucide-list-filter"
          >
            絞り込む
          </UButton>
        </form>
      </UCard>

      <UAlert
        v-if="batch"
        color="info"
        variant="soft"
        :title="`アップロード ${batch.status === 'completed' ? '完了' : batch.status === 'failed' ? '失敗' : '処理中'}（${batch.accepted_count}/${batch.total_files}）`"
      >
        <p v-if="batch.status === 'failed'" class="text-sm text-slate-700">
          一部またはすべての写真のアップロードに失敗しました。ファイルを確認して再試行してください。
        </p>
        <UProgress :model-value="batchProgress" class="mt-3" />
      </UAlert>

      <section>
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">写真一覧</h2>
          <span class="text-sm text-slate-500">{{ total }}件</span>
        </div>

        <div
          v-if="isLoading"
          class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6"
        >
          <USkeleton v-for="index in 12" :key="index" class="aspect-square" />
        </div>
        <div
          v-else-if="!photos.length"
          class="rounded-lg border border-dashed border-slate-300 bg-white px-5 py-16 text-center text-sm text-slate-500"
        >
          写真がありません。アップロードから登録してください。
        </div>
        <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
          <UCard
            v-for="photo in photos"
            :key="photo.photo_id"
            class="overflow-hidden p-0"
          >
            <button
              class="block w-full text-left"
              :disabled="photo.preview_status !== 'ready'"
              @click="openEditor(photo)"
            >
              <div class="aspect-square bg-slate-100">
                <img
                  v-if="photo.preview_url"
                  :src="photo.preview_url"
                  :alt="`写真 ${photo.photo_id}`"
                  loading="lazy"
                  class="size-full object-cover"
                >
                <div v-else class="flex size-full items-center justify-center">
                  <UIcon name="i-lucide-image" class="size-8 text-slate-400" />
                </div>
              </div>
              <div class="space-y-2 p-3">
                <div class="flex items-center justify-between gap-2">
                  <UBadge
                    :color="photo.preview_status === 'ready' ? 'success' : photo.preview_status === 'failed' ? 'error' : 'warning'"
                    variant="subtle"
                  >
                    {{ statusLabel(photo.preview_status) }}
                  </UBadge>
                  <UIcon
                    v-if="photo.preview_status === 'ready'"
                    name="i-lucide-pencil"
                    class="size-4 text-slate-400"
                  />
                </div>
                <p class="truncate text-sm font-medium text-slate-700">
                  {{ formatPrice(photo.price) }}
                </p>
              </div>
            </button>
          </UCard>
        </div>

        <div v-if="total > pageSize" class="flex justify-center pt-6">
          <UPagination
            :page="currentPage"
            :items-per-page="pageSize"
            :total="total"
            @update:page="goToPage"
          />
        </div>
      </section>
    </div>

    <USlideover v-model:open="isUploadOpen" title="写真をアップロード">
      <template #body>
        <div class="space-y-5">
          <UFormField label="写真ファイル" required>
            <UInput
              type="file"
              multiple
              accept=".jpg,.jpeg,.png,.heic"
              @change="handleFiles"
            />
            <p class="mt-2 text-xs text-slate-500">
              JPG、PNG、HEIC。1回10枚まで。
            </p>
          </UFormField>
          <UFormField label="アルバム">
            <div class="flex gap-2">
              <USelect
                v-model="uploadForm.album_id"
                :items="albumOptions"
                value-key="value"
                class="min-w-0 flex-1"
              />
              <UButton
                color="neutral"
                variant="outline"
                icon="i-lucide-plus"
                @click="createAlbum"
              >
                新規
              </UButton>
            </div>
          </UFormField>
          <UFormField label="販売価格（円）">
            <UInput v-model="uploadForm.price" type="number" min="1" placeholder="未設定" />
          </UFormField>
          <UFormField label="園児タグ">
            <USelect
              v-model="uploadForm.child_ids"
              :items="childOptions"
              value-key="value"
              multiple
              placeholder="園児を選択"
            />
          </UFormField>
          <p v-if="selectedFiles.length" class="text-sm text-slate-600">
            {{ selectedFiles.length }}枚を選択中
          </p>
          <UAlert v-if="uploadError" color="error" variant="soft" :title="uploadError" />
        </div>
      </template>
      <template #footer>
        <UButton color="neutral" variant="ghost" @click="isUploadOpen = false">
          閉じる
        </UButton>
        <UButton icon="i-lucide-upload" :loading="isSaving" @click="uploadBatch">
          アップロード開始
        </UButton>
      </template>
    </USlideover>

    <USlideover v-model:open="isEditOpen" title="写真を編集">
      <template #body>
        <div v-if="selectedPhoto" class="space-y-5">
          <img
            v-if="selectedPhoto.preview_url"
            :src="selectedPhoto.preview_url"
            alt="写真プレビュー"
            class="aspect-square w-full rounded-lg object-cover"
          >
          <UFormField label="アルバム">
            <USelect
              v-model="editForm.album_id"
              :items="albumOptions"
              value-key="value"
            />
          </UFormField>
          <UFormField label="販売価格（円）">
            <UInput v-model="editForm.price" type="number" min="1" placeholder="未設定" />
          </UFormField>
          <UFormField label="園児タグ">
            <USelect
              v-model="editForm.child_ids"
              :items="childOptions"
              value-key="value"
              multiple
            />
          </UFormField>
          <UAlert v-if="editError" color="error" variant="soft" :title="editError" />
        </div>
      </template>
      <template #footer>
        <UButton color="neutral" variant="ghost" @click="isEditOpen = false">
          キャンセル
        </UButton>
        <UButton :loading="isSaving" @click="savePhoto">保存</UButton>
      </template>
    </USlideover>
  </main>
</template>