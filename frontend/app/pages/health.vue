<script setup lang="ts">
import { computed } from 'vue'

const HEALTHCHECK_URL = 'https://backend.local/health'

type HealthResponse = string | Record<string, unknown> | null

const {
  data,
  error,
  status,
  refresh,
} = await useFetch<HealthResponse>(HEALTHCHECK_URL, {
  method: 'GET',
  server: true,
  immediate: true,
  retry: 0,
})

const statusLabel = computed(() => {
  if (status.value === 'pending') {
    return '確認中'
  }

  if (status.value === 'error') {
    return 'エラー'
  }

  return '正常'
})

const responseBody = computed(() => {
  if (data.value === null || data.value === undefined) {
    return null
  }

  if (typeof data.value === 'string') {
    return data.value
  }

  try {
    return JSON.stringify(data.value, null, 2)
  } catch {
    return String(data.value)
  }
})
</script>

<template>
  <main class="health-page">
    <section class="panel">
      <h1>Frontend / Backend Health</h1>
      <p>
        frontend から
        <a :href="HEALTHCHECK_URL" target="_blank" rel="noopener noreferrer">{{ HEALTHCHECK_URL }}</a>
        を呼び出して疎通確認します。
      </p>

      <div class="status-row">
        <span class="label">状態</span>
        <span class="badge" :data-state="status">{{ statusLabel }}</span>
      </div>

      <div class="actions">
        <button type="button" @click="refresh()">再確認</button>
      </div>

      <article v-if="error" class="error-box">
        <h2>エラー詳細</h2>
        <pre>{{ error.message }}</pre>
      </article>

      <article v-else-if="responseBody" class="response-box">
        <h2>レスポンス本文</h2>
        <pre>{{ responseBody }}</pre>
      </article>

      <p v-else class="empty-note">レスポンス待機中です。</p>
    </section>
  </main>
</template>

<style scoped>
:root {
  color-scheme: light;
}

.health-page {
  min-height: 100dvh;
  display: grid;
  place-items: center;
  padding: 2rem;
  background:
    radial-gradient(circle at 10% 10%, #ffe7c4 0%, transparent 45%),
    radial-gradient(circle at 90% 80%, #b8e8ff 0%, transparent 40%),
    linear-gradient(160deg, #f8f6ef 0%, #eef8ff 100%);
}

.panel {
  width: min(760px, 100%);
  border: 1px solid #d5dde8;
  border-radius: 16px;
  padding: 1.5rem;
  background: #ffffffd9;
  backdrop-filter: blur(6px);
  box-shadow: 0 12px 30px #0f243a1f;
}

h1 {
  margin: 0;
  font-size: clamp(1.5rem, 3vw, 2rem);
  letter-spacing: 0.02em;
  color: #17324f;
}

p {
  margin: 0.85rem 0 0;
  color: #2d4661;
  line-height: 1.6;
}

a {
  color: #0b6ea8;
  text-decoration-thickness: 2px;
}

.status-row {
  margin-top: 1.25rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.label {
  color: #35526d;
  font-size: 0.95rem;
}

.badge {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 0.3rem 0.75rem;
  font-weight: 700;
  font-size: 0.9rem;
  border: 1px solid #97abc0;
  background: #edf4fb;
  color: #214562;
}

.badge[data-state='pending'] {
  border-color: #ccb26b;
  background: #fff4d6;
  color: #7a5c16;
}

.badge[data-state='error'] {
  border-color: #d38b8b;
  background: #ffe8e8;
  color: #8b1e1e;
}

.actions {
  margin-top: 1rem;
}

button {
  border: 0;
  border-radius: 10px;
  padding: 0.6rem 1rem;
  font-weight: 700;
  color: #ffffff;
  background: linear-gradient(135deg, #1c6ea4 0%, #0b4f7f 100%);
  cursor: pointer;
}

button:hover {
  filter: brightness(1.06);
}

.response-box,
.error-box {
  margin-top: 1.25rem;
  border-radius: 12px;
  padding: 0.9rem;
}

.response-box {
  border: 1px solid #b7cce0;
  background: #f4f9ff;
}

.error-box {
  border: 1px solid #e1adad;
  background: #fff4f4;
}

h2 {
  margin: 0;
  font-size: 1rem;
  color: #1a3a58;
}

pre {
  margin: 0.7rem 0 0;
  padding: 0.7rem;
  border-radius: 8px;
  overflow: auto;
  background: #0f1925;
  color: #edf3fb;
  font-size: 0.85rem;
  line-height: 1.4;
}

.empty-note {
  margin-top: 1rem;
  color: #476482;
}

@media (max-width: 640px) {
  .health-page {
    padding: 1rem;
  }

  .panel {
    padding: 1rem;
  }
}
</style>
