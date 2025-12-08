<template>
  <MainLayout>
    <div>
      <!-- Page Title -->
      <h1 class="text-2xl font-bold mb-6">Admin Dashboard</h1>

      <!-- TOP METRICS -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <MetricCard title="Total Users" :value="stats.totalUsers" icon="Users" />
        <MetricCard title="KYC Pending" :value="stats.kycPending" icon="ShieldCheck" />
        <MetricCard title="Total Transactions" :value="stats.totalTransactions" icon="ListOrdered" />
        <MetricCard title="System Revenue" :value="'₦' + stats.revenue.toLocaleString()" icon="PieChart" />
      </div>

      <!-- CHARTS ROW -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- USER GROWTH CHART -->
        <div class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="font-semibold mb-4">User Growth</h2>
          <canvas ref="userGrowthChart"></canvas>
        </div>

        <!-- TRANSACTION VOLUME CHART -->
        <div class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="font-semibold mb-4">Monthly Transaction Volume</h2>
          <canvas ref="txnChart"></canvas>
        </div>

        <!-- PORTFOLIO DISTRIBUTION -->
        <div class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="font-semibold mb-4">Portfolio Distribution</h2>
          <canvas ref="pieChart"></canvas>
        </div>

      </div>

      <!-- TABLES ROW -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-10">

        <!-- Recent Users -->
        <div class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="font-semibold mb-4">Recent Users</h2>

          <table class="w-full text-left text-sm">
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

        <!-- Latest Transactions -->
        <div class="bg-[#1C1F2E] p-6 rounded-xl shadow">
          <h2 class="font-semibold mb-4">Latest Transactions</h2>

          <table class="w-full text-left text-sm">
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
                <td>₦{{ t.amount.toLocaleString() }}</td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>

    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import MainLayout from "@/Layouts/MainLayout.vue";
import Chart from "chart.js/auto";

// Metric Card Component
import MetricCard from "@/Components/Admin/MetricCard.vue";

// ---- Dashboard Data ----
const stats = {
  totalUsers: 12485,
  kycPending: 342,
  totalTransactions: 98214,
  revenue: 47200000,
};

const recentUsers = [
  { id: 1, name: "John Doe", email: "john@example.com", kyc: "verified" },
  { id: 2, name: "Sarah Lee", email: "sarah@example.com", kyc: "pending" },
  { id: 3, name: "Mike Jay", email: "mike@example.com", kyc: "verified" },
];

const recentTransactions = [
  { id: 1, user: "John Doe", type: "Deposit", amount: 500000 },
  { id: 2, user: "Sarah Lee", type: "Stock Buy", amount: 120000 },
  { id: 3, user: "Mike Jay", type: "Crypto Swap", amount: 95000 },
];

// Chart references
const userGrowthChart = ref(null);
const txnChart = ref(null);
const pieChart = ref(null);

// Create Charts
onMounted(() => {
  new Chart(userGrowthChart.value, {
    type: "line",
    data: {
      labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
      datasets: [{
        label: "Users",
        data: [500, 1200, 2300, 3500, 5800, 8200],
        borderColor: "#00D4FF",
        tension: 0.4,
      }],
    },
  });

  new Chart(txnChart.value, {
    type: "bar",
    data: {
      labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
      datasets: [{
        label: "Transactions",
        data: [800, 1400, 2000, 2600, 3000, 4500],
        backgroundColor: "#0047AB",
      }],
    },
  });

  new Chart(pieChart.value, {
    type: "doughnut",
    data: {
      labels: ["Crypto", "Stocks", "Cash"],
      datasets: [{
        data: [40, 35, 25],
        backgroundColor: ["#00D4FF", "#0047AB", "#1C2541"],
      }],
    },
  });
});
</script>
