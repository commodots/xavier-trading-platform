<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-white">Financial Report</h1>
    </div>

    <!-- Summary Cards -->
    <SkeletonLoader v-if="loading" type="card" :count="7" class="opacity-40" />
    <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
      <StatCard v-for="s in summary" :key="s.title" v-bind="s" />
    </div>

    <!-- Statistics -->
    <div v-if="!loading && statistics" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
      <h3 class="mb-4 text-lg font-semibold text-white">Statistics</h3>
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div v-for="(value, key) in statistics" :key="key" class="bg-[#16213A] rounded-lg p-4">
          <p class="text-xs tracking-wider text-gray-400 uppercase">{{ formatStatLabel(key) }}</p>
          <p class="mt-1 text-xl font-bold text-white">${{ formatNumber(Number(value)) }}</p>
        </div>
      </div>
    </div>

    <!-- Tabs and Filters -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex flex-wrap items-center gap-4">
        <div class="flex gap-1 bg-[#0F1724] border border-[#1f3348] rounded-xl p-1 w-fit">
          <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key; fetchData(1)" :class="activeTab === tab.key ? 'bg-[#0047AB] text-white' : 'text-gray-400 hover:text-white'" class="px-4 py-2 text-sm font-medium transition rounded-lg">{{ tab.label }}</button>
        </div>
        <DateRangeFilter v-model:from="filters.from" v-model:to="filters.to" />
        <select v-model="filters.status" class="bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm outline-none">
          <option disabled value="" class="text-white">Status</option>
          <option value="completed">Completed</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
        <button @click="fetchData(1)" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm">Search</button>
        <button @click="resetFilters" class="px-4 py-2 text-sm text-white bg-gray-700 rounded-lg">Reset</button>
      </div>
      <ExportButton
          reportType="financial"
          :startDate="filters.from"
          :endDate="filters.to"
          :extraParams="{ tab: activeTab }"
        />
      </div>

    <!-- Table with Skeleton -->
    <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
      <SkeletonLoader type="table" :count="10" class="opacity-40" />
    </div>
    <ReportTable v-else :columns="columns" :data="rows" :sort-by="sortBy" :sort-dir="sortDir" @sort="handleSort">
      <template #cell-amount="{ row }">
        <span class="block font-mono text-right">{{ getCurrencySymbol(row.currency || 'USD') }}{{ formatNumber(Number(row.amount)) }}</span>
      </template>
      <template #cell-status="{ row }">
        <span :class="row.status === 'completed' || row.status === 'approved' ? 'text-green-400' : row.status === 'rejected' ? 'text-red-400' : 'text-yellow-400'" class="text-xs font-medium capitalize">{{ row.status }}</span>
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

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import StatCard from '@/Components/Reports/StatCard.vue';
import ReportTable from '@/Components/Reports/ReportTable.vue';
import DateRangeFilter from '@/Components/Reports/DateRangeFilter.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import Pagination from '@/Components/Reports/Pagination.vue';
import ExportButton from '@/Components/Reports/ExportButton.vue';
import api from '@/api';

const summary = ref([]);
const statistics = ref(null);
const rows = ref([]);
const loading = ref(false);
const activeTab = ref('wallet_transactions');
const filters = reactive({ from: '', to: '', status: '' });
const pagination = ref({ current_page: 1, last_page: 1, per_page: 50, total: 0 });

const tabs = [
  { key: 'wallet_transactions', label: 'All Transactions' },
  { key: 'deposits', label: 'Deposits' },
  { key: 'withdrawals', label: 'Withdrawals' },
  { key: 'fees', label: 'Fees' },
  { key: 'revenue', label: 'Revenue' },
];

const columns = computed(() => {
  const base = [
    { key: 'user', label: 'User' },
    { key: 'reference', label: 'Reference' },
    { key: 'amount', label: 'Amount', align: 'right' },
    { key: 'method', label: 'Method' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Date' },
  ];
  if (activeTab.value === 'fees') {
    return [...base, { key: 'type', label: 'Type' }];
  }
  if (activeTab.value === 'revenue') {
    return [{ key: 'source', label: 'Source' }, ...base];
  }
  if (activeTab.value === 'wallet_transactions') {
    return [{ key: 'type', label: 'Type' }, { key: 'user', label: 'User' }, { key: 'amount', label: 'Amount', align: 'right' }, { key: 'currency', label: 'Currency' }, { key: 'created_at', label: 'Date' }];
  }
  return base;
});

const getCurrencySymbol = (currency) => {
  const symbols = {
    'USD': '$',
    'NGN': '₦',
    'GBP': '£',
    'EUR': '€',
  };
  return symbols[currency] || '$';
};

const sortBy = ref('');
const sortDir = ref('desc');

const getStatsType = () => {
  const typeMap = {
    'deposits': 'deposits',
    'withdrawals': 'withdrawals',
    'fees': 'fees',
    'revenue': 'revenue',
    'wallet_transactions': 'deposits',
  };
  return typeMap[activeTab.value] || 'deposits';
};

const fetchData = async (page = 1) => {
  loading.value = true;
  try {
    const params = { 
      tab: activeTab.value, 
      ...filters, 
      page,
      sort: sortBy.value,
      dir: sortDir.value
    };
    const statsType = getStatsType();
    const [sumRes, dataRes] = await Promise.all([
      api.get('/admin/reports/financial/summary'),
      api.get('/admin/reports/financial', { params }),
    ]);
    summary.value = sumRes.data;
    rows.value = dataRes.data.data || [];
    pagination.value = {
      current_page: dataRes.data.current_page || 1,
      last_page: dataRes.data.last_page || 1,
      per_page: dataRes.data.per_page || 50,
      total: dataRes.data.total || 0,
    };
    
    // Fetch statistics separately with catch
    try {
      const statsRes = await api.get('/admin/reports/financial/summary/statistics', { params: { type: statsType } });
      statistics.value = statsRes.data;
    } catch (e) {
      statistics.value = null;
    }
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

const handleSort = (key) => {
  if (sortBy.value === key) { sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'; }
  else { sortBy.value = key; sortDir.value = 'asc'; }
  fetchData(1);
};

const resetFilters = () => {
  filters.from = '';
  filters.to = '';
  filters.status = '';
  sortBy.value = '';
  sortDir.value = 'desc';
  fetchData(1);
};

const formatNumber = (num) => {
  // Format with maximum precision first
  const parts = num.toLocaleString('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 20,
  }).split('.');
  
  // Remove trailing zeros from decimal part
  if (parts[1]) {
    parts[1] = parts[1].replace(/0+$/, '');
    if (parts[1] === '') {
      return parts[0];
    }
    return parts.join('.');
  }
  
  return parts[0];
};

const formatStatLabel = (key) => {
  const labels = {
    today: 'Today',
    yesterday: 'Yesterday',
    this_week: 'This Week',
    this_month: 'This Month'
  };
  return labels[key] || key;
};

const handlePageChange = (page) => {
  fetchData(page);
};

onMounted(() => fetchData(1));
</script>