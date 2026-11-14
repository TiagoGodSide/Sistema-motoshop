<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class OrderNumberService
{
    public function reserve(FinancialContext $context): string
    {
        $context->assertActive();
        $year = $context->businessYear();
        $sequence = DB::table('document_sequences')->where('scope', 'orders')->where('business_year', $year)->lockForUpdate()->first();
        if ($sequence === null) {
            $last = 0;
            $numbers = DB::table('orders')->where('number', 'like', $year.'-%')->orderBy('number')->lockForUpdate()->pluck('number');
            foreach ($numbers as $number) {
                if (! preg_match('/\A'.$year.'-([0-9]{6})\z/', $number, $matches) || (int) $matches[1] < 1) {
                    throw new ConflictHttpException('Numeração histórica exige revisão; nenhum número foi alterado.');
                }
                $last = max($last, (int) $matches[1]);
            }
            DB::table('document_sequences')->insert([
                'scope' => 'orders', 'business_year' => $year, 'last_value' => $last,
                'updated_at_utc' => $context->utc(),
            ]);
        } else {
            $last = (int) $sequence->last_value;
        }
        if ($last >= 999999) {
            throw new ConflictHttpException('Numeração anual esgotada.');
        }
        $next = sprintf('%04d-%06d', $year, $last + 1);
        if (DB::table('orders')->where('number', $next)->lockForUpdate()->exists()) {
            throw new ConflictHttpException('Escritor externo alterou a numeração; preserve a chave.');
        }
        DB::table('document_sequences')->where('scope', 'orders')->where('business_year', $year)->update([
            'last_value' => $last + 1, 'updated_at_utc' => $context->utc(),
        ]);
        return $next;
    }
}
