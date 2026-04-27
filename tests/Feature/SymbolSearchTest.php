<?php

namespace Tests\Feature;

use App\Models\Symbol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SymbolSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_symbol_search_returns_matching_symbols(): void
    {
        Symbol::create([
            'symbol' => 'AAPL',
            'name' => 'Apple Inc',
            'type' => 'Common Stock',
            'exchange' => 'US',
        ]);

        Symbol::create([
            'symbol' => 'AAP',
            'name' => 'Advance Auto Parts Inc',
            'type' => 'Common Stock',
            'exchange' => 'US',
        ]);

        Symbol::create([
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'type' => 'Crypto',
            'exchange' => 'CRYPTO',
        ]);

        $response = $this->getJson('/api/stocks/search?q=App');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonFragment(['symbol' => 'AAPL'])
            ->assertJsonFragment(['symbol' => 'AAP']);
    }

    public function test_symbol_search_returns_empty_array_when_query_is_too_short(): void
    {
        Symbol::create([
            'symbol' => 'AAPL',
            'name' => 'Apple Inc',
            'type' => 'Common Stock',
            'exchange' => 'US',
        ]);

        $response = $this->getJson('/api/stocks/search?q=A');

        $response->assertStatus(200)
            ->assertExactJson([]);
    }
}
