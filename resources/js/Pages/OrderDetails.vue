<template>
  <MainLayout>
    <div class="max-w-3xl mx-auto space-y-6">

      <!-- Back button -->
      <button
        @click="$router.back()"
        class="text-gray-300 hover:text-white flex items-center gap-2"
      >
        ← Back
      </button>

      <!-- Title -->
      <div>
        <h1 class="text-2xl font-semibold">📘 Order Details</h1>
        <p class="text-gray-400 text-sm">Full breakdown of your investment order.</p>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center py-10 text-gray-400">
        Loading order details...
      </div>

      <!-- Error -->
      <div v-if="error" class="text-red-400 py-10 text-center">
        {{ error }}
      </div>

      <!-- Order Details Card -->
      <div
        v-if="order"
        class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6 space-y-6"
      >
        <!-- Header Row -->
        <div class="flex justify-between items-start">
          <div>
            <h2 class="text-xl font-semibold">{{ order.company }}</h2>
            <p class="text-gray-400">{{ order.symbol }}</p>

            <span
              class="text-xs px-3 py-1 rounded-lg inline-block mt-2"
              :class="statusClass(order.status)"
            >
              {{ beautifyStatus(order.status) }}
            </span>
          </div>

          <!-- Cancel Button -->
          <button
            v-if="order.status === 'pending_market'"
            @click="cancelOrder"
            class="bg-red-500/20 hover:bg-red-500/40 px-4 py-2 text-red-300 rounded-lg text-sm"
          >
            Cancel Order
          </button>
        </div>

        <!-- Order Info Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

          <div class="space-y-2">
            <p class="text-gray-400 text-sm">Order Type</p>
            <p class="font-semibold capitalize">{{ order.type }}</p>
          </div>

          <div class="space-y-2">
            <p class="text-gray-400 text-sm">Order Date</p>
            <p class="font-semibold">{{ formatDate(order.created_at) }}</p>
          </div>

          <div class="space-y-2">
            <p class="text-gray-400 text-sm">Units</p>
            <p class="font-semibold">{{ order.units }}</p>
          </div>

          <div class="space-y-2">
            <p class="text-gray-400 text-sm">Order Amount</p>
            <p class="font-semibold">₦{{ Number(order.amount).toLocaleString() }}</p>
          </div>

          <div class="space-y-2">
            <p class="text-gray-400 text-sm">Market Price</p>
            <p class="font-semibold">
              ₦{{ Number(order.market_price).toLocaleString() }}
            </p>
          </div>

          <div class="space-y-2">
            <p class="text-gray-400 text-sm">Value</p>
            <p class="font-semibold">
              ₦{{ (order.market_price * order.units).toLocaleString() }}
            </p>
          </div>

        </div>

        <!-- Divider -->
        <div class="border-t border-[#1f3348]"></div>

        <!-- Order Timeline -->
        <div>
          <h3 class="text-lg font-semibold mb-3">Order Timeline</h3>

          <div class="space-y-3">
            <div class="flex items-center gap-3">
              <span class="w-3 h-3 rounded-full bg-blue-400"></span>
              <span class="text-sm">Order created</span>
              <span class="text-gray-400 text-xs ml-auto">
                {{ formatDate(order.created_at) }}
              </span>
            </div>

            <div class="flex items-center gap-3">
              <span
                class="w-3 h-3 rounded-full"
                :class="order.status !== 'pending_market' ? 'bg-blue-400' : 'bg-yellow-300'"
              ></span>
              <span class="text-sm">
                {{ order.status === "pending_market" ? "Pending market" : "Market processing" }}
              </span>
            </div>

            <div
              v-if="order.status === 'filled'"
              class="flex items-center gap-3"
            >
              <span class="w-3 h-3 rounded-full bg-green-400"></span>
              <span class="text-sm">Order Filled</span>
            </div>

            <div
              v-if="order.status === 'cancelled'"
              class="flex items-center gap-3"
            >
              <span class="w-3 h-3 rounded-full bg-red-400"></span>
              <span class="text-sm">Order Cancelled</span>
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
import MainLayout from "@/layouts/MainLayout.vue";

const order = ref(null);
const loading = ref(true);
const error = ref("");

const route = useRoute();
const router = useRouter();
const id = route.params.id;

onMounted(async () => {
  await loadOrder();
});

async function loadOrder() {
  try {
    const token = localStorage.getItem("xavier_token");
    const res = await axios.get(`/api/orders`, {
      headers: { Authorization: `Bearer ${token}` },
    });

    const list = res.data.data || res.data;

    order.value = list.find((o) => o.id == id);

    if (!order.value) {
      error.value = "Order not found.";
    }
  } catch (e) {
    error.value = "Error loading order.";
  } finally {
    loading.value = false;
  }
}

async function cancelOrder() {
  if (!confirm("Cancel this order?")) return;

  try {
    const token = localStorage.getItem("xavier_token");
    await axios.post(
      `/api/orders/${id}/cancel`,
      {},
      { headers: { Authorization: `Bearer ${token}` } }
    );

    router.push("/orders");
  } catch (e) {
    alert("Failed to cancel order.");
  }
}

function beautifyStatus(s) {
  if (s === "pending_market") return "Pending Market";
  if (s === "filled") return "Filled";
  if (s === "cancelled") return "Cancelled";
  return s;
}

function statusClass(s) {
  return {
    "bg-yellow-500/20 text-yellow-300": s === "pending_market",
    "bg-blue-500/20 text-blue-300": s === "filled",
    "bg-red-500/20 text-red-300": s === "cancelled",
  };
}

function formatDate(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleDateString() + " " + d.toLocaleTimeString();
}
</script>
