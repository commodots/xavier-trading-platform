<script setup>
import { ref, computed, onBeforeUnmount } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import api from '@/api';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
});

const status = ref(null);
const processing = ref(false);
const timer = ref(0);
let interval = null;

const startTimer = () => {
    if (interval) clearInterval(interval);
    timer.value = 60; 
    interval = setInterval(() => {
        if (timer.value > 0) {
            timer.value--;
        } else {
            clearInterval(interval);
        }
    }, 1000);
};

onBeforeUnmount(() => {
    if (interval) clearInterval(interval);
});

const isVerified = computed(() => {
    // 1. Check the prop passed from parent
    if (props.user?.email_verified_at) return true;
    
    // 2. Check Local Storage (where your fresh API response usually goes)
    const localUser = JSON.parse(localStorage.getItem('user') || '{}');
    if (localUser.email_verified_at) return true;

    // 3. Admin check
    const u = props.user || {};
    return (u.role && u.role.toLowerCase().includes('admin'));
});

const submit = async () => {
    if (timer.value > 0 || processing.value) return;

    processing.value = true;
    status.value = null;

    try {
        // This hits your Laravel route: Route::post('/email/verification-notification')
        const res = await api.post('/email/verification-notification');

        // Match the key from your Controller: 'success' => true
        if (res.data.success) {
            status.value = 'verification-link-sent';
            startTimer();
        }
    } catch (error) {
        console.error("Send verification failed:", error);
        status.value = 'error';
    } finally {
        processing.value = false;
    }
};

const verificationLinkSent = computed(() => status.value === 'verification-link-sent');

const buttonText = computed(() => {
    if (processing.value) return 'Sending...';
    if (timer.value > 0) return `Resend in ${timer.value}s`;
    if (status.value === 'error') return 'Retry Verification';
    return 'Verify Now';
});

const goToWelcome = () => {
    window.location.href = '/welcome';
};
</script>

<template>
    <div v-if="!isVerified" class="p-4 mb-4 bg-white border border-red-400 rounded-md shadow-sm">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <p class="text-sm font-semibold text-red-600">
                    Action Required: Email Not Verified
                </p>
                <p class="mt-1 text-xs text-gray-600">
                    Please verify your email to enable transactions.
                </p>
                <p v-if="verificationLinkSent" class="mt-2 text-sm font-medium text-green-600">
                    A new verification link has been sent to your inbox.
                </p>
                <p v-if="status === 'error'" class="mt-2 text-sm text-red-600">
                    Failed to send link. Please try again in a moment.
                </p>
            </div>
            <div class="flex gap-2 ml-4">
                <PrimaryButton 
                    type="button"
                    @click="submit"
                    :class="{ 'opacity-50 cursor-not-allowed': processing || timer > 0 }" 
                    :disabled="processing || timer > 0" 
                    size="sm"
                >
                    {{ buttonText }}
                </PrimaryButton>
            </div>
        </div>
    </div>
</template>