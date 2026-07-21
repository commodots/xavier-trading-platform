<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-white">Withdrawal & Wallet Report</h1>

    <!-- Summary Cards -->
    <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-4">
        <h3 class="text-lg font-semibold text-white mb-3">Withdrawal Summary</h3>
        <div class="grid grid-cols-2 gap-4">
          <div v-for="i in 4" :key="i" class="p-4 bg-[#0F1724] border border-[#1f3348] rounded-lg space-y-3 animate-pulse">
            <div class="h-3 bg-gray-700 rounded w-20"></div>
            <div class="h-6 bg-gray-700 rounded w-16"></div>
          </div>
        </div>
      </div>
      <div class="space-y-4">
        <h3 class="text-lg font-semibold text-white mb-3">Wallet Summary</h3>
        <div class="grid grid-cols-2 gap-4">
          <div v-for="i in 4" :key="i" class="p-4 bg-[#0F1724] border border-[#1f3348] rounded-lg space-y-3 animate-pulse">
            <div class="h-3 bg-gray-700 rounded w-20"></div>
            <div class="h-6 bg-gray-700 rounded w-16"></div>
          </div>
        </div>
      </div>
    </div>
    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div>
        <h3 class="text-lg font-semibold text-white mb-3">Withdrawal Summary</h3>
        <div class="grid grid-cols-2 gap-4">
          <StatCard v-for="s in withdrawalSummary" :key="s.label" v-bind="s" />
        </div>
      </div>
      <div>
        <h3 class="text-lg font-semibold text-white mb-3">Wallet Summary</h3>
        <div class="grid grid-cols-2 gap-4">
          <StatCard v-for="s in walletSummary" :key="s.label" v-bind="s" />
        </div>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-4">
      <div class="flex gap-1 bg-[#0F1724] border border-[#1f3348] rounded-xl p-1 w-fit">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key; fetchData(1)" :class="activeTab === tab.key ? 'bg-[#0047AB] text-white' : 'text-gray-400 hover:text-white'" class="px-4 py-2 rounded-lg text-sm font-medium transition">{{ tab.label }}</button>
      </div>
      <DateRangeFilter v-model:from="filters.from" v-model:to="filters.to" />
      <select v-model="filters.status" class="bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm outline-none">
        <option value="" selected >All Status</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="paid">Paid</option>
      </select>
      <button @click="fetchData(1)" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm">Search</button>
      <button @click="resetFilters" class="px-4 py-2 bg-gray-700 text-white rounded-lg text-sm">Reset</button>
      <ExportButton @export-csv="exportReport('csv')" @export-excel="exportReport('excel')" />
    </div>

    <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
      <SkeletonLoader type="table" :count="8" class="opacity-40" />
    </div>
    <ReportTable v-else :columns="columns" :data="rows" :sort-by="sortBy" :sort-dir="sortDir" @sort="handleSort">
      <template #cell-status="{ row }">
        <span :class="row.status === 'approved' || row.status === 'paid' ? 'text-green-400' : row.status === 'rejected' ? 'text-red-400' : 'text-yellow-400'" class="text-xs font-medium capitalize">{{ row.status }}</span>
      </template>
      <template #cell-actions="{ row }" v-if="activeTab === 'withdrawals'">
        <div class="flex gap-2">
          <button v-if="row.status === 'pending'" @click="approveWithdrawal(row.id)" class="text-green-400 hover:text-green-300 text-xs">Approve</button>
          <button v-if="row.status === 'pending'" @click="rejectWithdrawal(row.id)" class="text-red-400 hover:text-red-300 text-xs">Reject</button>
          <button @click="viewWithdrawal(row.id)" class="text-blue-400 hover:text-blue-300 text-xs">View</button>
        </div>
      </template>
    </ReportTable>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import StatCard from '@/Components/Reports/StatCard.vue';
import ReportTable from '@/Components/Reports/ReportTable.vue';
import DateRangeFilter from '@/Components/Reports/DateRangeFilter.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import api from '@/api';

const withdrawalSummary = ref([]);
const walletSummary = ref([]);
const rows = ref([]);
const loading = ref(false);
const activeTab = ref('withdrawals');
const filters = reactive({ from: '', to: '' });

const tabs = [
  { key: 'withdrawals', label: 'Withdrawals' },
  { key: 'wallet_ledger', label: 'Wallet Ledger' },
  { key: 'adjustments', label: 'Adjustments' },
];

const columns = computed(() => {
  if (activeTab.value === 'withdrawals') {
    return [
      { key: 'user', label: 'User', sortable: true },
      { key: 'amount', label: 'Amount', align: 'right', sortable: true },
      { key: 'method', label: 'Method' },
      { key: 'status', label: 'Status' },
      { key: 'created_at', label: 'Requested', sortable: true },
      { key: 'actions', label: 'Actions', width: '180px' },
    ];
  }
  if (activeTab.value === 'wallet_ledger') {
    return [
      { key: 'type', label: 'Type' },
      { key: 'user', label: 'User' },
      { key: 'amount', label: 'Amount', align: 'right' },
      { key: 'currency', label: 'Currency' },
      { key: 'created_at', label: 'Date' },
    ];
  }
  if (activeTab.value === 'adjustments') {
    return [
      { key: 'user', label: 'User' },
      { key: 'amount', label: 'Amount', align: 'right' },
      { key: 'type', label: 'Type' },
      { key: 'reason', label: 'Reason' },
      { key: 'created_at', label: 'Date' },
    ];
  }
  return [
    { key: 'type', label: 'Type' },
    { key: 'amount', label: 'Amount', align: 'right' },
    { key: 'currency', label: 'Currency' },
    { key: 'created_at', label: 'Date' },
  ];
});

const sortBy = ref('');
const sortDir = ref('desc');
const pagination = ref({ current_page: 1, last_page: 1, per_page: 50, total: 0 });

const fetchData = async (page = 1) => {
  loading.value = true;
  try {
    const params = { tab: activeTab.value, ...filters, page, sort: sortBy.value, dir: sortDir.value };
    const [sumRes, dataRes] = await Promise.all([
      api.get('/admin/reports/wallet-withdrawals/summary'),
      api.get('/admin/reports/wallet-withdrawals', { params }),
    ]);
    withdrawalSummary.value = sumRes.data.withdrawals || [];
    walletSummary.value = sumRes.data.wallet || [];
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

const approveWithdrawal = async (id) => {
  if (!confirm('Are you sure you want to approve this withdrawal?')) return;
  try {
    await api.post(`/security/withdrawals/${id}/approve`);
    alert('Withdrawal approved successfully');
    fetchData();
  } catch (e) {
    console.error(e);
    alert('Failed to approve withdrawal');
  }
};

const rejectWithdrawal = async (id) => {
  if (!confirm('Are you sure you want to reject this withdrawal?')) return;
  try {
    await api.post(`/security/withdrawals/${id}/reject`);
    alert('Withdrawal rejected successfully');
    fetchData();
  } catch (e) {
    console.error(e);
    alert('Failed to reject withdrawal');
  }
};

const viewWithdrawal = (id) => {
  window.open(`/admin/users?withdrawal=${id}`, '_blank');
};

const exportReport = async (format) => {
  try {
    const params = { tab: activeTab.value, ...filters, export: format };
    const res = await api.get('/admin/reports/wallet-withdrawals', { params, responseType: 'blob' });
    const blob = new Blob([res.data]);
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `wallet-withdrawals-report-${activeTab.value}.${format === 'csv' ? 'csv' : 'xlsx'}`;
    a.click();
    window.URL.revokeObjectURL(url);
  } catch (e) {
    console.error(e);
  }
};

const handlePageChange = (page) => {
  fetchData(page);
};

onMounted(() => fetchData(1));
</script>