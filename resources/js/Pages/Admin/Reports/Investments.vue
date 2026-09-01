<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <h1 class="text-2xl font-bold text-white">Investment Report</h1>
    </div>

    <!-- Summary Cards -->
    <SkeletonLoader v-if="loading" type="card" :count="6" class="opacity-40" />
    <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-3">
      <StatCard v-for="s in summaryCards" :key="s.label" v-bind="s" />
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <DateFilter @filter-change="handleFilterChange" />
      <ExportButton
        reportType="investments"
        :startDate="filters.start_date"
        :endDate="filters.end_date"
        :extraParams="{ status: filters.status, plan: filters.plan }"
      />
    </div>

    <div v-if="!loading && (growthChart || distributionChart)" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <ReportChart
        v-if="growthChart"
        title="Investment Volume"
        type="line"
        :categories="growthChart.categories"
        :series="growthChart.series"
      />
      <ReportChart
        v-if="distributionChart"
        title="Investments by Plan"
        type="pie"
        :categories="distributionChart.labels"
        :series="distributionChart.values.map((value, index) => ({ name: distributionChart.labels[index], data: [value] }))"
      />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <SkeletonLoader type="table" :count="5" class="opacity-40" />
      </div>
      <ReportTable v-else :columns="columns" :data="investments" :pagination="pagination" :sort-by="sortBy" :sort-dir="sortDir" @sort="handleSort" @page-change="handlePageChange" :filters="tableFilters" title="Investment positions" description="Live positions and their current status for the selected range.">
        <template #cell-status="{ row }">
          <span :class="row.status === 'filled' ? 'text-green-400' : row.status === 'cancelled' ? 'text-red-400' : 'text-yellow-400'" class="text-xs font-medium capitalize">{{ row.status }}</span>
        </template>
      </ReportTable>
      <div>
        <h3 class="mb-3 text-lg font-semibold text-white">Top Investors</h3>
        <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
          <SkeletonLoader type="table" :count="5" class="opacity-40" />
        </div>
        <ReportTable v-else :columns="investorColumns" :data="topInvestors" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import StatCard from '@/Components/Reports/StatCard.vue';
import ReportChart from '@/Components/Reports/ReportChart.vue';
import ReportTable from '@/Components/Reports/ReportTable.vue';
import DateFilter from '@/Components/Reports/DateFilter.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import ExportButton from '@/Components/Reports/ExportButton.vue';
import api from '@/api';

const summary = ref({});
const investments = ref([]);
const topInvestors = ref([]);
const charts = ref({});
const loading = ref(false);
const filters = reactive({ start_date: '', end_date: '', period: 'month', status: '', plan: '' });
const pagination = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 });

const columns = [
  { key: 'investor', label: 'Investor', sortable: true },
  { key: 'plan', label: 'Plan' },
  { key: 'amount', label: 'Amount', align: 'right', sortable: true },
  { key: 'roi', label: 'ROI', align: 'right' },
  { key: 'start_date', label: 'Start Date', sortable: true },
  { key: 'maturity', label: 'Maturity' },
  { key: 'status', label: 'Status' },
];

const investorColumns = [
  { key: 'name', label: 'Name' },
  { key: 'email', label: 'Email' },
  { key: 'total_investments', label: 'Total', align: 'right' },
];

const filterOptions = ref({
  plans: [],
  statuses: ['open', 'pending', 'filled', 'cancelled']
});

const sortBy = ref('');
const sortDir = ref('desc');

const growthChart = computed(() => charts.value.growth || null);
const distributionChart = computed(() => {
  if (!charts.value.distribution?.length) {
    return null;
  }

  return {
    labels: charts.value.distribution.map((item) => item.plan || item.market || 'N/A'),
    values: charts.value.distribution.map((item) => item.total ?? 0),
  };
});

const summaryCards = computed(() => [
  { label: 'Total Investments', value: summary.value.total_investments ?? 0, icon: 'activity', color: '#0047AB' },
  { label: 'Active', value: summary.value.active ?? 0, icon: 'trending-up', color: '#10B981' },
  { label: 'Completed', value: summary.value.completed ?? 0, icon: 'check-circle', color: '#10B981' },
  { label: 'Pending', value: summary.value.pending ?? 0, icon: 'clock', color: '#F59E0B' },
  { label: 'Cancelled', value: summary.value.cancelled ?? 0, icon: 'ban', color: '#EF4444' },
  { label: 'Principal', value: summary.value.principal ?? 0, icon: 'dollar', color: '#8B5CF6', prefix: '$' },
]);

const handleFilterChange = (payload) => {
  filters.start_date = payload.start_date || '';
  filters.end_date = payload.end_date || '';
  filters.period = payload.period || 'month';
  fetchInvestments();
};


const tableFilters = computed(() => [
  {
    key: 'status',
    label: 'Status',
    allLabel: 'All Statuses',
    options: [
      { label: 'Active', value: 'open' },
      { label: 'Pending', value: 'pending' },
      { label: 'Completed', value: 'filled' },
      { label: 'Cancelled', value: 'cancelled' },
    ],
  },
  {
    key: 'plan',
    label: 'Plan',
    allLabel: 'All Plans',
    options: filterOptions.value.plans,
  },
]);

const fetchInvestments = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      ...filters,
      page,
      per_page: pagination.value.per_page,
      sort: sortBy.value,
      dir: sortDir.value,
    };
    const [sumRes, invRes, topRes, filtersRes, chartsRes] = await Promise.all([
      api.get('/admin/reports/investments/summary'),
      api.get('/admin/reports/investments', { params }),
      api.get('/admin/reports/investments/top-investors'),
      api.get('/admin/reports/investments/filters').catch(() => ({ data: { plans: [] } })),
      api.get('/admin/reports/investments/charts').catch(() => ({ data: {} })),
    ]);
    summary.value = sumRes.data || {};
    investments.value = invRes.data.data || [];
    topInvestors.value = topRes.data || [];
    filterOptions.value = filtersRes.data || { plans: [] };
    charts.value = chartsRes.data || {};
    pagination.value = {
      current_page: invRes.data.current_page || 1,
      last_page: invRes.data.last_page || 1,
      per_page: invRes.data.per_page || 20,
      total: invRes.data.total || 0,
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
  fetchInvestments(1);
};

const handlePageChange = (page) => {
  fetchInvestments(page);
};

onMounted(fetchInvestments);
</script>