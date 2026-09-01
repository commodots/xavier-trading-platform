<template>
  <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl overflow-hidden">
    <div v-if="title || description" class="flex items-start justify-between gap-3 border-b border-[#1f3348] px-4 py-4">
      <div>
        <h3 v-if="title" class="text-sm font-semibold text-white">{{ title }}</h3>
        <p v-if="description" class="mt-1 text-xs leading-5 text-gray-400">{{ description }}</p>
      </div>
      <slot name="actions" />
    </div>

    <!-- Search & Dropdown Filters -->
    <div v-if="searchable || filters.length" class="flex flex-wrap items-center gap-3 p-3 border-b border-[#1f3348]">
      <div v-if="searchable" class="relative min-w-[200px] flex-1">
        <svg class="absolute w-4 h-4 text-gray-500 -translate-y-1/2 left-3 top-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          v-model="searchQuery"
          type="text"
          :placeholder="searchPlaceholder"
          @input="onSearch"
          class="w-full bg-[#1C2541] text-white text-sm rounded-lg pl-9 pr-3 py-2 border border-[#1f3348] focus:border-[#0047AB] outline-none placeholder-gray-500"
        />
      </div>
      <select
        v-for="flt in filters"
        :key="flt.key"
        :value="flt.server ? (flt.value ?? '') : (activeFilters[flt.key] ?? '')"
        @change="onFilterSelect(flt, $event)"
        class="bg-[#1C2541] text-white text-xs rounded-lg px-3 py-2 border border-[#1f3348] focus:border-[#0047AB] outline-none"
      >
        <option value="">{{ flt.allLabel || `All ${flt.label}` }}</option>
        <option v-for="opt in normalizeOptions(flt.options)" :key="opt.value" :value="opt.value" class="text-white">
          {{ opt.label }}
        </option>
      </select>
      <button
        v-if="hasActiveFiltersOrSearch"
        @click="resetFiltersAndSearch"
        class="px-2 py-2 text-xs font-medium rounded-lg transition-colors text-gray-400 hover:text-white bg-[#2f5680]"
        title="Reset filters"
      >
       Reset Filters
      </button>
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
                <span v-else-if="col.type === 'number' || col.type === 'count'">{{ formatNumber(row[col.key]) }}</span>
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
          @click="changePage(computedPagination.current_page - 1)"
          :disabled="computedPagination.current_page <= 1"
          class="px-2 py-1 text-xs rounded hover:bg-[#1C2541] disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <ChevronLeft class="w-4 h-4" />
        </button>
        <span class="px-2 py-1 text-xs text-white">{{ computedPagination.current_page }}</span>
        <button
          @click="changePage(computedPagination.current_page + 1)"
          :disabled="computedPagination.current_page >= computedPagination.last_page"
          class="px-2 py-1 text-xs rounded hover:bg-[#1C2541] disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <ChevronRight class="w-4 h-4" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, useSlots, watch } from 'vue';
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
  searchPlaceholder: {
    type: String,
    default: 'Search...',
  },
  filters: {
    type: Array,
    default: () => [],
  },
  sortBy: {
    type: String,
    default: '',
  },
  sortDir: {
    type: String,
    default: 'asc',
  },
  title: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['sort', 'page-change', 'search', 'filter-change']);

const searchQuery = ref('');
const sortColumn = ref(props.sortBy || '');
const sortDirection = ref(props.sortDir || 'asc');
const slots = useSlots();
const activeFilters = ref({});

watch(
  () => props.filters,
  (defs) => {
    const next = {};
    for (const def of defs || []) {
      next[def.key] = activeFilters.value[def.key] || '';
    }
    activeFilters.value = next;
  },
  { immediate: true, deep: true }
);

// Plain-string options get a readable label (underscores become spaces,
// first letter capitalized) while keeping the raw value for exact matching.
const prettifyLabel = (value) =>
  String(value)
    .replace(/_/g, ' ')
    .replace(/^./, (ch) => ch.toUpperCase());

const normalizeOptions = (options) =>
  (options || []).map((opt) =>
    opt !== null && typeof opt === 'object'
      ? { label: opt.label, value: opt.value }
      : { label: prettifyLabel(opt), value: opt }
  );

const onFilterSelect = (def, event) => {
  const value = event.target.value;
  if (!def.server) {
    activeFilters.value[def.key] = value;
  }
  emit('filter-change', { key: def.key, value });
};

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

  for (const def of props.filters) {
    if (def.server) continue;
    const selected = activeFilters.value[def.key];
    if (selected === '' || selected === undefined || selected === null) continue;
    data = data.filter(
      (row) => String(row[def.key] ?? '').toLowerCase() === String(selected).toLowerCase()
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
  
  const { current_page, per_page } = props.pagination;
  // Clamp the displayed page so filtering/sorting never leaves an empty view
  const maxPage = Math.max(1, Math.ceil(sortedRows.value.length / per_page));
  const page = Math.min(current_page, maxPage);
  const start = (page - 1) * per_page;
  const end = start + per_page;
  
  return sortedRows.value.slice(start, end);
});

const hasClientFilters = computed(() => {
  if (searchQuery.value) return true;
  return props.filters.some((def) => {
    if (def.server) return false;
    const selected = activeFilters.value[def.key];
    return selected !== '' && selected !== undefined && selected !== null;
  });
});

const hasActiveFiltersOrSearch = computed(() => {
  return hasClientFilters.value;
});

const computedPagination = computed(() => {
  if (!props.pagination) {
    return null;
  }

  // While client-side search/filters are active, reflect the filtered view

  if (hasClientFilters.value) {
    const perPage = props.pagination.per_page;
    const total = sortedRows.value.length;
    const lastPage = Math.max(1, Math.ceil(total / perPage));
    const page = Math.min(props.pagination.current_page, lastPage);
    const from = total === 0 ? 0 : (page - 1) * perPage + 1;
    const to = Math.min(page * perPage, total);
    return { ...props.pagination, current_page: page, last_page: lastPage, total, from, to };
  }

  const from = (props.pagination.current_page - 1) * props.pagination.per_page + 1;
  const to = Math.min(props.pagination.current_page * props.pagination.per_page, props.pagination.total);

  return {
    ...props.pagination,
    from: from > props.pagination.total ? 0 : from,
    to: to > props.pagination.total ? props.pagination.total : to,
  };
});

watch(
  () => props.sortBy,
  (value) => {
    sortColumn.value = value || '';
  }
);

watch(
  () => props.sortDir,
  (value) => {
    sortDirection.value = value || 'asc';
  }
);

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
  if (page < 1 || (props.pagination && page > props.pagination.last_page)) {
    return;
  }

  emit('page-change', page);
};

const onSearch = () => {
  emit('search', searchQuery.value);
};

const resetFiltersAndSearch = () => {
  
  searchQuery.value = '';
  activeFilters.value = {};
  sortColumn.value = '';
  sortDirection.value = 'asc';
 
};

const formatCurrency = (value) => {
  if (value === null || value === undefined) return '0';
  return Number(value).toLocaleString('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  });
};

const formatNumber = (value) => {
  if (value === null || value === undefined) return '0';
  return Number(value).toLocaleString('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
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