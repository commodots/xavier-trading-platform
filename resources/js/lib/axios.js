// resources/js/lib/axios.js

import axios from "axios";

// Backend API base URL.

const API_URL = import.meta.env.VITE_API_BASE || "/api";

// Create axios instance
const api = axios.create({
  baseURL: API_URL,
  withCredentials: true,     // Required for Sanctum cookies
  timeout: 15000,
});

// REQUEST INTERCEPTOR
api.interceptors.request.use(
  (config) => {
    // Fetch token from localStorage
    const token = localStorage.getItem("token") || localStorage.getItem("xavier_token");

    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    config.headers.Accept = "application/json";

    return config;
  },
  (error) => Promise.reject(error)
);

// RESPONSE INTERCEPTOR
api.interceptors.response.use(
  (response) => response,

  async (error) => {
    // If unauthorized, force logout on frontend
    if (error.response && error.response.status === 401) {
      console.warn("Unauthorized: Removing auth state and redirecting to the landing page...");

      localStorage.removeItem("token");
      localStorage.removeItem("xavier_token");
      localStorage.removeItem("user");
      localStorage.removeItem("active_view");

      window.location.href = "/";
    }

    return Promise.reject(error);
  }
);

export default api;
