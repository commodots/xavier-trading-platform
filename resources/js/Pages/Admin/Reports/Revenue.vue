<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-bold text-white">Revenue Report</h1>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <DateFilter @filter-change="onFilterChange" />
      <ExportButton
        reportType="revenue"
        :startDate="filters.start_date"
        :endDate="filters.end_date"
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
          :filters="tableFilters"
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
          @change="handlePageChange"
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
  start_date: '',
  end_date: '',
  period: 'month',
  source: '',
  status: '',
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
  filters.start_date = payload.start_date || '';
  filters.end_date = payload.end_date || '';
  filters.period = payload.period || 'month';
  fetchData(1);
};


const tableFilters = computed(() => [
  {
    key: 'source_label',
    label: 'Source',
    allLabel: 'All Sources',
    options: [...new Set(tableData.value.map((row) => row.source_label).filter(Boolean))],
  },
  {
    key: 'status',
    label: 'Status',
    allLabel: 'All Statuses',
    options: [
      { label: 'Pending', value: 'pending' },
      { label: 'Completed', value: 'completed' },
      { label: 'Approved', value: 'approved' },
      { label: 'Paid', value: 'paid' },
      { label: 'Failed', value: 'failed' },
      { label: 'Cancelled', value: 'cancelled' },
    ],
  },
]);

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