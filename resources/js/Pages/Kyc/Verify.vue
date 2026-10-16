<template>
  <MainLayout>
  <main class="min-h-screen bg-[#0B132B] px-3 py-3 text-white sm:px-6 sm:py-8">
    <div class="mx-auto flex min-h-[calc(100vh-1.5rem)] max-w-3xl items-start justify-center sm:min-h-[calc(100vh-4rem)] sm:items-center">
      <VerifyIdentity @close="router.push('/dashboard')" />
    </div>
  </main>
  </MainLayout>
</template>

<script setup>
import { useRouter } from "vue-router";
import VerifyIdentity from "@/Pages/Kyc/VerifyIdentity.vue";
import MainLayout from "@/Layouts/MainLayout.vue";

const router = useRouter();
</script>
