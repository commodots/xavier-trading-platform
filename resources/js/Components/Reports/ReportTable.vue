<template>
  <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl overflow-hidden">
    <div v-if="$slots.header" class="p-4 border-b border-[#1f3348]">
      <slot name="header" />
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-xs text-gray-400 bg-black/20">
          <tr>
            <th v-for="col in columns" :key="col.key" :class="col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'" class="px-3.5 py-3.5 font-medium uppercase tracking-wider">
              <button v-if="col.sortable" @click="$emit('sort', col.key)" class="flex items-center gap-1 hover:text-white transition">
                {{ col.label }}
                <svg v-if="sortBy === col.key" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path v-if="sortDir === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                  <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <span v-else>{{ col.label }}</span>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[#1f3348]">
          <tr v-for="(row, i) in data" :key="i" class="hover:bg-[#16213A] transition">
            <td v-for="col in columns" :key="col.key" :class="col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'" class="px-3.5 py-4 text-gray-300">
              <slot :name="'cell-' + col.key" :row="row" :value="getValue(row, col.key)">
                {{ getValue(row, col.key) }}
              </slot>
            </td>
          </tr>
          <tr v-if="data.length === 0">
            <td :colspan="columns.length" class="px-5 py-10 text-center text-gray-500 italic">No data available</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="$slots.footer" class="p-4 border-t border-[#1f3348]">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  sortBy: { type: String, default: '' },
  sortDir: { type: String, default: 'desc' },
});
defineEmits(['sort']);

const getValue = (row, key) => {
  const keys = key.split('.');
  let val = row;
  for (const k of keys) {
    if (val === null || val === undefined) return '';
    val = val[k];
  }
  return val ?? '';
};
</script>