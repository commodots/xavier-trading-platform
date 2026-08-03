<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <h1 class="text-2xl font-bold text-white">Referral & Subscription Report</h1>
    </div>

    <div class="flex flex-wrap items-center gap-4">
      <div class="flex gap-1 bg-[#0F1724] border border-[#1f3348] rounded-xl p-1 w-fit">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key; fetchData(1)" :class="activeTab === tab.key ? 'bg-[#0047AB] text-white' : 'text-gray-400 hover:text-white'" class="px-4 py-2 text-sm font-medium transition rounded-lg">{{ tab.label }}</button>
      </div>
      <DateRangeFilter v-model:from="filters.from" v-model:to="filters.to" />
      <button @click="fetchData(1)" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm">Search</button>
      <button @click="resetFilters" class="px-4 py-2 text-sm text-white bg-gray-700 rounded-lg">Reset</button>
      <ExportButton
        reportType="referrals-subscriptions"
        :startDate="filters.from"
        :endDate="filters.to"
        :extraParams="{ tab: activeTab }"
      />
    </div>

    <div v-if="activeTab === 'referrals'">
      <!-- Referral Summary Cards -->
      <SkeletonLoader v-if="loading" type="card" :count="4" class="mb-6 opacity-40" />
      <div v-else class="grid grid-cols-2 gap-4 mb-6 sm:grid-cols-4">
        <StatCard v-for="s in referralSummary" :key="s.label" v-bind="s" />
      </div>

      <!-- Referrals Table -->
      <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <SkeletonLoader type="table" :count="8" class="opacity-40" />
      </div>
      <ReportTable v-else :columns="referralColumns" :data="referrals" :sort-by="sortBy" :sort-dir="sortDir" @sort="handleSort">
        <template #cell-status="{ row }">
          <span :class="row.status === 'paid' ? 'text-green-400' : row.status === 'pending' ? 'text-yellow-400' : 'text-red-400'" class="text-xs font-medium capitalize">{{ row.status }}</span>
        </template>
      </ReportTable>
    </div>

    <div v-else>
      <!-- Subscription Summary Cards -->
      <SkeletonLoader v-if="loading" type="card" :count="4" class="mb-6 opacity-40" />
      <div v-else class="grid grid-cols-2 gap-4 mb-6 sm:grid-cols-4">
        <StatCard v-for="s in subscriptionSummary" :key="s.label" v-bind="s" />
      </div>

      <!-- Subscriptions Table -->
      <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <SkeletonLoader type="table" :count="8" class="opacity-40" />
      </div>
      <ReportTable v-else :columns="subColumns" :data="subscriptions" :sort-by="sortBy" :sort-dir="sortDir" @sort="handleSort">
        <template #cell-status="{ row }">
          <span :class="row.status === 'active' ? 'text-green-400' : row.status === 'expired' ? 'text-red-400' : 'text-yellow-400'" class="text-xs font-medium capitalize">{{ row.status }}</span>
        </template>
      </ReportTable>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import StatCard from '@/Components/Reports/StatCard.vue';
import ReportTable from '@/Components/Reports/ReportTable.vue';
import DateRangeFilter from '@/Components/Reports/DateRangeFilter.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import ExportButton from '@/Components/Reports/ExportButton.vue';
import Pagination from '@/Components/Reports/Pagination.vue';
import api from '@/api';

const activeTab = ref('referrals');
const loading = ref(false);
const referralSummary = ref([]);
const subscriptionSummary = ref([]);
const referrals = ref([]);
const subscriptions = ref([]);
const filters = reactive({ from: '', to: '' });
const sortBy = ref('');
const sortDir = ref('desc');
const pagination = ref({ current_page: 1, last_page: 1, per_page: 50, total: 0 });

const tabs = [
  { key: 'referrals', label: 'Referrals' },
  { key: 'subscriptions', label: 'Subscriptions' },
];

const referralColumns = [
  { key: 'referrer', label: 'Referrer', sortable: true },
  { key: 'invitee', label: 'Invitee' },
  { key: 'investment', label: 'Investment', align: 'right' },
  { key: 'commission', label: 'Commission', align: 'right' },
  { key: 'status', label: 'Status' },
  { key: 'created_at', label: 'Date', sortable: true },
];

const subColumns = [
  { key: 'user', label: 'User', sortable: true },
  { key: 'plan', label: 'Plan' },
  { key: 'started', label: 'Started', sortable: true },
  { key: 'expires', label: 'Expires' },
  { key: 'auto_renew', label: 'Auto Renew' },
  { key: 'status', label: 'Status' },
];

const fetchData = async (page = 1) => {
  loading.value = true;
  try {
    const params = { tab: activeTab.value, ...filters, page, sort: sortBy.value, dir: sortDir.value };
    const [sumRes, dataRes] = await Promise.all([
      api.get('/admin/reports/referrals-subscriptions/summary'),
      api.get('/admin/reports/referrals-subscriptions', { params }),
    ]);
    referralSummary.value = sumRes.data.referrals || [];
    subscriptionSummary.value = sumRes.data.subscriptions || [];
    if (activeTab.value === 'referrals') {
      referrals.value = dataRes.data.data || [];
      pagination.value = {
        current_page: dataRes.data.current_page || 1,
        last_page: dataRes.data.last_page || 1,
        per_page: dataRes.data.per_page || 50,
        total: dataRes.data.total || 0,
      };
    } else {
      subscriptions.value = dataRes.data.data || [];
      pagination.value = {
        current_page: dataRes.data.current_page || 1,
        last_page: dataRes.data.last_page || 1,
        per_page: dataRes.data.per_page || 50,
        total: dataRes.data.total || 0,
      };
    }
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

const handleSort = (key) => {
  if (sortBy.value === key) { sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'; }
  else { sortBy.value = key; sortDir.value = 'asc'; }
  fetchData(1);
};

const resetFilters = () => {
  filters.from = '';
  filters.to = '';
  sortBy.value = '';
  sortDir.value = 'desc';
  fetchData(1);
};

const handlePageChange = (page) => {
  fetchData(page);
};

onMounted(() => fetchData(1));
</script>