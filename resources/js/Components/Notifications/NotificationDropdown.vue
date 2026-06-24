<template>
  <div class="fixed inset-x-4 top-16 z-50 max-h-[calc(100vh-5rem)] overflow-hidden sm:inset-auto sm:right-4 sm:top-16 sm:w-80 bg-[#16213A] shadow-2xl rounded-xl border border-[#1f3348]">
    <div class="p-4 border-b border-[#1f3348] text-white">
      <div class="flex items-center justify-between gap-4">
        <div>
          <p class="text-sm font-semibold">Notifications</p>
          <p class="text-xs text-gray-400">Recent updates, account alerts, and action items.</p>
        </div>
      </div>

      <div class="mt-3 flex flex-wrap gap-2">
        <button
          v-for="tab in tabs"
          :key="tab"
          @click="activeTab = tab"
          :class="['px-3 py-1.5 rounded-full text-xs font-medium transition', activeTab === tab ? 'bg-blue-600 text-white' : 'bg-[#0F1724] text-gray-300 hover:bg-[#1f2a44]']"
        >
          {{ tab }}
        </button>
      </div>
    </div>

    <div class="max-h-[calc(100vh-14rem)] overflow-y-auto divide-y divide-[#1f3348]">
      <div v-if="filteredNotifications.length === 0" class="p-6 text-center text-sm text-gray-400">
        All caught up!
      </div>

      <template v-else>
        <NotificationItem
          v-for="n in filteredNotifications"
          :key="n.id"
          :notification="n"
          @markRead="$emit('markRead', n.id)"
          @view="(notif) => handleView(notif)"
        />
      </template>
    </div>

    <div class="p-3 text-center border-t border-[#1f3348] bg-[#0F1724]">
      <router-link to="/notifications" class="text-blue-400 hover:text-blue-300 text-sm font-medium inline-flex items-center gap-1">
        View All Notifications →
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import NotificationItem from './NotificationItem.vue'

const props = defineProps({
  notifications: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['markRead', 'viewNotification'])
const router = useRouter()

const handleView = (notification) => {
  emit('viewNotification', notification)
}

const tabs = ['All', 'Unread', 'Important']
const activeTab = ref('All')

const safeNotifications = computed(() => {
  if (!props.notifications || !Array.isArray(props.notifications)) return []
  return props.notifications.filter(item => item !== null)
})

const filteredNotifications = computed(() => {
  if (activeTab.value === 'Unread') {
    return safeNotifications.value.filter(item => !item.read)
  }

  if (activeTab.value === 'Important') {
    return safeNotifications.value.filter(item => ['account', 'warning', 'error', 'billing'].includes(item.type))
  }

  return safeNotifications.value
})
</script>