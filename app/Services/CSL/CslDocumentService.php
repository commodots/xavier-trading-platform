<?php

namespace App\Services\CSL;

class CslDocumentService
{
    public function __construct(
        protected CslStockClient $st
    ) {}

    public function contractNote(
        string $marketAccountId,
        string $tradeNo
    ): array {
        return $this->st->contractNote(
            $marketAccountId,
            $tradeNo
        );
    }
}
