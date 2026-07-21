<template>
  <div class="flex items-center justify-between px-5 py-3">
    <div class="flex items-center gap-2">
      <span class="text-xs text-gray-400">Rows per page:</span>
      <select :value="perPage" @change="$emit('update:perPage', Number($event.target.value))" class="bg-[#16213A] border border-gray-700 rounded text-white text-xs p-1 outline-none">
        <option :value="10">10</option>
        <option :value="25">25</option>
        <option :value="50">50</option>
        <option :value="100">100</option>
        <option :value="200">200</option>
      </select>
    </div>
    <div class="flex items-center gap-4">
      <span class="text-xs text-gray-400">{{ from }}-{{ to }} of {{ total }}</span>
      <div class="flex gap-1">
        <button :disabled="currentPage <= 1" @click="$emit('page', currentPage - 1)" class="p-1 rounded hover:bg-[#1f3348] disabled:opacity-30 text-gray-400">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </button>
        <button :disabled="currentPage >= lastPage" @click="$emit('page', currentPage + 1)" class="p-1 rounded hover:bg-[#1f3348] disabled:opacity-30 text-gray-400">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  currentPage: { type: Number, default: 1 },
  lastPage: { type: Number, default: 1 },
  total: { type: Number, default: 0 },
  perPage: { type: Number, default: 50 },
});
defineEmits(['update:perPage', 'page']);

const from = computed(() => (props.currentPage - 1) * props.perPage + 1);
const to = computed(() => Math.min(props.currentPage * props.perPage, props.total));
</script>