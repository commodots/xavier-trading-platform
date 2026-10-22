<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FxConversion;
use App\Services\Fx\FxConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FincraWebhookController extends Controller
{
    /**
     * Handle incoming Fincra webhook notifications
     */
    public function handle(Request $request)
    {
        // Fincra webhook signature verification
        $signature = $request->header('x-fincra-signature');
        $webhookKey = config('services.fincra.webhook_key');

        if (! $webhookKey) {
            Log::warning('Fincra Webhook: Webhook key not configured');

            return response()->json(['error' => 'Webhook not configured'], 500);
        }

        // Verify webhook signature
        $computedSignature = hash_hmac('sha256', $request->getContent(), $webhookKey);

        if (! $signature || ! hash_equals($computedSignature, $signature)) {
            Log::warning('Fincra Webhook: Invalid Signature Attempt', [
                'ip' => $request->ip(),
                'signature' => $signature,
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? $payload['type'] ?? '';
        $data = $payload['data'] ?? $payload;

        Log::info('Fincra Webhook received', [
            'event' => $event,
            'data' => $data,
        ]);

        // Route Events
        switch ($event) {
            case 'conversion.completed':
            case 'conversion.success':
                $this->handleConversionCompleted($data);
                break;

            case 'conversion.failed':
            case 'conversion.failure':
                $this->handleConversionFailed($data);
                break;

            case 'conversion.processing':
                $this->handleConversionProcessing($data);
                break;

            default:
                Log::info('Fincra Webhook: Unhandled event ignored', ['event' => $event]);
                break;
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Handle successful conversion
     */
    private function handleConversionCompleted(array $data): void
    {
        $conversion = $this->findConversion($data);
        if (! $conversion) {
            Log::warning('Fincra Webhook: No reference found in payload');

            return;
        }

        app(FxConversionService::class)->completeProviderConversion($conversion->reference, [
            'webhook' => $data,
            'completed_at' => now()->toIso8601String(),
        ]);

        Log::info('Fincra Webhook: Conversion completed', [
            'conversion_id' => $conversion->id,
            'reference' => $conversion->reference,
        ]);
    }

    /**
     * Handle failed conversion
     */
    private function handleConversionFailed(array $data): void
    {
        $reason = $data['reason'] ?? $data['failureReason'] ?? 'Unknown error';
        $conversion = $this->findConversion($data);
        if (! $conversion) {
            Log::warning('Fincra Webhook: Conversion not found');

            return;
        }

        app(FxConversionService::class)->failProviderConversion($conversion->reference, [
            'webhook' => $data,
            'failed_at' => now()->toIso8601String(),
            'failure_reason' => $reason,
        ]);

        Log::error('Fincra Webhook: Conversion failed', [
            'conversion_id' => $conversion->id,
            'reference' => $conversion->reference,
            'reason' => $reason,
        ]);
    }

    /**
     * Handle processing conversion
     */
    private function handleConversionProcessing(array $data): void
    {
        $conversion = $this->findConversion($data);
        if (! $conversion) {
            Log::warning('Fincra Webhook: Conversion not found');

            return;
        }

        app(FxConversionService::class)->markProviderProcessing($conversion, [
            'webhook' => $data,
            'processing_started_at' => now()->toIso8601String(),
        ]);

        Log::info('Fincra Webhook: Conversion processing', [
            'conversion_id' => $conversion->id,
            'reference' => $conversion->reference,
        ]);
    }

    private function findConversion(array $data): ?FxConversion
    {
        $references = array_filter([
            $data['customerReference'] ?? null,
            $data['reference'] ?? null,
            $data['quoteReference'] ?? null,
            $data['conversionReference'] ?? null,
        ]);

        foreach ($references as $reference) {
            $conversion = FxConversion::query()
                ->where('reference', $reference)
                ->orWhere('response->quote_reference', $reference)
                ->orWhere('response->provider_reference', $reference)
                ->first();

            if ($conversion) {
                return $conversion;
            }
        }

        return null;
    }
}
