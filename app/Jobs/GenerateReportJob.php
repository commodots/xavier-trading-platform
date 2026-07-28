<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Reports\ReportExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public string $reportType,
        public array $filters = [],
        public string $format = 'pdf'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ReportExportService $exportService): void
    {
        // Get report data based on type
        $data = $this->getReportData();
        $headers = $this->getReportHeaders();

        // Generate the report file
        $filename = "{$this->reportType}_" . now()->format('Y-m-d_H-i-s');
        $path = "reports/{$this->user->id}/{$filename}.{$this->format}";

        if ($this->format === 'csv') {
            $response = $exportService->csv($data, $headers, $filename);
            Storage::put($path, $response->getContent());
        } else {
            $response = $exportService->excel($data, $headers, $filename);
            Storage::put($path, $response->getContent());
        }

        // Notify user that report is ready
        $this->user->notify(new \App\Notifications\ReportGeneratedNotification($path, $this->reportType));
    }

    /**
     * Get report data based on report type
     */
    private function getReportData(): array
    {
        // This is a simplified version - in production, use appropriate report service
        return match ($this->reportType) {
            'transactions' => \App\Models\Transaction::where('user_id', $this->user->id)
                ->when($this->filters['date_from'] ?? null, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
                ->when($this->filters['date_to'] ?? null, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
                ->get()
                ->map(fn($t) => [
                    'Date' => $t->created_at->format('Y-m-d'),
                    'Type' => $t->type,
                    'Amount' => $t->amount,
                    'Status' => $t->status,
                ])
                ->toArray(),
            default => [],
        };
    }

    /**
     * Get report headers based on report type
     */
    private function getReportHeaders(): array
    {
        return match ($this->reportType) {
            'transactions' => ['Date', 'Type', 'Amount', 'Status'],
            default => [],
        };
    }
}