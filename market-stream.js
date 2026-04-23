import 'dotenv/config';
import WebSocket from 'ws';
import axios from 'axios';

const token = process.env.FINNHUB_API_KEY;
const apiUrl = process.env.APP_URL ? process.env.APP_URL.replace(/\/$/, '') : 'http://127.0.0.1:8000';

if (!token) {
    console.error('Missing FINNHUB_API_KEY in environment variables.');
    process.exit(1);
}

const ws = new WebSocket(`wss://ws.finnhub.io?token=${token}`);
const symbols = ['AAPL', 'TSLA', 'MSFT'];

ws.on('open', () => {
    console.log('Finnhub WebSocket connected. Subscribing to symbols:', symbols.join(', '));

    symbols.forEach((symbol) => {
        ws.send(JSON.stringify({ type: 'subscribe', symbol }));
    });
});

ws.on('message', async (msg) => {
    try {
        const data = JSON.parse(msg.toString());

        if (data.type === 'trade' && Array.isArray(data.data) && data.data.length > 0) {
            console.log(`Price Update: ${data.data[0].s} @ ${data.data[0].p}`);
            await axios.post(`${apiUrl}/api/market/update`, data.data, {
                headers: {
                    'X-Finnhub-Secret': process.env.FINNHUB_WEBHOOK_SECRET || process.env.FINNHUB_SECRET || '',
                },
            });
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
