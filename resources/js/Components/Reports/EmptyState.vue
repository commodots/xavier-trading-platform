<template>
  <div class="flex flex-col items-center justify-center py-12 text-center">
    <div class="w-16 h-16 rounded-full bg-[#1C2541] flex items-center justify-center mb-4">
      <Inbox class="w-8 h-8 text-gray-500" />
    </div>
    <h3 class="text-sm font-medium text-gray-400 mb-1">{{ title }}</h3>
    <p v-if="message" class="text-xs text-gray-500 max-w-xs">{{ message }}</p>
    <slot />
  </div>
</template>

<script setup>
import { Inbox } from 'lucide-vue-next';

defineProps({
  title: {
    type: String,
    default: 'No data available',
  },
  message: {
    type: String,
    default: '',
  },
});
</script>