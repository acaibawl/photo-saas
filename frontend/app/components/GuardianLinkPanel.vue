<script setup lang="ts">
const props = defineProps<{ childId: string }>();
type Link = {
  link_id: string;
  guardian_name: string;
  guardian_email: string;
  label: string;
  linked_at: string;
  unlinked_at: string | null;
};
const { $api } = useNuxtApp();
const { normalizeError } = useApiError();
const { logout } = useStaffAuth();
const links = ref<Link[]>([]);
const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref("");
const target = ref<Link | null>(null);
const confirmText = ref("");
const modalOpen = computed({
  get: () => target.value !== null,
  set: (value) => {
    if (!value) {
      target.value = null;
      confirmText.value = "";
    }
  },
});
async function unauthorized(): Promise<void> {
  await logout().catch(() => undefined);
  await navigateTo("/staff/login");
}
async function load(): Promise<void> {
  isLoading.value = true;
  try {
    links.value = (
      await $api<{ data: Link[] }>(
        `/staff/children/${props.childId}/guardian-links`,
        { query: { include_unlinked: true, page: 1, per_page: 20 } },
      )
    ).data;
  } catch (error) {
    const normalized = normalizeError(error);
    if (normalized.status === 401) return await unauthorized();
    errorMessage.value = normalized.message;
  } finally {
    isLoading.value = false;
  }
}
async function unlink(): Promise<void> {
  if (!target.value || confirmText.value !== "UNLINK") return;
  isSaving.value = true;
  try {
    await $api(`/staff/guardian-links/${target.value.link_id}/unlink`, {
      method: "POST",
      body: { confirm_text: confirmText.value },
    });
    modalOpen.value = false;
    await load();
  } catch (error) {
    errorMessage.value = normalizeError(error).message;
  } finally {
    isSaving.value = false;
  }
}
async function restore(link: Link): Promise<void> {
  isSaving.value = true;
  try {
    await $api(`/staff/guardian-links/${link.link_id}/restore`, {
      method: "POST",
    });
    await load();
  } catch (error) {
    errorMessage.value = normalizeError(error).message;
  } finally {
    isSaving.value = false;
  }
}
onMounted(load);
</script>
<template>
  <UCard class="border border-slate-200 shadow-sm"
    ><template #header
      ><div>
        <h2 class="text-lg font-semibold text-slate-900">保護者の紐づけ</h2>
        <p class="mt-1 text-sm text-slate-600">
          園児に紐づく保護者アカウントを管理します。
        </p>
      </div></template
    ><UAlert
      v-if="errorMessage"
      class="mb-4"
      color="error"
      variant="soft"
      :title="errorMessage"
    />
    <div v-if="isLoading" class="py-10 text-center text-sm text-slate-500">
      読み込み中...
    </div>
    <div
      v-else-if="!links.length"
      class="py-10 text-center text-sm text-slate-500"
    >
      紐づいている保護者はいません。
    </div>
    <div v-else class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200">
        <thead>
          <tr>
            <th
              class="px-3 py-3 text-left text-xs font-semibold text-slate-500"
            >
              保護者
            </th>
            <th
              class="px-3 py-3 text-left text-xs font-semibold text-slate-500"
            >
              ラベル
            </th>
            <th
              class="px-3 py-3 text-left text-xs font-semibold text-slate-500"
            >
              状態
            </th>
            <th class="px-3 py-3" />
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="link in links" :key="link.link_id">
            <td class="px-3 py-3">
              <p class="text-sm font-medium text-slate-800">
                {{ link.guardian_name }}
              </p>
              <p class="text-xs text-slate-500">{{ link.guardian_email }}</p>
            </td>
            <td class="px-3 py-3 text-sm text-slate-600">
              {{ link.label || "-" }}
            </td>
            <td class="px-3 py-3">
              <UBadge
                :color="link.unlinked_at ? 'neutral' : 'success'"
                variant="subtle"
                >{{ link.unlinked_at ? "解除済み" : "有効" }}</UBadge
              >
            </td>
            <td class="px-3 py-3 text-right">
              <UButton
                v-if="!link.unlinked_at"
                color="error"
                variant="ghost"
                size="sm"
                @click="target = link"
                >解除</UButton
              ><UButton
                v-else
                color="neutral"
                variant="ghost"
                size="sm"
                :loading="isSaving"
                @click="restore(link)"
                >復元</UButton
              >
            </td>
          </tr>
        </tbody>
      </table>
    </div></UCard
  ><UModal v-model:open="modalOpen" title="保護者の紐づけを解除しますか？"
    ><template #body
      ><p class="text-sm leading-6 text-slate-700">
        解除する保護者: {{ target?.guardian_name }}。確認のため
        <strong>UNLINK</strong> と入力してください。
      </p>
      <UInput
        v-model="confirmText"
        class="mt-4"
        placeholder="UNLINK"
        autocomplete="off" /></template
    ><template #footer
      ><div class="flex w-full justify-end gap-3">
        <UButton color="neutral" variant="outline" @click="modalOpen = false"
          >キャンセル</UButton
        ><UButton
          color="error"
          :disabled="confirmText !== 'UNLINK'"
          :loading="isSaving"
          @click="unlink"
          >解除する</UButton
        >
      </div></template
    ></UModal
  >
</template>
