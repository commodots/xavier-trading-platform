<?php

namespace Tests\Feature\CSL;

use App\Services\CSL\CslDocumentService;
use App\Services\CSL\CslStockClient;
use Tests\TestCase;

class CslDocumentServiceTest extends TestCase
{
    public function test_contract_note_is_delegated_to_the_stock_client(): void
    {
        $response = [
            'document' => 'base64-document',
        ];

        $client = $this->createMock(CslStockClient::class);
        $client->expects($this->once())
            ->method('contractNote')
            ->with('ACCOUNT001', 'TRADE001')
            ->willReturn($response);

        $service = new CslDocumentService($client);

        $this->assertSame(
            $response,
            $service->contractNote('ACCOUNT001', 'TRADE001')
        );
    }
}
