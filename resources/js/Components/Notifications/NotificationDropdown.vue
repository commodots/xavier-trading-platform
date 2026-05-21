<template>
  <div class="absolute right-0 mt-2 w-80 bg-white shadow-xl rounded-xl border border-gray-100 overflow-hidden transform origin-top-right transition-all duration-200 ease-out">
    <div class="p-4 border-b border-gray-100 font-semibold text-gray-800 flex justify-between items-center">
      <span>Notifications</span>
    </div>

    <!-- Scrollable container -->
    <div class="max-h-96 overflow-y-auto divide-y divide-gray-50">
      <div v-if="notifications.length === 0" class="p-6 text-center text-sm text-gray-400">
        All caught up!
      </div>
      <NotificationItem 
        v-for="n in notifications" 
        :key="n.id" 
        :notification="n" 
        @markRead="$emit('markRead', n.id)"
      />
    </div>

    <div class="p-3 text-center border-t border-gray-100 bg-gray-50">
      <router-link to="/notifications" class="text-blue-600 hover:text-blue-700 text-sm font-medium inline-flex items-center gap-1">
        View All Notifications →
      </router-link>
    </div>
  </div>
</template>

<script setup>
import NotificationItem from './NotificationItem.vue'

defineProps({
  notifications: Array
})

defineEmits(['markRead'])
</script>