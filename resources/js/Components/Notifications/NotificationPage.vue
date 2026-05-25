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

      <div v-else-if="Array.isArray(notifications) && notifications.length > 0" class="flex flex-wrap gap-2 mb-6">
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
        />
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import NotificationItem from './NotificationItem.vue';
import api from '@/api';
import MainLayout from "@/Layouts/MainLayout.vue";

const loading = ref(false);
const notifications = ref([]);
const filters = ['All', 'Unread', 'Billing', 'Account']
const activeFilter = ref('All')

const fetchNotifications = async () => {
  try {
    loading.value = true;
    const res = await api.get('/user/notifications')
    
  
    if (res.data && res.data.notifications) {
      notifications.value = Array.isArray(res.data.notifications) 
        ? res.data.notifications 
        : (res.data.notifications.data || []);
    } else {
      notifications.value = Array.isArray(res.data) ? res.data : [];
    }
  } catch (error) {
    console.error('Failed to parse user feed listings:', error)
    notifications.value = [];
  } finally {
    loading.value = false;
  }
}

const filteredNotifications = computed(() => {
  const base = Array.isArray(notifications.value) ? notifications.value : []
  
  switch (activeFilter.value) {
  
    case 'Unread': 
      return base.filter(n => n && !n.read_at)
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
  if (notif && !notif.read_at) {
    notif.read_at = new Date().toISOString() 
    try {
      await api.post(`/user/notifications/${id}/read`)
    } catch (e) {
      console.error(e)
      notif.read_at = null 
    }
  }
}

const clearAll = async () => {
  const previousState = JSON.parse(JSON.stringify(notifications.value))
  const nowTimestamp = new Date().toISOString()
  
  notifications.value.forEach(n => {
    if (!n.read_at) n.read_at = nowTimestamp
  })

  try {
    
    await api.post('/user/notifications/read-all') 
  } catch (error) {
    console.error(error)
    notifications.value = previousState 
  }
}

onMounted(fetchNotifications)
</script>