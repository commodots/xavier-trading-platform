// resources/js/router/index.js
import { createRouter, createWebHistory } from "vue-router";

// Auth
import Login from "@/Pages/Auth/Login.vue";
import Register from "@/Pages/Auth/Register.vue";
import ForgotPassword from "@/Pages/Auth/ForgotPassword.vue";
import ResetPassword from "@/Pages/Auth/ResetPassword.vue";
import VerifyEmail from "@/Pages/Auth/VerifyEmail.vue";


// Main User Pages
import Dashboard from "@/Pages/Dashboard.vue";
import Wallet from "@/Pages/Wallet.vue";
import Transactions from "@/Pages/Transactions.vue";
import Portfolio from "@/Pages/Portfolio.vue";
import NgxMarket from "@/Pages/NGXMarket.vue";
import GlobalMarket from "@/Pages/GlobalStocks.vue";
import CryptoMarket from "@/Pages/CryptoMarket.vue";
import FixedIncomeMarket from "@/Pages/FixedIncomeMarket.vue";
import Profile from "@/Pages/Profile/Index.vue";
import Settings from "@/Pages/Settings.vue";
import Reports from "@/Pages/Reports.vue";
import Support from "@/Pages/Support.vue";

// OMS
import Orders from "@/Pages/Orders.vue";
import OrderDetails from "@/Pages/OrderDetails.vue";

// Admin
import AdminUsers from "@/Pages/Admin/Users.vue";
import AdminKYCs from "@/Pages/Admin/Kyc.vue";
import AdminKycReview from "@/Pages/Admin/KycReview.vue";
import AdminTransactions from "@/Pages/Admin/Transactions.vue";
import AdminOrders from "@/Pages/Admin/Orders.vue";
import ControlPanel from "@/Pages/Admin/Control Panel/ControlPanel.vue";
import AdminActivityLog from "@/Pages/Admin/ActivityLog.vue";
import AdminReports from "@/Pages/Admin/Reports.vue";
import AdminNotifications from "@/Pages/Admin/Notifications.vue";
import AdminFxDashboard from "@/Pages/Admin/FxDashboard.vue";

const routes = [

  /* ----------------------------------------------
     PUBLIC ROUTES
  ------------------------------------------------*/
  { path: "/", redirect: "/login" },
  { path: "/login", name: "login", component: Login },
  { path: "/register", name: "register", component: Register },
  { path: "/forgot-password", name: "forgot-password", component: ForgotPassword },
  { path: "/reset-password", name: "reset-password", component: ResetPassword },
  { path: "/verify-email", name: "verify-email", component: VerifyEmail },
  /* ----------------------------------------------
     USER AUTH PAGES
  ------------------------------------------------*/
  {
    path: "/dashboard",
    name: "dashboard",
    component: Dashboard,
    meta: { requiresAuth: true },
  },

  {
    path: "/wallet",
    name: "wallet",
    component: Wallet,
    meta: { requiresAuth: true },
  },

  {
    path: "/transactions",
    name: "transactions",
    component: Transactions,
    meta: { requiresAuth: true },
  },

  {
    path: "/portfolio",
    name: "portfolio",
    component: Portfolio,
    meta: { requiresAuth: true },
  },

  {
    path: "/settings",
    name: "settings",
    component: Settings,
    meta: { requiresAuth: true },
  },
  {
    path: "/reports",
    name: "reports",
    component: Reports,
    meta: { requiresAuth: true },
  },
  {
    path: "/support",
    name: "support",
    component: Support,
    meta: { requiresAuth: true },
  },
  /* ----------------------------------------------
     MARKETS
  ------------------------------------------------*/
  {
    path: "/market/ngx/:symbol",
    name: "ngx-stock",
    component: () => import("@/Pages/Market/Stock.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/ngx",
    name: "ngx",
    component: NgxMarket,
    meta: { requiresAuth: true },
  },
  {
    path: "/global-stocks",
    name: "global-stocks",
    component: GlobalMarket,
    meta: { requiresAuth: true },
  },
  {
    path: "/crypto",
    name: "crypto",
    component: CryptoMarket,
    meta: { requiresAuth: true },
  },
  {
    path: "/fixed-income",
    name: "fixed-income",
    component: FixedIncomeMarket,
    meta: { requiresAuth: true },
  },

  /* ----------------------------------------------
     PROFILE (Details + KYC Tab)
  ------------------------------------------------*/
  {
    path: "/profile",
    name: "profile",
    component: Profile,
    meta: { requiresAuth: true },
  },



  /* ----------------------------------------------
     OMS
  ------------------------------------------------*/
  {
    path: "/orders",
    name: "orders",
    component: Orders,
    meta: { requiresAuth: true },
  },
  {
    path: "/orders/:id",
    name: "order-details",
    component: OrderDetails,
    meta: { requiresAuth: true },
  },

  /* ----------------------------------------------
    ADMIN PAGES
  ------------------------------------------------*/
  {
    path: "/admin",
    name: "admin-dashboard",
    component: () => import("@/Pages/Admin/Dashboard.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },

  {
    path: "/admin/users",
    name: "admin-users",
    component: AdminUsers,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/users/:id",
    name: "admin-user-detail",
    component: () => import("@/Pages/Admin/UserDetail.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/kyc",
    name: "admin-kyc",
    component: AdminKYCs,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/kyc-review/:id",
    name: "admin-kyc-review",
    component: AdminKycReview,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/transactions",
    name: "admin-transactions",
    component: AdminTransactions,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/orders",
    name: "admin-orders",
    component: AdminOrders,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: '/admin/orderbook',
    name: 'admin-orderbook',
    component: () => import('@/Pages/Admin/OrderBook.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: "/admin/control-panel",
    name: "admin-control-panel",
    component: ControlPanel,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/activity-log",
    name: "admin-activity-log",
    component: AdminActivityLog,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/reports",
    name: "admin-reports",
    component: AdminReports,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/notifications",
    name: "admin-notifications",
    component: AdminNotifications,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/fx-dashboard",
    name: "admin-fx-dashboard",
    component: AdminFxDashboard,
    meta: { requiresAuth: true, adminOnly: true },
  },

];

/* --------------------------------------------------
   ROUTER
----------------------------------------------------*/
const router = createRouter({
  history: createWebHistory(),
  routes,
});

/* --------------------------------------------------
   NAVIGATION GUARDS
----------------------------------------------------*/
router.beforeEach((to, from, next) => {

  const token = localStorage.getItem("xavier_token");

  let user = {};
  try {
    const stored = localStorage.getItem("user");
    user = stored ? JSON.parse(stored) : {};
  } catch {
    user = {};
  }

  // Require login
  if (to.meta.requiresAuth && !token) {
    return next("/login");
  }


  if (to.meta.adminOnly) {
    const staffRoles = ['admin', 'super-admin', 'staff', 'compliance', 'manager', 'support', 'accounts'];

    let hasStaff = false;
    if (user && typeof user.role === 'string' && staffRoles.includes(user.role)) {
      hasStaff = true;
    }

    if (!hasStaff && Array.isArray(user.roles)) {
      hasStaff = user.roles.some(r => {

        if (typeof r === 'string') return staffRoles.includes(r);
        if (r && r.name) return staffRoles.includes(r.name);
        return false;
      });
    }

    if (!hasStaff) return next('/dashboard');
  }

  next();
});

export default router;
