<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ref } from 'vue';
import axios from "axios";
import { useRouter } from 'vue-router';


const router = useRouter();
const status = ref(null);

const form = ref({
    email: '',
    processing: false,
    errors: {}
});


const submit = async () => {
    form.value.processing = true;
    form.value.errors = {};
    status.value = null;

    try {
        const res = await axios.post('/forgot-password', {
            email: form.value.email
        });

        if (res.data.status) {
            status.value = "Password reset link sent!";
            form.value.email = '';
            setTimeout(() => {
                router.push('/reset-password')
            }, 1000)

        }


    } catch (error) {
        if (error.response?.status === 422) {
            form.value.errors = error.response.data.errors;
        } else {
            status.value = error.response?.data?.message || "An unexpected error occurred.";
        }
    } finally {
        form.value.processing = false;
    }
};
</script>

<template>
    <GuestLayout>
        <div class="mb-4 text-sm text-gray-600">
            Forgot your password? No problem. Just let us know your email
            address and we will email you a password reset link that will allow
            you to choose a new one.
        </div>

        <div v-if="status" class="mb-4 text-sm font-medium"
            :class="status.includes('sent') || status.includes('link') ? 'text-green-600' : 'text-red-600'">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput id="email" type="email" class="block w-full mt-1 text-black" v-model="form.email" required
                    autofocus autocomplete="username" />
                    
                <p class="mt-1 text-xs text-gray-500">We'll send a password reset link to this email</p>

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Email Password Reset Link
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
