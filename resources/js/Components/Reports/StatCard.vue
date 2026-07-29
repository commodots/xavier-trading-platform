<template>
  <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5 hover:border-blue-500/30 transition-all duration-200">
    <div class="flex items-start justify-between gap-4">
      <div class="flex-1  w-fit">
        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider truncate">{{ displayTitle }}</p>
        <p class="lg:text-[16px] sm:text-[13px] font-bold text-white mt-1 truncate" :title="fullValue">{{ formattedValue }}</p>
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
  return num.toLocaleString(undefined, {
    minimumFractionDigits: props.decimals,
    maximumFractionDigits: props.decimals, 
  });
});

const formattedValue = computed(() => {
  // Convert to number first, even if it's a string
  const num = Number(props.value) || 0;
  
  // Format with maximum precision first and comma separators
  const parts = num.toLocaleString('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 20,
  }).split('.');
  
  // Remove trailing zeros from decimal part
  if (parts[1]) {
    parts[1] = parts[1].replace(/0+$/, '');
    if (parts[1] === '') {
      return props.prefix + parts[0];
    }
    return props.prefix + parts.join('.');
  }
  
  return props.prefix + parts[0];
});
</script>