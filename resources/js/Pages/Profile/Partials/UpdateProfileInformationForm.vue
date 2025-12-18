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
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                />
                <InputError class="mt-2" :message="errors.name ? errors.name[0] : ''" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                />
                <InputError class="mt-2" :message="errors.email ? errors.email[0] : ''" />
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="processing">Save Changes</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="recentlySuccessful" class="text-sm text-green-400">Saved.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'; 
import axios from 'axios';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

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
const errors = reactive({});
const processing = ref(false);
const recentlySuccessful = ref(false);

const token = localStorage.getItem("xavier_token");
const headers = { 
    Authorization: `Bearer ${token}`,
    Accept: 'application/json'
};


onMounted(async () => {
    try {
        const response = await axios.get('/api/user/profile/show', { headers });
        const userData = response.data.data;

        user.value = userData; 
        form.name = userData.name;
        form.email = userData.email;
    } catch (e) {
        console.error("Failed to load profile data.", e);
    }
});

// --- METHODS ---
async function updateProfile() {
    processing.value = true;
    recentlySuccessful.value = false;
    // Clear previous errors
    Object.keys(errors).forEach(key => delete errors[key]);

    try {
        const response = await axios.put('/api/user/profile/update', {
            name: form.name,
            email: form.email,
        }, { headers });

        user.value = response.data.data;
        recentlySuccessful.value = true;
        setTimeout(() => recentlySuccessful.value = false, 3000);

    } catch (e) {
        if (e.response && e.response.status === 422) {
            Object.assign(errors, e.response.data.errors);
        } else {
            console.error("Profile update failed:", e);
        }
    } finally {
        processing.value = false;
    }
}
</script>