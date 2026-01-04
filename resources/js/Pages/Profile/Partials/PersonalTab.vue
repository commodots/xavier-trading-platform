<template>
  <div class="bg-[#0f172a] p-6 rounded-lg space-y-6 border border-gray-700">

    <!-- Avatar + Basic Data -->
    <div class="flex items-center space-x-4">
      <img :src="user.avatar ?? '/images/user.png'"
        @click="showSourceOptions = !showSourceOptions"
        class="object-cover w-20 h-20 border border-gray-600 rounded-full cursor-pointer" />

      <div v-if="showSourceOptions" class="text-sm border border-gray-600 p-3 rounded bg-[#1e293b] space-y-2 animate-fadeIn">
         <p class="text-xs text-gray-400">Upload profile picture from:</p>
         <div class="flex gap-3">
            <button @click="openPicker('file')" class="font-bold text-blue-400 hover:underline">Device</button>
            <button @click="openPicker('camera')" class="font-bold text-blue-400 hover:underline">Camera</button>
            <button @click="showSourceOptions = false" class="text-xs text-gray-500 hover:text-white">Cancel</button>
         </div>
      </div>

      <input type="file" ref="fileInput" class="hidden" accept="image/*" @change="handleUpload" />
      <input type="file" ref="cameraInput" class="hidden" accept="image/*" capture="user" @change="handleUpload" />

      <div>
        <h2 class="text-lg font-semibold">{{ user.name }}</h2>
        <p class="text-sm text-gray-400">{{ user.email }}</p>
        <p class="text-sm text-gray-400">{{ user.phone }}</p>
      </div>
    </div>

    <!-- Edit form -->
    <form @submit.prevent="updateProfile" class="space-y-4">
      <div>
        <label class="text-sm text-gray-400">First Name</label>
        <input v-model="form.first_name" class="input" />
      </div>

      <div>
        <label class="text-sm text-gray-400">Last Name</label>
        <input v-model="form.last_name" class="input" />
      </div>

      <div>
        <label class="text-sm text-gray-400">Email</label>
        <input v-model="form.email" class="input" />
      </div>

      <div>
        <label class="text-sm text-gray-400">Phone Number</label>
        <input v-model="form.phone" class="input" />
      </div>

      <div>
        <label class="text-sm text-gray-400">Address</label>
        <textarea v-model="form.address" class="input"></textarea>
      </div>
      <div>
        <label class="text-sm text-gray-400">Name of Next of Kin</label>
        <input v-model="form.next_of_kin" class="input" />
      </div>
      <div>
        <label class="text-sm text-gray-400">Phone Number of Next of Kin</label>
        <input v-model="form.next_of_kin_phone" class="input" />
      </div>
      <div>
        <label class="text-sm text-gray-400">Email of Next of Kin</label>
        <input v-model="form.next_of_kin_email" class="input" />
      </div>

      <button type="submit" :disabled="processing" class="px-4 py-2 text-white bg-blue-600 rounded hover:bg-blue-700">
        {{ processing ? 'Updating...' : 'Update Profile' }}
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

const emit = defineEmits(['refresh']);
const processing = ref(false);
const showSourceOptions = ref(false);
const fileInput = ref(null);
const cameraInput = ref(null);

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

const openPicker = (source) => {
  showSourceOptions.value = false;
  if (source === 'camera') {
    cameraInput.value.click();
  } else {
    fileInput.value.click();
  }
};

const handleUpload = async (e) => {
  const file = e.target.files[0];
  if (!file) return;

  const fd = new FormData();
  fd.append('avatar', file);

  processing.value = true;
  try {
    await api.post("/user/profile/avatar", fd);
    emit('refresh'); 
  } catch (error) {
    console.error("Avatar upload failed", error);
  } finally {
    processing.value = false;
  }
};

const updateProfile = async () => {
  processing.value = true;
  try {
    await api.put("/user/profile/update", form);
    alert("Profile updated successfully");
  } catch (error) {
    console.error("Update failed", error);
  } finally {
    processing.value = false;
  }
};
</script>

<style>
.input {
  @apply w-full bg-transparent border border-gray-600 rounded-lg px-3 py-2 text-sm;
}
.animate-fadeIn {
  animation: fadeIn 0.2s ease-in-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateX(-5px); }
  to { opacity: 1; transform: translateX(0); }
}
</style>