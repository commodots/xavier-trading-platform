<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-50 flex justify-end">
      <div class="absolute inset-0 bg-black/50" @click="$emit('close')" />
      <div class="relative w-96 bg-[#0F1724] border-l border-[#1f3348] h-full overflow-y-auto">
        <div class="p-6 border-b border-[#1f3348] flex items-center justify-between">
          <h3 class="text-lg font-bold text-white">Filters</h3>
          <button @click="$emit('close')" class="text-gray-400 hover:text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="p-6 space-y-5">
          <slot />
        </div>
        <div class="p-6 border-t border-[#1f3348]">
          <button @click="$emit('apply')" class="w-full bg-[#0047AB] text-white py-2.5 rounded-lg font-medium hover:bg-blue-700 transition">
            Apply Filters
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
defineProps({ show: { type: Boolean, default: false } });
defineEmits(['close', 'apply']);
</script>