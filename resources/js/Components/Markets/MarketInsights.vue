<template>
  <div class="bg-[#0F1724] rounded-xl border border-[#1f3348] flex flex-col h-full min-h-[500px]">
    <div class="p-4 border-b border-[#1f3348] flex justify-between items-center">
      <h3 class="text-sm font-bold tracking-wider text-gray-400 uppercase">Market Insights</h3>
      <span v-if="loading" class="text-[10px] text-blue-400 animate-pulse">Syncing...</span>
    </div>

    <!-- Tab Switcher -->
    <div class="flex border-b border-[#1f3348] text-[10px] font-bold uppercase overflow-x-auto no-scrollbar bg-[#0B121D]">
      <button v-for="tab in marketTabs" :key="tab.id" @click="activeTab = tab.id"
        class="flex-1 px-2 py-3 transition-all border-b-2"
        :class="activeTab === tab.id ? 'border-[#00D4FF] text-[#00D4FF] bg-[#00D4FF]/5' : 'border-transparent text-gray-500'">
        {{ tab.name }}
      </button>
    </div>

    <div class="flex-1 overflow-y-auto custom-scrollbar">
      <table class="w-full text-xs">
        <tbody class="divide-y divide-[#1f3348]/50">
          <tr v-for="item in currentMarketData" :key="item.symbol" class="hover:bg-[#16213A] transition group">
            <td class="px-4 py-3 cursor-pointer" @click="$emit('select', item)">
              <div class="font-bold text-white group-hover:text-[#00D4FF]">{{ item.symbol }}</div>
              <div class="text-[10px] text-gray-500 truncate w-20">{{ item.name }}</div>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="font-semibold text-white">
                {{ currencySymbol }}{{ item.price.toLocaleString(undefined, {minimumFractionDigits: 2}) }}
              </div>
              <div :class="item.change >= 0 ? 'text-green-400' : 'text-red-400'" class="text-[10px]">
                {{ item.change >= 0 ? '+' : '' }}{{ item.change }}%
              </div>
            </td>
            <td class="px-4 py-3 text-right">
               <button @click.stop="$emit('trade', item)" 
                 class="bg-[#00D4FF]/10 text-[#00D4FF] border border-[#00D4FF]/30 px-2 py-1 rounded text-[10px] font-bold hover:bg-[#00D4FF] hover:text-[#0F1724] transition">
                 BUY
               </button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!currentMarketData?.length" class="p-10 text-center text-gray-500 text-xs">
        No data available
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";

const props = defineProps({
  insightData: Object,
  currencySymbol: { type: String, default: '$' },
  loading: Boolean
});

const emit = defineEmits(['select', 'trade']);
const activeTab = ref('gainers');
const marketTabs = [
  { id: 'gainers', name: 'Gainers' },
  { id: 'losers', name: 'Losers' },
  { id: 'most_traded', name: 'Most Traded' }
];

const currentMarketData = computed(() => props.insightData?.[activeTab.value] || []);
</script>