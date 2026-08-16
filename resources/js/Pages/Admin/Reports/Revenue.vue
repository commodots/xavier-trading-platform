<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-bold text-white">Revenue Report</h1>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex flex-wrap items-center gap-4">
        <DateFilter @filter-change="onFilterChange" />

        <select v-model="filters.source" class="bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm outline-none">
          <option value="" class="text-white">All Sources</option>
          <option value="subscription" class="text-white">Subscription</option>
          <option value="platform_fee" class="text-white">Platform Fee</option>
          <option value="trading_fee" class="text-white">Trading Fee</option>
          <option value="other" class="text-white">Other Income</option>
        </select>

        <select v-model="filters.status" class="bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm outline-none">
          <option value="" class="text-white">All Statuses</option>
          <option value="paid" class="text-white">Paid</option>
          <option value="completed" class="text-white">Completed</option>
          <option value="active" class="text-white">Active</option>
        </select>

        <input
          v-model="filters.search"
          type="text"
          placeholder="Search reference or user..."
          class="bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm outline-none placeholder-gray-500"
          @keyup.enter="fetchData(1)"
        />
        <button @click="fetchData(1)" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm">Search</button>
        <button @click="resetFilters" class="px-4 py-2 text-sm text-white bg-gray-700 rounded-lg">Reset</button>
      </div>
      <ExportButton
        reportType="revenue"
        :startDate="filters.date_from"
        :endDate="filters.date_to"
        :extraParams="{ source: filters.source, status: filters.status, search: filters.search }"
      />
    </div>

    <div v-if="loading" class="space-y-6">
      <SkeletonLoader type="card" :count="5" />
      <SkeletonLoader type="table" :count="6" />
    </div>

    <template v-else>
      <!-- Summary Cards -->
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <StatCard v-for="card in summaryCards" :key="card.label" v-bind="card" />
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <ReportChart
          title="Monthly Revenue"
          type="line"
          :categories="charts[0]?.labels || []"
          :series="charts[0]?.series || []"
        />
        <ReportChart
          title="Revenue by Source"
          type="bar"
          :categories="charts[1]?.labels || []"
          :series="charts[1]?.series || []"
        />
      </div>

      <!-- Revenue Register -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <h2 class="mb-4 text-lg font-semibold text-white">Revenue Register</h2>
        <ReportTable
          :columns="tableColumns"
          :data="tableRows"
          :sort-by="sortBy"
          :sort-dir="sortDir"
          @sort="handleSort"
        >
          <template #cell-date="{ row }">
            {{ formatDate(row.date) }}
          </template>
          <template #cell-amount="{ row }">
            <span class="block font-mono text-right">₦{{ formatNumber(Number(row.amount)) }}</span>
          </template>
          <template #cell-source_label="{ row }">
            <span class="text-white">{{ row.source_label || row.source }}</span>
          </template>
          <template #cell-status="{ row }">
            <span :class="getStatusClass(row.status)" class="text-xs font-medium capitalize">{{ row.status }}</span>
          </template>
        </ReportTable>

        <!-- Pagination -->
        <Pagination
          v-if="!loading && pagination.last_page > 1"
          :current-page="pagination.current_page"
          :last-page="pagination.last_page"
          :per-page="pagination.per_page"
          :total="pagination.total"
          @page="handlePageChange"
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import StatCard from '@/Components/Reports/StatCard.vue';
import ReportTable from '@/Components/Reports/ReportTable.vue';
import DateFilter from '@/Components/Reports/DateFilter.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import Pagination from '@/Components/Reports/Pagination.vue';
import ExportButton from '@/Components/Reports/ExportButton.vue';
import ReportChart from '@/Components/Reports/ReportChart.vue';
import api from '@/api';

const loading = ref(true);
const summary = ref({});
const charts = ref([]);
const tableData = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 });
const filters = reactive({
  date_from: '',
  date_to: '',
  period: 'month',
  source: '',
  status: '',
  search: '',
  page: 1,
  per_page: 20,
});
const sortBy = ref('date');
const sortDir = ref('desc');

const summaryCards = computed(() => [
  { label: 'Total Revenue', value: summary.value.total_revenue || 0, icon: 'dollar', color: '#0047AB', prefix: '₦' },
  { label: 'Revenue This Month', value: summary.value.revenue_this_month || 0, icon: 'activity', color: '#10B981', prefix: '₦' },
  { label: 'Revenue This Year', value: summary.value.revenue_this_year || 0, icon: 'trending-up', color: '#F59E0B', prefix: '₦' },
  { label: 'Transactions', value: summary.value.transaction_count || 0, icon: 'users', color: '#8B5CF6' },
  { label: 'Average Revenue', value: summary.value.average_revenue || 0, icon: 'activity', color: '#06B6D4', prefix: '₦' },
]);

const tableColumns = [
  { key: 'date', label: 'Date', type: 'date' },
  { key: 'source_label', label: 'Source' },
  { key: 'reference', label: 'Reference' },
  { key: 'description', label: 'Description' },
  { key: 'user_id', label: 'User' },
  { key: 'amount', label: 'Amount', align: 'right', type: 'currency' },
  { key: 'status', label: 'Status', type: 'status' },
];

const tableRows = computed(() => tableData.value);

const fetchData = async (page = 1) => {
  loading.value = true;
  try {
    const params = { ...filters, page };
    const res = await api.get('/admin/reports/revenue', { params });

    summary.value = res.data.summary || {};
    charts.value = res.data.charts || [];

    // Extract table data from paginator
    if (res.data.table && typeof res.data.table === 'object' && res.data.table.data) {
      tableData.value = res.data.table.data;
      pagination.value = {
        current_page: res.data.table.current_page || 1,
        last_page: res.data.table.last_page || 1,
        per_page: res.data.table.per_page || 20,
        total: res.data.table.total || 0,
      };
    } else {
      tableData.value = res.data.table || [];
      pagination.value = {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: tableData.value.length,
      };
    }
  } catch (e) {
    console.error('Failed to load revenue:', e);
  } finally {
    loading.value = false;
  }
};

const onFilterChange = (payload) => {
  filters.date_from = payload.start_date || '';
  filters.date_to = payload.end_date || '';
  filters.period = payload.period || 'month';
  fetchData(1);
};

const handleSort = (key) => {
  if (sortBy.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortBy.value = key;
    sortDir.value = 'asc';
  }
  fetchData(1);
};

const handlePageChange = (page) => {
  fetchData(page);
};

const resetFilters = () => {
  filters.date_from = '';
  filters.date_to = '';
  filters.period = 'month';
  filters.source = '';
  filters.status = '';
  filters.search = '';
  fetchData(1);
};

const formatNumber = (num) => {
  const parts = num.toLocaleString('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 20,
  }).split('.');

  if (parts[1]) {
    parts[1] = parts[1].replace(/0+$/, '');
    if (parts[1] === '') {
      return parts[0];
    }
    return parts.join('.');
  }

  return parts[0];
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  const d = new Date(date + 'T00:00:00');
  return d.toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' });
};

const getStatusClass = (status) => {
  const classes = {
    paid: 'text-green-400',
    completed: 'text-green-400',
    active: 'text-blue-400',
    successful: 'text-green-400',
    approved: 'text-green-400',
    pending: 'text-yellow-400',
    failed: 'text-red-400',
    cancelled: 'text-red-400',
  };
  return classes[status] || 'text-gray-400';
};

onMounted(() => {

  fetchData(1);
});
</script>