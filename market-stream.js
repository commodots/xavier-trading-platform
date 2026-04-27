import 'dotenv/config';
import WebSocket from 'ws';
import axios from 'axios';
import Redis from 'ioredis';

const token = process.env.FINNHUB_API_KEY;
const apiUrl = process.env.APP_URL ? process.env.APP_URL.replace(/\/$/, '') : 'http://127.0.0.1:8000';

if (!token) {
    console.error('Missing FINNHUB_API_KEY in environment variables.');
    process.exit(1);
}

const trackedSymbols = new Set();

const redisConfig = {
  host: '127.0.0.1', 
  port: 6379,
  family: 4 // Force IPv4 to talk to Memurai
};

const redis = new Redis(redisConfig); 
const redisSub = new Redis(redisConfig); 

const ws = new WebSocket(`wss://ws.finnhub.io?token=${token}`);

ws.on('open', async () => {
    console.log('Finnhub WebSocket connected.');

    try {
        const activeTickers = await redis.hgetall('active_tickers');
        const symbolsToSubscribe = Object.keys(activeTickers);

        if (symbolsToSubscribe.length > 0) {
            
            console.log('Syncing active subscriptions from Memurai:', symbolsToSubscribe.join(', '));
            symbolsToSubscribe.forEach((symbol) => {
                ws.send(JSON.stringify({ type: 'subscribe', symbol }));
                trackedSymbols.add(symbol);
            });
        } else {
            console.log('No active tickers in Memurai. Waiting for Laravel updates...');
            // Subscribe to a default to keep connection warm
            ws.send(JSON.stringify({ type: 'subscribe', symbol: 'AAPL' }));
            trackedSymbols.add('AAPL');
            console.log('Subscribed to default AAPL. Current tracked symbols:', Array.from(trackedSymbols).join(', '));
        }
    } catch (err) {
        console.error('Error syncing from Memurai on startup:', err.message);
    }
});

// Helper to ensure we are watching what Redis says is active
async function syncFromRedis() {
    const tickers = await redis.hgetall('active_tickers');
    Object.keys(tickers).forEach(symbol => {
        if (!trackedSymbols.has(symbol)) {
            ws.send(JSON.stringify({ type: 'subscribe', symbol }));
            trackedSymbols.add(symbol);
            console.log('Current tracked symbols after Redis sync:', Array.from(trackedSymbols).join(', '));
            console.log(`Re-synced subscription for: ${symbol}`);
        }
    });
}

ws.on('message', async (msg) => {
    try {
        const data = JSON.parse(msg.toString());
        if (data.type === 'trade' && Array.isArray(data.data) && data.data.length > 0) {
            const trade = data.data[0]; // Assuming the first trade is representative for logging
            console.log(`Finnhub Trade Update: ${trade.s} @ ${trade.p} (Volume: ${trade.v}, Timestamp: ${new Date(trade.t)})`);
            
            // Send update to Laravel API
            await axios.post(`${apiUrl}/api/market/update`, data.data, {
                headers: {
                    'X-Finnhub-Secret': process.env.FINNHUB_WEBHOOK_SECRET || process.env.FINNHUB_SECRET || '',
                },
            }).catch(e => {
                console.error("Error sending trade update to Laravel API:");
                if (e.response) {
                    // The request was made and the server responded with a status code
                    // that falls out of the range of 2xx
                    console.error('  Status:', e.response.status);
                    console.error('  Response Data:', e.response.data);
                } else if (e.request) {
                    // The request was made but no response was received
                    console.error('  No response received from Laravel API. Request:', e.request);
                } else {
                    // Something happened in setting up the request that triggered an Error
                    console.error('  Axios Error Message:', e.message);
                }
            });
        } else if (data.type === 'ping') {
            // console.log('Finnhub ping received.
        } else if (data.type === 'subscribe') {
            console.log(`Finnhub subscription confirmation for ${data.symbol || 'multiple symbols'}.`);
        } else {
            console.log('Received other message from Finnhub:', data);
        }
    } catch (error) {
        console.error('Failed to process Finnhub message:', error.message);
    }
});

ws.on('error', (error) => {
    console.error('Finnhub WebSocket error:', error.message);
});

ws.on('close', (code, reason) => {
    console.warn(`Finnhub WebSocket closed with code=${code} reason=${reason}`);
});

// Listen for new subscription requests from Laravel
redisSub.subscribe('symbol-updates');
redisSub.on('message', (channel, msg) => {
    try {
        const { action, symbol } = JSON.parse(msg);
        if (action === 'subscribe' && symbol && !trackedSymbols.has(symbol)) {
            if (ws.readyState === WebSocket.OPEN) {
                console.log(`New Subscription Request: ${symbol}`);
                ws.send(JSON.stringify({ type: 'subscribe', symbol }));
                trackedSymbols.add(symbol);
            } else {
                console.warn(`Cannot subscribe to ${symbol}, WebSocket state is ${ws.readyState}`);
            }
        }
    } catch (e) {
        console.error("Invalid Redis message format");
    }
});

// Cleanup inactive tickers
setInterval(async () => {
    const now = Math.floor(Date.now() / 1000);
    
    // Ensure we are in sync with Redis
    await syncFromRedis();

    const tickers = await redis.hgetall('active_tickers');

    for (const [symbol, lastSeen] of Object.entries(tickers)) {
        if (now - parseInt(lastSeen) > 1800) { // 30 minutes of inactivity
            console.log(`Unsubscribing from ${symbol} (Inactive)`);
            ws.send(JSON.stringify({ type: 'unsubscribe', symbol }));
            trackedSymbols.delete(symbol);
            console.log('Current tracked symbols after cleanup:', Array.from(trackedSymbols).join(', '));
            await redis.hdel('active_tickers', symbol);
        }
    }
}, 60000);