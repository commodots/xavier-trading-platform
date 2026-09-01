<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-bold text-white">Financial Summary</h1>
    </div>

    <div class="flex items-center justify-between gap-4">
      <DateFilter @filter-change="onFilterChange" />
      <ExportButton
        reportType="financial-summary"
        :startDate="filters.start_date"
        :endDate="filters.end_date"
      />
    </div>

    <div v-if="loading" class="space-y-6">
      <SkeletonLoader type="card" :count="4" />
      <SkeletonLoader type="table" />
    </div>

    <template v-else>
      <!-- KPI Cards -->
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          v-for="card in summaryCards"
          :key="card.label"
          v-bind="card"
        />
      </div>

      <!-- Main Financial Chart -->
      <ReportChart
        v-if="charts.profit_loss && charts.profit_loss.labels && charts.profit_loss.labels.length > 0"
        title="Revenue vs Expenses vs Profit"
        type="bar"
        :categories="charts.profit_loss.labels"
        :series="charts.profit_loss.series"
      />

      <!-- Monthly Financial Summary -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <h2 class="mb-4 text-lg font-semibold text-white">Monthly Financial Summary</h2>
        <ReportTable
          :columns="tableColumns"
          :data="tableRows"
          :filters="monthFilters"
        >
          <template #cell-revenue="{ row }">
            <span class="font-mono">₦{{ formatNumber(row.revenue) }}</span>
          </template>
          <template #cell-expenses="{ row }">
            <span class="font-mono">₦{{ formatNumber(row.expenses) }}</span>
          </template>
          <template #cell-profit="{ row }">
            <span class="font-mono" :class="row.profit >= 0 ? 'text-green-400' : 'text-red-400'">
              ₦{{ formatNumber(row.profit) }}
            </span>
          </template>
          <template #cell-margin="{ row }">
            <span>{{ row.margin }}%</span>
          </template>
        </ReportTable>
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
import ExportButton from '@/Components/Reports/ExportButton.vue';
import ReportChart from '@/Components/Reports/ReportChart.vue';
import api from '@/api';

const loading = ref(true);
const summary = ref({});
const charts = ref({});
const tableData = ref([]);
const filters = reactive({
  start_date: '',
  end_date: '',
  period: 'month',
});

const summaryCards = computed(() => [
  { label: 'Total Revenue', value: summary.value.total_revenue || 0, icon: 'dollar', color: '#0047AB', prefix: '₦' },
  { label: 'Total Expenses', value: summary.value.total_expenses || 0, icon: 'trending-down', color: '#EF4444', prefix: '₦' },
  { label: 'Net Profit', value: summary.value.net_profit || 0, icon: 'activity', color: summary.value.result === 'profit' ? '#10B981' : '#DC2626', prefix: '₦' },
  { label: 'Profit Margin', value: summary.value.profit_margin || 0, icon: 'pie-chart', color: '#8B5CF6', suffix: '%' },
]);

const tableColumns = [
  { key: 'month', label: 'Month' },
  { key: 'revenue', label: 'Revenue', align: 'right' },
  { key: 'expenses', label: 'Expenses', align: 'right' },
  { key: 'profit', label: 'Net Profit', align: 'right' },
  { key: 'margin', label: 'Margin', align: 'right' },
];

const tableRows = computed(() => tableData.value);

const monthFilters = computed(() => [
  {
    key: 'month',
    label: 'Month',
    allLabel: 'All Months',
    options: [...new Set(tableData.value.map((row) => row.month).filter(Boolean))],
  },
]);

const fetchData = async () => {
  loading.value = true;
  try {
    const params = {};
    if (filters.start_date) params.start_date = filters.start_date;
    if (filters.end_date) params.end_date = filters.end_date;
    if (filters.period) params.period = filters.period;

    const res = await api.get('/admin/reports/financial-summary', { params });
    summary.value = res.data.summary || {};
    charts.value = res.data.charts || {};
    tableData.value = res.data.table || [];
  } catch (e) {
    console.error('Failed to load financial summary:', e);
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

const formatNumber = (num) => {
  if (num === null || num === undefined) return '0';
  return Number(num).toLocaleString('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  });
};

onMounted(fetchData);
</script>