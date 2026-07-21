<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Xavier Report Dashboard</h1>
        <p class="text-sm text-gray-400 mt-1">Today's Summary - {{ new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
      </div>
      <button @click="fetchDashboard" :disabled="loading" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm hover:bg-blue-600 transition disabled:opacity-50 disabled:cursor-not-allowed">
        <span v-if="loading">Refreshing...</span>
        <span v-else>Refresh</span>
      </button>
    </div>

    <!-- Today's Summary Cards -->
    <div class="mb-8">
      <h2 class="text-lg font-semibold text-white mb-4">Today's Summary</h2>
      <SkeletonLoader v-if="loading" type="card" :count="16" class="opacity-40" />
      <div v-else>
        <div v-for="(cards, section) in summary" :key="section">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <SummaryCard v-for="card in cards" :key="card.title" v-bind="card" />
          </div>
        </div>
      </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <h3 class="text-sm font-medium text-gray-300 mb-4">Users Registered (Last 30 Days)</h3>
        <apexchart v-if="charts?.usersGrowth" type="area" height="300" :options="chartOptions('#0047AB')" :series="charts.usersGrowth.series" />
      </div>
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <h3 class="text-sm font-medium text-gray-300 mb-4">Deposits vs Withdrawals</h3>
        <apexchart v-if="charts?.depositsVsWithdrawals" type="area" height="300" :options="chartOptions(['#10B981', '#EF4444'])" :series="charts.depositsVsWithdrawals.series" />
      </div>
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <h3 class="text-sm font-medium text-gray-300 mb-4">Investments</h3>
        <apexchart v-if="charts?.investments" type="bar" height="300" :options="chartOptions('#8B5CF6', 'bar')" :series="charts.investments.series" />
      </div>
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <h3 class="text-sm font-medium text-gray-300 mb-4">Revenue</h3>
        <apexchart v-if="charts?.revenue" type="area" height="300" :options="chartOptions('#F59E0B')" :series="charts.revenue.series" />
      </div>
    </div>

    <!-- Latest Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <ReportTable :columns="userColumns" :data="latestUsers">
        <template #header><h3 class="text-lg font-semibold text-white">Latest Users</h3></template>
        <template #cell-status="{ row }">
          <span :class="row.status === 'active' ? 'text-green-400' : row.status === 'suspended' ? 'text-red-400' : 'text-yellow-400'" class="text-xs font-medium capitalize">{{ row.status }}</span>
        </template>
      </ReportTable>
      <ReportTable :columns="depositColumns" :data="latestDeposits">
        <template #header><h3 class="text-lg font-semibold text-white">Latest Deposits</h3></template>
        <template #cell-amount="{ row }">
          <span class="text-right block font-mono">${{ Number(row.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
        </template>
        <template #cell-status="{ row }">
          <span :class="row.status === 'completed' ? 'text-green-400' : 'text-red-400'" class="text-xs font-medium capitalize">{{ row.status }}</span>
        </template>
      </ReportTable>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <ReportTable :columns="withdrawalColumns" :data="latestWithdrawals">
        <template #header><h3 class="text-lg font-semibold text-white">Latest Withdrawals</h3></template>
        <template #cell-amount="{ row }">
          <span class="text-right block font-mono">${{ Number(row.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
        </template>
        <template #cell-status="{ row }">
          <span :class="row.status === 'approved' ? 'text-green-400' : row.status === 'rejected' ? 'text-red-400' : 'text-yellow-400'" class="text-xs font-medium capitalize">{{ row.status }}</span>
        </template>
      </ReportTable>
      <ReportTable :columns="investmentColumns" :data="latestInvestments">
        <template #header><h3 class="text-lg font-semibold text-white">Latest Investments</h3></template>
        <template #cell-amount="{ row }">
          <span class="text-right block font-mono">${{ Number(row.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
        </template>
        <template #cell-status="{ row }">
          <span :class="row.status === 'filled' ? 'text-green-400' : 'text-red-400'" class="text-xs font-medium capitalize">{{ row.status }}</span>
        </template>
      </ReportTable>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import SummaryCard from '@/Components/Reports/SummaryCard.vue';
import ReportTable from '@/Components/Reports/ReportTable.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import api from '@/api';

const loading = ref(false);
const summary = ref(null);
const charts = ref(null);
const latestUsers = ref([]);
const latestDeposits = ref([]);
const latestWithdrawals = ref([]);
const latestInvestments = ref([]);

const userColumns = [
  { key: 'name', label: 'Name' },
  { key: 'email', label: 'Email' },
  { key: 'country', label: 'Country' },
  { key: 'status', label: 'Status' },
  { key: 'joined', label: 'Joined' },
];
const depositColumns = [
  { key: 'user', label: 'User' },
  { key: 'amount', label: 'Amount', align: 'right' },
  { key: 'method', label: 'Method' },
  { key: 'status', label: 'Status' },
  { key: 'time', label: 'Time' },
];
const withdrawalColumns = [
  { key: 'user', label: 'User' },
  { key: 'amount', label: 'Amount', align: 'right' },
  { key: 'status', label: 'Status' },
  { key: 'requested', label: 'Requested' },
];
const investmentColumns = [
  { key: 'user', label: 'User' },
  { key: 'plan', label: 'Plan' },
  { key: 'amount', label: 'Amount', align: 'right' },
  { key: 'status', label: 'Status' },
];

const chartOptions = (colors, type = 'area') => ({
  chart: {
    type,
    toolbar: { show: false },
    foreColor: '#9CA3AF',
    zoom: { enabled: false },
  },
  colors: Array.isArray(colors) ? colors : [colors],
  grid: { borderColor: '#1f3348', strokeDashArray: 3 },
  stroke: { curve: 'smooth', width: 2 },
  fill: type === 'area' ? { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0 } } : {},
  dataLabels: { enabled: false },
  xaxis: {
    type: 'category',
    labels: { style: { colors: '#6B7280', fontSize: '10px' } },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: { labels: { style: { colors: '#6B7280', fontSize: '10px' } } },
  legend: { position: 'top', labels: { colors: '#D1D5DB' } },
  tooltip: { theme: 'dark' },
});

const fetchDashboard = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/reports/dashboard');
    const d = res.data;
    summary.value = d.summary;
    charts.value = d.charts;
    latestUsers.value = d.latest_users || [];
    latestDeposits.value = d.latest_deposits || [];
    latestWithdrawals.value = d.latest_withdrawals || [];
    latestInvestments.value = d.latest_investments || [];
  } catch (e) {
    console.error('Failed to load dashboard:', e);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchDashboard);
</script>