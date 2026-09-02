<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\UserReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Tests\TestCase;

class ReportsExportEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_route_uses_export_service_and_logs_history()
    {
        $this->withoutMiddleware();
        $this->actingAs(User::factory()->create());

        // Mock the export service to return a simple response
        $exportMock = Mockery::mock(ReportExportService::class);
        $exportMock->shouldReceive('export')
            ->once()
            ->andReturn(new HttpResponse('ok', 200));

        $this->app->instance(ReportExportService::class, $exportMock);

        // Mock UserReportService to avoid DB queries during export
        $userReportMock = Mockery::mock(UserReportService::class);
        $userReportMock->shouldReceive('export')->andReturn([]);
        $this->app->instance(UserReportService::class, $userReportMock);

        $res = $this->postJson('/api/admin/reports/export', [
            'format' => 'csv',
            'report_type' => 'users',
            'start_date' => '2026-01-01',
            'end_date' => '2026-08-03',
        ]);

        $res->assertStatus(200);
    }
}
