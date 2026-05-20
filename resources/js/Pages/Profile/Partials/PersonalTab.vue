<template>
  <div class="bg-[#0f172a] p-6 rounded-xl space-y-6 border border-gray-700 max-w-3xl mx-auto">

    <!-- Avatar + Basic Data -->
    <div class="flex items-center space-x-4">
      <div class="relative">
        <img 
          :src="user.avatar ?? '/images/user.png'"
          class="object-cover w-20 h-20 border border-gray-600 rounded-full" 
          alt="Profile Avatar"
        />
      </div>

      <div>
        <h2 class="text-lg font-semibold">{{ user.name }}</h2>
        <p class="text-sm text-gray-400">{{ user.email }}</p>
        <p class="text-sm text-gray-400">{{ user.phone }}</p>
      </div>
    </div>
    
    <p class="text-xs text-gray-500 -mt-4 bg-gray-800/40 p-2 rounded-lg border border-gray-800">
      Your profile picture is set during KYC and cannot be changed manually.
    </p>

    <!-- Edit form -->
    <form @submit.prevent="updateProfile" class="space-y-6">
      
      <!-- Personal Details Section -->
      <div>
        <h3 class="text-sm font-medium text-[#00D4FF] mb-3 uppercase tracking-wider">Personal Details</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-gray-400 mb-1">First Name</label>
            <input v-model="form.first_name" class="input" />
          </div>

          <div>
            <label class="block text-xs text-gray-400 mb-1">Last Name</label>
            <input v-model="form.last_name" class="input" />
          </div>

          <div>
            <label class="block text-xs text-gray-400 mb-1">Email Address</label>
            <input v-model="form.email" type="email" class="input" />
          </div>

          <div>
            <label class="block text-xs text-gray-400 mb-1">Phone Number</label>
            <input v-model="form.phone" type="tel" class="input" />
          </div>
        </div>

        <div class="mt-4">
          <label class="block text-xs text-gray-400 mb-1">Residential Address</label>
          <textarea v-model="form.address" rows="2" class="input resize-none"></textarea>
        </div>
      </div>

      <hr class="border-gray-800" />

      <!-- Next of Kin Section -->
      <div>
        <h3 class="text-sm font-medium text-[#00D4FF] mb-3 uppercase tracking-wider">Next of Kin</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2">
            <label class="block text-xs text-gray-400 mb-1">Full Name</label>
            <input v-model="form.next_of_kin" class="input" placeholder="Name of next of kin" />
          </div>

          <div>
            <label class="block text-xs text-gray-400 mb-1">Phone Number</label>
            <input v-model="form.next_of_kin_phone" type="tel" class="input" placeholder="Phone number" />
          </div>

          <div>
            <label class="block text-xs text-gray-400 mb-1">Email Address</label>
            <input v-model="form.next_of_kin_email" type="email" class="input" placeholder="Email address" />
          </div>
        </div>
      </div>

      <div class="flex justify-end pt-2">
        <button 
          type="submit" 
          :disabled="processing" 
          class="w-full md:w-auto px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition disabled:opacity-50"
        >
          {{ processing ? 'Updating...' : 'Update Profile' }}
        </button>
      </div>

    </form>
  </div>
</template>

<script setup>
import { reactive, watch, ref } from "vue";
import api from "@/api";

const props = defineProps({
  user: Object,
});

const emit = defineEmits(['refresh']);
const processing = ref(false);

const form = reactive({
  first_name: "",
  last_name: "",
  email: "",
  phone: "",
  address: "",
  next_of_kin: "",
  next_of_kin_phone: "",
  next_of_kin_email: "",
});

watch(() => props.user, (newUser) => {
  if (newUser) {
    form.first_name = newUser.first_name ?? "";
    form.last_name = newUser.last_name ?? ""; 
    form.email = newUser.email ?? "";
    form.phone = newUser.phone ?? "";
    form.address = newUser.address ?? "";
    form.next_of_kin = newUser.next_of_kin ?? "";
    form.next_of_kin_phone = newUser.next_of_kin_phone ?? "";
    form.next_of_kin_email = newUser.next_of_kin_email ?? "";
  }
}, { immediate: true, deep: true });

const updateProfile = async () => {
  processing.value = true;
  try {
    await api.put("/user/profile/update", form);
    emit('refresh'); 
    alert("Profile updated successfully");
  } catch (error) {
    console.error("Update failed", error);
    alert("Failed to update profile. Please try again.");
  } finally {
    processing.value = false;
  }
};
</script>

<style scoped>
.input {
  @apply w-full bg-[#1e293b]/40 border border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:outline-none focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] transition;
}
</style>