<template>
  <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-white">Xavier Report Dashboard</h1>
      </div>

      <div v-if="loading" class="space-y-6">
        <SkeletonLoader type="card" :count="8" />
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <SkeletonLoader type="table" />
          <SkeletonLoader type="table" />
          <SkeletonLoader type="table" />
          <SkeletonLoader type="table" />
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <SkeletonLoader type="list" :count="5" />
          <SkeletonLoader type="list" :count="3" />
          <SkeletonLoader type="list" :count="5" />
        </div>
      </div>

      <template v-else>
        <!-- Summary Cards -->
        <SummaryCards :cards="summary.totals" />

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <ReportChart
            title="Revenue Trend"
            type="line"
            :categories="charts.revenueTrend.categories"
            :series="charts.revenueTrend.series"
          />
          <ReportChart
            title="Investment Trend"
            type="bar"
            :categories="charts.investmentTrend.categories"
            :series="charts.investmentTrend.series"
          />
          <ReportChart
            title="User Growth"
            type="line"
            :categories="charts.userGrowth.categories"
            :series="charts.userGrowth.series"
          />
          <ReportChart
            title="Transactions"
            type="bar"
            :categories="charts.transactions.categories"
            :series="charts.transactions.series"
          />
        </div>

        <!-- Bottom Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
            <h3 class="text-sm font-medium text-white mb-4">Recent Transactions</h3>
            <div v-if="recentTransactions.length === 0">
              <EmptyState message="No recent transactions" />
            </div>
            <div v-else class="space-y-3">
              <div v-for="tx in recentTransactions.slice(0, 5)" :key="tx.id" class="flex items-center justify-between py-2 border-b border-[#1f3348] last:border-0">
                <div>
                  <p class="text-sm text-white">{{ tx.user }}</p>
                  <p class="text-xs text-gray-500">{{ tx.type }} - {{ tx.date }}</p>
                </div>
                <span class="text-sm font-medium" :class="tx.type === 'deposit' ? 'text-green-400' : 'text-red-400'">
                  ${{ formatNumber(tx.amount) }}
                </span>
              </div>
            </div>
          </div>
          <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
            <h3 class="text-sm font-medium text-white mb-4">Latest Users</h3>
            <div class="space-y-3">
              <div class="flex items-center justify-between py-2 border-b border-[#1f3348]">
                <span class="text-sm text-gray-400">Total Users</span>
                <span class="text-sm font-medium text-white">{{ formatNumber(summary.totals[0]?.value) }}</span>
              </div>
              <div class="flex items-center justify-between py-2 border-b border-[#1f3348]">
                <span class="text-sm text-gray-400">Pending KYC</span>
                <span class="text-sm font-medium text-yellow-400">{{ formatNumber(summary.totals[6]?.value) }}</span>
              </div>
            </div>
          </div>
          <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
            <h3 class="text-sm font-medium text-white mb-4">Pending Approvals</h3>
            <div v-if="pendingApprovals.length === 0">
              <EmptyState message="No pending approvals" />
            </div>
            <div v-else class="space-y-3">
              <div v-for="(item, index) in pendingApprovals.slice(0, 5)" :key="index" class="flex items-center justify-between py-2 border-b border-[#1f3348] last:border-0">
                <div>
                  <p class="text-sm text-white">{{ item.user }}</p>
                  <p class="text-xs text-gray-500">{{ item.type }}</p>
                </div>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="item.status === 'pending' ? 'bg-yellow-900/50 text-yellow-400' : 'bg-gray-900/50 text-gray-400'">{{ item.status }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- System Health -->
        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
          <h3 class="text-sm font-medium text-white mb-4">System Health</h3>
          <div class="flex items-center gap-4">
            <div class="w-3 h-3 rounded-full" :class="systemHealth.healthy ? 'bg-green-400' : 'bg-red-400'"></div>
            <span class="text-sm text-white">{{ systemHealth.status }}</span>
            <div class="flex gap-4 ml-4">
              <div v-for="check in systemHealth.checks" :key="check.name" class="flex items-center gap-2">
                <span class="text-xs text-gray-400">{{ check.name }}</span>
                <span class="text-xs font-medium" :class="check.status === 'healthy' ? 'text-green-400' : 'text-red-400'">{{ check.status }}</span>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import SummaryCards from '@/Components/Reports/SummaryCards.vue';
import ReportChart from '@/Components/Reports/ReportChart.vue';
import EmptyState from '@/Components/Reports/EmptyState.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import api from '@/api';

const loading = ref(true);
const summary = ref({ totals: [] });
const charts = ref({
  revenueTrend: { categories: [], series: [] },
  investmentTrend: { categories: [], series: [] },
  userGrowth: { categories: [], series: [] },
  transactions: { categories: [], series: [] },
});
const recentTransactions = ref([]);
const pendingApprovals = ref([]);
const systemHealth = ref({ healthy: true, status: 'Healthy', checks: [] });

onMounted(async () => {
  try {
    const res = await api.get('/admin/reports/exec-dashboard');
    summary.value = res.data.summary || { totals: [] };
    charts.value = res.data.charts || {
      revenueTrend: { categories: [], series: [] },
      investmentTrend: { categories: [], series: [] },
      userGrowth: { categories: [], series: [] },
      transactions: { categories: [], series: [] },
    };
    recentTransactions.value = res.data.recentTransactions || [];
    pendingApprovals.value = res.data.pendingApprovals || [];
    systemHealth.value = res.data.systemHealth || { healthy: true, status: 'Healthy', checks: [] };
  } catch (e) {
    console.error('Failed to load dashboard:', e);
  } finally {
    loading.value = false;
  }
});

const formatNumber = (value) => {
  if (value === null || value === undefined) return '0';
  if (typeof value === 'number') {
    return value.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }
  return value;
};
</script>