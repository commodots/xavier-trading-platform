<template>
  <div class="bg-[#0B132B] border-b border-gray-700 px-6 py-4 flex justify-between items-center">
    <h2 class="text-xl font-semibold text-[#00D4FF]">Xavier</h2>
    <div class="flex items-center gap-4">
      <button class="text-gray-400 hover:text-white transition">
        <i class="lucide-bell"></i>
      </button>

      <div class="relative group">
        <img src="/images/avatar.png" alt="Avatar" class="w-10 h-10 rounded-full border border-gray-600 cursor-pointer" />
        <div
          class="absolute right-0 mt-2 bg-[#1C1F2E] border border-gray-700 rounded-lg py-2 w-48 hidden group-hover:block shadow-xl"
        >
          <p class="px-4 py-2 text-sm text-gray-400">{{ user.email }}</p>
          <hr class="border-gray-700" />
          <button @click="logout" class="block w-full text-left px-4 py-2 hover:bg-[#0B132B]">Logout</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
const router = useRouter();
const user = ref({});

onMounted(() => {
  user.value = JSON.parse(localStorage.getItem("user") || "{}");
});

const logout = () => {
  localStorage.removeItem("user");
  router.push("/login");
};
</script>
