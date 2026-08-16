<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'

type InvitationPreview = {
  kindergarten_name: string
  invited_name: string
  invited_email: string
  role: 'owner' | 'staff'
  expires_at: string
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
const { fetchMe } = useStaffAuth()
const { normalizeError } = useApiError()

const invitation = ref<InvitationPreview | null>(null)
const loadingPreview = ref(false)
const previewErrorMessage = ref('')

const form = reactive({
  password: '',
  password_confirmation: '',
})

const fieldErrors = reactive({
  password: '',
  password_confirmation: '',
})

const submitting = ref(false)
const submitErrorMessage = ref('')

const token = computed(() => {
  return typeof route.params.token === 'string' ? route.params.token : ''
})

const canSubmit = computed(() => {
  return (
    token.value !== '' &&
    invitation.value !== null &&
    !loadingPreview.value &&
    !submitting.value &&
    form.password.trim() !== '' &&
    form.password_confirmation.trim() !== ''
  )
})

function clearFieldErrors(): void {
  fieldErrors.password = ''
  fieldErrors.password_confirmation = ''
}

function validateForm(): boolean {
  clearFieldErrors()

  if (form.password.length < 8 || form.password.length > 72) {
    fieldErrors.password = 'パスワードは8〜72文字で入力してください。'
  }

  if (form.password_confirmation.trim() === '') {
    fieldErrors.password_confirmation = '確認用パスワードを入力してください。'
  } else if (form.password !== form.password_confirmation) {
    fieldErrors.password_confirmation = 'パスワードが一致しません。'
  }

  return fieldErrors.password === '' && fieldErrors.password_confirmation === ''
}

async function loadPreview(): Promise<void> {
  if (token.value === '') {
    invitation.value = null
    previewErrorMessage.value = '招待トークンが見つかりません。'
    return
  }

  loadingPreview.value = true
  previewErrorMessage.value = ''

  try {
    invitation.value = await $api<InvitationPreview>(`/public/staff-invitations/${token.value}`, {
      method: 'GET',
      skipAuthRetry: true,
    })
  } catch (error) {
    invitation.value = null

    const normalized = normalizeError(error)
    if (normalized.code === 'STAFF_INVITATION_INVALID_OR_EXPIRED') {
      previewErrorMessage.value = 'この招待は無効または期限切れです。園に再発行を依頼してください。'
    } else if (normalized.status === 429) {
      previewErrorMessage.value = '試行回数が多すぎます。時間をおいて再試行してください。'
    } else {
      previewErrorMessage.value = normalized.message
    }
  } finally {
    loadingPreview.value = false
  }
}

async function submit(): Promise<void> {
  if (!validateForm() || token.value === '' || invitation.value === null) {
    return
  }

  submitting.value = true
  submitErrorMessage.value = ''

  try {
    const response = await $api<AcceptResponse>(`/public/staff-invitations/${token.value}/accept`, {
      method: 'POST',
      body: {
        password: form.password,
        password_confirmation: form.password_confirmation,
      },
      skipAuthRetry: true,
    })

    authStore.setStaffAccessToken(response.access_token)
    authStore.markStaffSessionRestored()

    await fetchMe()
    await navigateTo('/staff', { replace: true })
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.code === 'VALIDATION_ERROR') {
      fieldErrors.password = normalized.fieldErrors.password?.[0] ?? fieldErrors.password
      fieldErrors.password_confirmation =
        normalized.fieldErrors.password_confirmation?.[0] ?? fieldErrors.password_confirmation
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
  } finally {
    submitting.value = false
  }
}

watch(token, () => {
  void loadPreview()
}, { immediate: true })
</script>

<template>
  <main class="invite-page">
    <section class="invite-shell">
      <div class="hero-panel">
        <p class="eyebrow">Staff Invitation</p>
        <h1>招待を受諾して初回設定を完了します</h1>
        <p class="lead">
          園から送られたスタッフ招待リンクを開いている状態です。パスワードを設定すると、そのままスタッフポータルへ進みます。
        </p>

        <div v-if="invitation" class="preview-card">
          <div class="preview-row">
            <span>園名</span>
            <strong>{{ invitation.kindergarten_name }}</strong>
          </div>
          <div class="preview-row">
            <span>招待先</span>
            <strong>{{ invitation.invited_name }}</strong>
          </div>
          <div class="preview-row">
            <span>メール</span>
            <strong>{{ invitation.invited_email }}</strong>
          </div>
          <div class="preview-row">
            <span>ロール</span>
            <strong>{{ invitation.role === 'owner' ? 'owner' : 'staff' }}</strong>
          </div>
          <div class="preview-row">
            <span>有効期限</span>
            <strong>{{ new Date(invitation.expires_at).toLocaleString('ja-JP') }}</strong>
          </div>
        </div>

        <p v-else class="helper-text">
          {{ loadingPreview ? '招待内容を確認しています。' : previewErrorMessage }}
        </p>
      </div>

      <div class="form-panel">
        <div v-if="previewErrorMessage && !invitation" class="error-banner">
          {{ previewErrorMessage }}
        </div>

        <form class="form" @submit.prevent="submit">
          <label class="field">
            <span>新しいパスワード</span>
            <input
              v-model="form.password"
              type="password"
              autocomplete="new-password"
              placeholder="8文字以上"
            >
            <small class="hint">8〜72文字で設定してください。</small>
            <small v-if="fieldErrors.password" class="field-error">{{ fieldErrors.password }}</small>
          </label>

          <label class="field">
            <span>パスワード確認</span>
            <input
              v-model="form.password_confirmation"
              type="password"
              autocomplete="new-password"
              placeholder="もう一度入力"
            >
            <small v-if="fieldErrors.password_confirmation" class="field-error">{{ fieldErrors.password_confirmation }}</small>
          </label>

          <p v-if="submitErrorMessage" class="error-banner">
            {{ submitErrorMessage }}
          </p>

          <button type="submit" :disabled="!canSubmit">
            {{ submitting ? '受諾中...' : '招待を受諾して開始する' }}
          </button>
        </form>
      </div>
    </section>
  </main>
</template>

<style scoped>
.invite-page {
  min-height: 100dvh;
  padding: 1.25rem;
  display: grid;
  place-items: center;
  background:
    radial-gradient(circle at 12% 20%, #ffd9ad 0%, transparent 34%),
    radial-gradient(circle at 88% 18%, #b9dcff 0%, transparent 30%),
    radial-gradient(circle at 70% 86%, #d3f0e4 0%, transparent 28%),
    linear-gradient(145deg, #f5efe2 0%, #eef5ff 100%);
}

.invite-shell {
  width: min(1040px, 100%);
  display: grid;
  grid-template-columns: 1.1fr 0.9fr;
  gap: 1rem;
}

.hero-panel,
.form-panel {
  border: 1px solid #cbd8e6;
  background: #fffffff0;
  backdrop-filter: blur(10px);
  box-shadow: 0 20px 48px #1d3c5420;
  border-radius: 22px;
  padding: 1.4rem;
}

.eyebrow {
  margin: 0;
  font-size: 0.76rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #55708a;
}

h1 {
  margin: 0.4rem 0 0;
  color: #17314a;
  font-size: clamp(1.5rem, 3vw, 2.2rem);
  line-height: 1.2;
}

.lead {
  margin: 0.85rem 0 0;
  color: #405b74;
  line-height: 1.7;
}

.preview-card {
  margin-top: 1.2rem;
  display: grid;
  gap: 0.7rem;
  padding: 1rem;
  border-radius: 18px;
  border: 1px solid #d7e2ec;
  background: linear-gradient(180deg, #f9fcff 0%, #eef6ff 100%);
}

.preview-row {
  display: grid;
  gap: 0.25rem;
}

.preview-row span {
  font-size: 0.82rem;
  color: #5f7488;
}

.preview-row strong {
  color: #18334b;
  font-size: 1rem;
  word-break: break-word;
}

.helper-text {
  margin: 1rem 0 0;
  color: #4f6679;
  line-height: 1.7;
}

.form {
  display: grid;
  gap: 1rem;
}

.field {
  display: grid;
  gap: 0.4rem;
}

.field span {
  color: #2d4760;
  font-size: 0.92rem;
}

input {
  border: 1px solid #a9bdd0;
  border-radius: 12px;
  padding: 0.72rem 0.82rem;
  font-size: 1rem;
  background: #fbfdff;
}

input:focus {
  outline: 2px solid #5690c4;
  outline-offset: 1px;
}

.hint {
  color: #637a8d;
  font-size: 0.8rem;
}

.field-error {
  color: #a51f2d;
  font-size: 0.82rem;
}

.error-banner {
  margin: 0;
  border: 1px solid #e3b4b4;
  background: #fff2f2;
  color: #97232b;
  border-radius: 12px;
  padding: 0.72rem 0.82rem;
  line-height: 1.6;
}

button {
  border: 0;
  border-radius: 12px;
  padding: 0.82rem 1rem;
  font-weight: 700;
  color: #ffffff;
  background: linear-gradient(135deg, #0c5d93 0%, #1f80ba 100%);
  cursor: pointer;
}

button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 860px) {
  .invite-shell {
    grid-template-columns: 1fr;
  }
}
</style>