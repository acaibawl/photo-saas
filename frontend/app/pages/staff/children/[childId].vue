<script setup lang="ts">
definePageMeta({ middleware: ["staff-auth"] });
type Child = {
  id: string;
  class_id: string;
  name: string;
  class_name: string;
  status: string;
  created_at: string;
  updated_at?: string;
};
type ChildClass = { id: string; name: string };
type ChildClassResponse = { data: ChildClass[] };
const route = useRoute();
const childId = String(route.params.childId);
const { $api } = useNuxtApp();
const { normalizeError } = useApiError();
const { logout } = useStaffAuth();
const child = ref<Child | null>(null);
const classes = ref<ChildClass[]>([]);
const isLoading = ref(true);
const isSaving = ref(false);
const pageError = ref("");
const formError = ref("");
const name = ref("");
const childClassId = ref("");
const status = ref("");
const activeTab = ref<"info" | "invitations" | "links">("info");
const statusOptions = [
  { label: "在籍", value: "enrolled" },
  { label: "卒園", value: "graduated" },
  { label: "退園", value: "withdrawn" },
];
async function unauthorized(): Promise<void> {
  await logout().catch(() => undefined);
  await navigateTo("/staff/login");
}
async function loadChild(): Promise<void> {
  isLoading.value = true;
  try {
    child.value = await $api<Child>(`/staff/children/${childId}`);
    name.value = child.value.name;
    childClassId.value = child.value.class_id;
    status.value = child.value.status;
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
    const response = await $api<ChildClassResponse>("/staff/child-classes", {
      query: { page: 1, per_page: 100 },
    });
    classes.value = response.data;
  } catch (error) {
    const normalized = normalizeError(error);
    if (normalized.status === 401) return await unauthorized();
    pageError.value = normalized.message;
  }
}
async function reloadPageData(): Promise<void> {
  await Promise.all([loadChild(), loadClasses()]);
}
async function saveChild(): Promise<void> {
  if (!name.value.trim() || !childClassId.value) {
    formError.value = "氏名と組を選択してください。";
    return;
  }
  isSaving.value = true;
  formError.value = "";
  try {
    child.value = await $api<Child>(`/staff/children/${childId}`, {
      method: "PATCH",
      body: { name: name.value.trim(), child_class_id: childClassId.value },
    });

    if (status.value !== child.value.status) {
      await $api(`/staff/children/${childId}/status`, {
        method: "PATCH",
        body: { status: status.value },
      });
      child.value.status = status.value;
    }
  } catch (error) {
    const normalized = normalizeError(error);
    if (normalized.status === 401) return await unauthorized();
    formError.value = normalized.message;
  } finally {
    isSaving.value = false;
  }
}
onMounted(async () => {
  await reloadPageData();
});
</script>
<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-6xl space-y-8">
      <header
        class="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between"
      >
        <div class="space-y-2">
          <NuxtLink
            to="/staff/children"
            class="inline-flex items-center gap-1 text-sm font-medium text-sky-700 hover:text-sky-900"
            ><UIcon name="i-lucide-arrow-left" class="size-4" />
            園児一覧</NuxtLink
          >
          <h1 class="text-3xl font-semibold text-slate-900">園児詳細</h1>
          <p v-if="child" class="text-sm text-slate-600">
            {{ child.name }}さんの情報と保護者管理
          </p>
        </div>
        <UButton
          to="/staff/children"
          color="neutral"
          variant="outline"
          icon="i-lucide-list"
          >一覧に戻る</UButton
        >
      </header>
      <UAlert v-if="pageError" color="error" variant="soft" :title="pageError"
        ><template #actions
          ><UButton color="error" variant="ghost" size="sm" @click="reloadPageData"
            >再読み込み</UButton
          ></template
        ></UAlert
      >
      <div v-if="isLoading" class="space-y-4">
        <div class="h-40 animate-pulse rounded-lg bg-slate-200" />
        <div class="h-72 animate-pulse rounded-lg bg-slate-200" />
      </div>
      <template v-else-if="child"
        ><UCard class="border border-slate-200 shadow-sm"
          ><template #header
            ><div class="flex items-center justify-between gap-4">
              <div>
                <p class="text-sm font-medium text-slate-600">基本情報</p>
                <h2 class="mt-1 text-lg font-semibold text-slate-900">
                  {{ child.name }}
                </h2>
              </div>
              <UBadge
                :color="child!.status === 'enrolled' ? 'success' : 'neutral'"
                variant="subtle"
                >{{
                  statusOptions.find((option) => option.value === child!.status)
                    ?.label
                }}</UBadge
              >
            </div></template
          >
          <form
            class="grid gap-4 md:grid-cols-3 md:items-end"
            @submit.prevent="saveChild"
          >
            <UFormField label="氏名" required
              ><UInput
                v-model="name"
                maxlength="100"
                :disabled="isSaving" /></UFormField
            ><UFormField label="組" required
              ><USelect
                v-model="childClassId"
                :items="classes"
                label-key="name"
                value-key="id"
                :disabled="isSaving" /></UFormField
            ><UFormField label="在籍状態"
              ><USelect
                v-model="status"
                :items="statusOptions"
                value-key="value"
                :disabled="isSaving"
            /></UFormField>
            <div
              class="md:col-span-3 flex flex-wrap items-center justify-between gap-3"
            >
              <p class="text-xs text-slate-500">
                登録日:
                {{ new Date(child.created_at).toLocaleDateString("ja-JP") }}
              </p>
              <UButton type="submit" :loading="isSaving"
                >基本情報を保存</UButton
              >
            </div>
          </form>
          <UAlert
            v-if="formError"
            class="mt-4"
            color="error"
            variant="soft"
            :title="formError"
        /></UCard>
        <section class="space-y-4">
          <div class="flex gap-1 border-b border-slate-200">
            <UButton
              v-for="tab in [
                { key: 'info', label: '概要' },
                { key: 'invitations', label: '招待' },
                { key: 'links', label: '紐づけ' },
              ]"
              :key="tab.key"
              color="neutral"
              :variant="activeTab === tab.key ? 'soft' : 'ghost'"
              @click="activeTab = tab.key as typeof activeTab"
              >{{ tab.label }}</UButton
            >
          </div>
          <div
            v-show="activeTab === 'info'"
            class="rounded-lg border border-dashed border-slate-300 bg-white px-5 py-8 text-sm text-slate-600"
          >
            招待と保護者の紐づけ状況は、それぞれのタブで管理できます。
          </div>
          <div v-show="activeTab === 'invitations'">
            <InvitationPanel :child-id="childId" />
          </div>
          <div v-show="activeTab === 'links'">
            <GuardianLinkPanel :child-id="childId" />
          </div></section
      ></template>
    </div>
  </main>
</template>
