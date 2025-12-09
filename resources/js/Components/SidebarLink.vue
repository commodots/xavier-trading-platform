<template>
  <router-link
    :to="to"
    class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-[#1C2541] transition"
    :class="{ 'bg-[#1C2541]': $route.path === to }"
  >
    <component :is="icon" class="w-5 h-5 text-[#00D4FF]" />
    <span><slot /></span>
  </router-link>
</template>

<script setup>
defineProps({
  to: { type: [String, Object], required: true },
  icon: { type: [Object, Function], required: true }
});
</script>
