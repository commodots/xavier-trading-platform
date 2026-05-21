<template>
  <div class="p-6 max-w-3xl mx-auto min-h-screen">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Notifications</h1>
      <button @click="clearAll" class="text-sm text-gray-500 hover:text-blue-600 transition">
        Mark all as read
      </button>
    </div>

    <!-- Pill Filtering Controls -->
    <div class="flex flex-wrap gap-2 mb-6">
      <button 
        v-for="f in filters" 
        :key="f"
        @click="activeFilter = f"
        :class="['px-4 py-1.5 rounded-full text-xs font-semibold tracking-wide border transition duration-150', activeFilter === f ? 'bg-blue-600 border-blue-600 text-white shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50']"
      >
        {{ f }}
      </button>
    </div>

    <!-- Render list cards dynamically -->
    <div class="bg-white border border-gray-100 rounded-xl divide-y divide-gray-100 shadow-sm overflow-hidden">
      <div v-if="filteredNotifications.length === 0" class="p-8 text-center text-gray-400 text-sm">
        No notifications found in this category.
      </div>
      <NotificationItem 
        v-for="n in filteredNotifications" 
        :key="n.id" 
        :notification="n"
        @markRead="markOneAsRead(n.id)"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import NotificationItem from './NotificationItem.vue'
import api from '@/lib/axios'

const notifications = ref([])
const filters = ['All', 'Unread', 'Billing', 'Account']
const activeFilter = ref('All')

const fetchNotifications = async () => {
  try {
    const res = await api.get('/user/notifications')
    notifications.value = res.data.notifications ?? res.data
  } catch (error) {
    console.error(error)
  }
}

const filteredNotifications = computed(() => {
  switch (activeFilter.value) {
    case 'Unread': return notifications.value.filter(n => !n.read)
    case 'Billing': return notifications.value.filter(n => n.type === 'billing')
    case 'Account': return notifications.value.filter(n => n.type === 'account')
    default: return notifications.value
  }
})

const markOneAsRead = async (id) => {
  const notif = notifications.value.find(n => n.id === id)
  if (notif && !notif.read) {
    notif.read = true
    await api.post(`/user/notifications/${id}/read`)
  }
}

const clearAll = async () => {
  notifications.value.forEach(n => n.read = true)
  try {
    await api.post('/user/notifications/read-all')
  } catch (error) {
    console.error(error)
  }
}

onMounted(fetchNotifications)
</script>