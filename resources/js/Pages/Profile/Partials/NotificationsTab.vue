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
          <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
        </label>
      </div>

      <div class="flex items-center justify-between p-4 bg-[#16213A] border border-gray-700 rounded-lg">
        <div>
          <h3 class="text-white font-medium">SMS Notifications</h3>
          <p class="text-gray-400 text-xs">Get alerts sent directly to your phone.</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" v-model="prefs.sms" class="sr-only peer">
          <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
        </label>
      </div>

      <div class="flex items-center justify-between p-4 bg-[#16213A] border border-gray-700 rounded-lg">
        <div>
          <h3 class="text-white font-medium">Push Notifications</h3>
          <p class="text-gray-400 text-xs">Receive instant alerts in your browser or app.</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" v-model="prefs.push" class="sr-only peer">
          <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
        </label>
      </div>
    </div>

    <button 
      @click="savePreferences" 
      :disabled="loading"
      class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 text-white px-8 py-2 rounded-lg font-medium transition"
    >
      {{ loading ? 'Saving...' : 'Save Preferences' }}
    </button>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const prefs = ref({
  email: true,
  sms: false,
  push: true
});
const loading = ref(false);

const fetchPrefs = async () => {
  try {
    const res = await axios.get('/api/user/notifications', {
      headers: { Authorization: `Bearer ${localStorage.getItem("xavier_token")}` }
    });
    // Ensure we handle cases where the user might not have a preferences row yet
    if (res.data.data) {
      prefs.value = res.data.data;
    }
  } catch (err) {
    console.error("Failed to fetch notification preferences", err);
  }
};

const savePreferences = async () => {
  loading.value = true;
  try {
    await axios.put('/api/user/notifications', prefs.value, {
      headers: { Authorization: `Bearer ${localStorage.getItem("xavier_token")}` }
    });
    alert("Preferences updated!");
  } catch (err) {
    alert("Failed to update preferences.");
  } finally {
    loading.value = false;
  }
};

onMounted(fetchPrefs);
</script>