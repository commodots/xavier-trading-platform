<template>
  <MainLayout>
    <div class="mx-auto space-y-6 max-w-7xl">
      
      <button 
        @click="router.push('/global-stocks')" 
        class="flex items-center text-gray-400 transition hover:text-white group"
      >
        <svg xmlns="http://www.w3.org/2000/svg"
          class="w-5 h-5 mr-2 transition-transform transform group-hover:-translate-x-1" 
          fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Back to Your Holdings
      </button>

      <div>
        <MarketTicker :selected-symbol="selectedSymbol" @select-symbol="selectedSymbol = $event" />
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        
        <div class="lg:col-span-2 bg-[#0F1724] border border-[#1f3348] rounded-xl p-6 overflow-hidden">
          <div class="flex flex-col gap-2 mb-4">
            <div class="flex items-center justify-between">
              <h2 class="text-xl font-semibold">Market Analysis - {{ selectedSymbol }}</h2>
              <span class="font-mono text-xs text-gray-500">LIVE FEED</span>
            </div>
            <p class="text-sm text-gray-400">Click a ticker above to change the chart and analysis.</p>
          </div>
          <MarketChart :symbol="selectedSymbol" />
        </div>

        <div class="flex flex-col gap-6">
          
          <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
            <TradePanel />
          </div>

          <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
            <PortfolioPanel />
          </div>

        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { useRouter } from 'vue-router'
import MainLayout from '@/Layouts/MainLayout.vue'
import MarketTicker from "@/Components/MarketTicker.vue";
import MarketChart from "@/Components/MarketChart.vue";
import TradePanel from "@/Components/TradePanel.vue";
import PortfolioPanel from "@/Components/PortfolioPanel.vue";
import api from '@/api';

const router = useRouter()
import { ref, watch } from 'vue'

const selectedSymbol = ref('AAPL')

watch(selectedSymbol, async (symbol) => {
  if (!symbol) {
    return;
  }

  try {
    await api.post('/stocks/track', { symbol });
  } catch (error) {
    console.error('Failed to track selected symbol:', symbol, error);
  }
});
</script>
