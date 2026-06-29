o<template>
  <MainLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold">Reports & Statements</h1>
        <p class="text-sm text-gray-400">Generate and download your transaction or trading history.</p>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-1">
          <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
            <h2 class="mb-4 text-lg font-medium text-white">Generate New Report</h2>
            
            <div class="space-y-4">
              <div>
                <label class="text-xs tracking-wider text-gray-400 uppercase">Report Type</label>
                <select v-model="form.type" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                  <option value="statement">Statement of Account</option>
                  <option value="trading">Trading Performance Report</option>
                </select>
              </div>

              <div>
                <label class="text-xs tracking-wider text-gray-400 uppercase">Wallet / Account</label>
                <select v-model="form.wallet" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                  <option value="all">All Wallets</option>
                  <option value="NGN">NGN Wallet</option>
                  <option value="USD">USD Wallet</option>
                </select>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="text-xs tracking-wider text-gray-400 uppercase">From</label>
                  <input type="date" v-model="form.start_date" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm" />
                </div>
                <div>
                  <label class="text-xs tracking-wider text-gray-400 uppercase">To</label>
                  <input type="date" v-model="form.end_date" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm" />
                </div>
              </div>

              <div>
                <label class="text-xs tracking-wider text-gray-400 uppercase">Export Format</label>
                <div class="flex gap-4 mt-2">
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="form.format" value="pdf" class="text-blue-500" />
                    <span class="text-sm text-white">PDF</span>
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="form.format" value="excel" class="text-blue-500" />
                    <span class="text-sm text-white">Excel</span>
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="form.format" value="csv" class="text-blue-500" />
                    <span class="text-sm text-white">CSV</span>
                  </label>
                </div>
              </div>

              <button 
                @click="generateReport"
                :disabled="loading"
                class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] py-3 rounded-lg font-bold text-white mt-4 hover:opacity-90 disabled:opacity-50 transition"
              >
                {{ loading ? 'Generating...' : 'Generate Report' }}
              </button>
            </div>
          </div>

          <div class="p-4 border bg-blue-900/10 border-blue-500/20 rounded-xl">
            <h4 class="flex items-center gap-2 text-sm font-bold text-blue-400">
              <span>&#x24D8;</span> Note
            </h4>
            <p class="mt-1 text-xs leading-relaxed text-gray-400">
              Reports may take a few moments to compile. You will receive an email once your report is ready for download if it takes longer than 30 seconds.
            </p>
          </div>
        </div>

        <div class="lg:col-span-2">
          <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl overflow-hidden">
            <div class="p-6 border-b border-[#1f3348]">
              <h2 class="text-lg font-medium text-white">Recent Downloads</h2>
            </div>
            
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="text-xs text-gray-400 bg-black/20">
                  <tr>
                    <th class="px-6 py-4 text-left">Report Name</th>
                    <th class="px-6 py-4 text-left">Period</th>
                    <th class="px-6 py-4 text-center">Format</th>
                    <th class="px-6 py-4 text-right">Action</th>
                  </tr>
                </thead>

                <!-- SKELETON LOADER STATE -->
                <tbody v-if="loading" class="divide-y divide-[#1f3348]/40">
                  <tr v-for="i in 4" :key="i">
                    <td class="px-6 py-5">
                      <SkeletonLoader class="h-4 mb-2 w-44 bg-gray-700/60" />
                      <SkeletonLoader class="h-3 bg-gray-800 w-28" />
                    </td>
                    <td class="px-6 py-5">
                      <SkeletonLoader class="h-4 w-36 bg-gray-700/50" />
                    </td>
                    <td class="flex items-center justify-center px-6 py-5 pt-6">
                      <SkeletonLoader class="w-10 h-4 bg-gray-800 rounded-md" />
                    </td>
                    <td class="px-6 py-5 text-right">
                      <SkeletonLoader class="inline-block w-16 h-4 bg-gray-800" />
                    </td>
                  </tr>
                </tbody>

                <!-- DATA RENDER STATE -->
                <tbody v-else class="divide-y divide-[#1f3348]">
                  <tr v-for="report in reportHistory" :key="report.id" class="hover:bg-[#16213A] transition">
                    <td class="px-6 py-4">
                      <div class="font-medium text-white">{{ report.name }}</div>
                      <div class="text-[10px] text-gray-500">{{ report.created_at }}</div>
                    </td>
                    <td class="px-6 py-4 text-gray-400">{{ report.period }}</td>
                    <td class="px-6 py-4 text-center">
                      <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-gray-800 border border-gray-600">
                        {{ report.format }}
                      </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                      <button class="font-medium text-blue-400 hover:text-blue-300">Download</button>
                    </td>
                  </tr>
                  <tr v-if="reportHistory.length === 0">
                    <td colspan="4" class="px-6 py-10 italic text-center text-gray-500">No reports generated yet.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <SuccessModal :show="showSuccessModal" :message="successMessage" @close="showSuccessModal = false" />
    <ErrorModal :show="showErrorModal" :message="errorMessage" @close="showErrorModal = false" />
    <WarningModal :show="showWarningModal" :message="warningMessage" @close="showWarningModal = false" />
    
    <!-- Report Preview Modal -->
    <div v-if="showReportModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl max-w-5xl w-full max-h-[90vh] flex flex-col shadow-2xl">
        <!-- Preview Header -->
        <div class="bg-[#0F1724] text-white p-6 rounded-t-xl">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-xl font-bold text-white mb-1">XAVIER TRADING PLATFORM</h3>
              <p class="text-sm text-gray-300">Account Statement Preview - {{ form.format.toUpperCase() }} Format</p>
            </div>
            <button @click="closeReportModal" class="text-gray-400 hover:text-white">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
        </div>

        <div class="p-6 flex-1 overflow-auto bg-gray-50">
          <div v-if="reportData && reportData.ledger && reportData.ledger.length > 0" class="space-y-4">
            <!-- Action Bar -->
            <div class="flex justify-between items-center bg-white border border-gray-200 rounded-lg p-4">
              <p class="text-sm text-gray-600">
                <span class="font-semibold">Format:</span> {{ form.format.toUpperCase() }} | 
                <span class="font-semibold">Period:</span> {{ form.start_date }} to {{ form.end_date }} | 
                <span class="font-semibold">Transactions:</span> {{ reportData.ledger.length }}
              </p>
              <button @click="downloadReport" class="px-4 py-2 bg-gradient-to-r from-[#0047AB] to-[#00D4FF] text-white rounded-lg text-sm font-medium hover:opacity-90">
                Download {{ form.format.toUpperCase() }}
              </button>
            </div>

            <!-- PDF Preview -->
            <div v-if="form.format === 'pdf'" class="bg-white border border-gray-300 rounded-lg overflow-hidden shadow-sm">
              <div class="border-b-2 border-[#0047AB] p-6 text-center bg-gray-50">
                <img src="/images/xavier-logo.png" alt="Xavier Logo" class="h-16 mx-auto mb-3" />
                <h2 class="text-2xl font-bold text-[#0047AB] mb-1">XAVIER TRADING PLATFORM</h2>
                <p class="text-sm text-gray-600">Account Statement</p>
              </div>
              
              <div class="p-4 bg-gray-100 border-b border-gray-300">
                <div class="max-w-2xl mx-auto space-y-1 text-sm">
                  <div class="flex">
                    <span class="font-semibold text-gray-700 w-40">Period:</span>
                    <span class="text-gray-900">{{ form.start_date }} to {{ form.end_date }}</span>
                  </div>
                  <div class="flex">
                    <span class="font-semibold text-gray-700 w-40">Generated On:</span>
                    <span class="text-gray-900">{{ new Date().toISOString().split('T')[0] }}</span>
                  </div>
                  <div v-if="reportData.current_balances" class="flex">
                    <span class="font-semibold text-gray-700 w-40">Current Balances:</span>
                    <span class="text-gray-900">
                      <span v-for="(balance, currency) in reportData.current_balances" :key="currency">
                        <strong>{{ currency }}:</strong> {{ formatCurrency(balance, currency) }}&nbsp;&nbsp;
                      </span>
                    </span>
                  </div>
                  <div class="flex">
                    <span class="font-semibold text-gray-700 w-40">Total Transactions:</span>
                    <span class="text-gray-900">{{ reportData.ledger ? reportData.ledger.length : 0 }}</span>
                  </div>
                </div>
              </div>

              <table class="w-full text-sm">
                <thead class="bg-[#0047AB] text-white">
                  <tr>
                    <th class="px-4 py-3 text-left font-semibold">Date/Time</th>
                    <th class="px-4 py-3 text-left font-semibold">Reference</th>
                    <th class="px-4 py-3 text-left font-semibold">Transaction Type</th>
                    <th class="px-4 py-3 text-left font-semibold">Wallet / Currency</th>
                    <th class="px-4 py-3 text-right font-semibold">Amount</th>
                    <th class="px-4 py-3 text-right font-semibold">Bal Before</th>
                    <th class="px-4 py-3 text-right font-semibold">Bal After</th>
                    <th class="px-4 py-3 text-center font-semibold">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  <tr v-for="item in reportData.ledger" :key="item.transaction.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-700">{{ formatDate(item.transaction.created_at) }}</td>
                    <td class="px-4 py-3 text-gray-700 font-mono text-xs">{{ item.transaction.reference || 'N/A' }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ formatTransactionType(item.transaction.type, item.trade_direction) }}</td>
                    <td class="px-4 py-3 text-gray-700 font-semibold">{{ item.transaction.asset || 'N/A' }}</td>
                    <td class="px-4 py-3 text-right text-gray-900 font-semibold">{{ formatCurrency(item.transaction.amount, item.transaction.asset) }}</td>
                    <td class="px-4 py-3 text-right text-gray-700">{{ formatCurrency(item.balance_before, item.transaction.asset) }}</td>
                    <td class="px-4 py-3 text-right text-gray-700">{{ formatCurrency(item.balance_after, item.transaction.asset) }}</td>
                    <td class="px-4 py-3 text-center">
                      <span :class="{
                        'text-green-700 bg-green-100': item.transaction.status === 'completed',
                        'text-yellow-700 bg-yellow-100': item.transaction.status === 'pending',
                        'text-red-700 bg-red-100': item.transaction.status === 'failed'
                      }" class="px-2 py-1 rounded text-xs font-medium">
                        {{ item.transaction.status || 'Pending' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>

              <div class="mt-6 pt-4 border-t border-gray-300 text-center text-xs text-gray-600">
                <p>This is a system-generated statement. For inquiries, contact support@xavier.com</p>
                <p class="mt-1">© {{ new Date().getFullYear() }} Xavier Trading Platform. All rights reserved.</p>
              </div>
            </div>

            <!-- Excel Preview -->
            <div v-else-if="form.format === 'excel'" class="bg-white border border-gray-300 rounded-lg overflow-hidden shadow-sm">
              <div class="bg-gray-100 p-4 border-b border-gray-300">
                <div class="text-center space-y-1">
                  <img src="/images/xavier-logo.png" alt="Xavier Logo" class="h-12 mx-auto mb-2" />
                  <h2 class="text-xl font-bold text-gray-800">XAVIER TRADING PLATFORM</h2>
                  <p class="text-sm text-gray-600">Account Statement</p>
                </div>
                <div class="mt-3 max-w-3xl mx-auto space-y-1 text-sm">
                  <div class="flex">
                    <span class="font-semibold text-gray-700 w-40">Period:</span>
                    <span class="text-gray-900">{{ form.start_date }} to {{ form.end_date }}</span>
                  </div>
                  <div class="flex">
                    <span class="font-semibold text-gray-700 w-40">Generated On:</span>
                    <span class="text-gray-900">{{ new Date().toISOString().split('T')[0] }}</span>
                  </div>
                  <div v-if="reportData.current_balances" class="flex">
                    <span class="font-semibold text-gray-700 w-40">Current Balances:</span>
                    <span class="text-gray-900">
                      <span v-for="(balance, currency) in reportData.current_balances" :key="currency">
                        <strong>{{ currency }}:</strong> {{ formatCurrency(balance, currency) }}&nbsp;&nbsp;
                      </span>
                    </span>
                  </div>
                  <div class="flex">
                    <span class="font-semibold text-gray-700 w-40">Total Transactions:</span>
                    <span class="text-gray-900">{{ reportData.ledger ? reportData.ledger.length : 0 }}</span>
                  </div>
                </div>
              </div>

              <table class="w-full text-sm border-collapse">
                <thead>
                  <tr class="bg-[#0047AB] text-white">
                    <th class="px-4 py-3 text-left font-semibold border border-gray-300">Date/Time</th>
                    <th class="px-4 py-3 text-left font-semibold border border-gray-300">Reference</th>
                    <th class="px-4 py-3 text-left font-semibold border border-gray-300">Transaction Type</th>
                    <th class="px-4 py-3 text-left font-semibold border border-gray-300">Wallet / Currency</th>
                    <th class="px-4 py-3 text-right font-semibold border border-gray-300">Amount</th>
                    <th class="px-4 py-3 text-right font-semibold border border-gray-300">Balance Before</th>
                    <th class="px-4 py-3 text-right font-semibold border border-gray-300">Balance After</th>
                    <th class="px-4 py-3 text-center font-semibold border border-gray-300">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-300">
                  <tr v-for="item in reportData.ledger" :key="item.transaction.id" class="hover:bg-gray-50">
                    <td class="px-4 py-2 border border-gray-300 text-gray-700">{{ formatDate(item.transaction.created_at) }}</td>
                    <td class="px-4 py-2 border border-gray-300 text-gray-700 font-mono text-xs">{{ item.transaction.reference || 'N/A' }}</td>
                    <td class="px-4 py-2 border border-gray-300 text-gray-700">{{ formatTransactionType(item.transaction.type, item.trade_direction) }}</td>
                    <td class="px-4 py-2 border border-gray-300 text-gray-700 font-semibold">{{ item.transaction.asset || 'N/A' }}</td>
                    <td class="px-4 py-2 border border-gray-300 text-right text-gray-900">{{ formatCurrency(item.transaction.amount, item.transaction.asset) }}</td>
                    <td class="px-4 py-2 border border-gray-300 text-right text-gray-700">{{ formatCurrency(item.balance_before, item.transaction.asset) }}</td>
                    <td class="px-4 py-2 border border-gray-300 text-right text-gray-700">{{ formatCurrency(item.balance_after, item.transaction.asset) }}</td>
                    <td class="px-4 py-2 border border-gray-300 text-center">
                      <span :class="{
                        'text-green-700': item.transaction.status === 'completed',
                        'text-yellow-700': item.transaction.status === 'pending',
                        'text-red-700': item.transaction.status === 'failed'
                      }" class="font-medium">
                        {{ item.transaction.status || 'Pending' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>

              <div class="mt-4 pt-3 border-t border-gray-300 text-center text-xs text-gray-600">
                <p>This is a system-generated statement. For inquiries, contact support@xavier.com</p>
                <p class="mt-1">© {{ new Date().getFullYear() }} Xavier Trading Platform. All rights reserved.</p>
              </div>
            </div>

            <!-- CSV Preview (same as Excel but in monospace) -->
            <div v-else class="bg-white border border-gray-300 rounded-lg overflow-hidden shadow-sm">
              <div class="bg-gray-100 p-4 border-b border-gray-300">
                <div class="text-center space-y-1">
                  <h2 class="text-xl font-bold text-gray-800">XAVIER TRADING PLATFORM</h2>
                  <p class="text-sm text-gray-600">Account Statement (CSV Format)</p>
                </div>
                <div class="mt-3 max-w-3xl mx-auto space-y-1 text-sm">
                  <div class="flex">
                    <span class="font-semibold text-gray-700 w-40">Period:</span>
                    <span class="text-gray-900">{{ form.start_date }} to {{ form.end_date }}</span>
                  </div>
                  <div class="flex">
                    <span class="font-semibold text-gray-700 w-40">Generated On:</span>
                    <span class="text-gray-900">{{ new Date().toISOString().split('T')[0] }}</span>
                  </div>
                  <div class="flex">
                    <span class="font-semibold text-gray-700 w-40">Total Transactions:</span>
                    <span class="text-gray-900">{{ reportData.ledger ? reportData.ledger.length : 0 }}</span>
                  </div>
                </div>
              </div>

              <table class="w-full text-sm border-collapse">
                <thead>
                  <tr class="bg-[#0047AB] text-white">
                    <th class="px-4 py-3 text-left font-semibold border border-gray-300">Date/Time</th>
                    <th class="px-4 py-3 text-left font-semibold border border-gray-300">Reference</th>
                    <th class="px-4 py-3 text-left font-semibold border border-gray-300">Transaction Type</th>
                    <th class="px-4 py-3 text-left font-semibold border border-gray-300">Wallet / Currency</th>
                    <th class="px-4 py-3 text-right font-semibold border border-gray-300">Amount</th>
                    <th class="px-4 py-3 text-right font-semibold border border-gray-300">Balance Before</th>
                    <th class="px-4 py-3 text-right font-semibold border border-gray-300">Balance After</th>
                    <th class="px-4 py-3 text-center font-semibold border border-gray-300">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-300">
                  <tr v-for="item in reportData.ledger" :key="item.transaction.id" class="hover:bg-gray-50">
                    <td class="px-4 py-2 border border-gray-300 text-gray-700">{{ formatDate(item.transaction.created_at) }}</td>
                    <td class="px-4 py-2 border border-gray-300 text-gray-700 font-mono text-xs">{{ item.transaction.reference || 'N/A' }}</td>
                    <td class="px-4 py-2 border border-gray-300 text-gray-700">{{ formatTransactionType(item.transaction.type, item.trade_direction) }}</td>
                    <td class="px-4 py-2 border border-gray-300 text-gray-700 font-semibold">{{ item.transaction.asset || 'N/A' }}</td>
                    <td class="px-4 py-2 border border-gray-300 text-right text-gray-900">{{ formatCurrency(item.transaction.amount, item.transaction.asset) }}</td>
                    <td class="px-4 py-2 border border-gray-300 text-right text-gray-700">{{ formatCurrency(item.balance_before, item.transaction.asset) }}</td>
                    <td class="px-4 py-2 border border-gray-300 text-right text-gray-700">{{ formatCurrency(item.balance_after, item.transaction.asset) }}</td>
                    <td class="px-4 py-2 border border-gray-300 text-center">
                      <span :class="{
                        'text-green-700': item.transaction.status === 'completed',
                        'text-yellow-700': item.transaction.status === 'pending',
                        'text-red-700': item.transaction.status === 'failed'
                      }" class="font-medium">
                        {{ item.transaction.status || 'Pending' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>

              <div class="mt-4 pt-3 border-t border-gray-300 text-center text-xs text-gray-600">
                <p>This is a system-generated statement. For inquiries, contact support@xavier.com</p>
                <p class="mt-1">© {{ new Date().getFullYear() }} Xavier Trading Platform. All rights reserved.</p>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-10 text-gray-500">
            No transactions found for the selected period.
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

  <script setup>
import { ref, reactive, onMounted } from 'vue';
import MainLayout from "@/Layouts/MainLayout.vue";
import SuccessModal from "@/Components/SuccessModal.vue";
import ErrorModal from "@/Components/ErrorModal.vue";
import WarningModal from "@/Components/WarningModal.vue";
import api from "@/api";
import SkeletonLoader from "@/Components/SkeletonLoader.vue";
import axios from 'axios';

const loading = ref(false);

// Stock tickers that represent USD trades
const stockTickers = ['AAPL', 'TSLA', 'GOOGL', 'MSFT', 'AMZN', 'META', 'NVDA', 'GOOG', 'NFLX', 'INTC'];

// Currency formatting helper
const formatCurrency = (amount, currency = 'USD') => {
  const num = Math.abs(Number(amount) || 0); // Remove minus sign
  const formatted = num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  
 
  if (stockTickers.includes(currency) || currency === 'BTC') {
    currency = 'USD';
  }
  
  const symbols = {
    'USD': '$',
    'NGN': '₦',
    'EUR': '€',
    'GBP': '£'
  };
  
  const symbol = symbols[currency] || currency + ' ';
  return `${symbol}${formatted}`;
};

// Transaction type formatting helper
const formatTransactionType = (type, tradeDirection = null) => {
  if (!type) return 'N/A';
  
  // Capitalize first letter only
  const formatted = type.charAt(0).toUpperCase() + type.slice(1).toLowerCase();
  
  // For trades, use trade_direction from backend
  if (formatted.toLowerCase() === 'trade' && tradeDirection) {
    return `${formatted} (${tradeDirection.charAt(0).toUpperCase() + tradeDirection.slice(1).toLowerCase()})`;
  }
  
  return formatted;
};

// Date formatting helper
const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  
  try {
    const date = new Date(dateString);
    // Format: MM/DD HH:MM AM/PM (shorter for PDF space)
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = date.getHours();
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    const formattedHours = hours % 12 || 12;
    
    return `${month}/${day} ${formattedHours}:${minutes} ${ampm}`;
  } catch (e) {
    return dateString;
  }
};
const reportHistory = ref([]); 
const showSuccessModal = ref(false);
const showErrorModal = ref(false);
const showWarningModal = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const warningMessage = ref('');
const showReportModal = ref(false);
const reportData = ref([]);

const form = reactive({
  type: 'statement',
  wallet: 'all',
  start_date: '',
  end_date: '',
  format: 'pdf'
});

  const generateReport = async () => {
  if (!form.start_date || !form.end_date) {
    warningMessage.value = "Please select a date range";
    showWarningModal.value = true;
    return;
  }

  loading.value = true;
  try {
    const params = {
      from: form.start_date,
      to: form.end_date,
      wallet: form.wallet,
      format: 'json', 
    };

    const response = await api.get('/reports/account-statement', { params });
    reportData.value = response.data;
    showReportModal.value = true;
  } catch (e) {
    console.error(e);
    if (e.response) {
      const msg = e.response.data?.message || e.response.statusText || "Server error";
      errorMessage.value = "Error generating report: " + msg;
    } else if (e.request) {
      errorMessage.value = "Network error: Unable to connect to the server. Please check your internet connection and try again.";
    } else {
      errorMessage.value = "Error generating report: " + e.message;
    }
    showErrorModal.value = true;
  } finally {
    loading.value = false;
  }
};

const closeReportModal = () => {
  showReportModal.value = false;
  reportData.value = [];
};

  const downloadReport = async () => {
  loading.value = true;
  try {
    const params = {
      from: form.start_date,
      to: form.end_date,
      wallet: form.wallet,
      type: form.type,
      format: form.format,
    };

    const response = await api.get('/reports/account-statement', { 
      params: params,
      responseType: 'blob',
      timeout: 60000 // 60 second timeout
    });
    
    // Create a blob from the response
    const blob = new Blob([response.data], { 
      type: form.format === 'pdf' ? 'application/pdf' : 
            form.format === 'excel' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' :
            'text/csv'
    });
    
    // Close the preview modal first
    closeReportModal();
    
    // Small delay to let the modal close before triggering download
    setTimeout(() => {
      // Create a download link and trigger it
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `account-statement-${form.start_date}-to-${form.end_date}.${form.format === 'excel' ? 'xlsx' : form.format}`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
      
      // Show success modal after download is triggered
      successMessage.value = "Report downloaded successfully";
      showSuccessModal.value = true;
    }, 100);
  } catch (downloadError) {
    console.error('Download error:', downloadError);
    if (downloadError.code === 'ERR_NETWORK' || downloadError.message?.includes('Network Error')) {
      errorMessage.value = "Network error: Unable to connect to the server. Please check your internet connection and try again.";
    } else if (downloadError.response) {
      const msg = downloadError.response.data?.message || downloadError.response.statusText || "Server error";
      errorMessage.value = "Download failed: " + msg;
    } else if (downloadError.code === 'ECONNABORTED') {
      errorMessage.value = "Request timed out. The server is taking too long to respond. Please try again.";
    } else {
      errorMessage.value = "Error downloading report. Please try again.";
    }
    showErrorModal.value = true;
  } finally {
    if (loading.value) {
      loading.value = false;
    }
  }
};

const loadReportHistory = async () => {
  try {
    const response = await api.get('/reports/history');
    reportHistory.value = response.data.reports || [];
  } catch (e) {
    console.error("Failed to load report history:", e);
  }
};

onMounted(() => {
  loadReportHistory();
});
</script>
