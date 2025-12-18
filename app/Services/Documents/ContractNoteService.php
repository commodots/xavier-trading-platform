<?php 

namespace App\Services\Documents;

use App\Models\Trade;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
class ContractNoteService
{
    public function generate(Trade $trade)
    {
        $pdf = PDF::loadView('pdf.contract-note', [
            'trade' => $trade,
            'order' => $trade->order,
            'user' => $trade->order->user,
        ]);

        $path = storage_path("app/contract-notes/contract_{$trade->id}.pdf");

        $pdf->save($path);

        return $path;
    }
}
