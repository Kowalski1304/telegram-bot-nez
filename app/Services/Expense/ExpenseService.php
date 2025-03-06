<?php

namespace App\Services\Expense;

use App\DTO\ExpenseDTO;
use App\Models\Expense;

class ExpenseService
{
    public function storeExpense(ExpenseDTO $expenseDTO): Expense
    {
        return Expense::create($expenseDTO->toArray());
    }
}
