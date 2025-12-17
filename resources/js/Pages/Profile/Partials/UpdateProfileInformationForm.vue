<script setup>
import { ref, reactive, onMounted } from 'vue'; 
import axios from 'axios';
import InputError from '@/Components/InputError.vue'; // Keep your custom components
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';import TextInput from '@/Components/TextInput.vue';

const user = ref({ 
    name: '', 
    email: '', 
    email_verified_at: null 
});

// 2. Form Data (Mutable)
const form = reactive({
    name: '',
    email: '',
});

// 3. UI State (Replaces Inertia's helpers)
const errors = reactive({});
const processing = ref(false);
const recentlySuccessful = ref(false);

// 4. Props (Simplified as we don't rely on Inertia props as much)
defineProps({
    mustVerifyEmail: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
    },
});

// --- LIFECYCLE HOOK ---

// Fetch the current user data when the component loads
onMounted(async () => {
    try {
        // Assuming you have a protected '/profile' endpoint
        const response = await api.get('/profile');
        const userData = response.data.data; // Assuming API returns data.data (as per previous context)

        // Set both the display user and the form data
        user.value = userData; 
        form.name = userData.name;
        form.email = userData.email;
    } catch (e) {
        console.error("Failed to load profile data.", e);
        // Handle error: e.g., redirect to login if 401
    }
});


// --- METHODS ---

async function updateProfile() {
    processing.value = true;
    recentlySuccessful.value = false;
    // Clear previous errors
    Object.keys(errors).forEach(key => delete errors[key]);

    try {
        // 🛑 Use api.patch() for updates
        const response = await api.patch('/profile', { // Assuming the API route is /api/profile
            name: form.name,
            email: form.email,
        });

        // Update the reactive user state with the new data
        user.value.name = response.data.data.name;
        user.value.email = response.data.data.email;
        
        // Success feedback
        recentlySuccessful.value = true;
        setTimeout(() => recentlySuccessful.value = false, 3000);

    } catch (e) {
        if (e.response && e.response.status === 422) {
            // Laravel Validation Errors (422)
            Object.assign(errors, e.response.data.errors);
        } else {
            console.error("Profile update failed:", e);
        }
    } finally {
        processing.value = false;
    }
}

// Handler for email verification re-send
async function sendVerification() {
    try {
        // 🛑 Assuming a POST request to a /verification-notification endpoint
        await api.post('/verification-notification');
        // Set success status to show the message
        // You would need a reactive 'status' variable if you want to mirror the Inertia logic exactly
    } catch (e) {
        console.error("Failed to re-send verification email.", e);
    }
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-white mt-3">
                Profile Information
            </h2>

            <p class="mt-1 text-sm text-gray-600">
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
                    autocomplete="name"
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
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="errors.email ? errors.email[0] : ''" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Your email address is unverified.
                    <button
                        @click="sendVerification"
                        type="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Click here to re-send the verification email.
                    </button>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="processing">Save</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>