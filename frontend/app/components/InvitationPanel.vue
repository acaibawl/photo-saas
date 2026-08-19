<script setup lang="ts">
import QRCode from "qrcode";

const props = defineProps<{ childId: string }>();
type Invitation = {
  invitation_id: string;
  label: string;
  expires_at: string;
  used_at: string | null;
  revoked_at: string | null;
};
type CreatedInvitation = {
  invitation_id: string;
  invite_url: string;
  token_expires_at: string;
  qr_payload?: string;
};
const { $api } = useNuxtApp();
const { normalizeError } = useApiError();
const { logout } = useStaffAuth();
const config = useRuntimeConfig();
const authStore = useAuthStore();
const invitations = ref<Invitation[]>([]);
const created = ref<CreatedInvitation | null>(null);
const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref("");
const label = ref("");
const revokeTarget = ref<Invitation | null>(null);
const revokeOpen = computed({
  get: () => revokeTarget.value !== null,
  set: (value) => {
    if (!value) revokeTarget.value = null;
  },
});
const qrUrl = ref("");
async function generateQrUrl(invitation: CreatedInvitation): Promise<void> {
  qrUrl.value = await QRCode.toDataURL(
    invitation.qr_payload ?? invitation.invite_url,
    { width: 220, margin: 1 },
  );
}
async function copyInviteUrl(): Promise<void> {
  if (created.value)
    {await navigator.clipboard.writeText(created.value.invite_url);}
}
async function unauthorized(): Promise<void> {
  await logout().catch(() => undefined);
  await navigateTo("/staff/login");
}
async function load(): Promise<void> {
  isLoading.value = true;
  try {
    invitations.value = (
      await $api<{ data: Invitation[] }>(
        `/staff/children/${props.childId}/invitations`,
        { query: { page: 1, per_page: 20 } },
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
async function create(): Promise<void> {
  if (!label.value.trim()) {
    errorMessage.value = "招待名を入力してください。";
    return;
  }
  isSaving.value = true;
  errorMessage.value = "";
  try {
    created.value = await $api<CreatedInvitation>(
      `/staff/children/${props.childId}/invitations`,
      { method: "POST", body: { label: label.value.trim() } },
    );
    await generateQrUrl(created.value);
    label.value = "";
    await load();
  } catch (error) {
    const normalized = normalizeError(error);
    if (normalized.status === 401) return await unauthorized();
    errorMessage.value = normalized.message;
  } finally {
    isSaving.value = false;
  }
}
async function revoke(): Promise<void> {
  if (!revokeTarget.value) return;
  isSaving.value = true;
  try {
    await $api(
      `/staff/invitations/${revokeTarget.value.invitation_id}/revoke`,
      { method: "POST" },
    );
    revokeTarget.value = null;
    await load();
  } catch (error) {
    errorMessage.value = normalizeError(error).message;
  } finally {
    isSaving.value = false;
  }
}
async function reissue(invitation: Invitation): Promise<void> {
  isSaving.value = true;
  try {
    created.value = await $api<CreatedInvitation>(
      `/staff/invitations/${invitation.invitation_id}/reissue`,
      { method: "POST" },
    );
    await generateQrUrl(created.value);
    await load();
  } catch (error) {
    errorMessage.value = normalizeError(error).message;
  } finally {
    isSaving.value = false;
  }
}
async function printCreated(): Promise<void> {
  if (!created.value) return;
  const token = created.value.invite_url.split("/").pop();

  try {
    const response = await fetch(
      `${config.public.apiBaseUrl}/staff/invitations/${created.value.invitation_id}/print?token=${encodeURIComponent(token ?? "")}`,
      { headers: { Authorization: `Bearer ${authStore.staffAccessToken}` } },
    );
    if (!response.ok) {
      errorMessage.value = "印刷用PDFを取得できませんでした。";
      return;
    }

    const url = URL.createObjectURL(await response.blob());
    window.open(url, "_blank", "noopener,noreferrer");
  } catch (error) {
    const normalized = normalizeError(error);
    if (normalized.status === 401) return await unauthorized();
    errorMessage.value = normalized.message;
  }
}
onMounted(load);
</script>
<template>
  <UCard class="border border-slate-200 shadow-sm"
    ><template #header
      ><div>
        <h2 class="text-lg font-semibold text-slate-900">保護者招待</h2>
        <p class="mt-1 text-sm text-slate-600">
          招待URLを発行して保護者へ共有します。
        </p>
      </div></template
    >
    <form
      class="flex flex-col gap-3 sm:flex-row sm:items-end"
      @submit.prevent="create"
    >
      <UFormField class="flex-1" label="招待名"
        ><UInput
          v-model="label"
          placeholder="例: 母"
          :disabled="isSaving" /></UFormField
      ><UButton type="submit" icon="i-lucide-send" :loading="isSaving"
        >招待を発行</UButton
      >
    </form>
    <UAlert
      v-if="errorMessage"
      class="mt-4"
      color="error"
      variant="soft"
      :title="errorMessage"
    />
    <div
      v-if="created"
      class="mt-5 grid gap-5 border-t border-slate-200 pt-5 sm:grid-cols-[220px_1fr]"
    >
      <img
        :src="qrUrl"
        alt="招待URLのQRコード"
        class="size-[220px] rounded border border-slate-200 p-2"
      >
      <div class="space-y-3">
        <p class="font-medium text-slate-900">招待を発行しました</p>
        <UInput :model-value="created.invite_url" readonly />
        <div class="flex flex-wrap gap-2">
          <UButton icon="i-lucide-printer" @click="printCreated"
            >印刷用PDF</UButton
          ><UButton
            color="neutral"
            variant="outline"
            icon="i-lucide-copy"
            @click="copyInviteUrl"
            >URLをコピー</UButton
          >
        </div>
        <p class="text-xs text-slate-500">
          有効期限:
          {{ new Date(created.token_expires_at).toLocaleDateString("ja-JP") }}
        </p>
      </div>
    </div>
    <div class="mt-6 overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200">
        <thead>
          <tr>
            <th
              class="px-3 py-3 text-left text-xs font-semibold text-slate-500"
            >
              招待名
            </th>
            <th
              class="px-3 py-3 text-left text-xs font-semibold text-slate-500"
            >
              状態
            </th>
            <th
              class="px-3 py-3 text-left text-xs font-semibold text-slate-500"
            >
              有効期限
            </th>
            <th class="px-3 py-3" />
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="isLoading">
            <td
              colspan="4"
              class="px-3 py-8 text-center text-sm text-slate-500"
            >
              読み込み中...
            </td>
          </tr>
          <tr v-else-if="!invitations.length">
            <td
              colspan="4"
              class="px-3 py-8 text-center text-sm text-slate-500"
            >
              招待はまだありません。
            </td>
          </tr>
          <tr
            v-for="invitation in invitations"
            v-else
            :key="invitation.invitation_id"
          >
            <td class="px-3 py-3 text-sm text-slate-800">
              {{ invitation.label }}
            </td>
            <td class="px-3 py-3">
              <UBadge
                :color="
                  invitation.used_at
                    ? 'neutral'
                    : invitation.revoked_at
                      ? 'error'
                      : new Date(invitation.expires_at) < new Date()
                        ? 'warning'
                        : 'success'
                "
                variant="subtle"
                >{{
                  invitation.used_at
                    ? "使用済み"
                    : invitation.revoked_at
                      ? "失効"
                      : new Date(invitation.expires_at) < new Date()
                        ? "期限切れ"
                        : "有効"
                }}</UBadge
              >
            </td>
            <td class="px-3 py-3 text-sm text-slate-500">
              {{ new Date(invitation.expires_at).toLocaleDateString("ja-JP") }}
            </td>
            <td class="px-3 py-3 text-right">
              <div class="flex justify-end gap-1">
                <UButton
                  v-if="!invitation.used_at && !invitation.revoked_at"
                  color="error"
                  variant="ghost"
                  size="sm"
                  @click="revokeTarget = invitation"
                  >失効</UButton
                ><UButton
                  v-if="invitation.revoked_at || invitation.used_at"
                  color="neutral"
                  variant="ghost"
                  size="sm"
                  :loading="isSaving"
                  @click="reissue(invitation)"
                  >再発行</UButton
                >
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div></UCard
  ><UModal v-model:open="revokeOpen" title="招待を失効しますか？"
    ><template #body
      ><p class="text-sm leading-6 text-slate-700">
        この招待URLは使用できなくなります。
      </p></template
    ><template #footer
      ><div class="flex w-full justify-end gap-3">
        <UButton color="neutral" variant="outline" @click="revokeOpen = false"
          >キャンセル</UButton
        ><UButton color="error" :loading="isSaving" @click="revoke"
          >失効する</UButton
        >
      </div></template
    ></UModal
  >
</template>
