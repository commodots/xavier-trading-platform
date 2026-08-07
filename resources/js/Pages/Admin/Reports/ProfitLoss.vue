<template>
  <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-white">Profit & Loss</h1>
      </div>

      <div class="flex items-center justify-between gap-4">
        <DateFilter @filter-change="onFilterChange" />
        <ExportButton
          reportType="profit-loss"
          :startDate="filters.start_date"
          :endDate="filters.end_date"
        />
      </div>

      <div v-if="loading" class="space-y-6">
        <SkeletonLoader type="card" :count="4" />
        <SkeletonLoader type="list" :count="5" />
        <SkeletonLoader type="list" :count="5" />
        <SkeletonLoader type="table" />
      </div>

      <template v-else>
        <SummaryCards :cards="summaryCards" />

        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
          <h3 class="text-sm font-medium text-white mb-4">Income</h3>
          <div class="space-y-2">
            <div v-for="item in data.income.breakdown" :key="item.source" class="flex items-center justify-between py-2 border-b border-[#1f3348] last:border-0">
              <span class="text-sm text-gray-300">{{ item.source }}</span>
              <div class="flex items-center gap-4">
                <span class="text-sm text-white">${{ formatNumber(item.amount) }}</span>
                <span class="text-xs text-gray-500 w-12 text-right">{{ item.percentage }}%</span>
              </div>
            </div>
            <div class="flex items-center justify-between py-2 font-medium">
              <span class="text-sm text-white">Total Income</span>
              <span class="text-sm font-bold text-green-400">${{ formatNumber(data.income.total) }}</span>
            </div>
          </div>
        </div>

        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
          <h3 class="text-sm font-medium text-white mb-4">Expenses</h3>
          <div class="space-y-2">
            <div v-for="item in data.expenses.breakdown" :key="item.category" class="flex items-center justify-between py-2 border-b border-[#1f3348] last:border-0">
              <span class="text-sm text-gray-300">{{ item.category }}</span>
              <div class="flex items-center gap-4">
                <span class="text-sm text-white">{{ item.currency || 'NGN' }} {{ formatNumber(item.amount) }}</span>
                <span class="text-xs text-gray-500 w-12 text-right">{{ item.percentage }}%</span>
              </div>
            </div>
            <div class="flex items-center justify-between py-2 font-medium">
              <span class="text-sm text-white">Total Expenses (NGN)</span>
              <span class="text-sm font-bold text-red-400">₦{{ formatNumber(data.expenses.total_ngn) }}</span>
            </div>
            <div class="flex items-center justify-between py-2 font-medium">
              <span class="text-sm text-white">Total Expenses (USD)</span>
              <span class="text-sm font-bold text-red-400">${{ formatNumber(data.expenses.total_usd) }}</span>
            </div>
          </div>
        </div>

        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-medium text-white">Net Profit</h3>
              <p class="text-xs text-gray-500">Profit Margin: {{ data.profit.margin }}%</p>
            </div>
            <div class="text-right">
              <span class="text-2xl font-bold" :class="data.profit.net_profit >= 0 ? 'text-green-400' : 'text-red-400'">
                ${{ formatNumber(data.profit.net_profit) }}
              </span>
              <p class="text-xs text-gray-500">Income - Expenses (USD)</p>
            </div>
          </div>
        </div>

        <div v-if="data.profit.net_loss > 0" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-medium text-white">Net Loss</h3>
              <p class="text-xs text-gray-500">Expenses exceed income</p>
            </div>
            <div class="text-right">
              <span class="text-2xl font-bold text-red-400">{{ getCurrencySymbol(data.profit.net_loss_currency) }}{{ formatNumber(data.profit.net_loss) }}</span>
              <p class="text-xs text-gray-500">Loss for the period</p>
            </div>
          </div>
        </div>

        <ReportChart title="Monthly Profit & Loss" type="line" :categories="data.chart.categories" :series="data.chart.series" />
      </template>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import SummaryCards from '@/Components/Reports/SummaryCards.vue';
import DateFilter from '@/Components/Reports/DateFilter.vue';
import ReportChart from '@/Components/Reports/ReportChart.vue';
import ExportButton from '@/Components/Reports/ExportButton.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import api from '@/api';

const loading = ref(true);
const data = ref({ income: { total: 0, breakdown: [] }, expenses: { total: 0, breakdown: [] }, profit: { income: 0, expenses: 0, net_profit: 0, margin: 0 }, chart: { categories: [], series: [] } });
const filters = ref({ start_date: '', end_date: '', period: 'month' });

const getCurrencySymbol = (currency) => {
  const symbols = {
    'USD': '$',
    'NGN': '₦',
    'GBP': '£',
    'EUR': '€',
  };
  return symbols[currency] || '$';
};

const summaryCards = computed(() => [
  { label: 'Total Income', value: data.value.profit.income, icon: 'TrendingUp', color: '#10B981', prefix: '$' },
  { label: 'Total Expenses (NGN)', value: data.value.profit.expenses_ngn, icon: 'TrendingDown', color: '#EF4444', prefix: '₦' },
  { label: 'Total Expenses (USD)', value: data.value.profit.expenses_usd, icon: 'TrendingDown', color: '#F59E0B', prefix: '$' },
  { label: 'Net Profit', value: data.value.profit.net_profit, icon: 'DollarSign', color: '#0047AB', prefix: '$' },
  { label: 'Net Loss', value: data.value.profit.net_loss, icon: 'AlertTriangle', color: '#DC2626', prefix: getCurrencySymbol(data.value.profit.net_loss_currency) },
  { label: 'Margin %', value: data.value.profit.margin + '%', icon: 'PieChart', color: '#8B5CF6' },
]);

const exportRows = computed(() => {
  const rows = [];
  data.value.income.breakdown.forEach(item => rows.push({ source: item.source, amount: item.amount, type: 'Income' }));
  data.value.expenses.breakdown.forEach(item => rows.push({ source: item.category, amount: item.amount, type: 'Expense' }));
  return rows;
});

const fetchData = async () => {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.start_date) params.start_date = filters.value.start_date;
    if (filters.value.end_date) params.end_date = filters.value.end_date;
    if (filters.value.period) params.period = filters.value.period;
    const res = await api.get('/admin/reports/profit-loss', { params });
    data.value = res.data || { income: { total: 0, breakdown: [] }, expenses: { total: 0, breakdown: [] }, profit: { income: 0, expenses: 0, net_profit: 0, margin: 0 }, chart: { categories: [], series: [] } };
  } catch (e) {
    console.error('Failed to load P&L:', e);
  } finally {
    loading.value = false;
  }
};

const onFilterChange = (newFilters) => {
  filters.value = newFilters;
  fetchData();
};

const formatNumber = (value) => {
  if (value === null || value === undefined) return '0.00';
  if (typeof value === 'number') return value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  return value;
};

onMounted(fetchData);
</script>