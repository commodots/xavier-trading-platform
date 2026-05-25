<template>
  <div 
    @click="handleClick"
    :class="['flex items-start gap-3 p-3 transition-colors duration-150 cursor-pointer relative border-b border-[#1f3348]/30 last:border-0', notification.read ? 'bg-transparent hover:bg-[#1f3348]/20' : 'bg-blue-500/5 hover:bg-blue-500/10']"
  >
    <!-- Dynamic Icon Badge based on Notification Type -->
    <div :class="['w-8 h-8 rounded-lg flex items-center justify-center text-base shrink-0', badgeStyle.bg]">
      {{ badgeStyle.icon }}
    </div>

    <!-- Text Body context -->
    <div class="flex-1 min-w-0">
      <div class="font-semibold text-sm text-white flex items-center gap-1.5">
        {{ notification.title }}
        <!-- Unread Blue Dot Indicator -->
        <span v-if="!notification.read" class="w-2 h-2 bg-blue-500 rounded-full inline-block"></span>
      </div>
      <p class="text-xs text-gray-600 mt-0.5 leading-relaxed break-words">
        {{ notification.message }}
      </p>

      <!-- Footer elements: Time and Custom Action buttons -->
      <div class="flex justify-between items-center mt-3 gap-2">
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
      return { icon: '💰', bg: 'bg-emerald-500/10 text-emerald-400' }
    case 'warning':
      return { icon: '⚠', bg: 'bg-amber-500/10 text-amber-400' }
    case 'account':
      return { icon: '🔐', bg: 'bg-rose-500/10 text-rose-400' }
    case 'success':
      return { icon: '✔', bg: 'bg-emerald-500/10 text-emerald-400' }
    default:
      return { icon: 'ℹ', bg: 'bg-blue-500/10 text-blue-400' }
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