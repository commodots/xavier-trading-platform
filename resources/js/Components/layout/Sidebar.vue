<template>
  <aside :class="[
    'h-screen text-gray-300 border-r border-[#1F2A44] flex flex-col transition-all duration-300 z-40',
    collapsed ? 'w-20' : 'w-64',
    sidebarBg
  ]">
    <!-- LOGO + TOGGLE -->
    <div class="flex items-center justify-between p-4 border-b border-[#1F2A44]">
      <img v-if="!collapsed" src="/images/xavier-logo.png" class="object-contain h-12 transition-all duration-300" />

      <button @click="$emit('toggle')" class="text-gray-400 transition hover:text-white">
        <Menu class="w-6 h-6" />
      </button>
    </div>

    <!-- MENU ITEMS -->
    <nav class="flex-1 py-4 space-y-1 overflow-y-auto">
      <SidebarLink v-for="item in filteredMenu" :key="item.to" :to="item.to" :icon="item.icon" :collapsed="collapsed">
        {{ item.label }}
      </SidebarLink>
    </nav>

    <!-- FOOTER + LOGOUT -->
    <div class="p-4 border-t border-[#1F2A44]">
      <button @click="logout"
        class="flex items-center w-full gap-3 px-3 py-2 text-white transition bg-red-600 rounded-lg hover:bg-red-700">
        <LogOut class="w-5 h-5" />
        <span v-if="!collapsed">Logout</span>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { computed, ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import api from '@/api';

import SidebarLink from "@/Components/SidebarLink.vue";

// Lucide icons
import {
  Home,
  Wallet,
  User,
  List,
  Settings,
  Users,
  ShieldCheck,
  BarChart2,
  LogOut,
  PieChart,
  ShoppingCart,
  Menu,
  MonitorCog,
  FileSpreadsheet,
  SquareChartGantt
} from "lucide-vue-next";

// Props
const props = defineProps({
  collapsed: Boolean,
  icon: {
    type: [Object, Function],
    required: true,
  },
});


// Router
const router = useRouter();

// Load user from localStorage
const user = ref({});
try {
  user.value = JSON.parse(localStorage.getItem("user") || "{}");
} catch {
  user.value = {};
}

const isAdmin = computed(() => user.value.role === "admin");
const isStaff = computed(() => !isAdmin.value && hasAnyPermissions());

const sidebarBg = computed(() => {
  if (isAdmin.value) return 'bg-[#0B132B]'; // Darker blue for admin
  if (isStaff.value) return 'bg-[#1F2A44]'; // Lighter blue for staff
  return 'bg-[#0B132B]'; // Default for others
});
const userPermissions = ref({});

const hasAnyPermissions = () => {
  return userPermissions.value && Object.values(userPermissions.value).some(p => !!p);
};

const fetchPermissions = async () => {
  if (user.role === "admin") return;
  try {
    const profileRes = await api.get('/user/profile/show');
    const currentUser = profileRes.data.data;
    userPermissions.value = currentUser.permissions || {};
    // Update localStorage
    let storedUser = JSON.parse(localStorage.getItem("user") || "{}");
    storedUser.permissions = userPermissions.value;
    localStorage.setItem("user", JSON.stringify(storedUser));
  } catch (e) {
    console.error('Failed to fetch permissions', e);
  }
};

// Fetch on mount if not admin
onMounted(() => {
  if (user.value?.role !== "admin") {
    fetchPermissions();
  }
});

// Menu structure
const menu = [
  { label: "Dashboard", to: "/dashboard", icon: Home },
  { label: "Wallet", to: "/wallet", icon: Wallet },
  { label: "Profile", to: "/profile", icon: User },
  { label: "Transactions", to: "/transactions", icon: List },
  { label: "Trade / OMS", to: "/orders", icon: ShoppingCart },
  { label: "Settings", to: "/settings", icon: Settings },
  { label: "Reports", to: "/reports", icon: FileSpreadsheet },

  // Admin/staff
  { label: "Admin Dashboard", to: "/admin", icon: PieChart, access: () => isAdmin.value },
  { label: "Admin Users", to: "/admin/users", icon: Users, access: () => isAdmin.value },
  { label: "Admin Transactions", to: "/admin/transactions", icon: List, access: () => isAdmin.value || !!userPermissions.value.manage_transaction_charges },
  { label: "KYC Review", to: "/admin/kyc", icon: ShieldCheck, access: () => isAdmin.value || !!userPermissions.value.manage_kyc_settings },
  { label: "Order Book", to: "/admin/orderbook", icon: BarChart2, access: () => isAdmin.value },
  { label: "Control Panel", to: "/admin/control-panel", icon: MonitorCog, access: () => isAdmin.value || hasAnyPermissions() },
  { label: "Activity Log", to: "/admin/activity-log", icon: SquareChartGantt, access: () => isAdmin.value },
  { label: "Reports", to: "/admin/reports", icon: FileSpreadsheet, access: () => isAdmin.value },
];

// Filter menu based on access functions
const filteredMenu = computed(() => {
  return menu.filter((m) => !m.access || m.access());
});

// Logout function
const logout = async () => {
  try {

    await api.post('/logout');
  } catch (e) {
    console.warn("Session already expired on server");
  } finally {
    localStorage.clear();
    sessionStorage.clear();

    window.location.href = "/login";
  }
};
</script>

<style scoped>
aside {
  transition: width 0.25s ease-in-out;
}
</style>
