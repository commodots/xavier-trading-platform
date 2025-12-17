<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ref, watch } from 'vue';
import axios from 'axios';
import { useRouter, useRoute } from 'vue-router';

const router = useRouter();
const route = useRoute();

const status = ref(null);

const form = ref({
    // Get token and email from the URL query parameters
    token: route.query.token,
    email: route.query.email,

    password: '',
    password_confirmation: '',

    // Manual form state
    processing: false,
    errors: {},
});


const submit = async () => {
    form.value.processing = true;
    form.value.errors = {};
    status.value = null;

    try {

        const res = await axios.post('/reset-password', form.value);

        if (res.data.status) {
            status.value = "Password successfully reset! You can now log in.";

            router.push({ path: '/login', query: { status: status.value } });
        }
    } catch (error) {
        if (error.response?.status === 422) {
            // Validation errors
            form.value.errors = error.response.data.errors;

        } else if (error.response?.data?.message) {
            // General server message (e.g., token expired)
            status.value = error.response.data.message;

        } else {
            // Unexpected error
            status.value = "An unexpected error occurred during reset.";
        }
    } finally {
        form.value.processing = false;
    }
};
</script>

<template>
    <GuestLayout>
        <div v-if="status" class="p-3 mb-4 text-sm font-medium rounded"
            :class="status.includes('successfully reset') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
            {{ status }}
        </div>
        
        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput 
                id="email"
                 type="email" 
                 class="block w-full mt-1 text-black" 
                 v-model="form.email" 
                 required 
                 autofocus
                autocomplete="username" />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="New Password" />

                <TextInput id="password" type="password" class="block w-full mt-1 text-black" v-model="form.password" required
                    autocomplete="new-password" />
                    
                <p class="mt-1 text-xs text-gray-500">Create a strong password with at least 8 characters</p>

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel for="password_confirmation" value="Confirm Password" />

                <TextInput id="password_confirmation" type="password" class="block w-full mt-1 text-black"
                    v-model="form.password_confirmation" required autocomplete="new-password" />
                    
                <p class="mt-1 text-xs text-gray-500">Re-enter your password to confirm</p>

                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Reset Password
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
