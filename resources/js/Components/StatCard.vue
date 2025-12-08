<template>
  <div class="bg-[#111827] rounded-xl p-5 border border-[#1F2A44] flex items-center justify-between">
    <div>
      <p class="text-gray-400 text-sm">{{ title }}</p>
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
