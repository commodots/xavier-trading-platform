<script setup>
import { computed, ref, onMounted } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import api from '@/api';
import { useRoute, useRouter } from 'vue-router';

const router = useRouter();
const route = useRoute();
const status = ref(null);
const processing = ref(false);

onMounted(async () => {
    const verificationUrl = route.query.url;

    if (verificationUrl) {
        processing.value = true;
        try {
            // Try with authenticated token if present, else raw get (no token required for signed route)
            await api.get(decodeURIComponent(verificationUrl));

            router.push('/welcome?verified=1');
            return;
        } catch (error) {
            console.error('Verification failed', error);
            status.value = 'error';
        } finally {
            processing.value = false;
        }
    }

    const token = localStorage.getItem('xavier_token');
    if (!token) {
        router.push('/login');
        return;
    }

    // If already verified (local state may not include email_verified_at), we can show resend.
});

const submit = async () => {
    processing.value = true;
    status.value = null;

    try {
        const res = await api.post('/email/verification-notification');

        // Check for the status key returned by the controller
        if (res.data.status === 'verification-link-sent') {
            status.value = 'verification-link-sent';
        }
    } catch (error) {
        // Handle API errors if necessary
        console.error("Resend verification failed:", error);
        status.value = 'error';
    } finally {
        processing.value = false;
    }
};

const verificationLinkSent = computed(
    () => status.value === 'verification-link-sent',
);

const verifyLater = () => {
    router.push('/welcome');
};

const logout = async () => {
    try {
        await api.post('/logout');
    } catch (error) {
        console.warn('Logout request failed, clearing local state anyway.', error);
    } finally {
        localStorage.removeItem('xavier_token');
        localStorage.removeItem('user');
        router.push('/login');
    }
};
</script>

<template>
    <GuestLayout>
        <div class="mb-4 text-sm text-gray-600">
            Thanks for signing up! To get started, please verify your email address.
        </div>

        <div class="mb-4 text-sm font-medium text-green-600" v-if="verificationLinkSent">
            A new verification link has been sent to the email address you
            provided during registration.
        </div>

        <form @submit.prevent="submit">
            <div class="flex items-center justify-between mt-4">
                <PrimaryButton :class="{ 'opacity-25': processing }" :disabled="processing">
                    Verify Now
                </PrimaryButton>

                <PrimaryButton @click="verifyLater" type="button">
                    Verify Later
                </PrimaryButton>

                <button type="button" @click="logout"
                    class="text-sm text-gray-600 underline rounded-md hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Log Out
                </button>

            </div>
        </form>
    </GuestLayout>
</template>
