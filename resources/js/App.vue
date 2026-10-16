<template>
  <a
    href="#main-content"
    class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[200] focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:font-semibold focus:text-slate-950 focus:outline focus:outline-2 focus:outline-cyan-500"
  >
    Skip to main content
  </a>
  <router-view />
  <div id="route-announcer" class="sr-only" aria-live="polite" aria-atomic="true"></div>
</template>

<script setup>
// No script needed — this just loads your routed pages
</script>

<style>
body {
  @apply bg-slate-900 text-white;
}
html, body {
  margin: 0;
  padding: 0;
  height: 100%;
}
</style>
