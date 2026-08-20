<script setup lang="ts">
import * as yup from 'yup'
import { toTypedSchema } from '@vee-validate/yup'

type InvitationPreview = {
  kindergarten_name: string
  child_name: string
  class_name: string
  label: string
  expires_at: string
}

type InvitationPreviewResult = {
  invitation: InvitationPreview | null
  errorMessage: string
}

type AcceptRegistrationResponse = {
  access_token: string
  token_type: string
  expires_in: number
  guardian?: {
    id: string
    name: string
    email: string
  }
}

useHead({
  title: '保護者招待',
})

const route = useRoute()
const authStore = useAuthStore()
const { $api } = useNuxtApp()
const { normalizeError } = useApiError()
const { fetchChildren } = useGuardianAuth()

const token = computed(() => {
  return typeof route.params.token === 'string' ? route.params.token : ''
})

const { data: invitationPreview, pending: loadingPreview } = await useAsyncData<InvitationPreviewResult>(
  () => `guardian-invitation-preview-${token.value}`,
  async () => {
    if (token.value === '') {
      return {
        invitation: null,
        errorMessage: '招待トークンが見つかりません。',
      }
    }

    try {
      const invitation = await $api<InvitationPreview>(`/public/invitations/${token.value}`, {
        method: 'GET',
        skipAuthRetry: true,
      })

      return { invitation, errorMessage: '' }
    } catch (error) {
      const normalized = normalizeError(error)
      let errorMessage: string

      if (normalized.code === 'INVITATION_INVALID_OR_EXPIRED') {
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
const formattedExpiresAt = computed(() => {
  if (!invitation.value) {
    return ''
  }

  return new Date(invitation.value.expires_at).toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo' })
})

const sessionChecking = computed(() => !authStore.guardianSessionRestored)
const isLoggedIn = computed(() => authStore.isGuardianAuthenticated)

// 新規登録フロー(未ログイン)
const registrationSucceeded = ref(false)
const registerErrorMessage = ref('')

const validationSchema = yup.object({
  name: yup
    .string()
    .trim()
    .label('お名前')
    .required()
    .max(255),
  email: yup
    .string()
    .trim()
    .label('メールアドレス')
    .required()
    .email()
    .max(255),
  password: yup
    .string()
    .label('パスワード')
    .required()
    .min(8)
    .max(72),
})

const { defineField, errors, handleSubmit, isSubmitting, setErrors } = useForm({
  validationSchema: toTypedSchema(validationSchema),
  initialValues: {
    name: '',
    email: '',
    password: '',
  },
})

const [name, nameAttrs] = defineField('name', {
  validateOnModelUpdate: false,
})
const [email, emailAttrs] = defineField('email', {
  validateOnModelUpdate: false,
})
const [password, passwordAttrs] = defineField('password', {
  validateOnModelUpdate: false,
})
const showPassword = ref(false)

const canSubmitRegister = computed(() => {
  return invitation.value !== null && !isSubmitting.value
})

const submitRegister = handleSubmit(async (values) => {
  if (invitation.value === null) {
    return
  }

  registerErrorMessage.value = ''

  try {
    const response = await $api<AcceptRegistrationResponse>(`/public/invitations/${token.value}/accept`, {
      method: 'POST',
      body: {
        name: values.name.trim(),
        email: values.email.trim(),
        password: values.password,
      },
      skipAuthRetry: true,
      credentials: 'include',
    })

    authStore.setGuardianAccessToken(response.access_token)
    authStore.markGuardianSessionRestored()

    if (response.guardian) {
      authStore.setGuardianUser(response.guardian)
    }

    registrationSucceeded.value = true
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.code === 'VALIDATION_ERROR') {
      setErrors({
        name: normalized.fieldErrors.name?.[0],
        email: normalized.fieldErrors.email?.[0],
        password: normalized.fieldErrors.password?.[0],
      })
      registerErrorMessage.value = '入力内容を確認してください。'
    } else if (normalized.code === 'INVITATION_ALREADY_USED') {
      registerErrorMessage.value = 'この招待はすでに使用されています。'
    } else if (normalized.code === 'INVITATION_INVALID_OR_EXPIRED') {
      registerErrorMessage.value = 'この招待は無効または期限切れです。'
    } else if (normalized.code === 'GUARDIAN_EMAIL_ALREADY_EXISTS') {
      registerErrorMessage.value = 'このメールアドレスはすでに登録されていますが、パスワードが一致しません。ログインしてから招待を開いてください。'
    } else {
      registerErrorMessage.value = normalized.message
    }
  }
})

// 追加紐づけフロー(ログイン済み)
const isLinking = ref(false)
const linkErrorMessage = ref('')
const linkAlreadyExists = ref(false)

const confirmLink = async (): Promise<void> => {
  if (invitation.value === null) {
    return
  }

  isLinking.value = true
  linkErrorMessage.value = ''

  try {
    await $api(`/guardian/invitations/${token.value}/accept`, {
      method: 'POST',
    })

    await fetchChildren().catch(() => undefined)
    await navigateTo('/guardian')
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.code === 'INVITATION_ALREADY_USED') {
      linkErrorMessage.value = 'この招待はすでに使用されています。'
    } else if (normalized.code === 'GUARDIAN_CHILD_LINK_ALREADY_EXISTS') {
      linkErrorMessage.value = 'この園児はすでに紐づけ済みです。'
      linkAlreadyExists.value = true
    } else if (normalized.code === 'INVITATION_INVALID_OR_EXPIRED') {
      linkErrorMessage.value = 'この招待は無効または期限切れです。'
    } else {
      linkErrorMessage.value = normalized.message
    }
  } finally {
    isLinking.value = false
  }
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-emerald-50 px-4 py-10">
    <UCard class="w-full max-w-lg border border-emerald-200 bg-white shadow-sm">
      <template #header>
        <div class="space-y-2">
          <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-700">Guardian Portal</p>
          <h1 class="text-2xl font-semibold tracking-tight text-slate-900">保護者招待</h1>
        </div>
      </template>

      <div class="space-y-6">
        <UAlert v-if="loadingPreview" color="neutral" variant="soft" title="招待内容を確認しています。" />

        <UAlert v-else-if="!invitation" color="error" variant="soft" :title="previewErrorMessage" />

        <template v-else>
          <div class="grid gap-3 rounded-lg border border-emerald-100 bg-emerald-50/60 p-4">
            <div class="grid gap-1">
              <span class="text-xs text-slate-500">園名</span>
              <strong class="break-words text-sm text-slate-900">{{ invitation.kindergarten_name }}</strong>
            </div>
            <div class="grid gap-1">
              <span class="text-xs text-slate-500">園児</span>
              <strong class="break-words text-sm text-slate-900">{{ invitation.child_name }}（{{ invitation.class_name }}）</strong>
            </div>
            <div class="grid gap-1">
              <span class="text-xs text-slate-500">招待名</span>
              <strong class="break-words text-sm text-slate-900">{{ invitation.label }}</strong>
            </div>
            <div class="grid gap-1">
              <span class="text-xs text-slate-500">有効期限</span>
              <strong class="text-sm text-slate-900">{{ formattedExpiresAt }}</strong>
            </div>
          </div>

          <p v-if="sessionChecking" class="text-sm text-slate-500">
            ログイン状態を確認しています...
          </p>

          <div v-else-if="registrationSucceeded" class="space-y-5">
            <UAlert
              color="success"
              variant="soft"
              title="登録が完了しました。"
              description="ご登録のメールアドレスに確認メールを送信しました。メール内のリンクから確認を完了してください。"
            />
            <UButton size="lg" class="w-full justify-center" @click="navigateTo('/guardian')">
              園児一覧へ進む
            </UButton>
          </div>

          <div v-else-if="isLoggedIn" class="space-y-5">
            <p class="text-sm leading-6 text-slate-600">
              ログイン中のアカウントにこの園児を追加します。よろしいですか？
            </p>

            <UAlert v-if="linkErrorMessage" color="error" variant="soft" :title="linkErrorMessage" />

            <UButton
              v-if="linkAlreadyExists"
              size="lg"
              class="w-full justify-center"
              @click="navigateTo('/guardian')"
            >
              園児一覧へ進む
            </UButton>
            <UButton
              v-else
              size="lg"
              class="w-full justify-center"
              :loading="isLinking"
              @click="confirmLink"
            >
              この園児を追加する
            </UButton>
          </div>

          <form v-else class="space-y-5" @submit="submitRegister">
            <UFormField label="お名前" :error="errors.name">
              <UInput
                v-model="name"
                v-bind="nameAttrs"
                size="lg"
                placeholder="山田 花子"
                autocomplete="name"
              />
            </UFormField>

            <UFormField label="メールアドレス" :error="errors.email">
              <UInput
                v-model="email"
                v-bind="emailAttrs"
                type="email"
                size="lg"
                placeholder="guardian@example.com"
                autocomplete="email"
              />
            </UFormField>

            <UFormField label="パスワード" :error="errors.password" hint="8〜72文字で設定してください。">
              <UInput
                id="invite-register-password"
                v-model="password"
                v-bind="passwordAttrs"
                :type="showPassword ? 'text' : 'password'"
                size="lg"
                placeholder="8文字以上"
                autocomplete="new-password"
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
                    aria-controls="invite-register-password"
                    tabindex="-1"
                    @click="showPassword = !showPassword"
                  />
                </template>
              </UInput>
            </UFormField>

            <UAlert v-if="registerErrorMessage" color="error" variant="soft" :title="registerErrorMessage" />

            <UButton
              type="submit"
              size="lg"
              class="w-full justify-center"
              :loading="isSubmitting"
              :disabled="!canSubmitRegister"
            >
              {{ isSubmitting ? '登録中...' : '登録して園児を追加する' }}
            </UButton>

            <p class="text-center text-sm leading-6 text-slate-600">
              すでにアカウントをお持ちの方は
              <NuxtLink to="/guardian/login" class="font-medium text-emerald-700 underline">
                ログイン
              </NuxtLink>
              してください。
            </p>
          </form>
        </template>
      </div>
    </UCard>
  </main>
</template>
