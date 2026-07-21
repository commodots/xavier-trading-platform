<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-white">Investment Report</h1>

    <!-- Summary Cards -->
    <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
      <div v-for="i in 6" :key="i" class="p-4 bg-[#0F1724] border border-[#1f3348] rounded-lg space-y-3 animate-pulse">
        <div class="h-3 bg-gray-700 rounded w-20"></div>
        <div class="h-6 bg-gray-700 rounded w-16"></div>
      </div>
    </div>
    <div v-else class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
      <StatCard v-for="s in summary" :key="s.label" v-bind="s" />
    </div>

    <DateRangeFilter v-model:from="filters.from" v-model:to="filters.to" />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <SkeletonLoader type="table" :count="5" class="opacity-40" />
      </div>
      <ReportTable v-else :columns="columns" :data="investments">
        <template #cell-status="{ row }">
          <span :class="row.status === 'filled' ? 'text-green-400' : 'text-yellow-400'" class="text-xs font-medium">{{ row.status }}</span>
        </template>
      </ReportTable>
      <div>
        <h3 class="text-lg font-semibold text-white mb-3">Top Investors</h3>
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
import api from '@/api';

const summary = ref([]);
const investments = ref([]);
const topInvestors = ref([]);
const loading = ref(false);
const filters = reactive({ from: '', to: '' });

const columns = [
  { key: 'investor', label: 'Investor' },
  { key: 'plan', label: 'Plan' },
  { key: 'amount', label: 'Amount', align: 'right' },
  { key: 'status', label: 'Status' },
  { key: 'start_date', label: 'Start Date' },
];

const investorColumns = [
  { key: 'name', label: 'Name' },
  { key: 'email', label: 'Email' },
  { key: 'total_investments', label: 'Total', align: 'right' },
];

const fetchInvestments = async () => {
  loading.value = true;
  try {
    const [sumRes, invRes, topRes] = await Promise.all([
      api.get('/admin/reports/investments/summary'),
      api.get('/admin/reports/investments', { params: filters }),
      api.get('/admin/reports/investments/top-investors'),
    ]);
    summary.value = sumRes.data;
    investments.value = invRes.data.data || [];
    topInvestors.value = topRes.data || [];
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchInvestments);
</script>