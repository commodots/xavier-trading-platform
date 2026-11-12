export function logoutImmediately() {
  const token = localStorage.getItem('xavier_token') || localStorage.getItem('token');

  if (token) {
    const apiBase = (import.meta.env.VITE_API_BASE || '/api').replace(/\/+$/, '');
    const logoutUrl = new URL(`${apiBase}/logout`, window.location.origin);

    fetch(logoutUrl, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      keepalive: true,
    }).catch((error) => {
      console.warn('Backend logout request failed after local logout.', error);
    });
  }

  localStorage.removeItem('xavier_token');
  localStorage.removeItem('token');
  localStorage.removeItem('user');
  localStorage.removeItem('active_view');
  sessionStorage.clear();
  window.location.replace('/login');
}
