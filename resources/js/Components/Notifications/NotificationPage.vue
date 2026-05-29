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
          v-for="f in filters" 
          :key="f"
          @click="activeFilter = f"
          :class="['px-4 py-1.5 rounded-full text-xs font-semibold tracking-wide border transition duration-150', activeFilter === f ? 'bg-blue-600 border-blue-600 text-white shadow-sm' : 'bg-[#16213A] text-gray-300 border-[#1f3348] hover:bg-[#1f3348]']"
        >
          {{ f }}
        </button>
      </div>

      <div v-else class="flex flex-col items-center justify-center py-20 bg-[#0F1724] border border-[#1f3348] rounded-2xl text-center">
        <h3 class="text-lg font-medium text-white">All caught up!</h3>
        <p class="text-gray-400 max-w-xs mx-auto mt-2">You don't have any notifications at the moment.</p>
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

      <!-- Notification Detail Modal -->
      <div v-if="selectedNotification" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div class="bg-[#111827] border border-[#1F2A44] rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl animate-fadeIn">
          <div class="p-6">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-xl font-bold text-white">{{ selectedNotification.title }}</h2>
              <button @click="selectedNotification = null" class="text-gray-400 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            
            <div class="text-gray-300 space-y-4 text-sm leading-relaxed">
              <p>{{ selectedNotification.message }}</p>
            </div>

            <div class="mt-8 flex flex-col gap-3 border-t border-[#1F2A44] pt-4 sm:flex-row sm:items-center sm:justify-between">
              <span class="text-xs text-gray-500">{{ selectedNotification.time }}</span>
              <div class="flex items-center gap-2 justify-end">
                <button 
                  @click="selectedNotification = null" 
                  class="px-4 py-2 text-sm font-medium text-gray-400 hover:text-white transition"
                >
                  Close
                </button>
                <button
                  v-if="selectedNotification.action"
                  @click="handleModalAction"
                  class="px-4 py-2 text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition"
                >
                  {{ selectedNotification.action }}
                </button>
              </div>
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
import { useRouter } from 'vue-router';

const router = useRouter();
const loading = ref(false);
const notifications = ref([]);
const filters = ['All', 'Unread', 'Billing', 'Account']
const activeFilter = ref('All')
const selectedNotification = ref(null);

const fetchNotifications = async () => {
  try {
    loading.value = true;
    const res = await api.get('/user/notifications')

    if (res.data?.notifications) {
      notifications.value = Array.isArray(res.data.notifications)
        ? res.data.notifications
        : []
    } else if (Array.isArray(res.data)) {
      notifications.value = res.data
    } else {
      notifications.value = []
    }
  } catch (error) {
    console.error('Failed to parse user feed listings:', error)
    notifications.value = [];
  } finally {
    loading.value = false;
  }
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

    if (aPriority !== bPriority) {
      return aPriority - bPriority
    }

    return 0
  })
})

const filteredNotifications = computed(() => {
  const base = sortedNotifications.value

  switch (activeFilter.value) {
    case 'Unread':
      return base.filter(n => n && !n.read)
    case 'Billing':
      return base.filter(n => n && n.type === 'billing')
    case 'Account':
      return base.filter(n => n && n.type === 'account')
    default:
      return base
  }
})

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

const handleModalAction = () => {
  if (!selectedNotification.value?.action) return;

  const action = selectedNotification.value.action;
  selectedNotification.value = null;

  const actionMap = {
    'Fund Wallet': '/wallet',
    'Pay Now': '/wallet',
    'Resolve Now': '/settings',
    'Upgrade': '/user/advisory/plans',
    'Resume': '/dashboard',
    'Go to Dashboard': '/dashboard',
  }

  const target = actionMap[action] || (action.startsWith('/') ? action : null)
  if (target) {
    router.push(target)
  }
}

onMounted(fetchNotifications)
</script>