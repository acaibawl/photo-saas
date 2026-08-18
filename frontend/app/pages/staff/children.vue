<script setup lang="ts">
definePageMeta({ middleware: ["staff-auth"] });

type Child = {
  id: string;
  name: string;
  class_name: string;
  status: string;
  created_at: string;
};
type ChildClass = { id: string; name: string };
type PageResponse<T> = {
  data: T[];
  meta: { current_page: number; per_page: number; total: number };
};

const { $api } = useNuxtApp();
const { normalizeError } = useApiError();
const { logout } = useStaffAuth();
const route = useRoute();
const router = useRouter();
const statusOptions = [
  { label: "すべて", value: "" },
  { label: "在籍", value: "enrolled" },
  { label: "卒園", value: "graduated" },
  { label: "退園", value: "withdrawn" },
];
const children = ref<Child[]>([]);
const classes = ref<ChildClass[]>([]);
const filters = reactive({
  status: String(route.query.status ?? "") || undefined,
  class_name: String(route.query.class_name ?? ""),
  keyword: String(route.query.keyword ?? ""),
});
const currentPage = ref(
  Number(route.query.page) > 0 ? Number(route.query.page) : 1,
);
const perPage = ref(20);
const total = ref(0);
const isLoading = ref(true);
const isSaving = ref(false);
const isModalOpen = ref(false);
const pageError = ref("");
const formError = ref("");
const fieldErrors = reactive<Record<string, string>>({
  name: "",
  class_name: "",
  status: "",
});
const form = reactive({ name: "", class_name: "", status: "enrolled" });
const hasPagination = computed(() => total.value > perPage.value);
const isDetailPage = computed(() => Boolean(route.params.childId));
const statusLabel = (status: string) =>
  statusOptions.find((option) => option.value === status)?.label ?? status;

async function unauthorized(): Promise<void> {
  await logout().catch(() => undefined);
  await navigateTo("/staff/login");
}
function queryValue(value: unknown): string {
  return Array.isArray(value) ? String(value[0] ?? "") : String(value ?? "");
}
async function loadChildren(): Promise<void> {
  isLoading.value = true;
  pageError.value = "";
  try {
    const childClassId = classes.value.find(
      (childClass) => childClass.name === filters.class_name,
    )?.id;
    const response = await $api<PageResponse<Child>>("/staff/children", {
      query: {
        page: currentPage.value,
        per_page: perPage.value,
        status: filters.status,
        child_class_id: childClassId,
        keyword: filters.keyword,
      },
    });
    children.value = response.data;
    currentPage.value = response.meta.current_page;
    perPage.value = response.meta.per_page;
    total.value = response.meta.total;
  } catch (error) {
    const normalized = normalizeError(error);
    if (normalized.status === 401) return await unauthorized();
    pageError.value = normalized.message;
  } finally {
    isLoading.value = false;
  }
}
async function loadClasses(): Promise<void> {
  try {
    const response = await $api<PageResponse<ChildClass>>(
      "/staff/child-classes",
      { query: { page: 1, per_page: 100 } },
    );
    classes.value = response.data;
  } catch (error) {
    const normalized = normalizeError(error);
    if (normalized.status === 401) return await unauthorized();
    pageError.value = normalized.message;
  }
}
async function reloadPageData(): Promise<void> {
  await Promise.all([loadChildren(), loadClasses()]);
}
async function applyFilters(): Promise<void> {
  currentPage.value = 1;
  await router.push({
    query: {
      status: filters.status || undefined,
      class_name: filters.class_name || undefined,
      keyword: filters.keyword || undefined,
    },
  });
}
async function goToPage(page: number): Promise<void> {
  currentPage.value = page;
  await router.push({
    query: { ...route.query, page: page > 1 ? String(page) : undefined },
  });
}
function clearForm(): void {
  form.name = "";
  form.class_name = "";
  form.status = "enrolled";
  formError.value = "";
  Object.keys(fieldErrors).forEach((key) => (fieldErrors[key] = ""));
}
async function createChild(): Promise<void> {
  formError.value = "";
  Object.keys(fieldErrors).forEach((key) => (fieldErrors[key] = ""));
  if (!form.name.trim()) fieldErrors.name = "氏名を入力してください。";
  if (!form.class_name) fieldErrors.class_name = "組を選択してください。";
  if (Object.values(fieldErrors).some(Boolean)) return;
  isSaving.value = true;
  try {
    const childClassId = classes.value.find(
      (childClass) => childClass.name === form.class_name,
    )?.id;
    await $api<Child>("/staff/children", {
      method: "POST",
      body: {
        name: form.name.trim(),
        child_class_id: childClassId,
        status: form.status,
      },
    });
    isModalOpen.value = false;
    clearForm();
    currentPage.value = 1;
    if (route.query.page) {
      await router.push({ query: { ...route.query, page: undefined } });
    } else {
      await loadChildren();
    }
  } catch (error) {
    const normalized = normalizeError(error);
    if (normalized.status === 401) return await unauthorized();
    Object.entries(normalized.fieldErrors).forEach(([key, messages]) => {
      if (key in fieldErrors) fieldErrors[key] = messages[0] ?? "";
    });
    formError.value = normalized.message;
  } finally {
    isSaving.value = false;
  }
}
function openCreateModal(): void {
  clearForm();
  isModalOpen.value = true;
}
watch(
  () => route.query,
  (query) => {
    filters.status = queryValue(query.status) || undefined;
    filters.class_name = queryValue(query.class_name);
    filters.keyword = queryValue(query.keyword);
    currentPage.value = Number(query.page) > 0 ? Number(query.page) : 1;
    void loadChildren();
  },
  { deep: true },
);
onMounted(() => {
  void reloadPageData();
});
</script>

<template>
  <main
    v-if="!isDetailPage"
    class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8"
  >
    <div class="mx-auto max-w-6xl space-y-8">
      <header
        class="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between"
      >
        <div class="space-y-2">
          <NuxtLink
            to="/staff"
            class="inline-flex items-center gap-1 text-sm font-medium text-sky-700 hover:text-sky-900"
            ><UIcon name="i-lucide-arrow-left" class="size-4" />
            ダッシュボード</NuxtLink
          >
          <h1 class="text-3xl font-semibold text-slate-900">園児管理</h1>
          <p class="text-sm text-slate-600">
            園児の情報、在籍状況、保護者招待を管理します。
          </p>
        </div>
        <UButton
          class="cursor-pointer"
          icon="i-lucide-plus"
          @click="openCreateModal"
          >園児を追加</UButton
        >
      </header>
      <UAlert v-if="pageError" color="error" variant="soft" :title="pageError"
        ><template #actions
          ><UButton
            color="error"
            variant="ghost"
            size="sm"
            @click="reloadPageData"
            >再読み込み</UButton
          ></template
        ></UAlert
      >
      <UCard class="border border-slate-200 shadow-sm"
        ><form
          class="grid gap-4 md:grid-cols-[1fr_1fr_1.5fr_auto] md:items-end"
          @submit.prevent="applyFilters"
        >
          <UFormField label="在籍状態"
            ><USelect
              v-model="filters.status"
              :items="statusOptions.filter((option) => option.value)"
              value-key="value"
              placeholder="すべて" /></UFormField
          ><UFormField label="組"
            ><USelect
              v-model="filters.class_name"
              :items="[
                ...classes.map((childClass) => ({
                  label: childClass.name,
                  value: childClass.name,
                })),
              ]"
              value-key="value"
              placeholder="すべて" /></UFormField
          ><UFormField label="キーワード"
            ><UInput
              v-model="filters.keyword"
              placeholder="園児名・組名"
              icon="i-lucide-search" /></UFormField
          ><UButton type="submit" color="primary" icon="i-lucide-list-filter"
            >絞り込む</UButton
          >
        </form></UCard
      >
      <section
        class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
      >
        <div
          class="flex items-center justify-between border-b border-slate-200 px-5 py-4"
        >
          <h2 class="text-lg font-semibold text-slate-900">園児一覧</h2>
          <span v-if="!isLoading" class="text-sm text-slate-500"
            >{{ total }}件</span
          >
        </div>
        <div v-if="isLoading" class="space-y-3 p-5" aria-label="読み込み中">
          <div
            v-for="index in 5"
            :key="index"
            class="h-14 animate-pulse rounded bg-slate-100"
          />
        </div>
        <div v-else-if="!children.length" class="px-5 py-14 text-center">
          <UIcon
            name="i-lucide-users-round"
            class="mx-auto size-8 text-slate-400"
          />
          <p class="mt-3 font-medium text-slate-700">
            該当する園児はいません。
          </p>
          <p class="mt-1 text-sm text-slate-500">
            条件を変えるか、園児を追加してください。
          </p>
        </div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <th
                  class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                >
                  氏名
                </th>
                <th
                  class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                >
                  組
                </th>
                <th
                  class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                >
                  状態
                </th>
                <th
                  class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                >
                  作成日
                </th>
                <th class="px-5 py-3" />
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr
                v-for="child in children"
                :key="child.id"
                class="hover:bg-slate-50"
              >
                <td
                  class="whitespace-nowrap px-5 py-4 font-medium text-slate-900"
                >
                  {{ child.name }}
                </td>
                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">
                  {{ child.class_name }}
                </td>
                <td class="whitespace-nowrap px-5 py-4">
                  <UBadge
                    :color="child.status === 'enrolled' ? 'success' : 'neutral'"
                    variant="subtle"
                    >{{ statusLabel(child.status) }}</UBadge
                  >
                </td>
                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">
                  {{ new Date(child.created_at).toLocaleDateString("ja-JP") }}
                </td>
                <td class="whitespace-nowrap px-5 py-4 text-right">
                  <UButton
                    :to="`/staff/children/${child.id}`"
                    color="neutral"
                    variant="ghost"
                    trailing-icon="i-lucide-chevron-right"
                    >詳細</UButton
                  >
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div
          v-if="!isLoading && hasPagination"
          class="flex justify-center border-t border-slate-200 px-5 py-4"
        >
          <UPagination
            :page="currentPage"
            :items-per-page="perPage"
            :total="total"
            @update:page="goToPage"
          />
        </div>
      </section>
    </div>
    <UModal
      v-model:open="isModalOpen"
      title="園児を追加"
      description="園児の基本情報を登録します。"
      :dismissible="!isSaving"
      :close="{ disabled: isSaving }"
      ><template #body
        ><form
          id="create-child-form"
          class="space-y-4"
          @submit.prevent="createChild"
        >
          <UFormField label="氏名" required :error="fieldErrors.name"
            ><UInput
              v-model="form.name"
              maxlength="100"
              placeholder="例: 山田 花子"
              :disabled="isSaving" /></UFormField
          ><UFormField label="組" required :error="fieldErrors.class_name"
            ><USelect
              v-model="form.class_name"
              :items="
                classes.map((childClass) => ({
                  label: childClass.name,
                  value: childClass.name,
                }))
              "
              value-key="value"
              placeholder="組を選択"
              :disabled="isSaving" /></UFormField
          ><UFormField label="在籍状態" :error="fieldErrors.status"
            ><USelect
              v-model="form.status"
              :items="statusOptions.filter((option) => option.value)"
              value-key="value"
              :disabled="isSaving" /></UFormField
          ><UAlert
            v-if="formError"
            color="error"
            variant="soft"
            :title="formError"
          /></form></template
      ><template #footer
        ><div class="flex w-full justify-end gap-3">
          <UButton
            color="neutral"
            variant="outline"
            :disabled="isSaving"
            @click="isModalOpen = false"
            >キャンセル</UButton
          ><UButton type="submit" form="create-child-form" :loading="isSaving"
            >登録する</UButton
          >
        </div></template
      ></UModal
    >
  </main>
  <NuxtPage v-else />
</template>
