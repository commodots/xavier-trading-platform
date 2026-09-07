import { formatCurrency, formatDate, formatDateTime } from '@/lib/formatters'

export const fixedIncomeCurrency = (value, currency = 'NGN') =>
  value === null || value === undefined || value === ''
    ? '-'
    : formatCurrency(value, currency)

export const fixedIncomeNumber = (value, decimals = 2) => {
  const number = Number(value)

  if (!Number.isFinite(number)) {
    return '-'
  }

  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(number)
}

export const fixedIncomePercent = (value, decimals = 2) => {
  const number = Number(value)

  if (!Number.isFinite(number)) {
    return '-'
  }

  return `${fixedIncomeNumber(number, decimals)}%`
}

export const fixedIncomeDate = (value) => formatDate(value)
export const fixedIncomeDateTime = (value) => formatDateTime(value)

export const fixedIncomeLabel = (value) => {
  if (!value) {
    return '-'
  }

  return String(value)
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (character) => character.toUpperCase())
}
