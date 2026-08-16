<script setup lang="ts">
definePageMeta({
  middleware: ['guardian-auth'],
})

const { logout, fetchChildren } = useGuardianAuth()
const { normalizeError } = useApiError()
const pending = ref(false)
const errorMessage = ref('')

onMounted(() => {
  // children data isn't rendered yet; failures here must not block the guardian portal
  fetchChildren().catch(() => {})
})

async function handleLogout(): Promise<void> {
  pending.value = true
  errorMessage.value = ''

  try {
    await logout()
  } catch (error) {
    errorMessage.value = normalizeError(error).message
  } finally {
    pending.value = false
    await navigateTo('/guardian/login')
  }
}
</script>

<template>
  <main class="shell">
    <section class="panel">
      <h1>保護者ホーム</h1>
      <p>ログイン済みです。フェーズ4以降で園児一覧を追加します。</p>
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
  background: linear-gradient(170deg, #eef9f1 0%, #f7f3ea 100%);
}

.panel {
  width: min(680px, 100%);
  border: 1px solid #c8d7ce;
  border-radius: 14px;
  padding: 1.2rem;
  background: #ffffff;
}

h1 {
  margin: 0;
}

p {
  color: #406256;
}

button {
  border: 0;
  border-radius: 10px;
  padding: 0.6rem 0.9rem;
  font-weight: 700;
  color: #ffffff;
  background: #1f7d5b;
}
</style>
