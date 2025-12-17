<script setup>
import { ref, reactive } from 'vue';
import axios from 'axios';
 import InputError from '@/Components/InputError.vue';
 import InputLabel from '@/Components/InputLabel.vue';
 import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';


const passwordInput = ref(null);
const currentPasswordInput = ref(null);

// --- STATE MANAGEMENT ---


const form = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});


const errors = reactive({});
const processing = ref(false);
const recentlySuccessful = ref(false);


const updatePassword = async () => {
    processing.value = true;
    recentlySuccessful.value = false;
    // Clear previous errors
    Object.keys(errors).forEach(key => delete errors[key]);

    try {
        // Laravel uses a PUT or PATCH request for updates. We'll use PATCH on the API.
        // Assuming your backend route is POST /api/password (or PUT/PATCH /api/password)
        await api.patch('/password', form);

        // --- SUCCESS LOGIC (Based on Inertia's onSuccess) ---
        
        // 1. Reset the form fields
        form.current_password = '';
        form.password = '';
        form.password_confirmation = '';

        // 2. Show success message
        recentlySuccessful.value = true;
        setTimeout(() => recentlySuccessful.value = false, 3000);

    } catch (e) {
        // --- ERROR LOGIC (Based on Inertia's onError) ---
        
        if (e.response && e.response.status === 422) {
            const apiErrors = e.response.data.errors;
            Object.assign(errors, apiErrors); // Populate errors state

            // 1. Handle validation errors and focus (Inertia's logic replicated)
            if (apiErrors.password) {
                form.password = '';
                form.password_confirmation = '';
                // Focus the new password field if it failed validation
                passwordInput.value.focus();
            }
            if (apiErrors.current_password) {
                form.current_password = '';
                // Focus the current password field if it failed validation
                currentPasswordInput.value.focus();
            }
            
        } else {
            console.error("Password update failed:", e);
            // Handle other server errors (e.g., 500)
            errors.general = ['An unexpected error occurred. Please try again.'];
        }
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-white mt-3">
                Update Password
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Ensure your account is using a long, random password to stay
                secure.
            </p>
        </header>

        <form @submit.prevent="updatePassword" class="mt-6 space-y-6">
            <div>
                <InputLabel for="current_password" value="Current Password" />

                <TextInput
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                />

                <InputError
                    :message="errors.current_password ? errors.current_password[0] : ''"
                    class="mt-2"
                />
            </div>

            <div>
                <InputLabel for="password" value="New Password" />

                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                />

                <InputError :message="errors.password ? errors.password[0] : ''" class="mt-2" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="Confirm Password"
                />

                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                />

                <InputError
                    :message="errors.password_confirmation ? errors.password_confirmation[0] : ''"
                    class="mt-2"
                />
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