<template>
  <MainLayout>
    <div class="space-y-8">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">📑 My Orders</h1>
          <p class="text-gray-400 text-sm">View and manage all your investment orders.</p>
        </div>
      </div>

      <!-- Orders List -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">

        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold">Order History</h2>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
              <tr>
                <th class="text-left py-2 px-2">Date</th>
                <th class="text-left px-2">Asset</th>
                <th class="text-left px-2">Symbol</th>
                <th class="text-left px-2">Units</th>
                <th class="text-left px-2">Amount</th>
                <th class="text-left px-2">Type</th>
                <th class="text-left px-2">Status</th>
                <th class="text-right px-2">Action</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="o in orders"
                :key="o.id"
                class="border-b border-[#1f3348] hover:bg-[#16213A] transition"
              >
                <td class="px-2 py-3">{{ formatDate(o.created_at) }}</td>
                <td class="px-2">{{ o.company }}</td>
                <td class="px-2 font-semibold uppercase">{{ o.symbol }}</td>
                <td class="px-2">{{ o.units }}</td>
                <td class="px-2">₦{{ Number(o.amount).toLocaleString() }}</td>
                <td class="px-2 capitalize text-blue-400">
                  {{ o.type || "buy" }}
                </td>

                <td class="px-2">
                  <span
                    class="px-3 py-1 text-xs rounded-lg"
                    :class="{
                      'bg-yellow-500/20 text-yellow-300': o.status === 'pending_market',
                      'bg-blue-500/20 text-blue-300': o.status === 'filled',
                      'bg-red-500/20 text-red-300': o.status === 'cancelled'
                    }"
                  >
                    {{ beautifyStatus(o.status) }}
                  </span>
                </td>

                <td class="px-2 text-right">
                  <button
                    v-if="o.status === 'pending_market'"
                    @click="cancel(o.id)"
                    class="bg-red-500/20 hover:bg-red-500/40 px-3 py-1 rounded-lg text-red-300 text-xs"
                  >
                    Cancel
                  </button>
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-if="orders.length === 0">
                <td colspan="8" class="text-center py-6 text-gray-500">
                  No orders found yet.
                </td>
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
import axios from "axios";
import MainLayout from "@/layouts/MainLayout.vue";

const orders = ref([]);

onMounted(() => loadOrders());

async function loadOrders() {
  try {
    const token = localStorage.getItem("xavier_token");
    const res = await axios.get("/api/orders", {
      headers: { Authorization: `Bearer ${token}` }
    });

    if (res.data.success || Array.isArray(res.data)) {
      orders.value = res.data.data || res.data || [];
    }
  } catch (e) {
    console.error("Failed to load orders", e);
  }
}

function beautifyStatus(s) {
  if (s === "pending_market") return "Pending (Market)";
  if (s === "filled") return "Filled";
  if (s === "cancelled") return "Cancelled";
  return s;
}

function formatDate(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  return d.toLocaleDateString() + " " + d.toLocaleTimeString();
}

async function cancel(id) {
  try {
    const token = localStorage.getItem("xavier_token");
    const res = await axios.post(`/api/orders/${id}/cancel`, {}, {
      headers: { Authorization: `Bearer ${token}` }
    });

    if (res.data.success) loadOrders();
  } catch (e) {
    console.error("Cancel failed", e);
  }
}
</script>
