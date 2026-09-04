<?php

namespace App\Services\FixedIncome;

use App\Models\FixedIncomeInvestment;
use Illuminate\Database\Eloquent\Builder;

class FixedIncomeReportService
{
  public function investments(array $filters = [])
  {
    $query = FixedIncomeInvestment::query()
      ->with(['user', 'product']);

    if (!empty($filters['status'])) {
      $query->where(
        'status',
        $filters['status']
      );
    }

    if (!empty($filters['product_id'])) {
      $query->where(
        'fixed_income_product_id',
        $filters['product_id']
      );
    }

    if (!empty($filters['currency'])) {
      $query->where(
        'currency',
        $filters['currency']
      );
    }

    if (!empty($filters['execution_mode'])) {
      $query->where(
        'execution_mode',
        $filters['execution_mode']
      );
    }

    if (!empty($filters['provider'])) {
      $query->where(
        'provider',
        $filters['provider']
      );
    }

    if (!empty($filters['date_from'])) {
      $query->whereDate(
        'investment_date',
        '>=',
        $filters['date_from']
      );
    }

    if (!empty($filters['date_to'])) {
      $query->whereDate(
        'investment_date',
        '<=',
        $filters['date_to']
      );
    }

    return $query
      ->latest('investment_date')
      ->get()
      ->map(function ($investment) {
        return [
          'reference' =>
          $investment->reference,

          'investor' =>
          $investment->user?->name,

          'product' =>
          $investment->product?->name,

          'principal' =>
          $investment->principal_amount,

          'currency' =>
          $investment->currency,

          'interest_rate' =>
          $investment->interest_rate,

          'expected_interest' =>
          $investment->expected_interest,

          'actual_interest' =>
          $investment->actual_interest,

          'expected_maturity_amount' =>
          $investment->expected_maturity_amount,

          'actual_maturity_amount' =>
          $investment->actual_maturity_amount,

          'status' =>
          $investment->status,

          'funding_method' =>
          $investment->funding_method,

          'execution_mode' =>
          $investment->execution_mode,

          'provider' =>
          $investment->provider,

          'investment_date' =>
          $investment->investment_date,

          'execution_date' =>
          $investment->execution_date,

          'maturity_date' =>
          $investment->maturity_date,

          'redeemed_at' =>
          $investment->redeemed_at,
        ];
      });
  }
}
