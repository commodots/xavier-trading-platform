<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-white">Referral & Subscription Report</h1>

    <div class="flex gap-1 bg-[#0F1724] border border-[#1f3348] rounded-xl p-1 w-fit">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key; fetchData()" :class="activeTab === tab.key ? 'bg-[#0047AB] text-white' : 'text-gray-400 hover:text-white'" class="px-4 py-2 rounded-lg text-sm font-medium transition">{{ tab.label }}</button>
    </div>

    <div v-if="activeTab === 'referrals'">
      <!-- Referral Summary Cards -->
      <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div v-for="i in 4" :key="i" class="p-4 bg-[#0F1724] border border-[#1f3348] rounded-lg space-y-3 animate-pulse">
          <div class="h-3 bg-gray-700 rounded w-20"></div>
          <div class="h-6 bg-gray-700 rounded w-16"></div>
        </div>
      </div>
      <div v-else class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <StatCard v-for="s in referralSummary" :key="s.label" v-bind="s" />
      </div>

      <!-- Referrals Table -->
      <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <SkeletonLoader type="table" :count="8" class="opacity-40" />
      </div>
      <ReportTable v-else :columns="referralColumns" :data="referrals" />
    </div>

    <div v-else>
      <!-- Subscription Summary Cards -->
      <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div v-for="i in 4" :key="i" class="p-4 bg-[#0F1724] border border-[#1f3348] rounded-lg space-y-3 animate-pulse">
          <div class="h-3 bg-gray-700 rounded w-20"></div>
          <div class="h-6 bg-gray-700 rounded w-16"></div>
        </div>
      </div>
      <div v-else class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <StatCard v-for="s in subscriptionSummary" :key="s.label" v-bind="s" />
      </div>

      <!-- Subscriptions Table -->
      <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <SkeletonLoader type="table" :count="8" class="opacity-40" />
      </div>
      <ReportTable v-else :columns="subColumns" :data="subscriptions">
        <template #cell-status="{ row }">
          <span :class="row.status === 'active' ? 'text-green-400' : 'text-red-400'" class="text-xs font-medium">{{ row.status }}</span>
        </template>
      </ReportTable>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import StatCard from '@/Components/Reports/StatCard.vue';
import ReportTable from '@/Components/Reports/ReportTable.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import api from '@/api';

const activeTab = ref('referrals');
const loading = ref(false);
const referralSummary = ref([]);
const subscriptionSummary = ref([]);
const referrals = ref([]);
const subscriptions = ref([]);

const tabs = [
  { key: 'referrals', label: 'Referrals' },
  { key: 'subscriptions', label: 'Subscriptions' },
];

const referralColumns = [
  { key: 'referrer', label: 'Referrer' },
  { key: 'invitee', label: 'Invitee' },
  { key: 'status', label: 'Status' },
  { key: 'created_at', label: 'Date' },
];

const subColumns = [
  { key: 'user', label: 'User' },
  { key: 'plan', label: 'Plan' },
  { key: 'started', label: 'Started' },
  { key: 'expires', label: 'Expires' },
  { key: 'status', label: 'Status' },
];

const fetchData = async () => {
  loading.value = true;
  try {
    const [sumRes, dataRes] = await Promise.all([
      api.get('/admin/reports/referrals-subscriptions/summary'),
      api.get('/admin/reports/referrals-subscriptions', { params: { tab: activeTab.value } }),
    ]);
    referralSummary.value = sumRes.data.referrals || [];
    subscriptionSummary.value = sumRes.data.subscriptions || [];
    if (activeTab.value === 'referrals') {
      referrals.value = dataRes.data.data || [];
    } else {
      subscriptions.value = dataRes.data.data || [];
    }
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchData);
</script>