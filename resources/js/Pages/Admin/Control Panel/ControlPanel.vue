<template>
  <MainLayout>
    <div class="p-6 space-y-6">
      <h1 class="text-2xl font-bold">Control Panel</h1>

      <!-- Tabs -->
      <div class="flex space-x-6 text-sm border-b border-gray-700">
        <button @click="activeTab = 'service-management'" :class="activeTab === 'service-management'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Service Management
        </button>

        <button @click="activeTab = 'transactions-management'" :class="activeTab === 'transactions-management'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Transactions Management
        </button>

        <button @click="activeTab = 'transactions-charges'" :class="activeTab === 'transactions-charges'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Transactions Charges
        </button>
      </div>

      <div>
        <div v-if="loading" class="text-white animate-pulse">
          Loading Control Panel...
        </div>

        <div v-else>
          <Services v-if="activeTab === 'service-management'" />
          <TransactionTypes v-if="activeTab === 'transactions-management'" />
          <TransactionCharges v-if="activeTab === 'transactions-charges'" />
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import MainLayout from "@/layouts/MainLayout.vue";
import Services from "./Services/Services.vue";
import TransactionTypes from "./TransactionTypes.vue";
import TransactionCharges from "./TransactionCharges.vue";
import api from "@/lib/axios";


const activeTab = ref("service-management");
const loading = ref(true);


const initializePanel = async () => {
  try {
    loading.value = true;
    await new Promise(resolve => setTimeout(resolve, 500));
  } catch (error) {
    console.error("Failed to initialize Control Panel:", error);
  } finally {
    loading.value = false;
  }
};
onMounted(initializePanel);
</script>
