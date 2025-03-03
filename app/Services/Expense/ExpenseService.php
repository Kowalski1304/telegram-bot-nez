<?php

namespace App\Services\Expense;

use App\Models\Expense;
use Carbon\Carbon;

class ExpenseService
{
    public function storeExpense(int $userId, float $amount, string $source, ?string $category = null, ?string $description = null): Expense
    {
        return Expense::create([
            'user_id' => $userId,
            'amount' => $amount,
            'source' => $source,
            'category' => $category,
            'description' => $description,
            'created_at' => Carbon::now()
        ]);
    }

    public function getRecentExpenses(int $userId, int $limit = 5): Expense
    {
        return Expense::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getTotalForPeriod(int $userId, Carbon $startDate, Carbon $endDate): Expense
    {
        return Expense::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');
    }
}
