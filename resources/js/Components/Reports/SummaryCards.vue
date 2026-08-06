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
      <div class="text-lg font-bold text-white">
        {{ formatAmount(card.value, card.prefix, card.isMoney ?? Boolean(card.prefix), card.suffix || '') }}
      </div>
      <div v-if="card.change !== undefined" class="flex items-center mt-1">
        <span
          class="text-xs font-medium"
          :class="card.change >= 0 ? 'text-green-400' : 'text-red-400'"
        >
          <component :is="card.change >= 0 ? TrendingUp : TrendingDown" class="w-3 h-3 inline mr-1" />
          {{ Math.abs(card.change) }}%
        </span>
        <span class="text-xs text-gray-500 ml-1">vs last period</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import * as LucideIcons from 'lucide-vue-next';
import { TrendingUp, TrendingDown } from 'lucide-vue-next';

const props = defineProps({
  cards: {
    type: Array,
    required: true,
  },
});

const getIcon = (iconName) => {
  if (!iconName) return null;

  const normalized = String(iconName)
    .split('-')
    .map((part, index) => index === 0 ? part : part.charAt(0).toUpperCase() + part.slice(1))
    .join('');

  const pascalName = normalized.charAt(0).toUpperCase() + normalized.slice(1);

  return LucideIcons[pascalName] || LucideIcons[iconName] || null;
};

const formatAmount = (value, prefix = '', isMoney = false, suffix = '') => {
  if (value === null || value === undefined) {
    return isMoney ? `${prefix}0` : '0';
  }

  const num = Number(value);
  if (!Number.isFinite(num)) {
    return isMoney ? `${prefix}${value}` : value;
  }

  const formatter = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: isMoney ? 2 : 0,
  });

  return `${prefix}${formatter.format(num)}${suffix}`;
};
</script>