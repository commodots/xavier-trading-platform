<template>
  <div class="absolute right-0 mt-2 w-80 bg-[#16213A] shadow-2xl rounded-xl border border-[#1f3348] overflow-hidden transform origin-top-right transition-all duration-200 ease-out z-50">
    <div class="p-4 border-b border-[#1f3348] font-semibold text-white flex justify-between items-center">
      <span>Notifications</span>
    </div>

    <div class="max-h-96 overflow-y-auto divide-y divide-[#1f3348]">
      <div v-if="!notifications || !Array.isArray(notifications) || notifications.length === 0" class="p-6 text-center text-sm text-gray-400">
        All caught up!
      </div>
      <template v-else>
        <NotificationItem 
          v-for="n in safeNotifications" 
          :key="n.id" 
          :notification="n" 
          @markRead="$emit('markRead', n.id)"
        />
      </template>
    </div>

    <div class="p-3 text-center border-t border-[#1f3348] bg-[#0F1724]">
      <a href="/notifications" class="text-blue-400 hover:text-blue-300 text-sm font-medium inline-flex items-center gap-1">
        View All Notifications →
      </a>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import NotificationItem from './NotificationItem.vue'

const props = defineProps({
  notifications: {
    type: Array,
    default: () => [] 
  }
})

defineEmits(['markRead'])


const safeNotifications = computed(() => {
  if (!props.notifications || !Array.isArray(props.notifications)) return [];
  return props.notifications.filter(item => item !== null);
})
</script>