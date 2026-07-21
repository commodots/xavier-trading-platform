<template>
  <div class="relative">
    <button @click="open = !open" class="flex items-center gap-2 px-4 py-2 bg-[#16213A] border border-gray-700 rounded-lg text-white text-sm hover:border-blue-500 transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      Export
    </button>
    <div v-if="open" class="absolute right-0 mt-2 w-36 bg-[#16213A] border border-[#1f3348] rounded-lg shadow-xl z-10">
      <button @click="exportCSV" class="w-full px-4 py-2.5 text-left text-white text-sm hover:bg-[#1f3348] first:rounded-t-lg">Export CSV</button>
      <button @click="exportExcel" class="w-full px-4 py-2.5 text-left text-white text-sm hover:bg-[#1f3348] last:rounded-b-lg">Export Excel</button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const open = ref(false);

const emit = defineEmits(['export-csv', 'export-excel']);

const exportCSV = () => { open.value = false; emit('export-csv'); };
const exportExcel = () => { open.value = false; emit('export-excel'); };
</script>