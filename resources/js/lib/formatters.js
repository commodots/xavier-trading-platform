/**
 * Format a number as currency
 * @param {number} amount - The amount to format
 * @param {string} currency - Currency code (default: USD)
 * @returns {string} Formatted currency string
 */
export const formatCurrency = (amount, currency = 'USD') => {
  if (!amount && amount !== 0) return '0.00';
  const value = parseFloat(amount) || 0;
  
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
};

/**
 * Format a number as percentage
 * @param {number} value - The value to format
 * @param {number} decimals - Number of decimal places (default: 2)
 * @returns {string} Formatted percentage string
 */
export const formatPercentage = (value, decimals = 2) => {
  if (!value && value !== 0) return '0.00%';
  const num = parseFloat(value) || 0;
  return `${num >= 0 ? '+' : ''}${num.toFixed(decimals)}%`;
};

/**
 * Format a large number with K, M, B suffix
 * @param {number} num - The number to format
 * @returns {string} Formatted number
 */
export const formatCompactNumber = (num) => {
  if (!num && num !== 0) return '0';
  const value = parseFloat(num) || 0;
  
  if (value >= 1e9) return (value / 1e9).toFixed(2) + 'B';
  if (value >= 1e6) return (value / 1e6).toFixed(2) + 'M';
  if (value >= 1e3) return (value / 1e3).toFixed(2) + 'K';
  
  return value.toFixed(2);
};

/**
 * Format a date to a readable format
 * @param {string|Date} date - The date to format
 * @returns {string} Formatted date
 */
export const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

/**
 * Format a date and time
 * @param {string|Date} date - The date to format
 * @returns {string} Formatted date and time
 */
export const formatDateTime = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

/**
 * Get profit/loss color class
 * @param {number} value - The P&L value
 * @returns {string} Tailwind color class
 */
export const getPnLColorClass = (value) => {
  const num = parseFloat(value) || 0;
  return num >= 0 ? 'text-green-400' : 'text-red-400';
};

/**
 * Get profit/loss background color class
 * @param {number} value - The P&L value
 * @returns {string} Tailwind background color class
 */
export const getPnLBgColorClass = (value) => {
  const num = parseFloat(value) || 0;
  return num >= 0 ? 'bg-green-600/20' : 'bg-red-600/20';
};

/**
 * Format a decimal number to specific places
 * @param {number} value - The value to format
 * @param {number} decimals - Number of decimal places
 * @returns {string} Formatted number
 */
export const formatDecimals = (value, decimals = 2) => {
  if (!value && value !== 0) return '0';
  const num = parseFloat(value) || 0;
  return num.toFixed(decimals);
};
