<?php

namespace App\Contracts\Orders;

interface OrderCommand
{
    public function operation(): string;
    public function key(): string;
    public function payload(): array;
    public function hash(): string;
}
