<template>
  <div class="bg-[#0f172a] p-6 rounded-lg space-y-6 border border-gray-700">

    <!-- Avatar + Basic Data -->
    <div class="flex items-center space-x-4">
      <img
        :src="user.avatar ?? '/images/avatar-placeholder.png'"
        class="h-20 w-20 rounded-full object-cover border border-gray-600"
      />

      <div>
        <h2 class="text-lg font-semibold">{{ user.name }}</h2>
        <p class="text-gray-400 text-sm">{{ user.email }}</p>
        <p class="text-gray-400 text-sm">Phone: {{ user.phone }}</p>
      </div>
    </div>

    <!-- Edit form -->
    <form @submit.prevent="updateProfile" class="space-y-4">
      <div>
        <label class="text-gray-400 text-sm">Full Name</label>
        <input v-model="form.name" class="input" />
      </div>

      <div>
        <label class="text-gray-400 text-sm">Phone Number</label>
        <input v-model="form.phone" class="input" />
      </div>

      <div>
        <label class="text-gray-400 text-sm">Address</label>
        <textarea v-model="form.address" class="input"></textarea>
      </div>

      <button
        type="submit"
        class="bg-blue-600 px-4 py-2 rounded text-white hover:bg-blue-700"
      >
        Update Profile
      </button>
    </form>
  </div>
</template>

<script setup>
import { reactive } from "vue";
import axios from "axios";

const props = defineProps({
  user: Object,
});

const token = localStorage.getItem("xavier_token");

const form = reactive({
  name: props.user.name,
  phone: props.user.phone,
  address: props.user.address,
});

const updateProfile = async () => {
  try {
    await axios.put("/api/user/profile/update", form, {
      headers: { Authorization: `Bearer ${token}` },
    });
    alert("Profile updated successfully");
  } catch (error) {
    console.error("Update failed", error);
  }
};
</script>

<style>
.input {
  @apply w-full bg-transparent border border-gray-600 rounded-lg px-3 py-2 text-sm;
}
</style>
