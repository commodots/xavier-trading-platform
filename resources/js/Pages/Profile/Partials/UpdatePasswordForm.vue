<script setup>
import { ref, reactive } from 'vue';
import api from '@/lib/axios';
 import InputError from '@/Components/InputError.vue';
 import InputLabel from '@/Components/InputLabel.vue';
 import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';


const passwordInput = ref(null);
const currentPasswordInput = ref(null);


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
        
        await api.put('/user/password', form);
        form.current_password = '';
        form.password = '';
        form.password_confirmation = '';

        
        recentlySuccessful.value = true;
        setTimeout(() => recentlySuccessful.value = false, 3000);

    } catch (e) {
        
        
        if (e.response && e.response.status === 422) {
            const apiErrors = e.response.data.errors;
            Object.assign(errors, apiErrors); // Populate errors state

            
            if (apiErrors.password) {
                form.password = '';
                form.password_confirmation = '';
                
                passwordInput.value.focus();
            }
            if (apiErrors.current_password) {
                form.current_password = '';
                
                currentPasswordInput.value.focus();
            }
            
        } else {
            console.error("Password update failed:", e);
            
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
                    class="mt-1 block w-full  text-gray-500 "
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
                    class="mt-1 block w-full  text-gray-500 "
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
                    class="mt-1 block w-full  text-gray-500 "
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