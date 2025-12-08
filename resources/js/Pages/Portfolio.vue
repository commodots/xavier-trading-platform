<template>
  <MainLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-semibold">📊 Portfolio</h1>
      <p class="text-gray-400 text-sm">Your asset allocation & performance overview.</p>

      <!-- Total Equity -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <div class="text-gray-400 text-sm">Total Portfolio Value</div>
        <div class="text-4xl font-bold mt-2">₦{{ totalEquity.toLocaleString() }}</div>
      </div>

      <!-- Chart -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <apexchart
          height="300"
          type="pie"
          :options="chartOptions"
          :series="chartSeries"
        />
      </div>

      <!-- Holdings -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <h2 class="text-lg font-semibold mb-4">Holdings</h2>

        <table class="w-full text-sm">
          <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
            <tr>
              <th class="text-left py-2">Asset</th>
              <th class="text-left">Qty</th>
              <th class="text-left">Avg Cost</th>
              <th class="text-left">Market Price</th>
              <th class="text-left">P/L</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="h in holdings"
              :key="h.symbol"
              class="border-b border-[#1f3348] hover:bg-[#16213A]"
            >
              <td class="py-3 font-semibold">{{ h.symbol }}</td>
              <td>{{ h.qty }}</td>
              <td>₦{{ h.avg_cost.toLocaleString() }}</td>
              <td>₦{{ h.market_price.toLocaleString() }}</td>
              <td>
                <span
                  :class="{
                    'text-green-400': (h.market_price - h.avg_cost) >= 0,
                    'text-red-400': (h.market_price - h.avg_cost) < 0
                  }"
                >
                  {{ ((h.market_price - h.avg_cost)).toFixed(2) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from "@/layouts/MainLayout.vue";
import { ref, onMounted } from "vue";
import VueApexCharts from "vue3-apexcharts";

const apexchart = VueApexCharts;

// --- State ---
const holdings = ref([]);
const totalEquity = ref(0);
const chartSeries = ref([]);
const chartOptions = ref({
  labels: ["Wallet", "NGX", "Global Stocks (USD)", "Crypto"],
  legend: { position: "bottom", labels: { colors: "#fff" } },
  theme: { mode: "dark" }
});

// --- Fetch Data ---
onMounted(async () => {
  const token = localStorage.getItem("xavier_token");

  const res = await axios.get("/api/dashboard", {
    headers: { Authorization: `Bearer ${token}` }
  });

  totalEquity.value = res.data.total_equity;
  holdings.value = res.data.holdings;

  chartSeries.value = [
    res.data.wallet_balance,
    res.data.ngx_value,
    res.data.global_stocks_value_usd,
    res.data.crypto_value
  ];
});
</script>
