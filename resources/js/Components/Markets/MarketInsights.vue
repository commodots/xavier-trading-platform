<template>
  <div class="bg-[#0F1724] rounded-xl border border-[#1f3348] flex flex-col h-full min-h-[500px]">
    <div class="flex flex-col gap-3 border-b border-[#1f3348] p-4 sm:flex-row sm:items-center sm:justify-between">
      <h3 class="text-sm font-bold tracking-wider text-gray-400 uppercase">Market Insights</h3>
      <input
        v-model="search"
        type="search"
        placeholder="Search stocks..."
        aria-label="Search NGX stocks"
        class="w-full rounded-lg border border-[#1f3348] bg-[#0B121D] px-3 py-2 text-xs text-gray-300 outline-none transition focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] sm:w-64"
      />
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
          <template v-if="loading">
            <tr v-for="row in 5" :key="`skeleton-${row}`" aria-hidden="true">
              <td class="px-4 py-3">
                <SkeletonLoader type="block" class="mb-2 h-3 w-16" />
                <SkeletonLoader type="block" class="h-3 w-32" />
              </td>
              <td class="px-4 py-3">
                <SkeletonLoader type="block" class="ml-auto h-3 w-20" />
                <SkeletonLoader type="block" class="ml-auto mt-2 h-3 w-12" />
              </td>
              <td class="px-4 py-3 text-right">
                <SkeletonLoader type="block" class="ml-auto h-6 w-12" />
              </td>
            </tr>
          </template>
          <template v-else>
            <tr v-for="item in visibleMarketData" :key="item.symbol" class="hover:bg-[#16213A] transition group">
            <td class="px-4 py-3 cursor-pointer" @click="$emit('select', item)">
              <div class="font-bold text-white group-hover:text-[#00D4FF]">{{ item.symbol }}</div>
              <div class="max-w-48 whitespace-normal break-words text-[10px] leading-4 text-gray-500">{{ item.name }}</div>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="font-semibold text-white">
                {{ currencySymbol }}{{ Number(item.price || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
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
          </template>
        </tbody>
      </table>
      <div v-if="!loading && !filteredMarketData.length" class="p-10 text-center text-gray-500 text-xs">
        {{ search.trim() ? 'No stocks match your search.' : 'No data available' }}
      </div>
      <div v-if="!loading && hasMoreMarketData" class="border-t border-[#1f3348] p-3 text-center">
        <button
          type="button"
          class="rounded-lg border border-[#1f3348] px-4 py-2 text-xs font-bold text-[#00D4FF] transition hover:bg-[#00D4FF]/10"
          @click="showMore"
        >
          See more
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import SkeletonLoader from "@/Components/SkeletonLoader.vue";

const props = defineProps({
  insightData: Object,
  allData: { type: Array, default: () => [] },
  currencySymbol: { type: String, default: '$' },
  loading: Boolean
});

const emit = defineEmits(['select', 'trade']);
const activeTab = ref('all');
const search = ref('');
const visibleAllCount = ref(20);
const marketTabs = [
  { id: 'all', name: 'All' },
  { id: 'gainers', name: 'Gainers' },
  { id: 'losers', name: 'Losers' },
  { id: 'most_traded', name: 'Most Traded' }
];

const currentMarketData = computed(() => activeTab.value === 'all'
  ? props.allData
  : props.insightData?.[activeTab.value] || []);
const filteredMarketData = computed(() => {
  const query = search.value.toLowerCase().trim();

  return currentMarketData.value.filter(item =>
    String(item.symbol || '').toLowerCase().includes(query) ||
    String(item.name || '').toLowerCase().includes(query)
  );
});
const visibleMarketData = computed(() => activeTab.value === 'all'
  ? filteredMarketData.value.slice(0, visibleAllCount.value)
  : filteredMarketData.value);
const hasMoreMarketData = computed(() =>
  activeTab.value === 'all' && visibleAllCount.value < filteredMarketData.value.length
);

watch([activeTab, search], () => {
  visibleAllCount.value = 20;
});

const showMore = () => {
  visibleAllCount.value += 20;
};
</script>