<template>
  <div class="bg-[#1C1F2E] p-4 rounded-xl shadow flex items-center justify-between">
    <div>
      <h3 class="text-sm text-gray-400">{{ title }}</h3>
      <p class="text-2xl font-bold mt-1">{{ value }}</p>
    </div>
    <component :is="icon" class="w-8 h-8 text-[#00D4FF]" />
  </div>
</template>

<script setup>
defineProps({
  title: String,
  value: [String, Number],
  icon: String,
});
</script>
