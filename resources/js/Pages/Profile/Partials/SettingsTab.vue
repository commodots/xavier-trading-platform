<template>
    <div class="p-8 text-white">
      <h1 class="text-2xl font-bold mb-4">Settings</h1>
        <UpdateProfileInformationForm />
        <UpdatePasswordForm />
     
      <p class="mt-3 text-gray-300 underline"><a href="/two-factor-setup">Two-Factor Authentication Setup</a></p>
        <DeleteUserForm />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from "vue-router";
import UpdatePasswordForm from "./UpdatePasswordForm.vue";
import UpdateProfileInformationForm from "./UpdateProfileInformationForm.vue";
import DeleteUserForm from "./DeleteUserForm.vue";


</script>