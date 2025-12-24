<template>
  <MainLayout>
    <div>
      <h1 class="mb-6 text-2xl font-bold">Admin Dashboard</h1>

      <div class="grid grid-cols-1 gap-6 mb-8 md:grid-cols-4">
        <MetricCard title="Total Users" :value="stats.totalUsers" icon="Users" />
        <MetricCard title="KYC Pending" :value="stats.kycPending" icon="ShieldCheck" />
        <MetricCard title="Total Transactions" :value="stats.totalTransactions" icon="ListOrdered" />
        
        <MetricCard 
          title="Today's Earnings" 
          :value="'₦' + stats.todayEarnings.toLocaleString()" 
          icon="PieChart"
          :subtitle="`This Month's Earnings: ₦` + stats.monthEarnings.toLocaleString()"
        />
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="mb-4 font-semibold">User Growth</h2>
          <canvas ref="userGrowthChart"></canvas>
        </div>
        <div class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="mb-4 font-semibold">Monthly Transaction Volume</h2>
          <canvas ref="txnChart"></canvas>
        </div>
        <div class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="mb-4 font-semibold">Portfolio Distribution</h2>
          <canvas ref="pieChart"></canvas>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 mt-10 lg:grid-cols-2">

        <div class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="mb-4 font-semibold">Recent Users</h2>
          <table class="w-full text-sm text-left">
            <thead class="text-gray-400">
              <tr><th class="py-2">Name</th><th>Email</th><th>Status</th></tr>
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

        <div class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="mb-4 font-semibold">Latest Transactions</h2>
          <table class="w-full text-sm text-left">
            <thead class="text-gray-400">
              <tr><th class="py-2">User</th><th>Type</th><th>Amount</th></tr>
            </thead>
            <tbody>
              <tr v-for="t in recentTransactions" :key="t.id" class="border-t border-gray-700">
                <td class="py-2">{{ t.user }}</td>
                <td>{{ t.type }}</td>
                <td>₦{{ t.amount.toLocaleString() }}</td>
              </tr>
            </tbody>
          </table>

          <div class="pt-6 mt-8 border-t border-gray-700">
            <h3 class="mb-4 text-xs font-bold tracking-widest text-gray-500 uppercase">Earnings By Transaction Type</h3>
            <div class="grid grid-cols-3 gap-4">
              <div v-for="item in earningsByType" :key="item.type" class="bg-[#151a27] p-3 rounded-lg border border-[#2A314A]">
                <p class="text-[10px] text-gray-400 uppercase mb-1">{{ item.type }}</p>
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
import { ref, onMounted } from "vue";
import axios from "axios";
import MainLayout from "@/Layouts/MainLayout.vue";
import Chart from "chart.js/auto";
import MetricCard from "@/Components/Admin/MetricCard.vue";


const userGrowthChart = ref(null);
const txnChart = ref(null);
const pieChart = ref(null);


const stats = ref({
  totalUsers: 12485,
  kycPending: 342,
  totalTransactions: 98214,
  todayEarnings: 472000,
  monthEarnings: 15400000,
});

const earningsByType = ref([
  { type: 'Deposit', total: 5200000 },
  { type: 'Withdrawal', total: 2100000 },
  { type: 'Trade', total: 8100000 }
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
    const [earningsRes, txnRes] = await Promise.all([
      axios.get('/admin/earnings'),
      axios.get('/admin/transactions')
    ]);

    if (earningsRes.data) {
      stats.value.todayEarnings = earningsRes.data.today_earnings ?? stats.value.todayEarnings;
      
      stats.value.monthEarnings = earningsRes.data.this_month_earnings ?? stats.value.monthEarnings;
      
      if (earningsRes.data.breakdown?.length > 0) {
        earningsByType.value = earningsRes.data.breakdown;
      }
    }

    const transactionList = txnRes.data.data?.data || [];
    if (transactionList.length > 0) {
      recentTransactions.value = transactionList.slice(0, 5).map(t => ({
        id: t.id,
        user: t.user?.name || `User #${t.user_id}`,
        type: t.type,
        amount: Number(t.amount) 
      }));
    }
  } catch (error) {
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