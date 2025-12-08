<template>
  <DashboardLayout>
    <div class="p-6 space-y-6">

      <h1 class="text-2xl font-bold">Order Management</h1>

      <!-- Tabs -->
      <div class="border-b border-gray-700 flex space-x-8 text-sm mb-4">
        <button
          v-for="t in tabs"
          :key="t.key"
          @click="activeTab = t.key"
          :class="activeTab === t.key
            ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
            : 'text-gray-400 pb-2'"
        >
          {{ t.label }}
        </button>
      </div>

      <!-- Order Form -->
      <div class="bg-[#0f172a] p-6 rounded-lg border border-gray-700 space-y-5">

        <h2 class="text-lg font-semibold">
          Place {{ activeTabLabel }} Order
        </h2>

        <!-- Symbol Search -->
        <div>
          <label class="text-sm text-gray-400">Search Company / Symbol</label>
          <input
            v-model="search"
            @input="searchCompany"
            class="input"
            placeholder="Type at least 2 letters…"
          />

          <!-- Suggestions -->
          <div v-if="suggestions.length" class="bg-[#1e293b] rounded mt-1 border border-gray-700">
            <div
              v-for="s in suggestions"
              :key="s.symbol"
              class="px-3 py-2 hover:bg-blue-900 cursor-pointer"
              @click="selectCompany(s)"
            >
              {{ s.symbol }} — {{ s.name }} (₦{{ s.market_price }})
            </div>
          </div>
        </div>

        <!-- Selected company -->
        <div v-if="order.symbol" class="p-3 bg-[#1e293b] rounded border border-gray-700">
          <p class="text-white font-semibold">{{ order.symbol }} — {{ order.company }}</p>
          <p class="text-gray-400 text-sm">Market Price: {{ currency(order.market_price) }}</p>
        </div>

        <!-- Amount -->
        <div>
          <label class="text-sm text-gray-400">Amount ({{ isUSD ? '$' : '₦' }})</label>
          <input
            v-model="order.amount"
            type="number"
            @input="calculateUnits"
            class="input"
          />
        </div>

        <!-- Units -->
        <div>
          <label class="text-sm text-gray-400">Units</label>
          <input
            v-model="order.units"
            type="number"
            readonly
            class="input bg-gray-800"
          />
        </div>

        <button
          @click="submitOrder"
          class="bg-blue-600 px-4 py-2 rounded text-white w-full hover:bg-blue-700"
        >
          Place Order
        </button>

        <p v-if="message" class="text-center text-yellow-400 text-sm">{{ message }}</p>

      </div>

      <!-- Order List -->
      <div class="bg-[#0f172a] p-6 rounded-lg border border-gray-700">
        <h2 class="text-lg font-semibold mb-3">Your Orders</h2>

        <table class="w-full text-sm">
          <thead class="text-gray-400 text-xs border-b border-gray-700">
            <tr>
              <th class="text-left py-2 px-2">Symbol</th>
              <th class="text-left px-2">Amount</th>
              <th class="text-left px-2">Units</th>
              <th class="text-left px-2">Status</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="o in orders"
              :key="o.id"
              class="border-b border-gray-800"
            >
              <td class="px-2 py-3">{{ o.symbol }}</td>
              <td class="px-2">{{ currency(o.amount) }}</td>
              <td class="px-2">{{ o.units }}</td>
              <td class="px-2">
                <span class="text-yellow-400">{{ o.status }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import DashboardLayout from "@/Layouts/DashboardLayout.vue";
import axios from "axios";

const tabs = [
  { key: "ngx", label: "NGX (₦)" },
  { key: "global", label: "Global Stocks ($)" },
  { key: "crypto", label: "Crypto ($)" },
];

const activeTab = ref("ngx");
const activeTabLabel = computed(() => tabs.find(t => t.key === activeTab.value)?.label);

const isUSD = computed(() => activeTab.value !== "ngx");

const search = ref("");
const suggestions = ref([]);
const message = ref("");

const orders = ref([]);

const order = reactive({
  company: "",
  symbol: "",
  market_price: 0,
  amount: 0,
  units: 0,
});

const currency = (n) => {
  if (isUSD.value) return `$${n}`;
  return `₦${n}`;
};

const token = localStorage.getItem("xavier_token");

const searchCompany = async () => {
  if (search.value.length < 2) return (suggestions.value = []);

  const res = await axios.get(`/api/companies/search/${search.value}`, {
    headers: { Authorization: `Bearer ${token}` },
  });

  suggestions.value = res.data.data;
};

const selectCompany = (s) => {
  order.company = s.name;
  order.symbol = s.symbol;
  order.market_price = s.market_price;
  suggestions.value = [];
  search.value = `${s.symbol} — ${s.name}`;
  calculateUnits();
};

const calculateUnits = () => {
  if (order.amount && order.market_price) {
    order.units = (order.amount / order.market_price).toFixed(4);
  }
};

const submitOrder = async () => {
  if (!order.symbol || !order.amount) {
    message.value = "Enter symbol and amount";
    return;
  }

  const payload = {
    company: order.company,
    symbol: order.symbol,
    market_price: order.market_price,
    amount: order.amount,
    units: order.units,
    type: activeTab.value,
  };

  const res = await axios.post("/api/orders", payload, {
    headers: { Authorization: `Bearer ${token}` },
  });

  message.value = "Order placed successfully.";
  loadOrders();
};

const loadOrders = async () => {
  const res = await axios.get("/api/orders", {
    headers: { Authorization: `Bearer ${token}` },
  });
  orders.value = res.data.data;
};

onMounted(() => loadOrders());
</script>

<style>
.input {
  @apply w-full bg-transparent border border-gray-600 rounded-lg px-3 py-2 text-sm;
}
</style>
