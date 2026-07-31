<template>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    <div
      v-for="card in cards"
      :key="card.label"
      class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4 hover:border-[#0047AB] transition-colors"
    >
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs text-gray-400 uppercase tracking-wider">{{ card.label }}</span>
        <component :is="getIcon(card.icon)" v-if="card.icon" class="w-5 h-5" :style="{ color: card.color }" />
      </div>
      <div class="text-2xl font-bold text-white">
        <span v-if="card.prefix">{{ card.prefix }}</span>
        {{ formatAmount(card.value) }}
      </div>
      <div v-if="card.change !== undefined" class="flex items-center mt-1">
        <span
          class="text-xs font-medium"
          :class="card.change >= 0 ? 'text-green-400' : 'text-red-400'"
        >
          <TrendingUp v-if="card.change >= 0" class="w-3 h-3 inline mr-1" />
          <TrendingDown v-else class="w-3 h-3 inline mr-1" />
          {{ Math.abs(card.change) }}%
        </span>
        <span class="text-xs text-gray-500 ml-1">vs last period</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { h } from 'vue';
import * as LucideIcons from 'lucide-vue-next';

const props = defineProps({
  cards: {
    type: Array,
    required: true,
  },
});

const getIcon = (iconName) => {
  if (!iconName) return null;
  const icon = LucideIcons[iconName];
  return icon || null;
};

const formatAmount = (value) => {
  if (value === null || value === undefined) return '0.00';
  if (typeof value === 'number') {
    return value.toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }
  return value;
};
</script>