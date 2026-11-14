<?php

namespace App\Data\Orders;

/** Internal prepared records. Only trusted future handlers may supply these IDs. */
final readonly class PreparedOrderOperation
{
    public function __construct(public OrderResult $result, public array $snapshots, public array $stockEffects = []) {}
}
