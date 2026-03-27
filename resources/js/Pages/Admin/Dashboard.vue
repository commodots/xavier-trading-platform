<template>
  <MainLayout>
    <div>
      <h1 class="mb-6 text-2xl font-bold">Admin Dashboard</h1>

      <div :class="gridClasses">
        <MetricCard v-if="hasRole('manager', 'compliance')" title="Total Users" :value="stats.totalUsers"
          icon="Users" @click="$router.push({ name: 'admin-users' })"
          class="cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95" />

        <MetricCard v-if="hasRole('support', 'compliance')"
          title="KYC Pending" :value="stats.kycPending" icon="ShieldCheck" @click="$router.push({ name: 'admin-kyc' })"
          class="cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95" />
          
        <MetricCard v-if="hasRole('accounts', 'support')"
          title="Total Transactions" :value="stats.totalTransactions" icon="ListOrdered"
          @click="$router.push({ name: 'admin-transactions' })"
          class="cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95" />

        <MetricCard v-if="hasRole('manager', 'accounts')"
          title="Today's Earnings" :value="'₦' + stats.todayEarnings.toLocaleString()" icon="PieChart"
          :subtitle="`This Month's Earnings: ₦` + stats.monthEarnings.toLocaleString()"
          @click="$router.push({ name: 'admin-control-panel', query: { tab: 'platform-earnings' } })"
          class="cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95" />

        <MetricCard v-if="hasRole('manager', 'accounts')"
          title="Buffer/Shortfall" 
          :value="'$' + formatNumber(Math.abs(fxStats.buffer))" 
          :subtitle="fxStats.buffer >= 0 ? 'Safe margin' : 'SHORTFALL'"
          class="cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95" />

        <MetricCard v-if="hasRole('manager', 'accounts')"
          title="Pending Settlements" :value="fxStats.pendingSettlements" icon="Clock"
          class="cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95" />

        <MetricCard v-if="hasRole('manager', 'accounts')"
          title="FX Margin Earned" :value="'₦' + formatNumber(fxStats.fxMargin)" icon="TrendingUp"
          :subtitle="'Today'"
           @click="$router.push({ name: 'admin-fx-dashboard' })"
          class="cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95" />

        <MetricCard v-if="hasRole('manager')"
          title="₿ Crypto Settings" :value="'Configure'" icon="Settings"
          @click="$router.push({ name: 'admin-crypto-settings' })"
          class="cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95" />
      </div>

      <div :class="chartGridClasses">
        <div v-if="hasRole('manager', 'compliance')" class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="mb-4 font-semibold">User Growth</h2>
          <canvas ref="userGrowthChart"></canvas>
        </div>
        <div v-if="hasRole('accounts', 'support')" class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="mb-4 font-semibold">Monthly Transaction Volume</h2>
          <canvas ref="txnChart"></canvas>
        </div>
        <div v-if="hasRole('manager', 'accounts')" class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="mb-4 font-semibold">Portfolio Distribution</h2>
          <canvas ref="pieChart"></canvas>
        </div>
      </div>

      <div :class="bottomGridClasses">
        <div v-if="hasRole('manager', 'compliance')" class="bg-[#1C1F2E] p-6 rounded-xl shadow">
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

        <div v-if="hasRole('accounts', 'support')" class="bg-[#1C1F2E] p-6 rounded-xl shadow">
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
            <h3 class="mb-4 text-xs font-bold tracking-widest text-gray-500 uppercase">Lifetime Earnings By Transaction Type</h3>
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

const isAdmin = computed(() => {
  return user.value.role === "admin" || 
         (user.value.roles && user.value.roles.some(r => (typeof r === 'string' ? r : r.name)?.toLowerCase() === 'admin'));
});

const hasRole = (...rolesAllowed) => {
  if (isAdmin.value) return true;
  if (!user.value?.roles) return false;
  
  return user.value.roles.some(r => {
    const roleName = (typeof r === 'string' ? r : r.name)?.toLowerCase();
    return rolesAllowed.includes(roleName);
  });
};

const visibleMetricCards = computed(() => {
  const conditions = [
    hasRole('manager', 'compliance'),
    hasRole('support', 'compliance'),
    hasRole('accounts', 'support'),
    hasRole('manager', 'accounts'),
    hasRole('manager', 'accounts'),
    hasRole('manager', 'accounts'),
    hasRole('manager', 'accounts'),
    hasRole('manager', 'accounts')
  ];
  return conditions.reduce((s, c) => s + (c ? 1 : 0), 0);
});

const gridClasses = computed(() => {
  const count = visibleMetricCards.value || 1;
  const mdCols = Math.max(1, Math.min(count, 4));
  const lgCols = Math.max(1, Math.min(count, 6));
  return `grid grid-cols-1 gap-6 mb-8 md:grid-cols-${mdCols} lg:grid-cols-${lgCols}`;
});

const visibleCharts = computed(() => {
  let count = 0;
  if (hasRole('manager', 'compliance')) count++;
  if (hasRole('accounts', 'support')) count++;
  if (hasRole('manager', 'accounts')) count++;
  return count;
});

const chartGridClasses = computed(() => {
  let classes = 'grid grid-cols-1 gap-6 ';
  if (visibleCharts.value === 3) classes += 'lg:grid-cols-3';
  else if (visibleCharts.value === 2) classes += 'lg:grid-cols-2';
  else classes += 'lg:grid-cols-1';
  return classes;
});

const visibleBottomSections = computed(() => {
  let count = 0;
  if (hasRole('manager', 'compliance')) count++;
  if (hasRole('accounts', 'support')) count++;
  return count;
});

const bottomGridClasses = computed(() => {
  let classes = 'grid grid-cols-1 gap-6 mt-10 ';
  if (visibleBottomSections.value === 2) classes += 'lg:grid-cols-2';
  else classes += 'lg:grid-cols-1';
  return classes;
});

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

const fxStats = ref({
  userTotal: 0,
  buffer: 0,
  pendingSettlements: 0,
  fxMargin: 0,
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
    const [earningsRes, txnRes, usersRes, dashboardRes, fxRes] = await Promise.all([
      api.get('/admin/earnings').catch(() => ({ data: {} })),
      api.get('/admin/transactions').catch(() => ({ data: { data: { data: [] } } })),
      api.get('/admin/kycs', { params: { per_page: 10 } }).catch(() => ({ data: { data: { data: [] } } })),
      api.get('/admin/dashboard').catch(() => ({ data: {} })),
      api.get('/admin/fx/reconciliation').catch(() => ({ data: {} }))
    ]);

    if (dashboardRes.data?.success) {
      const dData = dashboardRes.data.stats;
      stats.value.totalUsers = dData.users_count || 0;
      stats.value.kycPending = dData.pending_kyc || 0;
      stats.value.totalTransactions = dData.total_transactions || 0;
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

    const transactionList = txnRes.data?.data?.data || [];
    if (transactionList.length > 0) {
      recentTransactions.value = transactionList.slice(0, 5).map(t => ({
        id: t.id,
        user: t.user?.name || `User #${t.user_id}`,
        type: t.type,
        currency: t.currency,
        amount: Number(t.amount)
      }));
    }

    const kycList = usersRes.data?.data?.data || [];
    recentUsers.value = kycList
      .filter(u => u.user)
      .map(u => ({
        id: u.user_id,
        name: `${u.user.first_name || ''} ${u.user.last_name || ''}`.trim(),
        email: u.user.email,
        kyc: u.status || 'none'
      }));

    if (fxRes.data && fxRes.data.data) {
      const fxData = fxRes.data.data;

      fxStats.value = {
        userTotal: fxData.user_usd_total || 0,
        buffer: fxData.buffer || 0,
        pendingSettlements: fxData.pending_settlements || 0,
        fxMargin: fxData.fx_margin_today || 0,
      };
    }
  }
  catch (error) {
    console.warn("API Error: Using fallback data", error);
  }
}

const formatNumber = (num) => {
  return Number(num).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
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