<template>
  <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-white">Expense Report</h1>
      </div>

      <div class="flex items-center justify-between gap-4">
        <DateFilter @filter-change="onFilterChange" />
        <ExportButton
          reportType="expenses"
          :startDate="filters.start_date"
          :endDate="filters.end_date"
        />
      </div>

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
const summary = ref({ total: 0, total_ngn: 0, total_usd: 0, largest_category: null, outstanding: 0, average_monthly: 0 });
const chart = ref({ categories: [], series: [] });
const categories = ref([]);
const filters = ref({ start_date: '', end_date: '', period: 'month' });

const summaryCards = computed(() => [
  { label: 'Total Expenses (NGN)', value: summary.value.total_ngn, icon: 'DollarSign', color: '#EF4444', prefix: '₦' },
  { label: 'Total Expenses (USD)', value: summary.value.total_usd, icon: 'DollarSign', color: '#0047AB', prefix: '$' },
  { label: 'This Month', value: summary.value.month, icon: 'Calendar', color: '#10B981', prefix: '₦' },
  { label: 'Today', value: summary.value.today, icon: 'Clock', color: '#F59E0B', prefix: '₦' },
]);

const tableColumns = [
  { key: 'expense_no', label: 'Expense No' },
  { key: 'date', label: 'Date', type: 'date' },
  { key: 'category', label: 'Category' },
  { key: 'vendor', label: 'Vendor' },
  {
    key: 'amount',
    label: 'Amount',
    format: (value, row) => `${row.currency || 'NGN'} ${Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
  },
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