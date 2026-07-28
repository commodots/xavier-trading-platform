<template>
  <MainLayout>
    <div class="p-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Login History</h1>
        <p class="text-gray-400 mt-1">Monitor user login activity and security events</p>
      </div>

      <!-- Reusable Login History Component -->
      <LoginHistoryTable />
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import LoginHistoryTable from '@/Components/admin/LoginHistoryTable.vue';
</script>
