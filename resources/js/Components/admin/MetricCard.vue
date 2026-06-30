<template>
  <div class="bg-[#1C1F2E] p-3 rounded-lg shadow flex items-center justify-between">
    <div class="flex-1 min-w-0">
      <h3 class="text-xs text-gray-400 truncate">{{ title }}</h3>
      <p class="mt-1 text-lg font-bold truncate">{{ value }}</p>
      
      <div v-if="subtitle" class="mt-1 text-xs font-medium text-gray-500">
        {{ subtitle }}
      </div>
    </div>
    <component :is="icon" class="w-5 h-5 text-[#00D4FF] ml-2 flex-shrink-0" />
  </div>
</template>

<script setup>
defineProps({
  title: String,
  value: [String, Number],
  icon: String,
  subtitle: String,
});
</script>