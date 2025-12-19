<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-xl font-semibold text-white">Notification Preferences</h2>
      <p class="text-gray-400 text-sm">Choose how you want to be notified about your account activity.</p>
    </div>

    <div class="space-y-4 max-w-md">
      <div class="flex items-center justify-between p-4 bg-[#16213A] border border-gray-700 rounded-lg">
        <div>
          <h3 class="text-white font-medium">Email Notifications</h3>
          <p class="text-gray-400 text-xs">Receive updates via your registered email.</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" v-model="prefs.email" class="sr-only peer">
          <div
            class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
          </div>
        </label>
      </div>

      <div class="flex items-center justify-between p-4 bg-[#16213A] border border-gray-700 rounded-lg">
        <div>
          <h3 class="text-white font-medium">SMS Notifications</h3>
          <p class="text-gray-400 text-xs">Get alerts sent directly to your phone.</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" v-model="prefs.sms" class="sr-only peer">
          <div
            class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
          </div>
        </label>
      </div>

      <div class="flex items-center justify-between p-4 bg-[#16213A] border border-gray-700 rounded-lg">
        <div>
          <h3 class="text-white font-medium">Push Notifications</h3>
          <p class="text-gray-400 text-xs">Receive instant alerts in your browser or app.</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" v-model="prefs.push" class="sr-only peer">
          <div
            class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
          </div>
        </label>
      </div>
    </div>

    <button @click="savePreferences" :disabled="loading"
      class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 text-white px-8 py-2 rounded-lg font-medium transition">
      {{ loading ? 'Saving...' : 'Save Preferences' }}
    </button>

    <p v-if="recentlySuccessful" class="text-green-500 text-sm font-medium">
      Preferences updated successfully!
    </p>

    <p v-if="errorMessage" class="text-red-400 text-sm font-medium">
      {{ errorMessage }}
    </p>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/api';

const prefs = ref({
  email: true,
  sms: false,
  push: true
});
const loading = ref(false);
const recentlySuccessful = ref(false);
const errorMessage = ref("");

const fetchPrefs = async () => {
  try {
    const res = await api.get('/user/notifications/show');

    if (res.data.data) {
      prefs.value = {
        email: !!res.data.data.email,
        sms: !!res.data.data.sms,
        push: !!res.data.data.push
      };
    }
  } catch (err) {
    console.error("Failed to fetch notification preferences", err);
  }
};

const savePreferences = async () => {
  loading.value = true;
  recentlySuccessful.value = false;
  errorMessage.value = "";

  try {
    await api.put('/user/notifications/update', prefs.value);

    recentlySuccessful.value = true;
    setTimeout(() => {
      recentlySuccessful.value = false;
    }, 3000);

  } catch (err) {
    console.error("Technical details:", err.response?.data);
    errorMessage.value = "We couldn't save your preferences right now. Please check your connection or try again later.";
    setTimeout(() => {
      errorMessage.value = "";
    }, 5000);
    
  } finally {
    loading.value = false;
  }
};

onMounted(fetchPrefs);
</script>