<template>
  <MainLayout>
    <div class="max-w-3xl mx-auto py-10">
      <h1 class="text-2xl font-semibold mb-6">📄 Order Details</h1>

      <div class="bg-[#0F1724] p-6 rounded-xl border border-[#1f3348] space-y-4">

        <div class="flex justify-between">
          <div>
            <h2 class="text-xl font-semibold">{{ order.symbol }}</h2>
            <p class="text-gray-400">{{ order.company }}</p>
          </div>
          <span
            class="px-3 py-1 text-sm rounded"
            :class="{
              'bg-yellow-500/20 text-yellow-400': order.status === 'pending_market',
              'bg-blue-500/20 text-blue-400': order.status === 'executed',
              'bg-red-500/20 text-red-400': order.status === 'cancelled'
            }"
          >
            {{ order.status }}
          </span>
        </div>

        <div class="grid grid-cols-2 gap-4 pt-4 text-sm">
          <div>
            <p class="text-gray-400">Order Type</p>
            <p>{{ order.type }}</p>
          </div>

          <div>
            <p class="text-gray-400">Units</p>
            <p>{{ order.units }}</p>
          </div>

          <div>
            <p class="text-gray-400">Market Price</p>
            <p>₦{{ order.market_price.toLocaleString() }}</p>
          </div>

          <div>
            <p class="text-gray-400">Amount</p>
            <p>₦{{ order.amount.toLocaleString() }}</p>
          </div>

          <div>
            <p class="text-gray-400">Date</p>
            <p>{{ order.date }}</p>
          </div>

          <div>
            <p class="text-gray-400">Reference</p>
            <p>{{ order.ref }}</p>
          </div>
        </div>

        <button
          v-if="order.status === 'pending_market'"
          @click="cancelOrder"
          class="mt-6 bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg text-white"
        >
          Cancel Order
        </button>

        <p v-if="message" class="text-yellow-400 mt-3">{{ message }}</p>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import axios from "axios";
import MainLayout from "@/layouts/MainLayout.vue";

const route = useRoute();
const order = ref(null);
const message = ref("");

onMounted(async () => {
  const token = localStorage.getItem("xavier_token");

  const res = await axios.get("/api/orders", {
    headers: { Authorization: `Bearer ${token}` },
  });

  order.value = res.data.data.find((o) => o.id == route.params.id);
});

const cancelOrder = async () => {
  const token = localStorage.getItem("xavier_token");

  const res = await axios.post(
    `/api/orders/${order.value.id}/cancel`,
    {},
    { headers: { Authorization: `Bearer ${token}` } }
  );

  message.value = "Order cancelled successfully.";
  order.value.status = "cancelled";
};
</script>
