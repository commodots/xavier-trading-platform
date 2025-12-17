<template>
  <MainLayout>
    <div class="p-8 text-white">
      <h1 class="text-2xl font-bold mb-4">Settings</h1>
      <p>This is your Settings page. You can update your personal info here.</p>
        <UpdateProfileInformationForm />
        <UpdatePasswordForm />
     
      <p class="mt-3 text-gray-300 underline"><a href="/two-factor-setup">Two-Factor Authentication Setup</a></p>
        <DeleteUserForm />
    </div>
  </MainLayout>
</template>

<script setup>
import { ref } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import { useRouter } from "vue-router";
import UpdatePasswordForm from './Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Profile/Partials/UpdateProfileInformationForm.vue';
import DeleteUserForm from './Profile/Partials/DeleteUserForm.vue';
const router = useRouter();

</script>