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
                <InputLabel for="first-name" value="First Name" class="text-white" />
                <input id="first_name" type="text"
                    class="block w-full mt-1 border-gray-300 rounded-md shadow-sm text-gray-500 focus:border-indigo-500 focus:ring-indigo-500"
                    v-model="form.first_name" required autofocus />
                <InputError class="mt-2" :message="errors.name ? errors.name[0] : ''" />
            </div>

            <div>
                <InputLabel for="last_name" value="Last Name" class="text-white" />
                <input id="last_name" type="text"
                    class="block w-full mt-1 border-gray-300 rounded-md shadow-sm text-gray-600 focus:border-indigo-500 focus:ring-indigo-500"
                    v-model="form.last_name" required autofocus />
                <InputError class="mt-2" :message="errors.name ? errors.name[0] : ''" />
            </div>

            <div>
                <InputLabel for="email" value="Email" class="text-white" />
                <input id="email" type="email"
                    class="block w-full mt-1 border-gray-300 rounded-md shadow-sm  text-gray-600 focus:border-indigo-500 focus:ring-indigo-500"
                    v-model="form.email" required />
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
import { ref, reactive, watch } from 'vue';
import api from '@/api';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';


const props = defineProps({ user: Object });
const emit = defineEmits(['refresh']);


const form = reactive({
    first_name: '',
    last_name: '',
    email: '',
});


const errors = ref({});
const processing = ref(false);
const recentlySuccessful = ref(false);


watch(() => props.user, (newUserData) => {
    if (newUserData && Object.keys(newUserData).length > 0) {
        form.first_name = newUserData.first_name || '';
        form.last_name = newUserData.last_name || '';
        form.email = newUserData.email || '';
    }
}, { immediate: true, deep: true });


async function updateProfile() {
    processing.value = true;
    errors.value = {};

    try {
        await api.put('/user/profile/update', {
            name: `${form.first_name} ${form.last_name}`.trim(),
            email: form.email,
        });

        emit('refresh');

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