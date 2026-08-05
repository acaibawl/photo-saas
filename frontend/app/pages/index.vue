<template>
  <main class="home">
    <h1>Photo SaaS Frontend</h1>
    <NuxtLink to="/health">health ページを開く</NuxtLink>
  </main>
</template>

<style scoped>
.home {
  min-height: 100dvh;
  display: grid;
  place-content: center;
  gap: 1rem;
  padding: 1rem;
  text-align: center;
}

h1 {
  margin: 0;
}
</style>
