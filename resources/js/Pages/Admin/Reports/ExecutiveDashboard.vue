<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-white">Executive Dashboard</h1>
        <p class="mt-1 text-sm text-gray-400">{{ todayLabel }}</p>
      </div>
    </div>

    <div class="flex items-center justify-between gap-4">
      <DateFilter @filter-change="onFilterChange" />
    </div>

    <div v-if="loading" class="space-y-6">
      <SkeletonLoader type="card" :count="4" />
      <SkeletonLoader type="card" :count="4" />
      <SkeletonLoader type="card" :count="4" />
      <SkeletonLoader type="table" />
    </div>

    <template v-else>
      <!-- Today's Summary Cards -->
      <div v-if="hasSummaryCards">
        <h2 class="mb-3 text-sm font-semibold text-gray-400 uppercase tracking-wider">Today's Summary</h2>
        <div v-for="(cards, section) in summaryCards" :key="section" class="mb-6">
          <h3 class="mb-3 text-sm font-medium tracking-wider text-gray-300 uppercase">{{ section }}</h3>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <SummaryCard v-for="card in cards" :key="card.title" v-bind="card" />
          </div>
        </div>
      </div>

      <!-- Financial KPI Cards -->
      <div>
        <h2 class="mb-3 text-sm font-semibold text-gray-400 uppercase tracking-wider">Financial KPIs</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard
            v-for="card in financialCards"
            :key="card.label"
            v-bind="card"
          />
        </div>
      </div>

      <!-- Business KPI Cards -->
      <div>
        <h2 class="mb-3 text-sm font-semibold text-gray-400 uppercase tracking-wider">Business KPIs</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard
            v-for="card in businessCards"
            :key="card.label"
            v-bind="card"
          />
        </div>
      </div>

      <!-- Operational KPI Cards -->
      <div>
        <h2 class="mb-3 text-sm font-semibold text-gray-400 uppercase tracking-wider">Operational KPIs</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard
            v-for="card in operationalCards"
            :key="card.label"
            v-bind="card"
          />
        </div>
      </div>

      <!-- Main Financial Chart -->
      <ReportChart
        v-if="charts.financial && charts.financial.labels && charts.financial.labels.length > 0"
        title="Revenue vs Expenses vs Profit"
        type="bar"
        :categories="charts.financial.labels"
        :series="charts.financial.series"
      />

      <!-- 30-Day Charts -->
      <div v-if="hasDailyCharts" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <ReportChart
          v-if="charts.usersGrowth"
          title="Users Registered (Last 30 Days)"
          type="area"
          currency-symbol=""
          :categories="charts.usersGrowth.categories"
          :series="charts.usersGrowth.series"
          :colors="['#0047AB']"
        />
        <ReportChart
          v-if="charts.depositsVsWithdrawals"
          title="Deposits vs Withdrawals"
          type="area"
          currency-symbol="$"
          :categories="charts.depositsVsWithdrawals.categories"
          :series="charts.depositsVsWithdrawals.series"
          :colors="['#10B981', '#EF4444']"
        />
        <ReportChart
          v-if="charts.investments"
          title="Investments"
          type="bar"
          currency-symbol=""
          :categories="charts.investments.categories"
          :series="charts.investments.series"
          :colors="['#8B5CF6']"
        />
        <ReportChart
          v-if="charts.revenue"
          title="Revenue"
          type="area"
          currency-symbol="$"
          :categories="charts.revenue.categories"
          :series="charts.revenue.series"
          :colors="['#F59E0B']"
        />
      </div>

      <!-- Latest Tables -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <ReportTable :columns="userColumns" :data="latestUsers">
          <template #header>
            <h3 class="text-lg font-semibold text-white">Latest Users</h3>
          </template>
          <template #cell-status="{ row }">
            <span :class="row.status === 'active' ? 'text-green-400' : row.status === 'suspended' ? 'text-red-400' : 'text-yellow-400'" class="text-xs font-medium capitalize">{{ row.status }}</span>
          </template>
        </ReportTable>
        <ReportTable :columns="depositColumns" :data="latestDeposits">
          <template #header>
            <h3 class="text-lg font-semibold text-white">Latest Deposits</h3>
          </template>
          <template #cell-amount="{ row }">
            <span class="block font-mono text-right">${{ formatNumber(Number(row.amount)) }}</span>
          </template>
          <template #cell-status="{ row }">
            <span :class="row.status === 'completed' ? 'text-green-400' : 'text-red-400'" class="text-xs font-medium capitalize">{{ row.status }}</span>
          </template>
        </ReportTable>
      </div>
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <ReportTable :columns="withdrawalColumns" :data="latestWithdrawals">
          <template #header>
            <h3 class="text-lg font-semibold text-white">Latest Withdrawals</h3>
          </template>
          <template #cell-amount="{ row }">
            <span class="block font-mono text-right">${{ formatNumber(Number(row.amount)) }}</span>
          </template>
          <template #cell-status="{ row }">
            <span :class="row.status === 'approved' ? 'text-green-400' : row.status === 'rejected' ? 'text-red-400' : 'text-yellow-400'" class="text-xs font-medium capitalize">{{ row.status }}</span>
          </template>
        </ReportTable>
        <ReportTable :columns="investmentColumns" :data="latestInvestments">
          <template #header>
            <h3 class="text-lg font-semibold text-white">Latest Investments</h3>
          </template>
          <template #cell-amount="{ row }">
            <span class="block font-mono text-right">${{ formatNumber(Number(row.amount)) }}</span>
          </template>
          <template #cell-status="{ row }">
            <span :class="row.status === 'filled' ? 'text-green-400' : 'text-red-400'" class="text-xs font-medium capitalize">{{ row.status }}</span>
          </template>
        </ReportTable>
      </div>

      <!-- Alerts / Action Items -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <h2 class="mb-4 text-lg font-semibold text-white">Action Required</h2>
        <div v-if="alerts.length === 0" class="text-sm text-gray-500">
          No outstanding actions
        </div>
        <div v-else class="space-y-2">
          <router-link
            v-for="alert in alerts"
            :key="alert.type"
            :to="alert.link"
            class="flex items-center justify-between px-4 py-3 rounded-lg bg-[#1C2541] hover:bg-[#16213A] transition-colors"
          >
            <div class="flex items-center gap-3">
              <AlertTriangle
                v-if="alert.level === 'warning'"
                class="w-4 h-4 text-yellow-400"
              />
              <Info
                v-else
                class="w-4 h-4 text-blue-400"
              />
              <span class="text-sm text-white">{{ alert.message }}</span>
            </div>
            <ChevronRight class="w-4 h-4 text-gray-500" />
          </router-link>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { AlertTriangle, Info, ChevronRight } from 'lucide-vue-next';
import StatCard from '@/Components/Reports/StatCard.vue';
import SummaryCard from '@/Components/Reports/SummaryCard.vue';
import DateFilter from '@/Components/Reports/DateFilter.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import ReportChart from '@/Components/Reports/ReportChart.vue';
import ReportTable from '@/Components/Reports/ReportTable.vue';
import api from '@/api';

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

const loading = ref(true);
const data = ref({
  summary: {},
  business: {},
  operational: {},
  summary_cards: {},
  charts: {},
  alerts: [],
  latest_users: [],
  latest_deposits: [],
  latest_withdrawals: [],
  latest_investments: [],
});
const filters = reactive({
  start_date: '',
  end_date: '',
  period: 'month',
});

const todayLabel = new Date().toLocaleDateString('en-US', {
  weekday: 'long',
  year: 'numeric',
  month: 'long',
  day: 'numeric',
});

const financialCards = computed(() => [
  { label: 'Revenue', value: data.value.summary.total_revenue || 0, icon: 'dollar', color: '#0047AB', prefix: '₦' },
  { label: 'Expenses', value: data.value.summary.total_expenses || 0, icon: 'trending-down', color: '#EF4444', prefix: '₦' },
  { label: 'Net Profit', value: data.value.summary.net_profit || 0, icon: 'activity', color: data.value.summary.result === 'profit' ? '#10B981' : '#DC2626', prefix: '₦' },
  { label: 'Profit Margin', value: data.value.summary.profit_margin || 0, icon: 'pie-chart', color: '#8B5CF6', suffix: '%' },
]);

const businessCards = computed(() => [
  { label: 'Total Users', value: data.value.business.total_users || 0, icon: 'users', color: '#3B82F6' },
  { label: 'Active Users', value: data.value.business.active_users || 0, icon: 'user-check', color: '#10B981' },
  { label: 'Total Investments', value: data.value.business.total_investments || 0, icon: 'trending-up', color: '#F59E0B' },
  { label: 'Investment Value', value: data.value.business.investment_value || 0, icon: 'dollar', color: '#8B5CF6', prefix: '₦' },
]);

const operationalCards = computed(() => [
  { label: 'Pending KYC', value: data.value.operational.pending_kyc || 0, icon: 'shield', color: '#F59E0B' },
  { label: 'Maturing Investments', value: data.value.operational.maturing_investments || 0, icon: 'clock', color: '#06B6D4' },
  { label: 'Active Subscriptions', value: data.value.operational.active_subscriptions || 0, icon: 'zap', color: '#10B981' },
  { label: 'Pending Expenses', value: data.value.operational.pending_expenses || 0, icon: 'file-text', color: '#EF4444' },
]);

const summaryCards = computed(() => data.value.summary_cards || {});
const hasSummaryCards = computed(() => Object.keys(summaryCards.value).length > 0);
const charts = computed(() => data.value.charts || {});
const hasDailyCharts = computed(() => !!(
  charts.value.usersGrowth ||
  charts.value.depositsVsWithdrawals ||
  charts.value.investments ||
  charts.value.revenue
));
const alerts = computed(() => data.value.alerts || []);
const latestUsers = computed(() => data.value.latest_users || []);
const latestDeposits = computed(() => data.value.latest_deposits || []);
const latestWithdrawals = computed(() => data.value.latest_withdrawals || []);
const latestInvestments = computed(() => data.value.latest_investments || []);

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

const fetchData = async () => {
  loading.value = true;
  try {
    const params = {};
    if (filters.start_date) params.start_date = filters.start_date;
    if (filters.end_date) params.end_date = filters.end_date;
    if (filters.period) params.period = filters.period;

    const res = await api.get('/admin/reports/executive-dashboard', { params });
    data.value = res.data || {
      summary: {},
      business: {},
      operational: {},
      summary_cards: {},
      charts: {},
      alerts: [],
      latest_users: [],
      latest_deposits: [],
      latest_withdrawals: [],
      latest_investments: [],
    };
  } catch (e) {
    console.error('Failed to load executive dashboard:', e);
  } finally {
    loading.value = false;
  }
};

const onFilterChange = (payload) => {
  filters.start_date = payload.start_date || '';
  filters.end_date = payload.end_date || '';
  filters.period = payload.period || 'month';
  fetchData();
};

onMounted(fetchData);
</script>