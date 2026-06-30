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

      <!-- LOADING STATE -->
      <template v-if="loading">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div v-for="i in 4" :key="i" class="p-5 bg-[#111827] rounded-xl border border-[#1F2A44] animate-pulse">
            <div class="h-3 bg-gray-700 rounded w-20 mb-3"></div>
            <div class="h-8 bg-gray-700 rounded w-24"></div>
          </div>
        </div>
        <div class="bg-[#111827] p-6 rounded-xl border border-[#1F2A44] animate-pulse space-y-4">
          <div class="h-4 bg-gray-700 rounded w-28"></div>
          <div class="h-10 bg-gray-700 rounded w-40"></div>
          <div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-700">
            <div class="h-6 bg-gray-700 rounded w-32"></div>
            <div class="h-6 bg-gray-700 rounded w-32"></div>
          </div>
        </div>
        <div class="bg-[#111827] rounded-xl border border-[#1F2A44] animate-pulse overflow-hidden">
          <div class="p-4 border-b border-gray-700">
            <div class="h-4 bg-gray-700 rounded w-36"></div>
          </div>
          <div v-for="i in 4" :key="'row-' + i" class="p-4 border-b border-gray-800 flex items-center gap-4">
            <div class="h-4 bg-gray-700 rounded w-24"></div>
            <div class="h-4 bg-gray-700 rounded w-40"></div>
            <div class="h-4 bg-gray-700 rounded w-28"></div>
            <div class="h-4 bg-gray-700 rounded w-16"></div>
          </div>
        </div>
      </template>

      <!-- CONTENT -->
      <template v-else>
        <!-- SUMMARY CARDS -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div class="p-5 bg-[#111827] rounded-xl border border-[#1F2A44]">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Total Users</p>
            <p class="mt-1 text-2xl font-bold text-white">{{ summary.total_users }}</p>
          </div>
          <div class="p-5 bg-[#111827] rounded-xl border border-[#1F2A44]">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Active Subscription</p>
            <p class="mt-1 text-2xl font-bold text-green-400">{{ summary.active_subscription_users }}</p>
          </div>
          <div class="p-5 bg-[#111827] rounded-xl border border-[#1F2A44]">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Trial Users</p>
            <p class="mt-1 text-2xl font-bold text-yellow-400">{{ summary.trial_users }}</p>
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

       <!-- EARNINGS BY TYPE -->
       <div v-if="earningsByType && earningsByType.length" class="bg-[#111827] p-6 rounded-xl border border-[#1F2A44]">
         <h3 class="text-sm text-gray-400 mb-4 flex items-center">
           <span class="w-2 h-2 bg-[#00D4FF] rounded-full mr-2"></span>
           Lifetime Earnings By Transaction Type
         </h3>
         <div class="grid grid-cols-3 gap-4">
           <div v-for="item in earningsByType" :key="item.type" class="bg-[#0B132B]/50 p-4 rounded-lg border border-[#1F2A44]">
             <p class="text-xs text-gray-400 uppercase mb-2">{{ item.type.replace('_', ' ') }}</p>
             <p class="text-lg font-bold text-white">₦{{ formatCurrency(item.total) }}</p>
           </div>
         </div>
       </div>

        <!-- TABS -->
        <div class="flex gap-2 pb-2 border-b border-gray-700 flex-wrap">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            @click="switchTab(tab.key)"
            class="px-4 py-2 text-sm rounded-t-lg transition"
            :class="activeTab === tab.key ? 'bg-blue-600 text-white' : 'bg-[#1E293B] text-gray-300 hover:bg-[#2a3a55]'"
          >
            {{ tab.label }}
          </button>
        </div>

        <!-- USER LIST TAB: Total Users -->
        <div v-if="activeTab === 'all' || activeTab === 'active' || activeTab === 'trial'" class="bg-[#111827] rounded-xl border border-[#1F2A44] overflow-hidden">
          <div class="p-4 border-b border-gray-700 flex items-center justify-between">
            <h3 class="font-semibold">{{ currentTabLabel }}</h3>
            <span class="text-xs text-gray-500">{{ usersData.length }} user{{ usersData.length !== 1 ? 's' : '' }}</span>
          </div>
          <div v-if="tabLoading" class="p-8 text-center text-gray-500">Loading...</div>
          <table v-else class="w-full text-sm">
            <thead>
              <tr class="text-left text-gray-400 border-b border-gray-700">
                <th class="py-3 px-4">User</th>
                <th class="py-3 px-4">Email</th>
                <th class="py-3 px-4">Status</th>
                <th class="py-3 px-4">Plan</th>
                <th class="py-3 px-4">Next Billing</th>
                <th class="py-3 px-4">Debt</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in usersData" :key="user.id" class="border-b border-gray-800 hover:bg-[#1E293B]">
                <td class="py-3 px-4 capitalize">{{ user.name }}</td>
                <td class="py-3 px-4">{{ user.email }}</td>
                <td class="py-3 px-4">
                  <span class="text-xs px-2 py-0.5 rounded-full capitalize"
                    :class="statusClass(user.subscription_status)">
                    {{ user.subscription_status || 'none' }}
                  </span>
                </td>
                <td class="py-3 px-4">{{ getPlanName(user) }}</td>
                <td class="py-3 px-4">{{ formatDate(user.next_billing_date) }}</td>
                <td class="py-3 px-4">
                  <span v-if="user.wallet_debt > 0" class="text-red-400">₦{{ formatCurrency(user.wallet_debt) }}</span>
                  <span v-else class="text-gray-600">—</span>
                </td>
              </tr>
              <tr v-if="!usersData.length">
                <td colspan="6" class="py-8 text-center text-gray-500">No users found.</td>
              </tr>
            </tbody>
          </table>
          <!-- Pagination -->
          <div v-if="usersPagination.lastPage > 1" class="p-4 border-t border-gray-700 flex justify-center gap-2">
            <button @click="loadUsersTab(activeTab, usersPagination.currentPage - 1)" :disabled="usersPagination.currentPage <= 1"
              class="px-3 py-1 text-xs rounded bg-[#1E293B] text-gray-400 hover:bg-[#2a3a55] disabled:opacity-40">
              Previous
            </button>
            <span class="px-3 py-1 text-xs text-gray-500">Page {{ usersPagination.currentPage }} of {{ usersPagination.lastPage }}</span>
            <button @click="loadUsersTab(activeTab, usersPagination.currentPage + 1)" :disabled="usersPagination.currentPage >= usersPagination.lastPage"
              class="px-3 py-1 text-xs rounded bg-[#1E293B] text-gray-400 hover:bg-[#2a3a55] disabled:opacity-40">
              Next
            </button>
          </div>
        </div>

        <!-- RENEWALS TABLE -->
        <div v-if="activeTab === 'renewals'" class="bg-[#111827] rounded-xl border border-[#1F2A44] overflow-hidden">
          <div class="p-4 border-b border-gray-700 flex items-center justify-between">
            <h3 class="font-semibold">Upcoming Renewals</h3>
            <span class="text-xs text-gray-500">{{ renewals.length }} user{{ renewals.length !== 1 ? 's' : '' }}</span>
          </div>
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-gray-400 border-b border-gray-700">
                <th class="py-3 px-4">User</th>
                <th class="py-3 px-4">Email</th>
                <th class="py-3 px-4">Plan Type</th>
                <th class="py-3 px-4">Plan</th>
                <th class="py-3 px-4">Expires / Renews</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in renewals" :key="user.id" class="border-b border-gray-800 hover:bg-[#1E293B]">
                <td class="py-3 px-4 capitalize">{{ user.name }}</td>
                <td class="py-3 px-4">{{ user.email }}</td>
                <td class="py-3 px-4">
                  <span class="text-xs px-2 py-0.5 rounded-full capitalize"
                    :class="user.subscription_status === 'active' ? 'bg-blue-500/20 text-blue-400' : 'bg-yellow-500/20 text-yellow-400'">
                    {{ user.subscription_status === 'active' ? 'Subscription' : 'Trial' }}
                  </span>
                </td>
                <td class="py-3 px-4 capitalize">{{ getPlanName(user) }}</td>
                <td class="py-3 px-4">{{ formatDate(user.next_billing_date || user.trial_ends_at || getSubscriptionExpiry(user)) }}</td>
              </tr>
              <tr v-if="!renewals.length">
                <td colspan="5" class="py-8 text-center text-gray-500">No upcoming renewals found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- DEBTS TABLE -->
        <div v-if="activeTab === 'debts'" class="bg-[#111827] rounded-xl border border-[#1F2A44] overflow-hidden">
          <div class="p-4 border-b border-gray-700 flex items-center justify-between">
            <h3 class="font-semibold">Outstanding Debts</h3>
            <span class="text-xs text-gray-500">{{ debts.length }} user{{ debts.length !== 1 ? 's' : '' }}</span>
          </div>
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-gray-400 border-b border-gray-700">
                <th class="py-3 px-4">User</th>
                <th class="py-3 px-4">Email</th>
                <th class="py-3 px-4">Status</th>
                <th class="py-3 px-4">Debt Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in debts" :key="user.id" class="border-b border-gray-800 hover:bg-[#1E293B]">
                <td class="py-3 px-4 capitalize">{{ user.name }}</td>
                <td class="py-3 px-4">{{ user.email }}</td>
                <td class="py-3 px-4">
                  <span class="text-xs px-2 py-0.5 rounded-full capitalize"
                    :class="user.subscription_status === 'suspended' ? 'bg-red-500/20 text-red-400' : 'bg-yellow-500/20 text-yellow-400'">
                    {{ user.subscription_status || 'none' }}
                  </span>
                </td>
                <td class="py-3 px-4 text-red-400">₦{{ formatCurrency(user.wallet_debt) }}</td>
              </tr>
              <tr v-if="!debts.length">
                <td colspan="4" class="py-8 text-center text-gray-500">No outstanding debts.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import api from '@/api';
import MainLayout from '@/Layouts/MainLayout.vue';

const tabs = [
  { key: 'all', label: 'Total Users' },
  { key: 'active', label: 'Users with Active Subscription' },
  { key: 'trial', label: 'Users with Trial Subscription' },
  { key: 'renewals', label: 'Upcoming Renewals' },
  { key: 'debts', label: 'Outstanding Debts' },
];
const activeTab = ref('all');
const loading = ref(true);
const tabLoading = ref(false);

const summary = reactive({
  total_users: 0,
  active_subscription_users: 0,
  trial_users: 0,
  debt_total: 0,
});

const revenue = reactive({ total: 0, today: 0, monthly: 0 });
const usersData = ref([]);
const usersPagination = reactive({ currentPage: 1, lastPage: 1 });
const renewals = ref([]);
const debts = ref([]);
const earningsByType = ref([]);

const currentTabLabel = computed(() => {
  return tabs.find(t => t.key === activeTab.value)?.label || '';
});

const formatCurrency = (n) => {
  if (n === null || n === undefined) return '0';
  return Number(n).toLocaleString();
};
const formatDate = (d) => d ? new Date(d).toLocaleDateString() : '—';

const statusClass = (status) => {
  switch (status) {
    case 'active':  return 'bg-green-500/20 text-green-400';
    case 'trial':   return 'bg-yellow-500/20 text-yellow-400';
    case 'suspended': return 'bg-red-500/20 text-red-400';
    case 'inactive': return 'bg-gray-500/20 text-gray-400';
    default:        return 'bg-gray-500/20 text-gray-500';
  }
};

const getPlanName = (user) => {
  if (user.subscriptions && user.subscriptions.length) {
    const plan = user.subscriptions[0]?.plan;
    if (plan) return `${plan.name}${plan.tier ? ' (' + plan.tier + ')' : ''}`;
  }
  return user.subscription_status === 'active' ? 'Subscription' : (user.subscription_status === 'trial' ? 'Trial' : '—');
};

const getSubscriptionExpiry = (user) => {
  if (user.subscriptions && user.subscriptions.length) {
    return user.subscriptions[0]?.expires_at;
  }
  return null;
};

const loadUsersTab = async (type, page = 1) => {
  tabLoading.value = true;
  try {
    const res = await api.get('/admin/billing/users', { params: { type, page, per_page: 20 } });
    const data = res.data;
    usersData.value = data.data || [];
    usersPagination.currentPage = data.current_page || 1;
    usersPagination.lastPage = data.last_page || 1;
  } catch (err) {
    console.error('Billing users fetch error:', err);
    usersData.value = [];
  } finally {
    tabLoading.value = false;
  }
};

const switchTab = (key) => {
  activeTab.value = key;
  if (['all', 'active', 'trial'].includes(key)) {
    loadUsersTab(key, 1);
  }
};

const fetchDashboardData = async () => {
  loading.value = true;
  try {
    const [summaryRes, revenueRes, renewalsRes, debtsRes, earningsRes] = await Promise.all([
      api.get('/admin/billing/summary'),
      api.get('/admin/billing/revenue'),
      api.get('/admin/billing/renewals'),
      api.get('/admin/billing/debts'),
      api.get('/admin/earnings').catch(() => ({ data: { by_type: [] } })),
    ]);
    Object.assign(summary, summaryRes.data);
    Object.assign(revenue, revenueRes.data);
    renewals.value = Array.isArray(renewalsRes.data) ? renewalsRes.data : (renewalsRes.data?.data || []);
    debts.value = Array.isArray(debtsRes.data) ? debtsRes.data : (debtsRes.data?.data || []);
    
    // Add earnings by type
    if (earningsRes.data && earningsRes.data.by_type) {
      earningsByType.value = earningsRes.data.by_type.map(item => ({
        type: item.type,
        total: item.total_earnings
      }));
    }
  } catch (err) {
    console.error('Billing fetch error:', err);
  } finally {
    loading.value = false;
    // Load initial tab data
    loadUsersTab('all', 1);
  }
};

onMounted(fetchDashboardData);
</script>