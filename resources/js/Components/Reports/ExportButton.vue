<template>
  <div class="relative" ref="dropdownRef">
    <button
      @click="handleExportClick"
      :disabled="downloading"
      class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-[#0047AB] hover:bg-[#003580] rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
    >
      <Download class="w-4 h-4" />
      {{ downloading ? 'Exporting...' : 'Export' }}
    </button>

    <!-- Format Selection Dropdown -->
    <div
      v-if="showDropdown"
      class="absolute right-0 mt-2 w-48 bg-[#1C2541] border border-[#1f3348] rounded-xl shadow-xl z-50 overflow-hidden"
    >
      <button
        v-for="option in exportOptions"
        :key="option.format"
        @click="selectFormat(option.format)"
        class="flex items-center gap-3 w-full px-4 py-3 text-sm text-gray-300 hover:bg-[#0F1724] hover:text-white transition-colors"
      >
        <component :is="option.icon" class="w-4 h-4" />
        {{ option.label }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Download, FileText, FileSpreadsheet, FileType, Printer } from 'lucide-vue-next';
import api from '@/api';

const props = defineProps({
  reportType: {
    type: String,
    required: true,
  },
  startDate: {
    type: String,
    default: '',
  },
  endDate: {
    type: String,
    default: '',
  },
  extraParams: {
    type: Object,
    default: () => ({}),
  },
});

const showDropdown = ref(false);
const dropdownRef = ref(null);
const downloading = ref(false);

const exportOptions = [
  { format: 'csv', label: 'Export CSV', icon: FileText },
  { format: 'excel', label: 'Export Excel', icon: FileSpreadsheet },
  { format: 'pdf', label: 'Export PDF', icon: FileType },
  { format: 'print', label: 'Print', icon: Printer },
];

const handleExportClick = () => {
  if (downloading.value) {
    return;
  }

  // Check if date range is required but not provided
  if (!props.startDate || !props.endDate) {
    alert('Please select a date range before exporting. Use the date filter above to set your desired date range.');
    return;
  }

  showDropdown.value = !showDropdown.value;
};

const getDownloadFilename = (ext) => {
  const safeReportType = (props.reportType + (props.extraParams && props.extraParams.tab ? `-${props.extraParams.tab}` : '')).replace(/\s+/g, '-').toLowerCase();
  const safeStart = (props.startDate || '').replace(/\//g, '-');
  const safeEnd = (props.endDate || '').replace(/\//g, '-');
  const range = safeStart && safeEnd ? `${safeStart}-to-${safeEnd}` : 'all-dates';
  return `${safeReportType}-${range}.${ext}`;
};

const selectFormat = async (format) => {
  showDropdown.value = false;

  if (format === 'print') {
    window.print();
    return;
  }

  downloading.value = true;

  try {
    const response = await api.post('/admin/reports/export', {
      format: format,
      report_type: props.reportType,
      start_date: props.startDate,
      end_date: props.endDate,
      ...props.extraParams,
    }, {
      responseType: 'blob',
    });

    // Create blob and trigger download
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;

    const ext = format === 'excel' ? 'xlsx' : format;
    link.setAttribute('download', getDownloadFilename(ext));

    // Append to body, click, and remove
    document.body.appendChild(link);
    link.click();

    // Cleanup
    setTimeout(() => {
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
    }, 100);
  } catch (e) {
    console.error('Export failed:', e);
    alert('Failed to export report. Please try again.');
  } finally {
    downloading.value = false;
  }
};

// Close dropdown on click outside
const handleClickOutside = (e) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    showDropdown.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>