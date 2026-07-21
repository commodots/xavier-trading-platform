import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/api';

const INACTIVITY_LIMIT = 14 * 60 * 1000; // 14 minutes
const WARNING_DURATION = 60 * 1000; // 60 seconds warning
const TOTAL_TIMEOUT = 15 * 60 * 1000; // 15 minutes total

export function useIdleTimeout() {
  const router = useRouter();
  const showWarning = ref(false);
  const countdown = ref(60);
  const isExpired = ref(false);

  let inactivityTimer = null;
  let warningTimer = null;
  let countdownInterval = null;

  const resetTimers = () => {
    clearTimeout(inactivityTimer);
    clearTimeout(warningTimer);
    clearInterval(countdownInterval);

    if (isExpired.value) return;

    inactivityTimer = setTimeout(showWarningModal, INACTIVITY_LIMIT);
  };

  const showWarningModal = () => {
    showWarning.value = true;
    countdown.value = 60;

    countdownInterval = setInterval(() => {
      countdown.value--;
      if (countdown.value <= 0) {
        clearInterval(countdownInterval);
        executeLogout();
      }
    }, 1000);

    warningTimer = setTimeout(executeLogout, WARNING_DURATION);
  };

  const stayLoggedIn = () => {
    showWarning.value = false;
    isExpired.value = false;
    clearTimeout(warningTimer);
    clearInterval(countdownInterval);
    resetTimers();
  };

  const executeLogout = async () => {
    isExpired.value = true;
    showWarning.value = false;
    clearTimeout(warningTimer);
    clearInterval(countdownInterval);

    try {
      await api.post('/logout');
    } catch (e) {
      console.error('Backend logout failed, clearing local state anyway.');
    } finally {
      localStorage.removeItem('xavier_token');
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      localStorage.removeItem('active_view');
      router.push('/');
    }
  };

  const activityEvents = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'];

  const handleActivity = () => {
    if (!showWarning.value && !isExpired.value) {
      resetTimers();
    }
  };

  onMounted(() => {
    resetTimers();
    activityEvents.forEach(event => {
      window.addEventListener(event, handleActivity);
    });
  });

  onUnmounted(() => {
    clearTimeout(inactivityTimer);
    clearTimeout(warningTimer);
    clearInterval(countdownInterval);
    activityEvents.forEach(event => {
      window.removeEventListener(event, handleActivity);
    });
  });

  return {
    showWarning,
    countdown,
    stayLoggedIn,
    executeLogout,
  };
}