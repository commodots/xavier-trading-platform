<template>
  <div class="p-8 text-white">
    <h1 class="mb-4 text-2xl font-bold">Settings</h1>
    <UpdatePasswordForm />
    <TwoFactorSetup />
    <DeleteUserForm />
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from "vue-router";
import UpdatePasswordForm from "./UpdatePasswordForm.vue";
import TwoFactorSetup from "./TwoFactorSetup.vue";
import DeleteUserForm from "./DeleteUserForm.vue";


</script>