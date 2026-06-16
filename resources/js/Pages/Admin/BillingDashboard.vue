<template>
  <MainLayout>
    <div class="p-6 space-y-6 text-white">
      <!-- HEADER -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Billing Dashboard</h1>
          <p class="text-sm text-gray-400">Monitor subscriptions, renewals, debts, and revenue</p>
        </div>
      </div>

      <!-- SKELETON LOADING -->
      <template v-if="loading">
        <SkeletonLoader type="card" :count="4" class="!grid !grid-cols-1 sm:!grid-cols-2 lg:!grid-cols-4" />
        <SkeletonLoader type="banner" class="!h-32" />
        <SkeletonLoader type="table" :count="4" />
      </template>

      <!-- CONTENT -->
      <template v-else>
        <!-- SUMMARY CARDS -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div class="p-5 bg-[#111827] rounded-xl border border-[#1F2A44]">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Active Users</p>
            <p class="mt-1 text-2xl font-bold text-green-400">{{ summary.active_users }}</p>
          </div>
          <div class="p-5 bg-[#111827] rounded-xl border border-[#1F2A44]">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Trial Users</p>
            <p class="mt-1 text-2xl font-bold text-yellow-400">{{ summary.trial_users }}</p>
          </div>
          <div class="p-5 bg-[#111827] rounded-xl border border-[#1F2A44]">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Paying Users</p>
            <p class="mt-1 text-2xl font-bold text-blue-400">{{ summary.paying_users }}</p>
          </div>
          <div class="p-5 bg-[#111827] rounded-xl border border-[#1F2A44]">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Total Debt</p>
            <p class="mt-1 text-2xl font-bold text-red-400">₦{{ formatCurrency(summary.debt_total) }}</p>
          </div>
        </div>

        <!-- REVENUE -->
        <div class="bg-[#111827] p-6 rounded-xl border border-[#1F2A44] space-y-4">
          <div class="flex items-center justify-between">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Total Revenue</p>
            <p class="text-3xl font-bold text-green-400">₦{{ formatCurrency(revenue.total) }}</p>
          </div>
          <div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-700">
            <div>
              <p class="text-xs text-gray-500">Today's Revenue</p>
              <p class="mt-1 text-lg font-semibold text-green-300">₦{{ formatCurrency(revenue.today) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Monthly Revenue</p>
              <p class="mt-1 text-lg font-semibold text-green-300">₦{{ formatCurrency(revenue.monthly) }}</p>
            </div>
          </div>
        </div>

        <!-- TABS -->
        <div class="flex gap-2 pb-2 border-b border-gray-700">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            @click="activeTab = tab.key"
            class="px-4 py-2 text-sm rounded-t-lg transition"
            :class="activeTab === tab.key ? 'bg-blue-600 text-white' : 'bg-[#1E293B] text-gray-300 hover:bg-[#2a3a55]'"
          >
            {{ tab.label }}
          </button>
        </div>

        <!-- RENEWALS TABLE -->
        <div v-if="activeTab === 'renewals'" class="bg-[#111827] rounded-xl border border-[#1F2A44] overflow-hidden">
          <div class="p-4 border-b border-gray-700">
            <h3 class="font-semibold">Upcoming Renewals</h3>
          </div>
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-gray-400 border-b border-gray-700">
                <th class="py-3 px-4">User</th>
                <th class="py-3 px-4">Email</th>
                <th class="py-3 px-4">Next Billing</th>
                <th class="py-3 px-4">Plan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in renewals" :key="user.id" class="border-b border-gray-800 hover:bg-[#1E293B]">
                <td class="py-3 px-4 capitalize">{{ user.name }}</td>
                <td class="py-3 px-4">{{ user.email }}</td>
                <td class="py-3 px-4">{{ formatDate(user.next_billing_date) }}</td>
                <td class="py-3 px-4">{{ user.current_tier || '—' }}</td>
              </tr>
              <tr v-if="!renewals.length">
                <td colspan="4" class="py-8 text-center text-gray-500">No upcoming renewals.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- DEBTS TABLE -->
        <div v-if="activeTab === 'debts'" class="bg-[#111827] rounded-xl border border-[#1F2A44] overflow-hidden">
          <div class="p-4 border-b border-gray-700">
            <h3 class="font-semibold">Outstanding Debts</h3>
          </div>
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-gray-400 border-b border-gray-700">
                <th class="py-3 px-4">User</th>
                <th class="py-3 px-4">Email</th>
                <th class="py-3 px-4">Debt Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in debts" :key="user.id" class="border-b border-gray-800 hover:bg-[#1E293B]">
                <td class="py-3 px-4 capitalize">{{ user.name }}</td>
                <td class="py-3 px-4">{{ user.email }}</td>
                <td class="py-3 px-4 text-red-400">₦{{ formatCurrency(user.wallet_debt) }}</td>
              </tr>
              <tr v-if="!debts.length">
                <td colspan="3" class="py-8 text-center text-gray-500">No outstanding debts.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '@/api';
import MainLayout from '@/Layouts/MainLayout.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';

const tabs = [
  { key: 'renewals', label: 'Upcoming Renewals' },
  { key: 'debts', label: 'Outstanding Debts' },
];
const activeTab = ref('renewals');
const loading = ref(true);

const summary = reactive({
  active_users: 0,
  trial_users: 0,
  paying_users: 0,
  debt_total: 0,
});

const revenue = reactive({ total: 0, today: 0, monthly: 0 });
const renewals = ref([]);
const debts = ref([]);

const formatCurrency = (n) => {
  if (n === null || n === undefined) return '0';
  return Number(n).toLocaleString();
};
const formatDate = (d) => d ? new Date(d).toLocaleDateString() : '—';

const fetchData = async () => {
  loading.value = true;
  try {
    const [summaryRes, revenueRes, renewalsRes, debtsRes] = await Promise.all([
      api.get('/admin/billing/summary'),
      api.get('/admin/billing/revenue'),
      api.get('/admin/billing/renewals'),
      api.get('/admin/billing/debts'),
    ]);
    Object.assign(summary, summaryRes.data);
    Object.assign(revenue, revenueRes.data);
    renewals.value = Array.isArray(renewalsRes.data) ? renewalsRes.data : (renewalsRes.data?.data || []);
    debts.value = Array.isArray(debtsRes.data) ? debtsRes.data : (debtsRes.data?.data || []);
  } catch (err) {
    console.error('Billing fetch error:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchData);
</script>