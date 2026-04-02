import axios from "axios";

const API_BASE = import.meta.env.VITE_API_BASE || "https://app.myxavier.com.ng/api";

const api = axios.create({
  baseURL: API_BASE,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
  withCredentials: true,
});

// attach token to all requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem("xavier_token");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  } else {
    delete config.headers.Authorization;
  }

  const userStr = localStorage.getItem("user");
  if (userStr) {
    try {
      const user = JSON.parse(userStr);
      const mode = user.trading_mode || 'live';
      
      config.params = {
        ...config.params,
        mode: mode
      };
    } catch (e) {
      console.error("API Interceptor: Failed to parse user for mode injection", e);
    }
  }
  return config;
}, (error) => {
  return Promise.reject(error);
});

export default api;
