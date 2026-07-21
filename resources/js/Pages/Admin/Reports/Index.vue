<template>
  <MainLayout>
    <div class="space-y-6">
      <!-- Navigation tabs -->
      <div class="flex flex-wrap gap-2 bg-[#0F1724] border border-[#1f3348] rounded-xl p-2">
        <router-link
          v-for="link in navLinks"
          :key="link.path"
          :to="link.path"
          class="px-4 py-2 rounded-lg text-sm font-medium transition"
          :class="$route.path === link.path ? 'bg-[#0047AB] text-white' : 'text-gray-400 hover:text-white hover:bg-[#1f3348]'"
        >
          {{ link.label }}
        </router-link>
      </div>

      <!-- Child route content -->
      <router-view />
    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';

const navLinks = [
  { path: '/admin/reports', label: 'Dashboard' },
  { path: '/admin/reports/users', label: 'Users' },
  { path: '/admin/reports/financial', label: 'Financial' },
  { path: '/admin/reports/investments', label: 'Investments' },
  { path: '/admin/reports/wallet-withdrawals', label: 'Wallet' },
  { path: '/admin/reports/referrals', label: 'Referrals' },
  { path: '/admin/reports/system', label: 'System' },
];
</script>