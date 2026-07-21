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

    <div class="flex gap-1 bg-[#0F1724] border border-[#1f3348] rounded-xl p-1 w-fit">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key; fetchData()" :class="activeTab === tab.key ? 'bg-[#0047AB] text-white' : 'text-gray-400 hover:text-white'" class="px-4 py-2 rounded-lg text-sm font-medium transition">{{ tab.label }}</button>
    </div>

    <DateRangeFilter v-model:from="filters.from" v-model:to="filters.to" />

    <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
      <SkeletonLoader type="table" :count="8" class="opacity-40" />
    </div>
    <ReportTable v-else :columns="columns" :data="rows">
      <template #cell-status="{ row }">
        <span :class="row.status === 'approved' ? 'text-green-400' : row.status === 'rejected' ? 'text-red-400' : 'text-yellow-400'" class="text-xs font-medium">{{ row.status }}</span>
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
      { key: 'user', label: 'User' },
      { key: 'amount', label: 'Amount', align: 'right' },
      { key: 'method', label: 'Method' },
      { key: 'status', label: 'Status' },
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

const fetchData = async () => {
  loading.value = true;
  try {
    const [sumRes, dataRes] = await Promise.all([
      api.get('/admin/reports/wallet-withdrawals/summary'),
      api.get('/admin/reports/wallet-withdrawals', { params: { tab: activeTab.value, ...filters } }),
    ]);
    withdrawalSummary.value = sumRes.data.withdrawals || [];
    walletSummary.value = sumRes.data.wallet || [];
    rows.value = dataRes.data.data || [];
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchData);
</script>