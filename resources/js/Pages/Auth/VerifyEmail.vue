<script setup>
import { computed, ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import axios from 'axios';
import { useRouter } from 'vue-router';

const router = useRouter();
const status = ref(null);
const processing = ref(false);

const submit = async () => {
    processing.value = true;
    status.value = null;

    try {
        const res = await axios.post('/api/email/verification-notification');

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

const logout = async () => {
    try {
        const token = localStorage.getItem('xavier_token');
        await axios.post('/api/logout', {}, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        localStorage.removeItem('xavier_token');
        localStorage.removeItem('user');
        router.push('/login');
    } catch (error) {
        console.error('Logout failed:', error);
        // Still clear storage and redirect even if API call fails
        localStorage.removeItem('xavier_token');
        localStorage.removeItem('user');
        router.push('/login');
    }
};
</script>

<template>
    <GuestLayout>
        <div class="mb-4 text-sm text-gray-600">
            Thanks for signing up! Before getting started, could you verify your
            email address by clicking on the link we just emailed to you? If you
            didn't receive the email, we will gladly send you another.
        </div>

        <div class="mb-4 text-sm font-medium text-green-600" v-if="verificationLinkSent">
            A new verification link has been sent to the email address you
            provided during registration.
        </div>

        <form @submit.prevent="submit">
            <div class="flex items-center justify-between mt-4">
                <PrimaryButton :class="{ 'opacity-25': processing }" :disabled="processing">
                    Resend Verification Email
                </PrimaryButton>

                <button 
                    type="button"
                    @click="logout"
                    class="text-sm text-gray-600 underline rounded-md hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Log Out
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
