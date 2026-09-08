<template>
  <MainLayout>
    <div class="max-w-4xl mx-auto space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-white">Notifications</h1>
          <p class="text-sm text-gray-400">Stay updated with your account activity and market alerts.</p>
        </div>
        <button 
          @click="clearAll"
          v-if="Array.isArray(notifications) && notifications.length > 0"
          class="text-sm text-blue-400 hover:text-blue-300 transition-colors flex items-center gap-2"
        >
          Mark all as read
        </button>
      </div>

      <div v-if="loading" class="space-y-4">
        <div v-for="i in 3" :key="i" class="h-24 bg-[#0F1724] border border-[#1f3348] rounded-xl animate-pulse"></div>
      </div>

      <div v-else-if="Array.isArray(sortedNotifications) && sortedNotifications.length > 0" class="flex flex-wrap gap-2 mb-6">
        <button 
          v-for="filter in filters" 
          :key="filter.key"
          @click="activeFilter = filter.key"
          :class="['px-4 py-1.5 rounded-full text-xs font-semibold tracking-wide border transition duration-150', activeFilter === filter.key ? 'bg-blue-600 border-blue-600 text-white shadow-sm' : 'bg-[#16213A] text-gray-300 border-[#1f3348] hover:bg-[#1f3348]']"
        >
          {{ filter.label }}
        </button>
      </div>

      <div v-else class="flex flex-col items-center justify-center py-20 bg-[#0F1724] border border-[#1f3348] rounded-2xl text-center">
        <h3 class="text-lg font-medium text-white">All caught up!</h3>
        <p class="text-gray-400 max-w-xs mx-auto mt-2">You don't have any notifications at the moment.</p>
      </div>

      <div v-if="filteredNotifications.length === 0 && !loading && notifications.length > 0" class="flex flex-col items-center justify-center py-16 bg-[#0F1724] border border-[#1f3348] rounded-2xl text-center">
        <h3 class="text-lg font-medium text-white">No {{ activeFilterLabel.toLowerCase() }} messages available</h3>
        <p class="text-gray-400 max-w-xs mx-auto mt-2">There are no {{ activeFilterLabel.toLowerCase() }} notifications at this time.</p>
      </div>

      <div v-if="filteredNotifications.length > 0" class="bg-[#0F1724] border border-[#1f3348] rounded-xl divide-y divide-[#1f3348] shadow-sm overflow-hidden">
        <NotificationItem 
          v-for="n in filteredNotifications" 
          :key="n.id" 
          :notification="n"
          @markRead="markOneAsRead(n.id)"
          @view="openDetails"
        />
      </div>

      <!-- Load More Button -->
      <div v-if="hasMorePages && filteredNotifications.length > 0" class="flex justify-center mt-6">
        <button
          @click="loadMore"
          :disabled="loadingMore"
          class="px-6 py-2 text-sm font-medium text-blue-400 bg-[#16213A] border border-[#1f3348] rounded-lg hover:bg-[#1f3348] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ loadingMore ? 'Loading...' : 'Load More' }}
        </button>
      </div>

      <!-- Notification Detail Modal -->
      <div v-if="selectedNotification" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="selectedNotification = null">
        <div class="bg-[#111827] border border-[#1F2A44] rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl">
          <div class="p-6">
            <div class="mb-4">
              <h2 class="text-xl font-bold text-white">{{ selectedNotification.title }}</h2>
            </div>
            
            <div class="text-gray-300 space-y-4 text-sm leading-relaxed whitespace-pre-wrap">
              <p>{{ selectedNotification.message }}</p>
            </div>

            <div class="mt-6 flex items-center justify-between border-t border-[#1F2A44] pt-4">
              <span class="text-xs text-gray-500">{{ selectedNotification.time }}</span>
              <button 
                @click="selectedNotification = null" 
                class="px-4 py-2 text-sm font-medium text-gray-400 hover:text-white transition bg-[#16213A] hover:bg-[#1f3348] rounded-lg"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import NotificationItem from './NotificationItem.vue';
import api from '@/api';
import MainLayout from "@/Layouts/MainLayout.vue";
import { useRoute } from 'vue-router';

const route = useRoute();
const loading = ref(false);
const loadingMore = ref(false);
const notifications = ref([]);
const currentPage = ref(1);
const hasMorePages = ref(true);
const filters = [
  { key: 'all', label: 'All' },
  { key: 'unread', label: 'Unread' },
  { key: 'important', label: 'Important' },
  { key: 'billing', label: 'Billing' },
  { key: 'account', label: 'Account & Security' },
  { key: 'warning', label: 'Warnings' },
  { key: 'updates', label: 'Updates' },
  { key: 'success', label: 'Success' },
]
const activeFilter = ref('all')
const selectedNotification = ref(null);

const fetchNotifications = async (page = 1, append = false) => {
  try {
    if (page === 1) {
      loading.value = true;
    } else {
      loadingMore.value = true;
    }
    
    const res = await api.get('/user/notifications', {
      params: { page }
    })

    let newNotifications = []
    if (res.data?.notifications) {
      newNotifications = Array.isArray(res.data.notifications)
        ? res.data.notifications
        : []
    } else if (Array.isArray(res.data)) {
      newNotifications = res.data
    }

    if (append) {
      const existingIds = new Set(notifications.value.map(n => n.id))
      const uniqueNew = newNotifications.filter(n => !existingIds.has(n.id))
      notifications.value = [...notifications.value, ...uniqueNew]
    } else {
      notifications.value = newNotifications
    }

    if (res.data?.meta) {
      currentPage.value = res.data.meta.current_page || 1
      hasMorePages.value = res.data.meta.has_more ?? false
    }
  } catch (error) {
    console.error('Failed to fetch notifications:', error)
    if (!append) {
      notifications.value = [];
    }
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

const loadMore = async () => {
  if (loadingMore.value || !hasMorePages.value) return
  await fetchNotifications(currentPage.value + 1, true)
}

const sortedNotifications = computed(() => {
  const base = Array.isArray(notifications.value) ? [...notifications.value] : []

  const priority = {
    account: 1,
    billing: 2,
    warning: 3,
    error: 4,
    success: 5,
    info: 6,
  }

  return base.sort((a, b) => {
    const aPriority = priority[a?.type] ?? 99
    const bPriority = priority[b?.type] ?? 99
    if (aPriority !== bPriority) return aPriority - bPriority
    return 0
  })
})

const filteredNotifications = computed(() => {
  const base = sortedNotifications.value
  switch (activeFilter.value) {
    case 'unread': return base.filter(n => n && !n.read)
    case 'important': return base.filter(n => n && ['account', 'billing', 'warning', 'error'].includes(n.type))
    case 'billing': return base.filter(n => n && n.type === 'billing')
    case 'account': return base.filter(n => n && ['account', 'security', 'suspension'].includes(n.type))
    case 'warning': return base.filter(n => n && ['warning', 'error'].includes(n.type))
    case 'updates': return base.filter(n => n && ['info', 'news', 'broadcast'].includes(n.type))
    case 'success': return base.filter(n => n && n.type === 'success')
    default: return base
  }
})

const activeFilterLabel = computed(() => filters.find(filter => filter.key === activeFilter.value)?.label || 'All')

const markOneAsRead = async (id) => {
  const notif = notifications.value.find(n => n.id === id)
  if (notif && !notif.read) {
    notif.read = true
    try {
      await api.post(`/user/notifications/${id}/read`)
    } catch (e) {
      console.error(e)
      notif.read = false
    }
  }
}

const clearAll = async () => {
  const previousState = JSON.parse(JSON.stringify(notifications.value))
  notifications.value = notifications.value.map((n) => ({ ...n, read: true }))
  try {
    await api.post('/user/notifications/read-all')
  } catch (error) {
    console.error(error)
    notifications.value = previousState
  }
}

const openDetails = (notification) => {
  selectedNotification.value = notification;
}

onMounted(async () => {
  await fetchNotifications()

  const notificationId = route.query.notification
  if (notificationId) {
    const notification = notifications.value.find(item => String(item.id) === String(notificationId))
    if (notification) {
      openDetails(notification)
      await markOneAsRead(notification.id)
    }
  }
})
</script>