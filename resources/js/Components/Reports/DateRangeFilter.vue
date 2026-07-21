<template>
  <div class="flex items-center gap-3">
    <div>
      <label class="text-xs text-gray-400 uppercase tracking-wider">From</label>
      <input
        type="date"
        :value="from"
        @input="$emit('update:from', $event.target.value)"
        class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm focus:border-blue-500 outline-none"
      />
    </div>
    <div>
      <label class="text-xs text-gray-400 uppercase tracking-wider">To</label>
      <input
        type="date"
        :value="to"
        @input="$emit('update:to', $event.target.value)"
        class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm focus:border-blue-500 outline-none"
      />
    </div>
  </div>
</template>

<script setup>
defineProps({
  from: { type: String, default: '' },
  to: { type: String, default: '' },
});
defineEmits(['update:from', 'update:to']);
</script>