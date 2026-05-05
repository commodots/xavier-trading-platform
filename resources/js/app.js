import "./bootstrap";
import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import "./style.css"; // Tailwind
import axios from "axios";
import VueApexCharts from "vue3-apexcharts";


const app = createApp(App);

app.config.globalProperties.$axios = axios;
axios.defaults.baseURL = "/api";
const token = localStorage.getItem("xavier_token");

if (token) {
    axios.defaults.headers.common["Authorization"] = `Bearer ${token}`;
}

// Fetch CSRF cookie once on app load using the current host.
(async () => {
    try {
        await axios.get('/sanctum/csrf-cookie', { baseURL: '/', withCredentials: true });
    } catch (e) {
        console.error('Failed to fetch CSRF cookie', e);
    }

    app.component('apexchart', VueApexCharts);
    app.use(router);
    app.mount("#app");
})();

