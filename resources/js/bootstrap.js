import axios from 'axios';

/*
 |---------------------------------------------------------
 | Axios Setup
 |---------------------------------------------------------
 | Axios will be used to send HTTP requests. This ensures
 | the CSRF token and headers are configured correctly.
 */

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  window.axios.defaults.withCredentials = true; // CRITICAL for Sanctum
window.axios.defaults.withXSRFToken = true;    // CRITICAL for Laravel 10/11
