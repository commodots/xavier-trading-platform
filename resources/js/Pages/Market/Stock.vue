<template>
  <MainLayout>
    <div class="p-6 space-y-4">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">
          {{ symbol }} — Stock Chart
        </h1>

        <!-- timeframe / chart toggles later -->
      </div>

      <StockChart
        :symbol="symbol"
        market="ngx"
      />
    </div>
  </MainLayout>
</template>

<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
import MainLayout from "@/Layouts/MainLayout.vue";
import StockChart from "@/components/charts/StockChart.vue";

const route = useRoute();

const symbol = computed(() => route.params.symbol);
</script>
