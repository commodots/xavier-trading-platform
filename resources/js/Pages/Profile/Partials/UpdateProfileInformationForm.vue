<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-medium text-white">Profile Information</h2>
            <p class="mt-1 text-sm text-gray-400">
                Update your account's profile information and email address.
            </p>
        </header>

        <form @submit.prevent="updateProfile" class="mt-6 space-y-6">
            <div>
                <InputLabel for="name" value="Name" />
                <input
                    id="name"
                    type="text"
                    class="block w-full mt-1 border-gray-300 rounded-md shadow-sm text-gray-500 focus:border-indigo-500 focus:ring-indigo-500"
                    v-model="form.name"
                    required
                    autofocus
                />
                <InputError class="mt-2" :message="errors.name ? errors.name[0] : ''" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />
                <input
                    id="email"
                    type="email"
                    class="block w-full mt-1 border-gray-300 rounded-md shadow-sm  text-gray-500 focus:border-indigo-500 focus:ring-indigo-500"
                    v-model="form.email"
                    required
                />
                <InputError class="mt-2" :message="errors.email ? errors.email[0] : ''" />
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="processing">Save Changes</PrimaryButton>
                <p v-if="recentlySuccessful" class="text-sm text-green-400">Saved.</p>
            </div>
        </form>
    </section>
</template>


<script setup>
import { ref, reactive, onMounted } from 'vue'; 
import api from '@/lib/axios';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

// 1. User State
const user = ref({ 
    name: '', 
    email: '', 
    email_verified_at: null 
});

// 2. Form Data
const form = reactive({
    name: '',
    email: '',
});

// 3. UI State
const errors = ref({});
const processing = ref(false);
const recentlySuccessful = ref(false);


onMounted(async () => {
    try {
        const response = await api.get('/user/profile/show');
        const userData = response.data.data;
        console.log(userData)

        user.value = userData; 
        form.name = userData.name || '';
        form.email = userData.email || '';
    } catch (e) {
        console.error("Failed to load profile data.", e);
    }
});

// --- METHODS ---
async function updateProfile() {
    processing.value = true;
    recentlySuccessful.value = false;
 
    try {
        const response = await api.put('/user/profile/update', {
            name: form.name,
            email: form.email,
        });

        user.value = response.data.data;
        recentlySuccessful.value = true;
        setTimeout(() => recentlySuccessful.value = false, 3000);

    } catch (e) {
        if (e.response && e.response.status === 422) {
           errors.value = e.response.data.errors;
        } 
    } finally {
        processing.value = false;
    }
}
</script>