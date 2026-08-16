<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-bold text-white">Expense Report</h1>
    </div>

    <div class="flex items-center justify-between gap-4">
      <DateFilter @filter-change="onFilterChange" />
      <ExportButton
        reportType="expenses"
        :startDate="filters.start_date"
        :endDate="filters.end_date"
      />
    </div>

    <div v-if="loading" class="space-y-6">
      <SkeletonLoader type="card" :count="6" />
      <SkeletonLoader type="table" />
    </div>

    <template v-else>
      <!-- Summary Cards -->
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-1 lg:grid-cols-3">
        <StatCard
          v-for="card in summaryCards"
          :key="card.label"
          v-bind="card"
        />
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <ReportChart
          title="Monthly Expenses"
          type="line"
          :categories="charts[0]?.labels || []"
          :series="charts[0]?.series || []"
        />
        <ReportChart
          title="Expenses by Category"
          type="bar"
          :categories="charts[1]?.labels || []"
          :series="charts[1]?.series || []"
        />
        <ReportChart
          title="Expenses by Vendor"
          type="bar"
          :categories="charts[2]?.labels || []"
          :series="charts[2]?.series || []"
        />
        <ReportChart
          title="Expenses by Department"
          type="bar"
          :categories="charts[3]?.labels || []"
          :series="charts[3]?.series || []"
        />
        <ReportChart
          v-if="charts[4] && charts[4].labels && charts[4].labels.length > 0"
          title="Expense Status"
          type="donut"
          :categories="charts[4].labels"
          :series="charts[4].series"
        />
      </div>

      <!-- Expense Register Table -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <h2 class="mb-4 text-lg font-semibold text-white">Expense Register</h2>
        <ReportTable
          :columns="tableColumns"
          :data="tableRows"
          :sort-by="sortBy"
          :sort-dir="sortDir"
          @sort="handleSort"
        >
          <template #cell-amount="{ row }">
            <span class="block font-mono text-right">₦{{ formatNumber(Number(row.amount)) }}</span>
          </template>
          <template #cell-status="{ row }">
            <span :class="getStatusClass(row.status)" class="text-xs font-medium capitalize">
              {{ row.status }}
            </span>
          </template>
          <template #cell-expense_date="{ row }">
            {{ formatDate(row.expense_date) }}
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
const categories = ref([]);
const vendors = ref([]);
const departments = ref([]);
const filters = reactive({
  date_from: '',
  date_to: '',
  category_id: '',
  vendor_id: '',
  department_id: '',
  status: '',
  payment_method: '',
  search: '',
  page: 1,
  per_page: 20,
  sort: 'expense_date',
  direction: 'desc'
});
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0
});
const sortBy = ref('expense_date');
const sortDir = ref('desc');

const summaryCards = computed(() => [
  { label: 'Total Expenses', value: summary.value.total_expenses || 0, icon: 'DollarSign', color: '#EF4444', prefix: '₦' },
  { label: 'Expense Count', value: summary.value.expense_count || 0, icon: 'Hash', color: '#3B82F6' },
  { label: 'Approved', value: summary.value.approved_expenses || 0, icon: 'CheckCircle', color: '#10B981', prefix: '₦' },
  { label: 'Paid', value: summary.value.paid_expenses || 0, icon: 'CreditCard', color: '#8B5CF6', prefix: '₦' },
  { label: 'Draft', value: summary.value.draft_expenses || 0, icon: 'FileText', color: '#F59E0B', prefix: '₦' },
  { label: 'Cancelled', value: summary.value.cancelled_expenses || 0, icon: 'XCircle', color: '#6B7280', prefix: '₦' },
]);

const tableColumns = [
  { key: 'expense_no', label: 'Expense No' },
  { key: 'expense_date', label: 'Date', type: 'date' },
  { key: 'category', label: 'Category' },
  { key: 'vendor', label: 'Vendor' },
  { key: 'department', label: 'Department' },
  { key: 'amount', label: 'Amount', align: 'right', type: 'currency' },
  { key: 'payment_method', label: 'Payment Method' },
  { key: 'status', label: 'Status', type: 'status' },
  { key: 'requester', label: 'Requested By' },
];

const tableRows = computed(() => tableData.value);

    const fetchData = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      ...filters,
      page
    };

    const res = await api.get('/admin/reports/expenses', { params });
    
    summary.value = res.data.summary || {};
    charts.value = res.data.charts || [];
    
    // Extract table data from paginator
    if (res.data.table && typeof res.data.table === 'object' && res.data.table.data) {
      tableData.value = res.data.table.data;
      pagination.value = {
        current_page: res.data.table.current_page || 1,
        last_page: res.data.table.last_page || 1,
        per_page: res.data.table.per_page || 20,
        total: res.data.table.total || 0
      };
    } else {
      tableData.value = res.data.table || [];
      pagination.value = {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: tableData.value.length
      };
    }
    
    categories.value = res.data.categories || [];
    vendors.value = res.data.vendors || [];
    departments.value = res.data.departments || [];
  } catch (e) {
    console.error('Failed to load expenses:', e);
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
  const d = new Date(date);
  return d.toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' });
};

const getStatusClass = (status) => {
  const classes = {
    paid: 'text-green-400',
    approved: 'text-blue-400',
    draft: 'text-yellow-400',
    cancelled: 'text-red-400'
  };
  return classes[status] || 'text-gray-400';
};

onMounted(() => {
  // Set default date range to last 90 days to show more data including all statuses
  const now = new Date();
  const ninetyDaysAgo = new Date(now.getTime() - (90 * 24 * 60 * 60 * 1000));
  filters.date_from = ninetyDaysAgo.toISOString().split('T')[0];
  filters.date_to = now.toISOString().split('T')[0];
  fetchData(1);
});
</script>