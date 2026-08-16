<script setup lang="ts">
import * as yup from 'yup'
import { toTypedSchema } from '@vee-validate/yup'
import { computed, ref } from 'vue'

type InvitationPreview = {
  kindergarten_name: string
  invited_name: string
  invited_email: string
  role: 'owner' | 'staff'
  expires_at: string
}

type InvitationPreviewResult = {
  invitation: InvitationPreview | null
  errorMessage: string
}

type AcceptResponse = {
  access_token: string
  token_type: string
  expires_in: number
  staff: {
    id: number
    kindergarten_id: number
    role: 'owner' | 'staff'
  }
}

useHead({
  title: 'スタッフ招待受諾',
})

const route = useRoute()
const authStore = useAuthStore()
const { $api } = useNuxtApp()
const { normalizeError } = useApiError()

const submitErrorMessage = ref('')

const token = computed(() => {
  return typeof route.params.token === 'string' ? route.params.token : ''
})

const validationSchema = yup.object({
  password: yup
    .string()
    .label('パスワード')
    .required()
    .min(8)
    .max(72),
  password_confirmation: yup
    .string()
    .label('パスワード確認')
    .required()
    .oneOf([yup.ref('password')], 'パスワードと確認用パスワードが一致しません。'),
})

const { defineField, errors, handleSubmit, isSubmitting, setErrors } = useForm({
  validationSchema: toTypedSchema(validationSchema),
  initialValues: {
    password: '',
    password_confirmation: '',
  },
})

const [password, passwordAttrs] = defineField('password', {
  validateOnModelUpdate: false,
})
const [passwordConfirmation, passwordConfirmationAttrs] = defineField('password_confirmation', {
  validateOnModelUpdate: false,
})

const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

const { data: invitationPreview, pending: loadingPreview } = await useAsyncData<InvitationPreviewResult>(
  () => `staff-invitation-preview-${token.value}`,
  async () => {
    if (token.value === '') {
      return {
        invitation: null,
        errorMessage: '招待トークンが見つかりません。',
      }
    }

    try {
      const invitation = await $api<InvitationPreview>(`/public/staff-invitations/${token.value}`, {
        method: 'GET',
        skipAuthRetry: true,
      })

      return { invitation, errorMessage: '' }
    } catch (error) {
      const normalized = normalizeError(error)
      let errorMessage: string

      if (normalized.code === 'STAFF_INVITATION_INVALID_OR_EXPIRED') {
        errorMessage = 'この招待は無効または期限切れです。園に再発行を依頼してください。'
      } else if (normalized.status === 429) {
        errorMessage = '試行回数が多すぎます。時間をおいて再試行してください。'
      } else {
        errorMessage = normalized.message
      }

      return { invitation: null, errorMessage }
    }
  },
  {
    watch: [token],
    default: (): InvitationPreviewResult => ({ invitation: null, errorMessage: '' }),
  },
)

const invitation = computed(() => invitationPreview.value.invitation)
const previewErrorMessage = computed(() => invitationPreview.value.errorMessage)

const canSubmit = computed(() => {
  return token.value !== '' && invitation.value !== null && !loadingPreview.value && !isSubmitting.value
})

const submit = handleSubmit(async (values) => {
  if (token.value === '' || invitation.value === null) {
    return
  }

  submitErrorMessage.value = ''

  try {
    const response = await $api<AcceptResponse>(`/public/staff-invitations/${token.value}/accept`, {
      method: 'POST',
      body: {
        password: values.password,
        password_confirmation: values.password_confirmation,
      },
      skipAuthRetry: true,
    })

    authStore.setStaffAccessToken(response.access_token)
    authStore.markStaffSessionRestored()

    await navigateTo('/staff', { replace: true })
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.code === 'VALIDATION_ERROR') {
      setErrors({
        password: normalized.fieldErrors.password?.[0],
        password_confirmation: normalized.fieldErrors.password_confirmation?.[0],
      })
      submitErrorMessage.value = '入力内容を確認してください。'
    } else if (normalized.code === 'STAFF_INVITATION_ALREADY_ACCEPTED') {
      submitErrorMessage.value = 'この招待はすでに受諾済みです。'
    } else if (normalized.code === 'STAFF_INVITATION_INVALID_OR_EXPIRED') {
      submitErrorMessage.value = 'この招待は無効または期限切れです。'
    } else if (normalized.code === 'STAFF_EMAIL_ALREADY_EXISTS') {
      submitErrorMessage.value = 'このメールアドレスはすでに登録されています。'
    } else {
      submitErrorMessage.value = normalized.message
    }
  }
})

</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10">
    <div class="grid w-full max-w-5xl gap-6 lg:grid-cols-[1.1fr_0.9fr]">
      <UCard class="border border-slate-200 bg-white shadow-sm">
        <template #header>
          <div class="space-y-2">
            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Staff Invitation</p>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">招待を受諾して初回設定を完了します</h1>
          </div>
        </template>

        <div class="space-y-5">
          <p class="text-sm leading-6 text-slate-600">
            園から送られたスタッフ招待リンクを開いている状態です。パスワードを設定すると、そのままスタッフポータルへ進みます。
          </p>

          <div v-if="invitation" class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="grid gap-1">
              <span class="text-xs text-slate-500">園名</span>
              <strong class="break-words text-sm text-slate-900">{{ invitation.kindergarten_name }}</strong>
            </div>
            <div class="grid gap-1">
              <span class="text-xs text-slate-500">招待先</span>
              <strong class="break-words text-sm text-slate-900">{{ invitation.invited_name }}</strong>
            </div>
            <div class="grid gap-1">
              <span class="text-xs text-slate-500">メール</span>
              <strong class="break-words text-sm text-slate-900">{{ invitation.invited_email }}</strong>
            </div>
            <div class="grid gap-1">
              <span class="text-xs text-slate-500">ロール</span>
              <strong class="text-sm text-slate-900">{{ invitation.role === 'owner' ? 'owner' : 'staff' }}</strong>
            </div>
            <div class="grid gap-1">
              <span class="text-xs text-slate-500">有効期限</span>
              <strong class="text-sm text-slate-900">{{ new Date(invitation.expires_at).toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo' }) }}</strong>
            </div>
          </div>

          <UAlert
            v-else
            :color="previewErrorMessage ? 'error' : 'neutral'"
            variant="soft"
            :title="loadingPreview ? '招待内容を確認しています。' : previewErrorMessage"
          />
        </div>
      </UCard>

      <UCard class="border border-slate-200 bg-white shadow-sm">
        <template #header>
          <h2 class="text-lg font-semibold text-slate-900">パスワードを設定</h2>
        </template>

        <form class="space-y-5" @submit="submit">
          <UFormField label="新しいパスワード" :error="errors.password" hint="8〜72文字で設定してください。">
            <UInput
              id="staff-invitation-password"
              v-model="password"
              v-bind="passwordAttrs"
              :type="showPassword ? 'text' : 'password'"
              size="lg"
              autocomplete="new-password"
              placeholder="8文字以上"
              :ui="{ trailing: 'pe-1' }"
            >
              <template #trailing>
                <UButton
                  type="button"
                  color="neutral"
                  variant="link"
                  size="sm"
                  :icon="showPassword ? 'i-lucide-eye-off' : 'i-lucide-eye'"
                  :aria-label="showPassword ? 'パスワードを隠す' : 'パスワードを表示'"
                  :aria-pressed="showPassword"
                  aria-controls="staff-invitation-password"
                  tabindex="-1"
                  @click="showPassword = !showPassword"
                />
              </template>
            </UInput>
          </UFormField>

          <UFormField label="パスワード確認" :error="errors.password_confirmation">
            <UInput
              id="staff-invitation-password-confirmation"
              v-model="passwordConfirmation"
              v-bind="passwordConfirmationAttrs"
              :type="showPasswordConfirmation ? 'text' : 'password'"
              size="lg"
              autocomplete="new-password"
              placeholder="もう一度入力"
              :ui="{ trailing: 'pe-1' }"
            >
              <template #trailing>
                <UButton
                  type="button"
                  color="neutral"
                  variant="link"
                  size="sm"
                  :icon="showPasswordConfirmation ? 'i-lucide-eye-off' : 'i-lucide-eye'"
                  :aria-label="showPasswordConfirmation ? '確認用パスワードを隠す' : '確認用パスワードを表示'"
                  :aria-pressed="showPasswordConfirmation"
                  aria-controls="staff-invitation-password-confirmation"
                  tabindex="-1"
                  @click="showPasswordConfirmation = !showPasswordConfirmation"
                />
              </template>
            </UInput>
          </UFormField>

          <UAlert v-if="submitErrorMessage" color="error" variant="soft" :title="submitErrorMessage" />

          <UButton
            type="submit"
            size="lg"
            class="w-full justify-center enabled:cursor-pointer"
            :loading="isSubmitting"
            :disabled="!canSubmit"
          >
            {{ isSubmitting ? '受諾中...' : '招待を受諾して開始する' }}
          </UButton>
        </form>
      </UCard>
    </div>
  </main>
</template>