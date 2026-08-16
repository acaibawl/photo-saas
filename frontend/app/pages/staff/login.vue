<script setup lang="ts">
import * as yup from 'yup'
import { toTypedSchema } from '@vee-validate/yup'

definePageMeta({
  middleware: ['staff-guest'],
})

const { login, fetchMe } = useStaffAuth()
const { normalizeError } = useApiError()

const validationSchema = yup.object({
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
    email: '',
    password: '',
  },
})

const [email, emailAttrs] = defineField('email', {
  validateOnModelUpdate: false,
})
const [password, passwordAttrs] = defineField('password', {
  validateOnModelUpdate: false,
})
const errorMessage = ref('')

const canSubmit = computed(() => {
  return !isSubmitting.value && !!email.value?.trim() && !!password.value
})

const submit = handleSubmit(async (values) => {
  errorMessage.value = ''

  try {
    await login(values.email.trim(), values.password)
    await fetchMe()
    await navigateTo('/staff')
  } catch (error) {
    const normalized = normalizeError(error)

    if (normalized.code === 'STAFF_AUTH_INVALID_CREDENTIALS') {
      errorMessage.value = 'メールアドレスまたはパスワードが正しくありません。'
    } else if (normalized.code === 'STAFF_AUTH_RATE_LIMITED') {
      errorMessage.value = '試行回数が多すぎます。時間をおいて再試行してください。'
    } else if (normalized.code === 'VALIDATION_ERROR') {
      setErrors({
        email: normalized.fieldErrors.email?.[0],
        password: normalized.fieldErrors.password?.[0],
      })
      errorMessage.value = '入力内容を確認してください。'
    } else {
      errorMessage.value = normalized.message
    }
  }
})
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10">
    <UCard class="w-full max-w-md border border-slate-200 bg-white shadow-sm">
      <template #header>
        <div class="space-y-2">
          <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Staff Portal</p>
          <h1 class="text-2xl font-semibold tracking-tight text-slate-900">園スタッフ ログイン</h1>
        </div>
      </template>

      <form class="space-y-5" @submit="submit">
        <UFormField label="メールアドレス" :error="errors.email">
          <UInput
            v-model="email"
            v-bind="emailAttrs"
            type="email"
            size="lg"
            placeholder="staff@example.com"
            autocomplete="username"
          />
        </UFormField>

        <UFormField label="パスワード" :error="errors.password">
          <UInput
            v-model="password"
            v-bind="passwordAttrs"
            type="password"
            size="lg"
            placeholder="8文字以上"
            autocomplete="current-password"
          />
        </UFormField>

        <UAlert v-if="errorMessage" color="error" variant="soft" :title="errorMessage" />

        <UButton type="submit" size="lg" class="w-full enabled:cursor-pointer" :loading="isSubmitting" :disabled="!canSubmit">
          {{ isSubmitting ? 'ログイン中...' : 'ログイン' }}
        </UButton>
      </form>
    </UCard>
  </main>
</template>
