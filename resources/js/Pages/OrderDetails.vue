<template>
  <MainLayout>
    <div class="max-w-3xl mx-auto space-y-6">

      <button @click="$router.back()" class="flex items-center gap-2 text-gray-300 hover:text-white">
        ← Back
      </button>

      <div>
        <h1 class="text-2xl font-semibold">📘 Order Details</h1>
        <p class="text-sm text-gray-400">Full breakdown of your investment order.</p>
      </div>

      <div v-if="loading" class="py-10 text-center text-gray-400">
        Loading order details...
      </div>

      <div v-if="error" class="py-10 text-center text-red-400">
        {{ error }}
      </div>

      <div v-if="order" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6 space-y-6">
        <div class="flex items-start justify-between">
          <div>
            <h2 class="text-xl font-semibold">{{ order.company }}</h2>
            <p class="text-gray-400">{{ order.symbol }}</p>

            <span class="inline-block px-3 py-1 mt-2 text-xs rounded-lg" :class="statusClass(order.status)">
              {{ beautifyStatus(order.status) }}
            </span>
          </div>

          <button v-if="order.status === 'open'" @click="showCancelModal = true"
            class="px-4 py-2 text-sm text-red-300 rounded-lg bg-red-500/20 hover:bg-red-500/40 transition-colors">
            Cancel Order
          </button>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

          <div class="space-y-2">
            <p class="text-sm text-gray-400">Order Side</p>
            <p class="font-bold uppercase" :class="order.side === 'buy' ? 'text-green-400' : 'text-red-400'">
              {{ order.side }} order
            </p>
          </div>

          <div class="space-y-2">
            <p class="text-sm text-gray-400">Order Type</p>
            <p class="font-semibold capitalize">{{ order.type }}</p>
          </div>

          <div class="space-y-2">
            <p class="text-sm text-gray-400">Order Date</p>
            <p class="font-semibold">{{ formatDate(order.created_at) }}</p>
          </div>

          <div class="space-y-2">
            <p class="text-sm text-gray-400">Units</p>
            <p class="font-semibold">{{ order.units }}</p>
          </div>

          <div class="space-y-2">
            <p class="text-sm text-gray-400">Order Amount</p>
            <p class="font-semibold">₦{{ Number(order.amount).toLocaleString() }}</p>
          </div>

          <div class="space-y-2">
            <p class="text-sm text-gray-400">Market Price</p>
            <p class="font-semibold">
              {{ (order.market === 'CRYPTO' || order.market === 'GLOBAL') ? '$' : '₦' }}{{ Number(order.market_price).toLocaleString() }}
            </p>
          </div>

          <div class="space-y-2">
            <p class="text-sm text-gray-400">Value</p>
            <p class="font-semibold">
              {{ (order.market === 'CRYPTO' || order.market === 'GLOBAL') ? '$' : '₦' }}{{ (order.market_price * order.units).toLocaleString(undefined, { maximumFractionDigits: 2 }) }}
            </p>
          </div>

        </div>

        <div class="border-t border-[#1f3348]"></div>

        <div>
          <h3 class="mb-3 text-lg font-semibold">Order Lifecycle</h3>
          <ul class="space-y-3 text-sm">
            <li class="flex justify-between">
              <span>📤 Order Submitted</span>
              <span>{{ formatDate(order.created_at) }}</span>
            </li>
            <li v-if="order.filled_quantity > 0" class="flex justify-between text-blue-300">
              <span>🔄 Partially Matched</span>
              <span>{{ order.filled_quantity }} / {{ order.quantity }}</span>
            </li>
            <li v-if="order.status === 'filled'" class="flex justify-between text-green-400">
              <span>✅ Fully Matched</span>
              <span>Completed</span>
            </li>
            <li v-if="order.status === 'settled'" class="flex justify-between text-purple-400">
              <span>🏦 Settled (CSCS)</span>
              <span>Posted to Portfolio</span>
            </li>
            <li v-if="order.status === 'cancelled' || order.status === 'canceled'" class="flex justify-between text-red-400">
              <span>❌ Cancelled</span>
            </li>
          </ul>
        </div>

        <div class="border-t border-[#1f3348] pt-4">
          <h3 class="mb-3 text-lg font-semibold">Settlement Timeline</h3>
          <ul class="space-y-3">
            <li class="text-sm flex items-center gap-2">📝 Order Submitted</li>
            <li v-if="order.filled_quantity > 0" class="text-sm flex items-center gap-2 text-blue-300">⚡ Matched</li>
            <li v-if="order.status === 'settled'" class="text-sm flex items-center gap-2 text-purple-300">🏦 CSCS Settled</li>
          </ul>
        </div>

        <div class="flex items-center gap-3">
          <span class="w-3 h-3 rounded-full"
            :class="order.status !== 'open' ? 'bg-blue-400' : 'bg-yellow-300'"></span>
          <span class="text-sm">
            {{ order.status === "open" ? "Pending" : "Market processing" }}
          </span>
        </div>
      </div>

      <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="bg-[#0F1724] border border-[#1f3348] w-full max-w-md rounded-2xl p-6 shadow-2xl">
          <h3 class="text-xl font-bold mb-2">Cancel Order?</h3>
          <p class="text-gray-400 mb-6">Are you sure you want to cancel this {{ order.side }} order for {{ order.symbol }}? This action cannot be undone.</p>
          
          <div class="flex gap-3">
            <button @click="showCancelModal = false" class="flex-1 px-4 py-3 rounded-xl bg-gray-800 hover:bg-gray-700 transition-colors">
              Go Back
            </button>
            <button @click="confirmCancel" :disabled="isCancelling" class="flex-1 px-4 py-3 rounded-xl bg-red-600 hover:bg-red-700 transition-colors disabled:opacity-50">
              {{ isCancelling ? 'Cancelling...' : 'Yes, Cancel' }}
            </button>
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
import { useRoute, useRouter } from "vue-router";

const order = ref(null);
const loading = ref(true);
const error = ref("");
const showCancelModal = ref(false);
const isCancelling = ref(false);

const route = useRoute();
const router = useRouter();
const id = route.params.id;

onMounted(async () => {
  await loadOrder();
});

async function loadOrder() {
  try {
    const token = localStorage.getItem("xavier_token");
    const res = await axios.get(`/orders`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    const list = res.data.data || res.data;
    order.value = list.find((o) => o.id == id);
    if (!order.value) error.value = "Order not found.";
  } catch (e) {
    error.value = "Error loading order.";
  } finally {
    loading.value = false;
  }
}

async function confirmCancel() {
  isCancelling.value = true;
  try {
    const token = localStorage.getItem("xavier_token");
    await axios.post(`/orders/${id}/cancel`, {}, { 
      headers: { Authorization: `Bearer ${token}` } 
    });
    showCancelModal.value = false;
    router.push("/orders");
  } catch (e) {
    alert("Failed to cancel order.");
  } finally {
    isCancelling.value = false;
  }
}

function beautifyStatus(s) {
  if (s === "open") return "Open";
  if (s === "filled") return "Filled";
  if (s === "canceled" || s === "cancelled") return "Canceled";
  return s;
}

function statusClass(s) {
  return {
    "bg-yellow-500/20 text-yellow-300": s === "open",
    "bg-blue-500/20 text-blue-300": s === "filled",
    "bg-red-500/20 text-red-300": s === "canceled" || s === "cancelled",
  };
}

function formatDate(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleDateString() + " " + d.toLocaleTimeString();
}
</script>