<?php

namespace App\Services;

use App\Contracts\Orders\PriceExceptionAuthorization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class DenyPriceExceptions implements PriceExceptionAuthorization
{
    public function authorize(User $actor, string $action, array $context): void
    {
        throw new AuthorizationException('Exceção de preço/desconto exige autorização específica no servidor.');
    }
}
