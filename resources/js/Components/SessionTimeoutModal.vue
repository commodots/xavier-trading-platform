<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
        <div class="relative bg-[#1a253b] border border-[#4d69aa] rounded-2xl p-8 max-w-md w-full mx-4 shadow-2xl">
          <div class="text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-yellow-500/20 flex items-center justify-center">
              <svg class="w-8 h-8 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>

            <h2 class="text-xl font-bold text-white mb-2">Session Expiring</h2>
            <p class="text-gray-300 mb-2">You have been inactive.</p>
            <p class="text-gray-400 mb-6">
              You'll be logged out in
              <span class="text-yellow-400 font-bold text-lg">{{ countdown }}</span>
              seconds.
            </p>

            <div class="w-full bg-gray-700 rounded-full h-2 mb-6">
              <div class="bg-yellow-400 h-2 rounded-full transition-all duration-1000 ease-linear"
                :style="{ width: (countdown / 60) * 100 + '%' }"></div>
            </div>

            <div class="flex gap-4 justify-center">
              <button @click="$emit('stay')"
                class="px-6 py-2.5 bg-[#00D4FF] hover:bg-[#00B8E6] text-black font-semibold rounded-lg transition-colors">
                Stay Logged In
              </button>
              <button @click="$emit('logout')"
                class="px-6 py-2.5 bg-red-500/20 hover:bg-red-500/30 text-red-400 font-semibold rounded-lg border border-red-500/30 transition-colors">
                Logout
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  countdown: {
    type: Number,
    default: 60,
  },
});

defineEmits(['stay', 'logout']);
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>