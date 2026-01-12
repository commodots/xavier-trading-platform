<template>
  <MainLayout>
    <div>
      <h1 class="mb-6 text-2xl font-bold">Admin Dashboard</h1>

      <div class="grid grid-cols-1 gap-6 mb-8 md:grid-cols-4">
        <MetricCard v-if="isAdmin || user.roles?.includes('manager')" title="Total Users" :value="stats.totalUsers"
          icon="Users" @click="$router.push({ name: 'admin-users' })"
          class="cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95" />

        <MetricCard v-if="isAdmin || user.roles?.includes('support') || user.roles?.includes('compliance')"
          title="KYC Pending" :value="stats.kycPending" icon="ShieldCheck" @click="$router.push({ name: 'admin-kyc' })"
          class="cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95" />
          
        <MetricCard v-if="isAdmin || user.roles?.includes('accounts') || user.roles?.includes('support')"
          title="Total Transactions" :value="stats.totalTransactions" icon="ListOrdered"
          @click="$router.push({ name: 'admin-transactions' })"
          class="cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95" />

        <MetricCard v-if="isAdmin || user.roles?.includes('manager') || user.roles?.includes('accounts')"
          title="Today's Earnings" :value="'₦' + stats.todayEarnings.toLocaleString()" icon="PieChart"
          :subtitle="`This Month's Earnings: ₦` + stats.monthEarnings.toLocaleString()"
          @click="$router.push({ name: 'admin-control-panel', query: { tab: 'platform-earnings' } })"
          class="cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95" />
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div v-if="isAdmin || user.roles?.includes('manager')" class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="mb-4 font-semibold">User Growth</h2>
          <canvas ref="userGrowthChart"></canvas>
        </div>
        <div v-if="isAdmin || user.roles?.includes('accounts') || user.roles?.includes('support')"
          class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="mb-4 font-semibold">Monthly Transaction Volume</h2>
          <canvas ref="txnChart"></canvas>
        </div>
        <div v-if="isAdmin || user.roles?.includes('manager') || user.roles?.includes('accounts')"
          class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="mb-4 font-semibold">Portfolio Distribution</h2>
          <canvas ref="pieChart"></canvas>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 mt-10 lg:grid-cols-2">

        <div v-if="isAdmin || user.roles?.includes('manager')" class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="mb-4 font-semibold">Recent Users</h2>
          <table class="w-full text-sm text-left">
            <thead class="text-gray-400">
              <tr>
                <th class="py-2">Name</th>
                <th>Email</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in recentUsers" :key="u.id" class="border-t border-gray-700">
                <td class="py-2">{{ u.name }}</td>
                <td>{{ u.email }}</td>
                <td>
                  <span :class="u.kyc === 'verified' ? 'text-green-400' : 'text-yellow-400'">
                    {{ u.kyc }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="isAdmin || user.roles?.includes('accounts') || user.roles?.includes('support')"
          class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="mb-4 font-semibold">Latest Transactions</h2>
          <table class="w-full text-sm text-left">
            <thead class="text-gray-400">
              <tr>
                <th class="py-2">User</th>
                <th>Type</th>
                <th>Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in recentTransactions" :key="t.id" class="border-t border-gray-700">
                <td class="py-2">{{ t.user }}</td>
                <td>{{ t.type }}</td>
                <td>{{ t.currency === 'USD' ? '$' : '₦' }}{{ Number(t.amount).toLocaleString() }}</td>
              </tr>
            </tbody>
          </table>

          <div class="pt-6 mt-8 border-t border-gray-700">
            <h3 class="mb-4 text-xs font-bold tracking-widest text-gray-500 uppercase">Lifetime Earnings By Transaction
              Type</h3>
            <div class="grid grid-cols-3 gap-4">
              <div v-for="item in earningsByType" :key="item.type"
                class="bg-[#151a27] p-3 rounded-lg border border-[#2A314A]">
                <p class="text-[10px] text-gray-400 uppercase mb-1">{{ item.type.replace('_', ' ') }}</p>
                <p class="text-sm font-bold text-white">₦{{ Number(item.total).toLocaleString() }}</p>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import api from "@/api";
import MainLayout from "@/Layouts/MainLayout.vue";
import Chart from "chart.js/auto";
import MetricCard from "@/Components/Admin/MetricCard.vue";

// Load user
const user = ref({});
try {
  user.value = JSON.parse(localStorage.getItem("user") || "{}");
} catch {
  user.value = {};
}

const isAdmin = computed(() => user.value.role === "admin" || user.value.roles?.includes('admin'));

const userGrowthChart = ref(null);
const txnChart = ref(null);
const pieChart = ref(null);


const stats = ref({
  totalUsers: 0,
  kycPending: 0,
  totalTransactions: 0,
  todayEarnings: 0,
  monthEarnings: 0,
});

const earningsByType = ref([
  { type: 'Deposit', total: 0 },
  { type: 'Withdrawal', total: 0 },
  { type: 'Trade', total: 0 }
]);

const recentUsers = ref([
  { id: 1, name: "John Doe", email: "john@example.com", kyc: "verified" },
  { id: 2, name: "Sarah Lee", email: "sarah@example.com", kyc: "pending" },
]);

const recentTransactions = ref([
  { id: 1, user: "John Doe", type: "Deposit", amount: 500000 },
  { id: 2, user: "Sarah Lee", type: "Stock Buy", amount: 120000 },
]);

async function fetchDashboardData() {
  try {
    const [earningsRes, txnRes, usersRes, dashboardRes] = await Promise.all([
      api.get('/admin/earnings'),
      api.get('/admin/transactions'),
      api.get('/admin/kycs', { params: { per_page: 10 } }),
      api.get('/admin/dashboard')
    ]);

    if (dashboardRes.data.success) {
      const dData = dashboardRes.data.stats;
      stats.value.totalUsers = dData.users_count;
      stats.value.kycPending = dData.pending_kyc;
      stats.value.totalTransactions = dData.total_transactions;
    }
    if (earningsRes.data) {
      stats.value.todayEarnings = earningsRes.data.today_earnings ?? stats.value.todayEarnings;

      stats.value.monthEarnings = earningsRes.data.this_month_earnings ?? stats.value.monthEarnings;

      if (earningsRes.data.by_type) {
        earningsByType.value = earningsRes.data.by_type.map(item => ({
          type: item.type,
          total: item.total_earnings
        }));
      }
    }

    const transactionList = txnRes.data.data?.data || [];
    if (transactionList.length > 0) {
      recentTransactions.value = transactionList.slice(0, 5).map(t => ({
        id: t.id,
        user: t.user?.name || `User #${t.user_id}`,
        type: t.type,
        currency: t.currency,
        amount: Number(t.amount)
      }));
    }

    const kycList = usersRes.data.data?.data || [];
    recentUsers.value = kycList
      .filter(u => u.user)
      .map(u => ({
        id: u.user_id,
        name: `${u.user.first_name} ${u.user.last_name}`,
        email: u.user.email,
        kyc: u.status || 'none'
      }));
  }
  catch (error) {
    console.warn("API Error: Fallback data");
  }
}

onMounted(async () => {
  await fetchDashboardData();


  if (userGrowthChart.value) {
    new Chart(userGrowthChart.value, {
      type: "line",
      data: {
        labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
        datasets: [{ label: "Users", data: [500, 1200, 2300, 3500, 5800, 8200], borderColor: "#00D4FF", tension: 0.4 }]
      }
    });
  }


  if (txnChart.value) {
    new Chart(txnChart.value, {
      type: "bar",
      data: {
        labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
        datasets: [{ label: "Transactions", data: [800, 1400, 2000, 2600, 3000, 4500], backgroundColor: "#0047AB" }]
      }
    });
  }


  if (pieChart.value) {
    new Chart(pieChart.value, {
      type: "doughnut",
      data: {
        labels: ["Crypto", "Stocks", "Cash"],
        datasets: [{ data: [40, 35, 25], backgroundColor: ["#00D4FF", "#0047AB", "#1C2541"] }]
      }
    });
  }
});
</script>