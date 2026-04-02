<script setup>
import { ref, computed } from 'vue';
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

const isAdminUser = (u) => {
    if (!u) return false;

    const role = (u.role || '').toString().toLowerCase();
    if (role.includes('admin')) return true;

    if (Array.isArray(u.roles)) {
        return u.roles.some((r) => {
            const candidate = (typeof r === 'string' ? r : r?.name || '').toString().toLowerCase();
            return candidate.includes('admin');
        });
    }

    return false;
};

const isVerified = computed(() => {
    const u = props.user || {};
    return Boolean(u.email_verified_at) || isAdminUser(u);
});

const submit = async () => {
    processing.value = true;
    status.value = null;

    try {
        const res = await api.post('/email/verification-notification');

        if (res.data.status === 'verification-link-sent') {
            status.value = 'verification-link-sent';
        }
    } catch (error) {
        console.error("Send verification failed:", error);
        status.value = 'error';
    } finally {
        processing.value = false;
    }
};

const verificationLinkSent = computed(() => status.value === 'verification-link-sent');

const goToWelcome = () => {
    window.location.href = '/welcome';
};
</script>

<template>
    <div v-if="!isVerified" class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-4">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <p class="text-sm text-yellow-800">
                    Verify your email to access this feature.
                </p>
                <p v-if="verificationLinkSent" class="text-sm text-green-600 mt-2">
                    A verification link has been sent to your email address.
                </p>
                <p v-if="status === 'error'" class="text-sm text-red-600 mt-2">
                    Failed to send verification email. Please try again.
                </p>
            </div>
            <div class="ml-4 flex gap-2">
                <PrimaryButton
                    v-if="!verificationLinkSent"
                    @click="submit"
                    :class="{ 'opacity-25': processing }"
                    :disabled="processing"
                    size="sm"
                >
                    {{ processing ? 'Sending...' : 'Verify Now' }}
                </PrimaryButton>
                <PrimaryButton
                    v-if="verificationLinkSent"
                    @click="goToWelcome"
                    type="button"
                    size="sm"
                >
                    Verify Later
                </PrimaryButton>
            </div>
        </div>
    </div>
</template>