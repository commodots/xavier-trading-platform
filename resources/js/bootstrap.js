import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

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

/*
 |---------------------------------------------------------
 | Echo Setup
 |---------------------------------------------------------
 | Echo will be used for real-time broadcasting with Pusher.
 */

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
    forceTLS: true,
});
