<template>
  <div class="bg-[#0F1724] border border-[#1f3348] rounded-lg p-4 min-w-0">
    <div class="flex gap-3">
      <div v-if="IconComponent" class="p-3 rounded-lg flex-shrink-0" :style="{ backgroundColor: color + '20' }">
        <component :is="IconComponent" :style="{ color: color }" class="text-xl" />
      </div>
      <p class="text-xs text-gray-400 uppercase tracking-wider truncate">{{ displayTitle }}</p>
    </div>
    <p class="text-lg font-bold text-white mt-1 truncate" :title="fullValue">{{ formattedValue }}</p>
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
  title: { type: String, default: '' },
  label: { type: String, default: '' },
  value: { type: [Number, String], required: true },
  prefix: { type: String, default: '' },
  decimals: { type: Number, default: 2 },
  icon: { type: String, default: '' },
  color: { type: String, default: '#0047AB' },
});

const displayTitle = computed(() => {
  return props.label || props.title || '';
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

const fullValue = computed(() => {
  if (typeof props.value === 'string') return props.value;
  const num = Number(props.value) || 0;
  return props.prefix + num.toLocaleString(undefined, {
    minimumFractionDigits: props.decimals,
    maximumFractionDigits: props.decimals,
  });
});

const formattedValue = computed(() => {
  if (typeof props.value === 'string') return props.value;
  const num = Number(props.value) || 0;

  if (num >= 1_000_000_000) {
    return props.prefix + (num / 1_000_000_000).toFixed(1) + 'B';
  }
  if (num >= 1_000_000) {
    return props.prefix + (num / 1_000_000).toFixed(1) + 'M';
  }
  if (num >= 1_000) {
    return props.prefix + (num / 1_000).toFixed(1) + 'K';
  }

  return props.prefix + num.toLocaleString(undefined, {
    minimumFractionDigits: props.decimals,
    maximumFractionDigits: props.decimals,
  });
});
</script>