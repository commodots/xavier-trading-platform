<template>
  <div class="space-y-6">
    <!-- Summary Cards -->
    <div v-if="loading" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <div class="space-y-4">
        <h3 class="mb-3 text-lg font-semibold text-white">Withdrawal Summary</h3>
        <div class="grid grid-cols-2 gap-4">
          <SkeletonLoader type="card" :count="4" class="opacity-40" />
        </div>
      </div>
      <div class="space-y-4">
        <h3 class="mb-3 text-lg font-semibold text-white">Wallet Summary</h3>
        <div class="grid grid-cols-2 gap-4">
          <SkeletonLoader type="card" :count="4" class="opacity-40" />
        </div>
      </div>
    </div>
    <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <div>
        <h3 class="mb-3 text-lg font-semibold text-white">Withdrawal Summary</h3>
        <div class="grid grid-cols-2 gap-4">
          <StatCard v-for="s in withdrawalSummary" :key="s.label" v-bind="s" />
        </div>
      </div>
      <div>
        <h3 class="mb-3 text-lg font-semibold text-white">Wallet Summary</h3>
        <div class="grid grid-cols-2 gap-4">
          <StatCard v-for="s in walletSummary" :key="s.label" v-bind="s" />
        </div>
      </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white">Withdrawal & Wallet Report</h1>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-4">
      <div class="flex gap-1 bg-[#0F1724] border border-[#1f3348] rounded-xl p-1 w-fit">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key; fetchData(1)" :class="activeTab === tab.key ? 'bg-[#0047AB] text-white' : 'text-gray-400 hover:text-white'" class="px-4 py-2 text-sm font-medium transition rounded-lg">{{ tab.label }}</button>
      </div>
      <DateFilter @filter-change="handleFilterChange" />
      <select v-model="filters.status" class="bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm outline-none">
        <option disabled value="" class="text-white">Status</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="paid">Paid</option>
      </select>
      <button @click="fetchData(1)" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm">Search</button>
      <button @click="resetFilters" class="px-4 py-2 text-sm text-white bg-gray-700 rounded-lg">Reset</button>
      <ExportButton
        reportType="wallet-withdrawals"
        :startDate="filters.from"
        :endDate="filters.to"
        :extraParams="{ tab: activeTab, status: filters.status }"
      />
    </div>

    <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
      <SkeletonLoader type="table" :count="8" class="opacity-40" />
    </div>
    <ReportTable v-else :columns="columns" :data="rows" :sort-by="sortBy" :sort-dir="sortDir" @sort="handleSort" :title="activeTab === 'withdrawals' ? 'Withdrawal activity' : activeTab === 'wallet_ledger' ? 'Wallet ledger' : 'Adjustment history'" :description="activeTab === 'withdrawals' ? 'Withdrawal requests and their review outcome.' : activeTab === 'wallet_ledger' ? 'Wallet movements for the selected range.' : 'Manual balance adjustments and reasons.'">
      <template #cell-status="{ row }">
        <span :class="row.status === 'approved' || row.status === 'paid' ? 'text-green-400' : row.status === 'rejected' ? 'text-red-400' : 'text-yellow-400'" class="text-xs font-medium capitalize">{{ row.status }}</span>
      </template>
      <template #cell-actions="{ row }" v-if="activeTab === 'withdrawals'">
        <div class="flex gap-2">
          <button v-if="row.status === 'pending'" @click="approveWithdrawal(row.id)" class="text-xs text-green-400 hover:text-green-300">Approve</button>
          <button v-if="row.status === 'pending'" @click="rejectWithdrawal(row.id)" class="text-xs text-red-400 hover:text-red-300">Reject</button>
          <button @click="viewWithdrawal(row.id)" class="text-xs text-blue-400 hover:text-blue-300">View</button>
        </div>
      </template>
    </ReportTable>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import StatCard from '@/Components/Reports/StatCard.vue';
import ReportTable from '@/Components/Reports/ReportTable.vue';
import DateFilter from '@/Components/Reports/DateFilter.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import ExportButton from '@/Components/Reports/ExportButton.vue';
import api from '@/api';

const withdrawalSummary = ref([]);
const walletSummary = ref([]);
const rows = ref([]);
const loading = ref(false);
const activeTab = ref('withdrawals');
const filters = reactive({ from: '', to: '', period: 'month' });

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

const handleFilterChange = (payload) => {
  filters.from = payload.start_date || '';
  filters.to = payload.end_date || '';
  filters.period = payload.period || 'month';
  fetchData(1);
};

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
  filters.period = 'month';
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

const handlePageChange = (page) => {
  fetchData(page);
};

onMounted(() => fetchData(1));
</script>