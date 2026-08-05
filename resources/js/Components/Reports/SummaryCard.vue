<template>
  <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5 hover:border-blue-500/30 transition-all duration-200">
    <div class="flex items-start justify-between gap-4">
      <div class="flex-1 w-fit">
        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider truncate">{{ title }}</p>
        <p class="text-xl font-bold text-white mt-1 break-words">{{ formattedValue }}</p>
        <div v-if="percentage !== undefined" class="flex items-center gap-1.5 mt-2">
          <span :class="trend === 'up' ? 'text-green-400' : 'text-red-400'" class="text-sm font-medium">
            {{ trend === 'up' ? '↑' : '↓' }} {{ Math.abs(percentage) }}%
          </span>
          <span class="text-xs text-gray-500">vs last period</span>
        </div>
      </div>
      <div v-if="IconComponent" class="p-3 rounded-lg flex-shrink-0" :style="{ backgroundColor: color + '20' }">
        <component :is="IconComponent" :style="{ color: color }" class="text-xl" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import {
  Users, UserPlus, CheckCircle, Clock, Ban, Star, UserX,
  DollarSign, TrendingUp, Activity, Shield, AlertTriangle,
  Server, Database, Mail, Bell, Zap, Globe
} from 'lucide-vue-next';

const props = defineProps({
  title: { type: String, required: true },
  value: { type: [Number, String], required: true },
  icon: { type: String, default: '' },
  color: { type: String, default: '#0047AB' },
  percentage: { type: Number, default: undefined },
  trend: { type: String, default: 'up' },
  prefix: { type: String, default: '' },
  decimals: { type: Number, default: 0 },
});

const iconMap = {
  'users': Users,
  'user-plus': UserPlus,
  'check-circle': CheckCircle,
  'clock': Clock,
  'ban': Ban,
  'star': Star,
  'user-x': UserX,
  'dollar': DollarSign,
  'trending-up': TrendingUp,
  'activity': Activity,
  'shield': Shield,
  'alert-triangle': AlertTriangle,
  'server': Server,
  'database': Database,
  'mail': Mail,
  'bell': Bell,
  'zap': Zap,
  'globe': Globe,
};

const IconComponent = computed(() => {
  return iconMap[props.icon] || null;
});

const formattedValue = computed(() => {
  const num = Number(props.value) || 0;
  const isMoney = Boolean(props.prefix);
  const formatter = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: isMoney ? 2 : 0,
    maximumFractionDigits: isMoney ? 2 : 0,
  });

  if (num >= 1_000_000_000) {
    const value = (num / 1_000_000_000).toFixed(isMoney ? 2 : 0).replace(/\.0$/, '');
    return props.prefix + value + 'B';
  }
  if (num >= 1_000_000) {
    const value = (num / 1_000_000).toFixed(isMoney ? 2 : 0).replace(/\.0$/, '');
    return props.prefix + value + 'M';
  }
  if (num >= 1_000) {
    const value = (num / 1_000).toFixed(isMoney ? 2 : 0).replace(/\.0$/, '');
    return props.prefix + value + 'K';
  }

  return `${props.prefix}${formatter.format(num)}`;
});
</script>