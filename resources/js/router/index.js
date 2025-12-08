// resources/js/router/index.js
import { createRouter, createWebHistory } from "vue-router";

// Auth
import Login from "@/Pages/Auth/Login.vue";
import Register from "@/Pages/Auth/Register.vue";

// Main User Pages
import Dashboard from "@/Pages/Dashboard.vue";
import Wallet from "@/Pages/Wallet.vue";
import Transactions from "@/Pages/Transactions.vue";
import Portfolio from "@/Pages/Portfolio.vue";
import NgxMarket from "@/Pages/NGXMarket.vue";
import GlobalMarket from "@/Pages/GlobalStocks.vue";
import CryptoMarket from "@/Pages/CryptoMarket.vue";
import Profile from "@/Pages/Profile/Index.vue";

// OMS
import Orders from "@/Pages/Orders.vue";
import OrderDetails from "@/Pages/OrderDetails.vue";

// Admin
import AdminUsers from "@/Pages/Admin/Users.vue";
import AdminKYCs from "@/Pages/Admin/Kyc.vue";
import AdminKycReview from "@/Pages/Admin/KycReview.vue";
import AdminTransactions from "@/Pages/Admin/Transactions.vue";

const routes = [

  /* ----------------------------------------------
     PUBLIC ROUTES
  ------------------------------------------------*/
  { path: "/", redirect: "/login" },
  { path: "/login", name: "login", component: Login },
  { path: "/register", name: "register", component: Register },

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

  /* ----------------------------------------------
     MARKETS
  ------------------------------------------------*/
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
	  path: '/admin/orderbook',
	  name: 'admin-orderbook',
	  component: () => import('@/Pages/Admin/OrderBook.vue'),
	  meta: { requiresAuth: true }
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

  // Admin only
  if (to.meta.adminOnly && user.role !== "admin") {
    return next("/dashboard");
  }

  next();
});

export default router;
