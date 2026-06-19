<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    message: {
        type: String,
        default: 'Operation successful!',
    },
});

const emit = defineEmits(['close']);
</script>

<template>
    <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm px-4 py-6"
            @click.self="emit('close')"
        >
            <Transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
                <div
                    v-if="show"
                    class="w-full max-w-md p-6 bg-[#1C1F2E] rounded-2xl shadow-xl border border-[#2A314A] relative"
                >
                    <button
                        type="button"
                        class="absolute text-gray-400 transition-colors top-4 right-4 hover:text-white"
                        @click="emit('close')"
                    >
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <div class="text-center">
                        <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 text-green-400 bg-green-500/10 rounded-full border border-green-500/20">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h3 class="mb-2 text-xl font-bold text-green-400">
                            Success
                        </h3>
                        <p class="text-sm text-gray-300">
                            {{ message }}
                        </p>
                        <button
                            type="button"
                            class="w-full mt-6 px-4 py-2.5 text-sm font-semibold text-white transition bg-green-600 rounded-lg hover:bg-green-500"
                            @click="emit('close')"
                        >
                            OK
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>