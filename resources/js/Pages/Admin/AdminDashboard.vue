<template>
  <MainLayout>
    <div class="space-y-8">

      <h1 class="text-2xl font-bold text-white">Admin Dashboard</h1>

      <!-- STAT CARDS -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

        <div class="p-6 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <p class="text-gray-400 text-sm">Total Users</p>
          <h2 class="text-3xl font-bold text-white mt-2">{{ stats.users_count }}</h2>
        </div>

        <div class="p-6 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <p class="text-gray-400 text-sm">Pending KYCs</p>
          <h2 class="text-3xl font-bold text-yellow-400 mt-2">{{ stats.pending_kyc }}</h2>
        </div>

        <div class="p-6 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <p class="text-gray-400 text-sm">Total Transactions</p>
          <h2 class="text-3xl font-bold text-blue-400 mt-2">{{ stats.total_transactions }}</h2>
        </div>

        <div class="p-6 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <p class="text-gray-400 text-sm">Pending Orders</p>
          <h2 class="text-3xl font-bold text-red-400 mt-2">{{ stats.pending_orders }}</h2>
        </div>

      </div>

      <!-- WALLET BALANCES -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="p-6 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <p class="text-gray-400 text-sm">Total NGN Wallet Value</p>
          <h2 class="text-3xl font-bold text-white">₦{{ format(stats.wallets?.ngn) }}</h2>
        </div>

        <div class="p-6 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <p class="text-gray-400 text-sm">Total USD Wallet Value</p>
          <h2 class="text-3xl font-bold text-white">${{ format(stats.wallets?.usd) }}</h2>
        </div>
      </div>

      <!-- CHARTS -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- User Registrations -->
        <div class="bg-[#111827] p-6 rounded-xl border border-[#1F2A44]">
          <h3 class="text-white text-lg font-semibold mb-3">User Registrations (7 days)</h3>
          <apexchart
            type="line"
            height="250"
            :options="userChartOptions"
            :series="userChartSeries"
          />
        </div>

        <!-- Transaction Volume -->
        <div class="bg-[#111827] p-6 rounded-xl border border-[#1F2A44]">
          <h3 class="text-white text-lg font-semibold mb-3">Transactions Volume (7 days)</h3>
          <apexchart
            type="bar"
            height="250"
            :options="txChartOptions"
            :series="txChartSeries"
          />
        </div>

      </div>

    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "@/lib/axios";
import MainLayout from "@/layouts/MainLayout.vue";
import VueApexCharts from "vue3-apexcharts";

const apexchart = VueApexCharts;

// STATE
const stats = ref({});
const userChartSeries = ref([]);
const txChartSeries = ref([]);

const userChartOptions = ref({
  chart: { toolbar: { show: false }, foreColor: "#ccc" },
  stroke: { curve: "smooth" },
  xaxis: { categories: [] },
});

const txChartOptions = ref({
  chart: { toolbar: { show: false }, foreColor: "#ccc" },
  xaxis: { categories: [] },
});

// LOAD STATS
onMounted(async () => {
  const res = await axios.get("/admin/dashboard");

  stats.value = res.data;

  userChartSeries.value = [
    { name: "Users", data: res.data.chart.users.data },
  ];
  userChartOptions.value.xaxis.categories = res.data.chart.users.labels;

  txChartSeries.value = [
    { name: "Volume", data: res.data.chart.transactions.data },
  ];
  txChartOptions.value.xaxis.categories = res.data.chart.transactions.labels;
});

const format = (n) =>
  Intl.NumberFormat().format(Number(n ?? 0));
</script>

<style>
/* Optional: improve chart text */
.apexcharts-legend-text,
.apexcharts-tooltip-text {
  color: #fff !important;
}
</style>
