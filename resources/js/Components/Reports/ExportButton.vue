<template>
  <div class="relative" ref="dropdownRef">
    <button
      @click="toggleDropdown"
      class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-[#0047AB] hover:bg-[#003580] rounded-lg transition-colors"
    >
      <Download class="w-4 h-4" />
      Export
      <ChevronDown class="w-3 h-3" :class="{ 'rotate-180': isOpen }" />
    </button>

    <div
      v-if="isOpen"
      class="absolute right-0 mt-2 w-48 bg-[#1C2541] border border-[#1f3348] rounded-xl shadow-xl z-50 overflow-hidden"
    >
      <button
        v-for="option in exportOptions"
        :key="option.format"
        @click="exportReport(option.format)"
        class="flex items-center gap-3 w-full px-4 py-3 text-sm text-gray-300 hover:bg-[#0F1724] hover:text-white transition-colors"
      >
        <component :is="option.icon" class="w-4 h-4" />
        {{ option.label }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Download, ChevronDown, FileText, FileSpreadsheet, FileType, Printer } from 'lucide-vue-next';
import api from '@/api';

const props = defineProps({
  title: {
    type: String,
    default: 'report',
  },
  rows: {
    type: Array,
    default: () => [],
  },
  headers: {
    type: Array,
    default: () => [],
  },
  type: {
    type: String,
    default: 'report',
  },
  startDate: {
    type: String,
    default: '',
  },
  endDate: {
    type: String,
    default: '',
  },
});

const isOpen = ref(false);
const dropdownRef = ref(null);

const exportOptions = [
  { format: 'csv', label: 'Export CSV', icon: FileText },
  { format: 'excel', label: 'Export Excel', icon: FileSpreadsheet },
  { format: 'pdf', label: 'Export PDF', icon: FileType },
  { format: 'print', label: 'Print', icon: Printer },
];

const toggleDropdown = () => {
  isOpen.value = !isOpen.value;
};

const exportReport = async (format) => {
  isOpen.value = false;

  if (format === 'print') {
    window.print();
    return;
  }

  try {
    const response = await api.post('/admin/reports/export', {
      format,
      title: props.title,
      rows: props.rows,
      headers: props.headers,
      type: props.type,
      start_date: props.startDate,
      end_date: props.endDate,
    }, {
      responseType: 'blob',
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `${props.title}.${format === 'excel' ? 'xlsx' : format}`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (e) {
    console.error('Export failed:', e);
  }
};

// Close dropdown on click outside
document.addEventListener('click', (e) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    isOpen.value = false;
  }
});
</script>