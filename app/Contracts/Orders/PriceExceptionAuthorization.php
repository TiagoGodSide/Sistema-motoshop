<?php

namespace App\Contracts\Orders;

use App\Models\User;

interface PriceExceptionAuthorization
{
    /** Must throw unless server-side approval exists. No approver identity comes from the request. */
    public function authorize(User $actor, string $action, array $context): void;
}
