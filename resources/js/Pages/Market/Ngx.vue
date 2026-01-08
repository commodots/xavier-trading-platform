<template>
  <DashboardLayout>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-4">NGX Market</h1>

      <div class="space-y-3">
        <div
          v-for="item in market"
          :key="item.symbol"
           class="bg-[#0f172a] p-4 rounded border border-gray-700 cursor-pointer hover:bg-[#1e293b]"
  @click="openStock(item.symbol)"
        >
          <p class="font-semibold text-white">
            {{ item.symbol }} — {{ item.name }}
          </p>
          <p class="text-gray-400 text-sm">
            ₦{{ item.price }} (Today: {{ item.change }}%)
          </p>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import DashboardLayout from "@/Layouts/DashboardLayout.vue";
import { ref, onMounted } from "vue";
import axios from "axios";

import { useRouter } from "vue-router";
const router = useRouter();

const market = ref([]);
const openStock = (symbol) => {
  router.push(`/market/ngx/${symbol}`);
};

onMounted(async () => {
  const token = localStorage.getItem("xavier_token");

  const res = await axios.get("/api/market/ngx", {
    headers: { Authorization: `Bearer ${token}` },
  });

  market.value = res.data.data;
});
</script>
