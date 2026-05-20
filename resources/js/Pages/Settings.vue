<template>
    <div class="p-8 text-white">
      <h1 class="text-2xl font-bold mb-4">Settings</h1>
      <p class="text-sm text-gray-400 mb-6">This is your Settings page. You can update your personal info here.</p>

      <!-- SKELETON LOADER STATE -->
      <div v-if="loading" class="space-y-8 animate-pulse">
        <!-- Profile Info Block Skeleton -->
        <div class="bg-[#0F1724] border border-[#1f3348]/60 rounded-xl p-6 space-y-4">
          <div class="h-5 bg-gray-700/60 rounded w-48 mb-2"></div>
          <div class="h-3 bg-gray-800 rounded w-72 mb-6"></div>
          <div class="space-y-3">
            <div class="h-3 bg-gray-800 rounded w-16"></div>
            <div class="h-10 bg-[#16213A] rounded-lg w-full"></div>
          </div>
          <div class="space-y-3 pt-2">
            <div class="h-3 bg-gray-800 rounded w-14"></div>
            <div class="h-10 bg-[#16213A] rounded-lg w-full"></div>
          </div>
          <div class="h-10 bg-gray-800/80 rounded-lg w-24 mt-4"></div>
        </div>

        <!-- Password Block Skeleton -->
        <div class="bg-[#0F1724] border border-[#1f3348]/60 rounded-xl p-6 space-y-4">
          <div class="h-5 bg-gray-700/60 rounded w-40 mb-2"></div>
          <div class="space-y-3">
            <div class="h-10 bg-[#16213A] rounded-lg w-full"></div>
          </div>
          <div class="h-10 bg-gray-800/80 rounded-lg w-28 mt-4"></div>
        </div>

        <!-- Link Skeleton -->
        <div class="h-4 bg-gray-800 rounded w-56 my-4"></div>

        <!-- Danger Zone Skeleton -->
        <div class="bg-red-950/10 border border-red-900/20 rounded-xl p-6 space-y-4">
          <div class="h-5 bg-red-900/40 rounded w-32"></div>
          <div class="h-10 bg-red-900/20 rounded-lg w-full"></div>
        </div>
      </div>

      <!-- DATA RENDER STATE -->
      <div v-else class="space-y-8">
        <UpdateProfileInformationForm />
        <UpdatePasswordForm />
     
        <p class="mt-3 text-gray-300 underline">
          <a href="/two-factor-setup">Two-Factor Authentication Setup</a>
        </p>
        
        <DeleteUserForm />
      </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from "vue-router";
import UpdatePasswordForm from './Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Profile/Partials/UpdateProfileInformationForm.vue';
import DeleteUserForm from './Profile/Partials/DeleteUserForm.vue';

const router = useRouter();


const loading = ref(false);
</script>