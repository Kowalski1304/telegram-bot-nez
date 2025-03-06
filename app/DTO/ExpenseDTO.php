<?php

namespace App\DTO;

class ExpenseDTO
{
    public function __construct(
        private readonly int $userId,
        private readonly float $amount,
        private readonly string $source,
        private readonly ?string $category = null,
        private readonly ?string $description = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'amount' => $this->amount,
            'source' => $this->source,
            'category' => $this->category,
            'description' => $this->description,
        ];
    }
}
