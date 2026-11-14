<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Connection;
use LogicException;
use Illuminate\Support\Facades\DB;

final readonly class FinancialContext
{
    public function __construct(
        private FinancialCoordinator $owner,
        private object $token,
        private Connection $connection,
        public DateTimeImmutable $instant,
    ) {}

    public function assertActive(): void
    {
        if (! $this->owner->ownsContext($this->token) || DB::connection() !== $this->connection || $this->connection->transactionLevel() < 1) {
            throw new LogicException('A operação exige a coordenação financeira ativa.');
        }
    }

    public function utc(): string
    {
        return $this->instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    public function businessYear(): int
    {
        return (int) $this->instant->setTimezone(new DateTimeZone('America/Recife'))->format('Y');
    }
}
