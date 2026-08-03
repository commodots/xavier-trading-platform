<template>
  <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl overflow-hidden">
    <!-- Search -->
    <div v-if="searchable" class="p-3 border-b border-[#1f3348]">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Search..."
        @input="onSearch"
        class="w-full bg-[#1C2541] text-white text-sm rounded-lg px-3 py-2 border border-[#1f3348] focus:border-[#0047AB] outline-none placeholder-gray-500"
      />
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-[#1f3348]">
            <th
              v-for="col in columns"
              :key="col.key"
              @click="col.sortable !== false ? toggleSort(col.key) : null"
              class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase cursor-pointer hover:text-white"
              :class="col.sortable !== false ? 'cursor-pointer' : ''"
              :style="col.width ? { width: col.width } : null"
            >
              <span class="flex items-center gap-1">
                {{ col.label }}
                <span v-if="sortColumn === col.key" class="text-[#0047AB]">
                  <ChevronUp v-if="sortDirection === 'asc'" class="w-3 h-3" />
                  <ChevronDown v-else class="w-3 h-3" />
                </span>
              </span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="sortedRows.length === 0">
            <td :colspan="columns.length" class="px-4 py-8 text-center text-gray-500">
              No data available
            </td>
          </tr>
          <tr
            v-for="(row, rowIndex) in paginatedRows"
            :key="rowIndex"
            class="border-b border-[#1f3348] hover:bg-[#1C2541] transition-colors"
          >
            <td
              v-for="col in columns"
              :key="col.key"
              :class="['text-white', col.cellClass || 'px-4 py-3']"
              :style="col.width ? { width: col.width } : null"
            >
              <template v-if="slots[`cell-${col.key}`]">
                <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]" />
              </template>
              <template v-else>
                <span v-if="col.format">{{ col.format(row[col.key], row) }}</span>
                <span v-else-if="col.type === 'currency'">{{ formatCurrency(row[col.key]) }}</span>
                <span v-else-if="col.type === 'date'">{{ formatDate(row[col.key]) }}</span>
                <span v-else-if="col.type === 'status'">
                  <span
                    class="px-2 py-0.5 rounded-full text-xs font-medium"
                    :class="getStatusClass(row[col.key])"
                  >
                    {{ row[col.key] }}
                  </span>
                </span>
                <span v-else>{{ row[col.key] }}</span>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="computedPagination && computedPagination.total > computedPagination.per_page" class="flex items-center justify-between px-4 py-3 border-t border-[#1f3348]">
      <span class="text-xs text-gray-500">
        Showing {{ computedPagination.from }} - {{ computedPagination.to }} of {{ computedPagination.total }}
      </span>
      <div class="flex items-center gap-1">
        <button
          @click="changePage(pagination.current_page - 1)"
          :disabled="!pagination.prev_page_url"
          class="px-2 py-1 text-xs rounded hover:bg-[#1C2541] disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <ChevronLeft class="w-4 h-4" />
        </button>
        <span class="px-2 py-1 text-xs text-white">{{ pagination.current_page }}</span>
        <button
          @click="changePage(pagination.current_page + 1)"
          :disabled="!pagination.next_page_url"
          class="px-2 py-1 text-xs rounded hover:bg-[#1C2541] disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <ChevronRight class="w-4 h-4" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, useSlots } from 'vue';
import { ChevronUp, ChevronDown, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
  columns: {
    type: Array,
    required: true,
  },
  rows: {
    type: Array,
    default: () => [],
  },
  data: {
    type: Array,
    default: () => [],
  },
  pagination: {
    type: Object,
    default: null,
  },
  searchable: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['sort', 'page-change', 'search']);

const searchQuery = ref('');
const sortColumn = ref('');
const sortDirection = ref('asc');
const slots = useSlots();

const tableRows = computed(() => {
  return props.data.length ? props.data : props.rows;
});

const sortedRows = computed(() => {
  let data = [...tableRows.value];

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    data = data.filter((row) =>
      Object.values(row).some((val) =>
        String(val).toLowerCase().includes(q)
      )
    );
  }

  if (sortColumn.value) {
    data.sort((a, b) => {
      const aVal = a[sortColumn.value];
      const bVal = b[sortColumn.value];
      if (aVal < bVal) return sortDirection.value === 'asc' ? -1 : 1;
      if (aVal > bVal) return sortDirection.value === 'asc' ? 1 : -1;
      return 0;
    });
  }

  return data;
});

const paginatedRows = computed(() => {
  if (!props.pagination) return sortedRows.value;
  const start = (props.pagination.current_page - 1) * props.pagination.per_page;
  return sortedRows.value.slice(start, start + props.pagination.per_page);
});

const computedPagination = computed(() => {
  if (!props.pagination) {
    return null;
  }

  const from = (props.pagination.current_page - 1) * props.pagination.per_page + 1;
  const to = Math.min(props.pagination.current_page * props.pagination.per_page, props.pagination.total);

  return {
    ...props.pagination,
    from: from > props.pagination.total ? 0 : from,
    to: to > props.pagination.total ? props.pagination.total : to,
  };
});

const toggleSort = (key) => {
  if (sortColumn.value === key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortColumn.value = key;
    sortDirection.value = 'asc';
  }
  emit('sort', { column: key, direction: sortDirection.value });
};

const changePage = (page) => {
  emit('page-change', page);
};

const onSearch = () => {
  emit('search', searchQuery.value);
};

const formatCurrency = (value) => {
  if (value === null || value === undefined) return '$0.00';
  return '$' + Number(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const formatDate = (value) => {
  if (!value) return '';
  return new Date(value).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const getStatusClass = (status) => {
  const map = {
    completed: 'bg-green-900/50 text-green-400',
    pending: 'bg-yellow-900/50 text-yellow-400',
    failed: 'bg-red-900/50 text-red-400',
    active: 'bg-green-900/50 text-green-400',
    paid: 'bg-green-900/50 text-green-400',
    unpaid: 'bg-red-900/50 text-red-400',
    approved: 'bg-green-900/50 text-green-400',
    rejected: 'bg-red-900/50 text-red-400',
  };
  return map[status?.toLowerCase()] || 'bg-gray-900/50 text-gray-400';
};
</script>