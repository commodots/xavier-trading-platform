<template>
  <div class="bg-[#0f172a] p-6 rounded-lg space-y-6 border border-gray-700">

    <!-- Avatar + Basic Data -->
    <div class="flex items-center space-x-4">
      <img :src="user.avatar ?? '/images/user.png'"
        class="object-cover w-20 h-20 border border-gray-600 rounded-full" />

      <div>
        <h2 class="text-lg font-semibold">{{ user.name }}</h2>
        <p class="text-sm text-gray-400">{{ user.email }}</p>
        <p class="text-sm text-gray-400">{{ user.phone }}</p>
      </div>
    </div>

    <!-- Edit form -->
    <form @submit.prevent="updateProfile" class="space-y-4">
      <div>
        <label class="text-sm text-gray-400">Full Name</label>
        <input v-model="form.name" class="input" />
      </div>

      <div>
        <label class="text-sm text-gray-400">Phone Number</label>
        <input v-model="form.phone" class="input" />
      </div>

      <div>
        <label class="text-sm text-gray-400">Address</label>
        <textarea v-model="form.address" class="input"></textarea>
      </div>

      <button type="submit" class="px-4 py-2 text-white bg-blue-600 rounded hover:bg-blue-700">
        Update Profile
      </button>
    </form>
  </div>
</template>

<script setup>
import { reactive, watch, ref } from "vue";
import api from "@/api";

const props = defineProps({
  user: Object,
});

const processing = ref(false);

const form = reactive({
  name: "",
  phone: "",
  address: "",
});

watch(() => props.user, (newUser) => {
  if (newUser) {
    form.name = newUser.name || (newUser.first_name ? `${newUser.first_name} ${newUser.last_name}` : "");
    form.phone = newUser.phone ?? "";
    form.address = newUser.address ?? "";
  }
}, { immediate: true, deep: true });


const updateProfile = async () => {
  processing.value = true;
  try {
    await api.put("/user/profile/update", form, {
    });
    alert("Profile updated successfully");
  } catch (error) {
    console.error("Update failed", error);
  }finally {
    processing.value = false;
  }
};
</script>

<style>
.input {
  @apply w-full bg-transparent border border-gray-600 rounded-lg px-3 py-2 text-sm;
}
</style>
