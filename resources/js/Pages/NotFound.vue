<template>
  <main class="flex min-h-screen items-center justify-center bg-slate-950 px-6 text-white">
    <section class="w-full max-w-lg space-y-6 text-center">
      <p class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-400">404 · Page not found</p>
      <h1 class="text-4xl font-bold sm:text-5xl">We couldn't find that page.</h1>
      <p class="text-gray-400">
        The address may be incorrect, or the page may have moved.
      </p>
      <div class="flex flex-col justify-center gap-3 sm:flex-row">
        <RouterLink
          to="/"
          class="rounded-lg border border-slate-700 px-5 py-3 font-semibold transition hover:border-cyan-400"
        >
          Go to home
        </RouterLink>
        <RouterLink
          v-if="hasSession"
          to="/dashboard"
          class="rounded-lg bg-cyan-500 px-5 py-3 font-semibold text-slate-950 transition hover:bg-cyan-400"
        >
          Open dashboard
        </RouterLink>
      </div>
    </section>
  </main>
</template>

<script setup>
import { computed } from "vue";

const hasSession = computed(() => Boolean(localStorage.getItem("xavier_token")));
</script>
