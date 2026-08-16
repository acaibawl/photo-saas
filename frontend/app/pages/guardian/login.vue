<script setup lang="ts">
import { computed, reactive, ref } from 'vue'

definePageMeta({
  middleware: ['guardian-guest'],
})

const form = reactive({
  email: '',
  password: '',
})

const fieldErrors = reactive({
  email: '',
  password: '',
})

const submitting = ref(false)
const errorMessage = ref('')

const { login, fetchChildren } = useGuardianAuth()
const { normalizeError } = useApiError()

const canSubmit = computed(() => {
  return form.email.trim() !== '' && form.password.trim() !== '' && !submitting.value
})

function validate(): boolean {
  fieldErrors.email = ''
  fieldErrors.password = ''

  const email = form.email.trim()
  const password = form.password

  if (email === '') {
    fieldErrors.email = 'メールアドレスを入力してください。'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    fieldErrors.email = 'メールアドレスの形式が不正です。'
  }

  if (password.length < 8 || password.length > 72) {
    fieldErrors.password = 'パスワードは8〜72文字で入力してください。'
  }

  return fieldErrors.email === '' && fieldErrors.password === ''
}

async function submit(): Promise<void> {
  if (!validate()) {
    return
  }

  submitting.value = true
  errorMessage.value = ''

  try {
    await login(form.email.trim(), form.password)
    await fetchChildren()
    await navigateTo('/guardian')
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.code === 'GUARDIAN_AUTH_INVALID_CREDENTIALS') {
      errorMessage.value = 'メールアドレスまたはパスワードが正しくありません。'
    } else if (normalized.code === 'GUARDIAN_AUTH_RATE_LIMITED') {
      errorMessage.value = '試行回数が多すぎます。時間をおいて再試行してください。'
    } else if (normalized.code === 'VALIDATION_ERROR') {
      fieldErrors.email = normalized.fieldErrors.email?.[0] ?? fieldErrors.email
      fieldErrors.password = normalized.fieldErrors.password?.[0] ?? fieldErrors.password
      errorMessage.value = '入力内容を確認してください。'
    } else {
      errorMessage.value = normalized.message
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="auth-page">
    <section class="card">
      <header class="head">
        <p class="eyebrow">Guardian Portal</p>
        <h1>保護者ログイン</h1>
      </header>

      <form class="form" @submit.prevent="submit">
        <label class="field">
          <span>メールアドレス</span>
          <input
            v-model="form.email"
            type="email"
            autocomplete="username"
            placeholder="guardian@example.com"
          >
          <small v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email }}</small>
        </label>

        <label class="field">
          <span>パスワード</span>
          <input
            v-model="form.password"
            type="password"
            autocomplete="current-password"
            placeholder="8文字以上"
          >
          <small v-if="fieldErrors.password" class="field-error">{{ fieldErrors.password }}</small>
        </label>

        <p class="invite-note">
          招待URLをお持ちの方は、招待リンクからアカウント登録または園児追加を行ってください。
        </p>

        <p v-if="errorMessage" class="error-banner">{{ errorMessage }}</p>

        <button type="submit" :disabled="!canSubmit">
          {{ submitting ? 'ログイン中...' : 'ログイン' }}
        </button>
      </form>
    </section>
  </main>
</template>

<style scoped>
.auth-page {
  min-height: 100dvh;
  display: grid;
  place-items: center;
  padding: 1.25rem;
  background:
    radial-gradient(circle at 88% 22%, #ffd8c1 0%, transparent 38%),
    radial-gradient(circle at 12% 76%, #bce8da 0%, transparent 42%),
    linear-gradient(155deg, #f8f2eb 0%, #edf8f3 100%);
}

.card {
  width: min(460px, 100%);
  border-radius: 18px;
  border: 1px solid #c8d8d1;
  background: #fffffff2;
  backdrop-filter: blur(8px);
  box-shadow: 0 18px 40px #315a481f;
  padding: 1.4rem;
}

.head h1 {
  margin: 0.35rem 0 0;
  font-size: clamp(1.25rem, 2.5vw, 1.75rem);
  color: #23463c;
}

.eyebrow {
  margin: 0;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-size: 0.75rem;
  color: #4d7064;
}

.form {
  margin-top: 1.2rem;
  display: grid;
  gap: 0.95rem;
}

.field {
  display: grid;
  gap: 0.38rem;
}

.field span {
  color: #31574b;
  font-size: 0.92rem;
}

input {
  border: 1px solid #a7c6bb;
  border-radius: 10px;
  padding: 0.68rem 0.78rem;
  font-size: 1rem;
  background: #fbfefd;
}

input:focus {
  outline: 2px solid #57b08b;
  outline-offset: 1px;
}

.invite-note {
  margin: 0;
  font-size: 0.88rem;
  color: #4f6f65;
  line-height: 1.5;
}

button {
  margin-top: 0.2rem;
  border: 0;
  border-radius: 10px;
  background: linear-gradient(135deg, #1f7c5b 0%, #24a173 100%);
  color: #ffffff;
  font-weight: 700;
  padding: 0.7rem 0.9rem;
  cursor: pointer;
}

button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.field-error {
  color: #9f1f1f;
  font-size: 0.82rem;
}

.error-banner {
  margin: 0;
  border: 1px solid #e6b1b1;
  background: #fff0f0;
  color: #8e2020;
  border-radius: 9px;
  padding: 0.55rem 0.7rem;
  font-size: 0.9rem;
}
</style>
