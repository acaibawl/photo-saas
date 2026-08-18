<script setup lang="ts">
definePageMeta({
  middleware: ['staff-auth'],
})

type StaffRole = 'owner' | 'staff'
type StaffStatus = 'active' | 'inactive'
type InvitationStatus = 'pending' | 'accepted' | 'revoked' | 'expired'

type StaffMember = {
  staff_id: string
  name: string
  email: string
  role: StaffRole
  status: StaffStatus
  last_login_at: string | null
  invited_at: string | null
  joined_at: string | null
}

type StaffInvitationItem = {
  invitation_id: string
  name: string
  email: string
  role: StaffRole
  status: InvitationStatus
  expires_at: string | null
  accepted_at: string | null
}

type PageResponse<T> = {
  data: T[]
  meta: { current_page: number, total: number }
}

const PER_PAGE = 20

const authStore = useAuthStore()
const { $api } = useNuxtApp()
const { normalizeError } = useApiError()
const { logout, fetchMe } = useStaffAuth()

const statusOptions = [
  { label: 'すべて', value: '' },
  { label: '有効', value: 'active' },
  { label: '停止', value: 'inactive' },
]
const roleFilterOptions = [
  { label: 'すべて', value: '' },
  { label: 'オーナー', value: 'owner' },
  { label: 'スタッフ', value: 'staff' },
]
const roleSelectOptions = [
  { label: 'オーナー', value: 'owner' },
  { label: 'スタッフ', value: 'staff' },
]
const invitationStatusOptions = [
  { label: 'すべて', value: '' },
  { label: '保留中', value: 'pending' },
  { label: '承諾済み', value: 'accepted' },
  { label: '失効', value: 'revoked' },
  { label: '期限切れ', value: 'expired' },
]
const tabItems = [
  { label: 'スタッフ一覧', value: 'members' },
  { label: '招待一覧', value: 'invitations' },
]

const activeTab = ref('members')
const isCheckingAccess = ref(true)
const accessDenied = ref(false)

const members = ref<StaffMember[]>([])
const memberFilters = reactive({ status: '', role: '', keyword: '' })
const memberPage = ref(1)
const memberTotal = ref(0)
const isMembersLoading = ref(true)
const membersError = ref('')
const roleActionError = ref('')
const roleUpdatingId = ref('')
const statusUpdatingId = ref('')

const invitations = ref<StaffInvitationItem[]>([])
const invitationFilters = reactive({ status: '' })
const invitationPage = ref(1)
const invitationTotal = ref(0)
const isInvitationsLoading = ref(true)
const invitationsError = ref('')
const isRevoking = ref(false)
const revokeTarget = ref<StaffInvitationItem | null>(null)

const isInviteModalOpen = ref(false)
const isInviting = ref(false)
const inviteForm = reactive({ name: '', email: '', expires_in_days: '' })
const inviteFormError = ref('')
const inviteFieldErrors = reactive<Record<string, string>>({ name: '', email: '', expires_in_days: '' })

const hasMemberPagination = computed(() => memberTotal.value > PER_PAGE)
const hasInvitationPagination = computed(() => invitationTotal.value > PER_PAGE)
const isRevokeModalOpen = computed({
  get: () => revokeTarget.value !== null,
  set: (value: boolean) => {
    if (!value) {
      revokeTarget.value = null
    }
  },
})

function isSelf(staffId: string): boolean {
  return authStore.staffUser !== null && String(authStore.staffUser.id) === staffId
}

function memberStatusLabel(status: StaffStatus): string {
  return status === 'active' ? '有効' : '停止'
}

function invitationStatusLabel(status: InvitationStatus): string {
  return invitationStatusOptions.find(option => option.value === status)?.label ?? status
}

function invitationStatusColor(status: InvitationStatus): 'success' | 'error' | 'warning' | 'neutral' {
  if (status === 'accepted') return 'success'
  if (status === 'revoked') return 'error'
  if (status === 'expired') return 'warning'
  return 'neutral'
}

async function unauthorized(): Promise<void> {
  await logout().catch(() => undefined)
  await navigateTo('/staff/login')
}

async function checkAccess(): Promise<boolean> {
  try {
    const profile = await fetchMe()

    if (profile.role !== 'owner') {
      await navigateTo('/staff')
      return false
    }

    return true
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.status === 401) {
      await unauthorized()
      return false
    }

    accessDenied.value = true
    return false
  }
}

async function loadMembers(): Promise<void> {
  isMembersLoading.value = true
  membersError.value = ''

  try {
    const response = await $api<PageResponse<StaffMember>>('/staff/staff-members', {
      query: {
        page: memberPage.value,
        per_page: PER_PAGE,
        status: memberFilters.status || undefined,
        role: memberFilters.role || undefined,
        keyword: memberFilters.keyword || undefined,
      },
    })
    members.value = response.data
    memberPage.value = response.meta.current_page
    memberTotal.value = response.meta.total
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.status === 401) {
      await unauthorized()
      return
    }

    if (normalized.code === 'STAFF_ROLE_FORBIDDEN') {
      await navigateTo('/staff')
      return
    }

    membersError.value = normalized.message
  } finally {
    isMembersLoading.value = false
  }
}

async function loadInvitations(): Promise<void> {
  isInvitationsLoading.value = true
  invitationsError.value = ''

  try {
    const response = await $api<PageResponse<StaffInvitationItem>>('/staff/staff-invitations', {
      query: {
        page: invitationPage.value,
        per_page: PER_PAGE,
        status: invitationFilters.status || undefined,
      },
    })
    invitations.value = response.data
    invitationPage.value = response.meta.current_page
    invitationTotal.value = response.meta.total
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.status === 401) {
      await unauthorized()
      return
    }

    if (normalized.code === 'STAFF_ROLE_FORBIDDEN') {
      await navigateTo('/staff')
      return
    }

    invitationsError.value = normalized.message
  } finally {
    isInvitationsLoading.value = false
  }
}

async function initialize(): Promise<void> {
  isCheckingAccess.value = true
  accessDenied.value = false

  const canAccess = await checkAccess()
  isCheckingAccess.value = false

  if (!canAccess) {
    return
  }

  await Promise.all([loadMembers(), loadInvitations()])
}

function applyMemberFilters(): void {
  memberPage.value = 1
  void loadMembers()
}

function goToMemberPage(page: number): void {
  memberPage.value = page
  void loadMembers()
}

function applyInvitationFilters(): void {
  invitationPage.value = 1
  void loadInvitations()
}

function goToInvitationPage(page: number): void {
  invitationPage.value = page
  void loadInvitations()
}

async function changeRole(member: StaffMember, role: StaffRole): Promise<void> {
  if (member.role === role || isSelf(member.staff_id)) {
    return
  }

  roleActionError.value = ''
  roleUpdatingId.value = member.staff_id

  try {
    await $api(`/staff/staff-members/${member.staff_id}/role`, {
      method: 'PATCH',
      body: { role },
    })
    await loadMembers()
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.status === 401) {
      await unauthorized()
      return
    }

    if (normalized.code === 'STAFF_ROLE_CHANGE_SELF_FORBIDDEN') {
      roleActionError.value = '自分自身のロールは変更できません。'
    } else if (normalized.code === 'OWNER_MINIMUM_REQUIRED') {
      roleActionError.value = 'オーナーは最低1名必要なため変更できません。'
    } else if (normalized.code === 'STAFF_MEMBER_NOT_FOUND') {
      roleActionError.value = '対象のスタッフが見つかりません。'
      await loadMembers()
    } else {
      roleActionError.value = normalized.message
    }
  } finally {
    roleUpdatingId.value = ''
  }
}

async function toggleActive(member: StaffMember): Promise<void> {
  roleActionError.value = ''
  statusUpdatingId.value = member.staff_id

  const action = member.status === 'active' ? 'deactivate' : 'reactivate'

  try {
    await $api(`/staff/staff-members/${member.staff_id}/${action}`, { method: 'POST' })
    await loadMembers()
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.status === 401) {
      await unauthorized()
      return
    }

    if (normalized.code === 'STAFF_DEACTIVATE_SELF_FORBIDDEN') {
      roleActionError.value = '自分自身を停止することはできません。'
    } else if (normalized.code === 'OWNER_MINIMUM_REQUIRED') {
      roleActionError.value = 'オーナーは最低1名必要なため停止できません。'
    } else if (normalized.code === 'STAFF_MEMBER_NOT_FOUND') {
      roleActionError.value = '対象のスタッフが見つかりません。'
      await loadMembers()
    } else {
      roleActionError.value = normalized.message
    }
  } finally {
    statusUpdatingId.value = ''
  }
}

function openInviteModal(): void {
  inviteForm.name = ''
  inviteForm.email = ''
  inviteForm.expires_in_days = ''
  inviteFormError.value = ''
  Object.keys(inviteFieldErrors).forEach((key) => { inviteFieldErrors[key] = '' })
  isInviteModalOpen.value = true
}

function closeInviteModal(): void {
  isInviteModalOpen.value = false
}

async function submitInvite(): Promise<void> {
  inviteFormError.value = ''
  Object.keys(inviteFieldErrors).forEach((key) => { inviteFieldErrors[key] = '' })

  if (!inviteForm.name.trim()) {
    inviteFieldErrors.name = '氏名を入力してください。'
  }

  if (!inviteForm.email.trim()) {
    inviteFieldErrors.email = 'メールアドレスを入力してください。'
  }

  if (Object.values(inviteFieldErrors).some(Boolean)) {
    return
  }

  isInviting.value = true

  try {
    const body: Record<string, unknown> = {
      name: inviteForm.name.trim(),
      email: inviteForm.email.trim(),
      role: 'staff',
    }

    if (inviteForm.expires_in_days !== '') {
      body.expires_in_days = Number(inviteForm.expires_in_days)
    }

    await $api('/staff/staff-invitations', { method: 'POST', body })
    isInviteModalOpen.value = false
    invitationPage.value = 1
    activeTab.value = 'invitations'
    await loadInvitations()
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.status === 401) {
      await unauthorized()
      return
    }

    if (normalized.code === 'STAFF_INVITATION_ALREADY_EXISTS') {
      inviteFormError.value = 'このメールアドレス宛の招待はすでに送信されています。'
    } else if (normalized.code === 'STAFF_EMAIL_ALREADY_EXISTS') {
      inviteFormError.value = 'このメールアドレスはすでに登録されています。'
    } else if (normalized.code === 'VALIDATION_ERROR') {
      Object.entries(normalized.fieldErrors).forEach(([key, messages]) => {
        if (key in inviteFieldErrors) {
          inviteFieldErrors[key] = messages[0] ?? ''
        }
      })
      inviteFormError.value = normalized.message
    } else {
      inviteFormError.value = normalized.message
    }
  } finally {
    isInviting.value = false
  }
}

function openRevokeModal(invitation: StaffInvitationItem): void {
  revokeTarget.value = invitation
}

function closeRevokeModal(): void {
  revokeTarget.value = null
}

async function revokeInvitation(): Promise<void> {
  if (!revokeTarget.value) {
    return
  }

  isRevoking.value = true
  invitationsError.value = ''

  try {
    await $api(`/staff/staff-invitations/${revokeTarget.value.invitation_id}/revoke`, { method: 'POST' })
    revokeTarget.value = null
    await loadInvitations()
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.status === 401) {
      await unauthorized()
      return
    }

    if (normalized.code === 'STAFF_INVITATION_ALREADY_ACCEPTED') {
      invitationsError.value = 'この招待はすでに承諾されています。'
    } else if (normalized.code === 'STAFF_INVITATION_NOT_FOUND') {
      invitationsError.value = '対象の招待が見つかりません。'
    } else {
      invitationsError.value = normalized.message
    }

    revokeTarget.value = null
  } finally {
    isRevoking.value = false
  }
}

onMounted(initialize)
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-6xl space-y-8">
      <header class="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-2">
          <NuxtLink to="/staff" class="inline-flex items-center gap-1 text-sm font-medium text-sky-700 hover:text-sky-900">
            <UIcon name="i-lucide-arrow-left" class="size-4" /> ダッシュボード
          </NuxtLink>
          <h1 class="text-3xl font-semibold text-slate-900">スタッフ管理</h1>
          <p class="text-sm text-slate-600">スタッフの招待、ロール変更、有効/停止を管理します。</p>
        </div>
        <UButton v-if="!isCheckingAccess && !accessDenied" icon="i-lucide-user-plus" @click="openInviteModal">
          スタッフを招待
        </UButton>
      </header>

      <section v-if="isCheckingAccess" class="space-y-3" aria-label="読み込み中">
        <div v-for="index in 4" :key="index" class="h-12 animate-pulse rounded bg-slate-100" />
      </section>

      <UAlert v-else-if="accessDenied" color="error" variant="soft" title="スタッフ管理情報を読み込めませんでした。">
        <template #actions>
          <UButton color="error" variant="ghost" size="sm" @click="initialize">再読み込み</UButton>
        </template>
      </UAlert>

      <template v-else>
        <UTabs v-model="activeTab" :items="tabItems" :content="false" />

        <div v-if="activeTab === 'members'" class="space-y-6 pt-4">
          <UCard class="border border-slate-200 shadow-sm">
            <template #header>
              <h2 class="text-lg font-semibold text-slate-900">絞り込み</h2>
            </template>
            <form class="grid gap-4 md:grid-cols-[1fr_1fr_1.5fr_auto] md:items-end" @submit.prevent="applyMemberFilters">
              <UFormField label="状態">
                <USelect v-model="memberFilters.status" :items="statusOptions.filter((option) => option.value)" value-key="value" placeholder="すべて" />
              </UFormField>
              <UFormField label="ロール">
                <USelect v-model="memberFilters.role" :items="roleFilterOptions.filter((option) => option.value)" value-key="value" placeholder="すべて" />
              </UFormField>
              <UFormField label="キーワード">
                <UInput v-model="memberFilters.keyword" placeholder="氏名・メールアドレス" icon="i-lucide-search" />
              </UFormField>
              <UButton type="submit" color="primary" icon="i-lucide-list-filter">絞り込む</UButton>
            </form>
          </UCard>

          <UAlert v-if="membersError" color="error" variant="soft" :title="membersError">
            <template #actions>
              <UButton color="error" variant="ghost" size="sm" @click="loadMembers">再読み込み</UButton>
            </template>
          </UAlert>
          <UAlert v-if="roleActionError" color="error" variant="soft" :title="roleActionError" />

          <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
              <h2 class="text-lg font-semibold text-slate-900">スタッフ一覧</h2>
              <span v-if="!isMembersLoading" class="text-sm text-slate-500">{{ memberTotal }}件</span>
            </div>

            <div v-if="isMembersLoading" class="space-y-3 p-5" aria-label="読み込み中">
              <div v-for="index in 4" :key="index" class="h-12 animate-pulse rounded bg-slate-100" />
            </div>

            <div v-else-if="!members.length" class="px-5 py-14 text-center">
              <UIcon name="i-lucide-users-round" class="mx-auto size-8 text-slate-400" />
              <p class="mt-3 font-medium text-slate-700">該当するスタッフがいません。</p>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-slate-200">
                <thead>
                  <tr>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">氏名 / メール</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">ロール</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">状態</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">最終ログイン</th>
                    <th class="px-3 py-3" />
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="member in members" :key="member.staff_id">
                    <td class="px-3 py-3">
                      <p class="font-medium text-slate-900">
                        {{ member.name }}
                        <span v-if="isSelf(member.staff_id)" class="ml-1 text-xs text-slate-500">(自分)</span>
                      </p>
                      <p class="text-xs text-slate-500">{{ member.email }}</p>
                    </td>
                    <td class="px-3 py-3">
                      <USelect
                        :model-value="member.role"
                        :items="roleSelectOptions"
                        value-key="value"
                        class="w-32"
                        :disabled="isSelf(member.staff_id) || roleUpdatingId === member.staff_id"
                        @update:model-value="(value) => changeRole(member, value as StaffRole)"
                      />
                    </td>
                    <td class="px-3 py-3">
                      <UBadge :color="member.status === 'active' ? 'success' : 'neutral'" variant="subtle">
                        {{ memberStatusLabel(member.status) }}
                      </UBadge>
                    </td>
                    <td class="px-3 py-3 text-sm text-slate-500">
                      {{ member.last_login_at ? new Date(member.last_login_at).toLocaleString('ja-JP') : '未ログイン' }}
                    </td>
                    <td class="px-3 py-3 text-right">
                      <UButton
                        v-if="!isSelf(member.staff_id)"
                        :color="member.status === 'active' ? 'error' : 'neutral'"
                        variant="ghost"
                        size="sm"
                        :loading="statusUpdatingId === member.staff_id"
                        @click="toggleActive(member)"
                      >
                        {{ member.status === 'active' ? '停止' : '再開' }}
                      </UButton>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="!isMembersLoading && hasMemberPagination" class="flex justify-center border-t border-slate-200 px-5 py-4">
              <UPagination :page="memberPage" :items-per-page="PER_PAGE" :total="memberTotal" @update:page="goToMemberPage" />
            </div>
          </section>
        </div>

        <div v-else class="space-y-6 pt-4">
          <UCard class="border border-slate-200 shadow-sm">
            <template #header>
              <h2 class="text-lg font-semibold text-slate-900">絞り込み</h2>
            </template>
            <form class="grid gap-4 md:grid-cols-[1fr_auto] md:items-end" @submit.prevent="applyInvitationFilters">
              <UFormField label="状態">
                <USelect v-model="invitationFilters.status" :items="invitationStatusOptions.filter((option) => option.value)" value-key="value" placeholder="すべて" />
              </UFormField>
              <UButton type="submit" color="primary" icon="i-lucide-list-filter">絞り込む</UButton>
            </form>
          </UCard>

          <UAlert v-if="invitationsError" color="error" variant="soft" :title="invitationsError">
            <template #actions>
              <UButton color="error" variant="ghost" size="sm" @click="loadInvitations">再読み込み</UButton>
            </template>
          </UAlert>

          <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
              <h2 class="text-lg font-semibold text-slate-900">招待一覧</h2>
              <span v-if="!isInvitationsLoading" class="text-sm text-slate-500">{{ invitationTotal }}件</span>
            </div>

            <div v-if="isInvitationsLoading" class="space-y-3 p-5" aria-label="読み込み中">
              <div v-for="index in 4" :key="index" class="h-12 animate-pulse rounded bg-slate-100" />
            </div>

            <div v-else-if="!invitations.length" class="px-5 py-14 text-center">
              <UIcon name="i-lucide-mail" class="mx-auto size-8 text-slate-400" />
              <p class="mt-3 font-medium text-slate-700">該当する招待がありません。</p>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-slate-200">
                <thead>
                  <tr>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">氏名 / メール</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">状態</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500">有効期限</th>
                    <th class="px-3 py-3" />
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="invitation in invitations" :key="invitation.invitation_id">
                    <td class="px-3 py-3">
                      <p class="font-medium text-slate-900">{{ invitation.name }}</p>
                      <p class="text-xs text-slate-500">{{ invitation.email }}</p>
                    </td>
                    <td class="px-3 py-3">
                      <UBadge :color="invitationStatusColor(invitation.status)" variant="subtle">
                        {{ invitationStatusLabel(invitation.status) }}
                      </UBadge>
                    </td>
                    <td class="px-3 py-3 text-sm text-slate-500">
                      {{ invitation.expires_at ? new Date(invitation.expires_at).toLocaleDateString('ja-JP') : '-' }}
                    </td>
                    <td class="px-3 py-3 text-right">
                      <UButton
                        v-if="invitation.status === 'pending'"
                        color="error"
                        variant="ghost"
                        size="sm"
                        @click="openRevokeModal(invitation)"
                      >
                        失効
                      </UButton>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="!isInvitationsLoading && hasInvitationPagination" class="flex justify-center border-t border-slate-200 px-5 py-4">
              <UPagination :page="invitationPage" :items-per-page="PER_PAGE" :total="invitationTotal" @update:page="goToInvitationPage" />
            </div>
          </section>
        </div>
      </template>
    </div>

    <UModal
      v-model:open="isInviteModalOpen"
      title="スタッフを招待"
      description="招待メールが指定したアドレスに送信されます。"
      :dismissible="!isInviting"
      :close="{ disabled: isInviting }"
    >
      <template #body>
        <form id="invite-staff-form" class="space-y-4" @submit.prevent="submitInvite">
          <UFormField label="氏名" :error="inviteFieldErrors.name || undefined">
            <UInput v-model="inviteForm.name" placeholder="例: 山田 太郎" :disabled="isInviting" autofocus />
          </UFormField>
          <UFormField label="メールアドレス" :error="inviteFieldErrors.email || undefined">
            <UInput v-model="inviteForm.email" type="email" placeholder="staff@example.com" :disabled="isInviting" />
          </UFormField>
          <UFormField label="有効期限（日数、任意）" :error="inviteFieldErrors.expires_in_days || undefined">
            <UInput v-model="inviteForm.expires_in_days" type="number" min="1" max="30" placeholder="未入力の場合は7日" :disabled="isInviting" />
          </UFormField>
          <UAlert v-if="inviteFormError" color="error" variant="soft" :title="inviteFormError" />
        </form>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-3">
          <UButton color="neutral" variant="outline" :disabled="isInviting" @click="closeInviteModal">キャンセル</UButton>
          <UButton type="submit" form="invite-staff-form" :loading="isInviting">招待を送信</UButton>
        </div>
      </template>
    </UModal>

    <UModal
      v-model:open="isRevokeModalOpen"
      title="招待を失効しますか？"
      :dismissible="!isRevoking"
      :close="{ disabled: isRevoking }"
    >
      <template #body>
        <p class="text-sm leading-6 text-slate-700">「{{ revokeTarget?.name }}」宛の招待URLは使用できなくなります。</p>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-3">
          <UButton color="neutral" variant="outline" :disabled="isRevoking" @click="closeRevokeModal">キャンセル</UButton>
          <UButton color="error" :loading="isRevoking" @click="revokeInvitation">失効する</UButton>
        </div>
      </template>
    </UModal>
  </main>
</template>
