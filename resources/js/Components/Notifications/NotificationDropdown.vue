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
          :key="tab.key"
          @click="activeTab = tab.key"
          :class="['px-3 py-1.5 rounded-full text-xs font-medium transition', activeTab === tab.key ? 'bg-blue-600 text-white' : 'bg-[#0F1724] text-gray-300 hover:bg-[#1f2a44]']"
        >
          {{ tab.label }}
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
import NotificationItem from './NotificationItem.vue'

const props = defineProps({
  notifications: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['markRead', 'viewNotification'])

const handleView = (notification) => {
  emit('viewNotification', notification)
}

const tabs = [
  { key: 'all', label: 'All' },
  { key: 'unread', label: 'Unread' },
  { key: 'important', label: 'Important' },
  { key: 'billing', label: 'Billing' },
  { key: 'account', label: 'Account' },
  { key: 'updates', label: 'Updates' },
]
const activeTab = ref('all')

const safeNotifications = computed(() => {
  if (!props.notifications || !Array.isArray(props.notifications)) return []
  return props.notifications.filter(item => item !== null)
})

const filteredNotifications = computed(() => {
  if (activeTab.value === 'unread') {
    return safeNotifications.value.filter(item => !item.read)
  }

  if (activeTab.value === 'important') {
    return safeNotifications.value.filter(item => ['account', 'warning', 'error', 'billing'].includes(item.type))
  }

  if (activeTab.value === 'billing') {
    return safeNotifications.value.filter(item => item.type === 'billing')
  }

  if (activeTab.value === 'account') {
    return safeNotifications.value.filter(item => ['account', 'security', 'suspension'].includes(item.type))
  }

  if (activeTab.value === 'updates') {
    return safeNotifications.value.filter(item => ['info', 'news', 'broadcast'].includes(item.type))
  }

  return safeNotifications.value
})
</script>