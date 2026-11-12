import { ref, onMounted, onUnmounted } from 'vue';
import { logoutImmediately } from '@/auth/logout';

const INACTIVITY_LIMIT = 14 * 60 * 1000; // 14 minutes
const WARNING_DURATION = 60 * 1000; // 60 seconds warning
const TOTAL_TIMEOUT = 15 * 60 * 1000; // 15 minutes total

export function useIdleTimeout() {
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

  const executeLogout = () => {
    isExpired.value = true;
    showWarning.value = false;
    clearTimeout(warningTimer);
    clearInterval(countdownInterval);
    logoutImmediately();
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