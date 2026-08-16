<script setup lang="ts">
definePageMeta({
  middleware: ['staff-auth'],
})

const authStore = useAuthStore()
const { fetchMe, logout } = useStaffAuth()
const { normalizeError } = useApiError()

const pending = ref(false)
const errorMessage = ref('')
const {
  error: profileError,
  refresh: retryFetchMe,
} = useAsyncData('staff-profile', fetchMe, { server: false })

async function handleLogout(): Promise<void> {
  pending.value = true
  errorMessage.value = ''

  try {
    await logout()
  } catch (error) {
    errorMessage.value = normalizeError(error).message
  } finally {
    pending.value = false
    await navigateTo('/staff/login')
  }
}
</script>

<template>
  <main class="shell">
    <section class="panel">
      <h1>スタッフ管理トップ</h1>
      <p>ログイン済みです。フェーズ1でダッシュボードを拡張します。</p>
      <p v-if="authStore.staffUser">{{ authStore.staffUser.name }} ({{ authStore.staffUser.role }})</p>
      <UAlert
        v-if="profileError"
        color="error"
        variant="soft"
        title="スタッフ情報を取得できませんでした。"
      />
      <button v-if="profileError" type="button" @click="() => retryFetchMe()">スタッフ情報を再取得</button>
      <UAlert v-if="errorMessage" color="error" variant="soft" :title="errorMessage" />
      <button type="button" :disabled="pending" @click="handleLogout">
        {{ pending ? 'ログアウト中...' : 'ログアウト' }}
      </button>
    </section>
  </main>
</template>

<style scoped>
.shell {
  min-height: 100dvh;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: linear-gradient(170deg, #f3f8ff 0%, #f9f4ea 100%);
}

.panel {
  width: min(680px, 100%);
  border: 1px solid #cdd6e2;
  border-radius: 14px;
  padding: 1.2rem;
  background: #ffffff;
}

h1 {
  margin: 0;
}

p {
  color: #39536f;
}

button {
  border: 0;
  border-radius: 10px;
  padding: 0.6rem 0.9rem;
  font-weight: 700;
  color: #ffffff;
  background: #195f93;
}
</style>
