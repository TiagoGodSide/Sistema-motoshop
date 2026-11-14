<?php

namespace App\Data\Orders;

use App\Contracts\Orders\OrderCommand;
use App\Support\CanonicalJson;

abstract readonly class OrderCommandData implements OrderCommand
{
    final protected function __construct(private string $identity, private array $data) {}

    final public function key(): string { return $this->identity; }
    final public function payload(): array { return $this->data; }
    final public function hash(): string
    {
        return CanonicalJson::hash(['contract_version' => 1, 'operation' => $this->operation(), 'payload' => $this->data]);
    }
}
