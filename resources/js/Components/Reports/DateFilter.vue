<template>
  <div class="flex flex-wrap items-center gap-2 bg-[#0F1724] border border-[#1f3348] rounded-xl p-3">
    <button
      v-for="option in dateOptions"
      :key="option.value"
      @click="selectPreset(option.value)"
      class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors"
      :class="selectedPreset === option.value
        ? 'bg-[#0047AB] text-white'
        : 'text-gray-400 hover:text-white hover:bg-[#1f3348]'"
    >
      {{ option.label }}
    </button>

    <div class="flex items-center gap-2 ml-auto">
      <input
        v-model="startDate"
        type="date"
        @change="emitFilter"
        class="bg-[#1C2541] text-white text-xs rounded-lg px-2 py-1.5 border border-[#1f3348] focus:border-[#0047AB] outline-none"
      />
      <span class="text-xs text-gray-500">to</span>
      <input
        v-model="endDate"
        type="date"
        @change="emitFilter"
        class="bg-[#1C2541] text-white text-xs rounded-lg px-2 py-1.5 border border-[#1f3348] focus:border-[#0047AB] outline-none"
      />
      <button
        @click="resetFilters"
        class="px-2 py-1.5 text-xs font-medium rounded-lg transition-colors text-gray-400 hover:text-white bg-[#1f3348]"
        title="Reset to default"
      >
        Reset
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const emit = defineEmits(['filter-change']);

const selectedPreset = ref('month');
const startDate = ref('');
const endDate = ref('');

const dateOptions = [
  { label: 'Today', value: 'today' },
  { label: 'Yesterday', value: 'yesterday' },
  { label: 'Week', value: 'week' },
  { label: 'Month', value: 'month' },
  { label: 'Quarter', value: 'quarter' },
  { label: 'Year', value: 'year' },
  { label: 'Custom', value: 'custom' },
];

const getDateRange = (preset) => {
  const now = new Date();
  const start = new Date();
  const end = new Date();

  switch (preset) {
    case 'today':
      start.setHours(0, 0, 0, 0);
      end.setHours(23, 59, 59, 999);
      break;
    case 'yesterday':
      start.setDate(start.getDate() - 1);
      start.setHours(0, 0, 0, 0);
      end.setDate(end.getDate() - 1);
      end.setHours(23, 59, 59, 999);
      break;
    case 'week':
      start.setDate(start.getDate() - start.getDay());
      start.setHours(0, 0, 0, 0);
      break;
    case 'month':
      start.setDate(1);
      start.setHours(0, 0, 0, 0);
      break;
    case 'quarter':
      start.setMonth(Math.floor(start.getMonth() / 3) * 3);
      start.setDate(1);
      start.setHours(0, 0, 0, 0);
      break;
    case 'year':
      start.setMonth(0, 1);
      start.setHours(0, 0, 0, 0);
      break;
    default:
      return null;
  }

  return {
    start_date: start.toISOString().split('T')[0],
    end_date: end.toISOString().split('T')[0],
  };
};

const selectPreset = (value) => {
  selectedPreset.value = value;
  if (value !== 'custom') {
    const range = getDateRange(value);
    if (range) {
      startDate.value = range.start_date;
      endDate.value = range.end_date;
    }
    emitFilter();
  }
};

const emitFilter = () => {
  emit('filter-change', {
    start_date: startDate.value,
    end_date: endDate.value,
    period: selectedPreset.value,
  });
};

const resetFilters = () => {
  selectedPreset.value = 'month';
  startDate.value = '';
  endDate.value = '';
  emitFilter();
};
</script>