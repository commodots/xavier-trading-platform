<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-white">Financial Report</h1>
    </div>

    <!-- Summary Cards -->
    <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
      <div v-for="i in 6" :key="i" class="p-4 bg-[#0F1724] border border-[#1f3348] rounded-lg space-y-3 animate-pulse">
        <div class="h-3 bg-gray-700 rounded w-20"></div>
        <div class="h-6 bg-gray-700 rounded w-16"></div>
      </div>
    </div>
    <div v-else class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
      <StatCard v-for="s in summary" :key="s.label" v-bind="s" />
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 bg-[#0F1724] border border-[#1f3348] rounded-xl p-1 w-fit">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key; fetchData(1)" :class="activeTab === tab.key ? 'bg-[#0047AB] text-white' : 'text-gray-400 hover:text-white'" class="px-4 py-2 rounded-lg text-sm font-medium transition">{{ tab.label }}</button>
    </div>

    <DateRangeFilter v-model:from="filters.from" v-model:to="filters.to" />

    <!-- Table with Skeleton -->
    <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
      <SkeletonLoader type="table" :count="10" class="opacity-40" />
    </div>
    <ReportTable v-else :columns="columns" :data="rows">
      <template #cell-amount="{ row }">
        <span class="text-right block font-mono">{{ getCurrencySymbol(row.currency || 'USD') }}{{ Number(row.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
      </template>
      <template #cell-status="{ row }">
        <span :class="row.status === 'completed' || row.status === 'approved' ? 'text-green-400' : 'text-yellow-400'" class="text-xs font-medium">{{ row.status }}</span>
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
import api from '@/api';

const summary = ref([]);
const rows = ref([]);
const loading = ref(false);
const activeTab = ref('deposits');
const filters = reactive({ from: '', to: '' });
const pagination = ref({ current_page: 1, last_page: 1, per_page: 50, total: 0 });

const tabs = [
  { key: 'deposits', label: 'Deposits' },
  { key: 'withdrawals', label: 'Withdrawals' },
  { key: 'wallet_transactions', label: 'Wallet Transactions' },
  { key: 'fees', label: 'Fees' },
  { key: 'revenue', label: 'Revenue' },
];

const columns = computed(() => {
  const base = [
    { key: 'user', label: 'User' },
    { key: 'amount', label: 'Amount', align: 'right' },
  ];
  if (activeTab.value === 'deposits' || activeTab.value === 'withdrawals') {
    return [...base, { key: 'status', label: 'Status' }, { key: 'created_at', label: 'Date' }];
  }
  if (activeTab.value === 'fees') {
    return [...base, { key: 'type', label: 'Type' }, { key: 'created_at', label: 'Date' }];
  }
  if (activeTab.value === 'revenue') {
    return [{ key: 'source', label: 'Source' }, ...base, { key: 'created_at', label: 'Date' }];
  }
  return [{ key: 'type', label: 'Type' }, ...base, { key: 'created_at', label: 'Date' }];
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

const fetchData = async (page = 1) => {
  loading.value = true;
  try {
    const params = { tab: activeTab.value, ...filters, page };
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
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

const handlePageChange = (page) => {
  fetchData(page);
};

onMounted(() => fetchData(1));
</script>