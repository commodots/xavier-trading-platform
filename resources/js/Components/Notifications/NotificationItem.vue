<template>
  <div 
    @click="handleClick"
    :class="['flex items-start gap-3 p-3 transition-colors duration-150 cursor-pointer relative border-b border-gray-50 last:border-0', notification.read ? 'bg-white hover:bg-gray-50' : 'bg-blue-50/20 hover:bg-blue-50/40']"
  >
    <!-- Dynamic Icon Badge based on Notification Type -->
    <div :class="['w-8 h-8 rounded-full flex items-center justify-center text-base shrink-0', badgeStyle.bg]">
      {{ badgeStyle.icon }}
    </div>

    <!-- Text Body context -->
    <div class="flex-1 min-w-0">
      <div class="font-semibold text-sm text-gray-900 flex items-center gap-1.5">
        {{ notification.title }}
        <!-- Unread Blue Dot Indicator -->
        <span v-if="!notification.read" class="w-2 h-2 bg-blue-500 rounded-full inline-block"></span>
      </div>
      <p class="text-xs text-gray-600 mt-0.5 leading-relaxed break-words">
        {{ notification.message }}
      </p>

      <!-- Footer elements: Time and Custom Action buttons -->
      <div class="flex justify-between items-center mt-2 gap-2">
        <span class="text-[11px] text-gray-400 font-medium">
          {{ notification.time }}
        </span>

        <button 
          v-if="notification.action" 
          @click.stop="handleAction"
          class="text-[10px] uppercase tracking-wider bg-blue-600 hover:bg-blue-700 text-white font-bold px-2.5 py-1 rounded-md shadow-sm transition duration-150 active:scale-95"
        >
          {{ notification.action }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  notification: Object
})

const emit = defineEmits(['markRead'])
const router = useRouter()

// UI Type Mapping config
const badgeStyle = computed(() => {
  switch (props.notification.type) {
    case 'billing':
      return { icon: '💰', bg: 'bg-emerald-50 text-emerald-700' }
    case 'warning':
      return { icon: '⚠', bg: 'bg-amber-50 text-amber-700' }
    case 'account':
      return { icon: '🔐', bg: 'bg-rose-50 text-rose-700' }
    case 'success':
      return { icon: '✔', bg: 'bg-emerald-50 text-emerald-700' }
    default:
      return { icon: 'ℹ', bg: 'bg-blue-50 text-blue-700' }
  }
})

const handleClick = () => {
  emit('markRead')
}

const handleAction = () => {
  const actionMap = {
    'Fund Wallet': '/wallet',
    'Pay Now': '/wallet',
    'Resolve Now': '/settings',
    'Upgrade': '/user/advisory/plans',
    'Resume': '/dashboard',
    'Go to Dashboard': '/dashboard'
  }

  const target = actionMap[props.notification.action]
  if (target) {
    router.push(target)
  } else {
    console.log('Action unmapped:', props.notification.action)
  }
}
</script>