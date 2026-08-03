<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <h1 class="text-2xl font-bold text-white">Investment Report</h1>
    </div>

    <!-- Summary Cards -->
    <SkeletonLoader v-if="loading" type="card" :count="6" class="opacity-40" />
    <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-3">
      <StatCard v-for="s in summary" :key="s.label" v-bind="s" />
    </div>

    <!-- Filters -->
    <div v-if="!loading" class="flex flex-wrap items-center gap-4">
      <DateRangeFilter v-model:from="filters.from" v-model:to="filters.to" />
      <select v-model="filters.status" class="bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm outline-none">
        <option disabled value="" class="text-white">Status</option>
        <option value="open">Active</option>
        <option value="pending">Pending</option>
        <option value="filled">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <select v-model="filters.plan" class="bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm outline-none">
        <option disabled value="" class="text-white">Plan</option>
        <option v-for="plan in filterOptions.plans" :key="plan" :value="plan">{{ plan }}</option>
      </select>
      <button @click="fetchInvestments" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm">Search</button>
      <button @click="resetFilters" class="px-4 py-2 text-sm text-white bg-gray-700 rounded-lg">Reset</button>
      <ExportButton
        reportType="investments"
        :startDate="filters.from"
        :endDate="filters.to"
        :extraParams="{ status: filters.status, plan: filters.plan }"
      />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <SkeletonLoader type="table" :count="5" class="opacity-40" />
      </div>
      <ReportTable v-else :columns="columns" :data="investments" :sort-by="sortBy" :sort-dir="sortDir" @sort="handleSort">
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
import { ref, reactive, onMounted } from 'vue';
import StatCard from '@/Components/Reports/StatCard.vue';
import ReportTable from '@/Components/Reports/ReportTable.vue';
import DateRangeFilter from '@/Components/Reports/DateRangeFilter.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import ExportButton from '@/Components/Reports/ExportButton.vue';
import api from '@/api';

const summary = ref([]);
const investments = ref([]);
const topInvestors = ref([]);
const loading = ref(false);
const filters = reactive({ from: '', to: '' });

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

const fetchInvestments = async () => {
  loading.value = true;
  try {
    const params = { ...filters, sort: sortBy.value, dir: sortDir.value };
    const [sumRes, invRes, topRes, filtersRes] = await Promise.all([
      api.get('/admin/reports/investments/summary'),
      api.get('/admin/reports/investments', { params }),
      api.get('/admin/reports/investments/top-investors'),
      api.get('/admin/reports/investments/filters').catch(() => ({ data: { plans: [] } })),
    ]);
    summary.value = sumRes.data;
    investments.value = invRes.data.data || [];
    topInvestors.value = topRes.data || [];
    filterOptions.value = filtersRes.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

const handleSort = (key) => {
  if (sortBy.value === key) { sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'; }
  else { sortBy.value = key; sortDir.value = 'asc'; }
  fetchInvestments();
};

const resetFilters = () => {
  filters.from = '';
  filters.to = '';
  filters.status = '';
  filters.plan = '';
  filters.user = '';
  filters.amount_min = '';
  filters.amount_max = '';
  sortBy.value = '';
  sortDir.value = 'desc';
  fetchInvestments();
};

onMounted(fetchInvestments);
</script>