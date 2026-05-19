<script setup>
import { computed } from 'vue';

const props = defineProps({
    subscription: {
        type: Object,
        required: true,
        // Expected payload keys shape: { status: 'suspended'|'trial', days_left: 3, next_billing_at: '2026-06-01' }
    }
});

const emit = defineEmits(['actionClicked']);

const bannerConfig = computed(() => {
    switch (props.subscription?.status) {
        case 'suspended':
            return {
                bgClass: 'bg-red-50 dark:bg-red-950/30 border-red-200 dark:border-red-900/50 text-red-800 dark:text-red-300',
                title: 'Account Access Restricted',
                description: 'Your quarterly advisory subscription billing failed due to insufficient cleared funds.',
                btnText: 'Fund USD Wallet',
                btnClass: 'bg-red-600 hover:bg-red-700 focus:ring-red-500 text-white'
            };
        case 'trial':
            if (props.subscription?.days_left <= 5) {
                return {
                    bgClass: 'bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-900/50 text-amber-800 dark:text-amber-300',
                    title: 'Your Premium Advisory Trial is Expiring Soon',
                    description: `You have ${props.subscription.days_left} days remaining. Fund your cleared balances to prevent downgrade interruptions.`,
                    btnText: 'Upgrade Tier Now',
                    btnClass: 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500 text-white'
                };
            }
            return null;
        default:
            return null;
    }
});
</script>

<template>
    <div v-if="bannerConfig" :class="['p-4 mb-6 border rounded-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 transition-all duration-200 shadow-sm', bannerConfig.bgClass]">
        <div class="flex items-start space-x-3">
            <!-- Information Icon -->
            <svg class="h-5 w-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
                <h4 class="font-semibold text-sm sm:text-base leading-snug">{{ bannerConfig.title }}</h4>
                <p class="text-xs sm:text-sm mt-0.5 opacity-90">{{ bannerConfig.description }}</p>
            </div>
        </div>
        <button 
            @click="emit('actionClicked', props.subscription.status)"
            :class="['text-xs font-semibold px-4 py-2 rounded-lg tracking-wide transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 whitespace-nowrap shrink-0 w-full sm:w-auto text-center', bannerConfig.btnClass]"
        >
            {{ bannerConfig.btnText }}
        </button>
    </div>
</template>