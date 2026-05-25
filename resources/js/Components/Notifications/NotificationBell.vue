<template>
  <div class="relative inline-block">
    <!-- Bell Trigger Button -->
    <button @click="toggleDropdown" class="p-2 text-gray-600 hover:text-gray-900 focus:outline-none relative transition duration-150 ease-in-out">
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
    <div v-if="open" @click="open = false" class="fixed inset-0 z-40 bg-transparent"></div>

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
      />
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import NotificationDropdown from './NotificationDropdown.vue'
import api from '@/api'

const open = ref(false)
const notifications = ref([])

const toggleDropdown = () => {
  open.value = !open.value
}

const fetchNotifications = async () => {
  try {
    const res = await api.get('/user/notifications')
    
   
    if (res.data && res.data.notifications) {
      notifications.value = Array.isArray(res.data.notifications.data) 
        ? res.data.notifications.data 
        : (Array.isArray(res.data.notifications) ? res.data.notifications : []);
    } else if (res.data && res.data.data) {
      notifications.value = res.data.data;
    } else {
      notifications.value = Array.isArray(res.data) ? res.data : [];
    }
  } catch (error) {
    console.error('Failed to load notifications:', error)
    notifications.value = [] 
  }
}


const unreadCount = computed(() => {
  if (!Array.isArray(notifications.value)) return 0;
  return notifications.value.filter(n => !n.read_at).length
})

const markAsRead = async (id) => {
  const notif = notifications.value.find(n => n.id === id)
  if (notif && !notif.read_at) {
    notif.read_at = new Date().toISOString() 
    try {
      await api.post(`/user/notifications/${id}/read`)
    } catch (error) {
      console.error('Failed to sync read status to backend:', error)
      notif.read_at = null 
    }
  }
}

onMounted(fetchNotifications)
</script>