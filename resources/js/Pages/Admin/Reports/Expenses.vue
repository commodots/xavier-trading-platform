<template>
  <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-white">Expense Report</h1>
        <ExportButton
          title="expense-report"
          :rows="categories"
          :headers="['Category', 'Amount', 'Status']"
          type="expenses"
          :startDate="filters.start_date"
          :endDate="filters.end_date"
        />
      </div>

      <DateFilter @filter-change="onFilterChange" />

      <div v-if="loading" class="space-y-6">
        <SkeletonLoader type="card" :count="4" />
        <SkeletonLoader type="table" />
        <SkeletonLoader type="table" :count="6" />
      </div>

      <template v-else>
        <SummaryCards :cards="summaryCards" />

        <ReportChart
          title="Expenses by Category"
          type="bar"
          :categories="chart.categories"
          :series="chart.series"
        />

        <ReportTable :columns="tableColumns" :rows="categories" searchable />
      </template>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import SummaryCards from '@/Components/Reports/SummaryCards.vue';
import DateFilter from '@/Components/Reports/DateFilter.vue';
import ReportChart from '@/Components/Reports/ReportChart.vue';
import ReportTable from '@/Components/Reports/ReportTable.vue';
import ExportButton from '@/Components/Reports/ExportButton.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import api from '@/api';

const loading = ref(true);
const summary = ref({ total: 0, largest_category: null, outstanding: 0, average_monthly: 0 });
const chart = ref({ categories: [], series: [] });
const categories = ref([]);
const filters = ref({ start_date: '', end_date: '', period: 'month' });

const summaryCards = computed(() => [
  { label: 'Total Expenses', value: summary.value.total, icon: 'DollarSign', color: '#EF4444', prefix: '$' },
  { label: 'Largest Category', value: summary.value.largest_category?.name || 'N/A', icon: 'PieChart', color: '#F59E0B' },
  { label: 'Outstanding', value: summary.value.outstanding, icon: 'AlertTriangle', color: '#F59E0B', prefix: '$' },
  { label: 'Average Monthly', value: summary.value.average_monthly, icon: 'TrendingUp', color: '#0047AB', prefix: '$' },
]);

const tableColumns = [
  { key: 'date', label: 'Date', type: 'date' },
  { key: 'category', label: 'Category' },
  { key: 'vendor', label: 'Vendor' },
  { key: 'amount', label: 'Amount', type: 'currency' },
  { key: 'status', label: 'Status', type: 'status' },
];

const fetchData = async () => {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.start_date) params.start_date = filters.value.start_date;
    if (filters.value.end_date) params.end_date = filters.value.end_date;
    if (filters.value.period) params.period = filters.value.period;
    const res = await api.get('/admin/reports/expenses', { params });
    summary.value = res.data.summary || { total: 0, largest_category: null, outstanding: 0, average_monthly: 0 };
    chart.value = res.data.chart || { categories: [], series: [] };
    categories.value = res.data.categories || [];
  } catch (e) {
    console.error('Failed to load expenses:', e);
  } finally {
    loading.value = false;
  }
};

const onFilterChange = (newFilters) => {
  filters.value = newFilters;
  fetchData();
};

onMounted(fetchData);
</script>