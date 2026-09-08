<template>
  <div class="relative inline-block">
    <!-- Bell Trigger Button -->
    <button
      type="button"
      aria-label="Notifications"
      :aria-expanded="open"
      class="relative p-2 text-gray-600 transition duration-150 ease-in-out hover:text-gray-900 focus:outline-none"
      @click="toggleDropdown"
    >
      <span class="text-xl">🔔</span>
      <!-- Unread count badge -->
      <span 
        v-if="unreadCount > 0" 
        class="absolute top-0 right-0 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-500 rounded-full transformation translate-x-1/3 -translate-y-1/3 animate-pulse"
      >
        {{ unreadCount }}
      </span>
    </button>

    <!-- Dropdown Overlay Closes Panel on Outside Click -->
    <div v-if="open" class="fixed inset-0 z-40 bg-transparent" @click="open = false"></div>

    <!-- Dropdown Panel -->
    <transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <NotificationDropdown 
        v-if="open" 
        :notifications="notifications" 
        class="z-50"
        @markRead="markAsRead"
        @viewNotification="handleViewNotification"
      />
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import NotificationDropdown from './NotificationDropdown.vue'
import api from '@/api'

const router = useRouter()
const open = ref(false)
const notifications = ref([])
const unreadTotal = ref(0)
const pollingInterval = ref(null)
const POLLING_DELAY = 30000 // 30 seconds

const toggleDropdown = () => {
  open.value = !open.value
}

const fetchNotifications = async () => {
  try {
    const res = await api.get('/user/notifications')

    notifications.value = Array.isArray(res.data?.notifications)
      ? res.data.notifications
      : Array.isArray(res.data) ? res.data : []
    unreadTotal.value = Number.isFinite(Number(res.data?.unread_count))
      ? Number(res.data.unread_count)
      : notifications.value.filter(notification => !notification.read).length
  } catch (error) {
    console.error('Failed to load notifications:', error)
    notifications.value = []
  }
}

const startPolling = () => {
  // Clear any existing interval first
  stopPolling()
  
  pollingInterval.value = setInterval(() => {
    if (!open.value) fetchNotifications()
  }, POLLING_DELAY)
}

const stopPolling = () => {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value)
    pollingInterval.value = null
  }
}

watch(open, (value) => {
  if (value) {
    fetchNotifications()
  }
})

const unreadCount = computed(() => {
  return unreadTotal.value
})

const markAsRead = async (id) => {
  const notif = notifications.value.find(n => n.id === id)
  if (notif && !notif.read) {
    notif.read = true

    try {
      await api.post(`/user/notifications/${id}/read`)
      unreadTotal.value = Math.max(0, unreadTotal.value - 1)
    } catch (error) {
      console.error('Failed to sync read status to backend:', error)
      notif.read = false
      unreadTotal.value += 1
    }
  }
}

const handleViewNotification = (notification) => {
  open.value = false
  
  router.push({ name: 'notifications', query: { notification: notification.id } })
}

onMounted(() => {
  fetchNotifications()
  startPolling()
})

onUnmounted(() => {
  stopPolling()
})
</script>
