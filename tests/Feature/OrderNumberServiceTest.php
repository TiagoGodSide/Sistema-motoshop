<?php

namespace Tests\Feature;

use App\Models\DocumentSequence;
use App\Services\FinancialCoordinator;
use App\Services\OrderNumberService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Support\FinancialTestCase;

class OrderNumberServiceTest extends FinancialTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public static function boundary(): array
    {
        return [
            ['2027-01-01 02:59:59 UTC','2026-000001',2026],
            ['2027-01-01 03:00:00 UTC','2027-000001',2027],
        ];
    }

    #[DataProvider('boundary')]
    public function test_recife_year_utc_instant_and_no_cash_requirement(string $instant,string $expected,int $year): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($instant));
        $number=app(FinancialCoordinator::class)->orderTransaction(fn($ctx)=>app(OrderNumberService::class)->reserve($ctx));
        $this->assertSame($expected,$number);
        $seq=DocumentSequence::forYear($year);
        $this->assertSame(1,$seq->last_value);
        $this->assertStringStartsWith(substr($instant,0,19),$seq->updated_at_utc);
        $this->assertDatabaseCount('cash_registers',0);
    }

    public function test_initialization_reads_number_suffix_not_id_or_row_count(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-12 12:00:00 UTC'));
        DB::table('orders')->insert([
            ['id'=>900,'number'=>'2026-000007','status'=>'draft'],
            ['id'=>3,'number'=>'2026-000021','status'=>'paid'],
            ['id'=>999,'number'=>'2025-000999','status'=>'paid'],
        ]);
        $before=DB::table('orders')->orderBy('id')->get()->toJson();
        $coordinator=app(FinancialCoordinator::class);$service=app(OrderNumberService::class);
        $this->assertSame('2026-000022',$coordinator->orderTransaction(fn($ctx)=>$service->reserve($ctx)));
        $this->assertSame('2026-000023',$coordinator->orderTransaction(fn($ctx)=>$service->reserve($ctx)));
        $this->assertSame($before,DB::table('orders')->orderBy('id')->get()->toJson());
    }

    public static function badNumbers(): array {return [['2026-1'],['2026-000000'],['2026-1000000'],['2026-ABCDEF']];}

    #[DataProvider('badNumbers')]
    public function test_ambiguous_history_stops_before_counter_creation(string $number): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-12 12:00:00 UTC'));
        DB::table('orders')->insert(['number'=>$number,'status'=>'draft']);
        $before=$this->state();
        try{app(FinancialCoordinator::class)->orderTransaction(fn($ctx)=>app(OrderNumberService::class)->reserve($ctx));$this->fail();}
        catch(ConflictHttpException){}
        $this->assertSame($before,$this->state());
    }

    public function test_exhaustion_does_not_wrap_or_rename_history(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-12 12:00:00 UTC'));
        DB::table('orders')->insert(['number'=>'2026-999999','status'=>'draft']);
        $before=$this->state();
        try{app(FinancialCoordinator::class)->orderTransaction(fn($ctx)=>app(OrderNumberService::class)->reserve($ctx));$this->fail();}
        catch(ConflictHttpException){}
        $this->assertSame($before,$this->state());
    }

    public function test_rollback_restores_counter_and_external_collision_is_not_silently_skipped(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-12 12:00:00 UTC'));
        $c=app(FinancialCoordinator::class);$n=app(OrderNumberService::class);
        try{$c->orderTransaction(function($ctx)use($n){$n->reserve($ctx);throw new RuntimeException('Fixture rollback');});$this->fail();}
        catch(RuntimeException){}
        $this->assertDatabaseCount('document_sequences',0);
        $this->assertSame('2026-000001',$c->orderTransaction(fn($ctx)=>$n->reserve($ctx)));
        DB::table('orders')->insert(['number'=>'2026-000002','status'=>'draft']);
        $before=$this->state();
        try{$c->orderTransaction(fn($ctx)=>$n->reserve($ctx));$this->fail();}catch(ConflictHttpException){}
        $this->assertSame($before,$this->state());
    }

    public function test_escaped_context_cannot_allocate(): void
    {
        $c=app(FinancialCoordinator::class);$ctx=$c->coordinate(fn()=>$c->context());
        $before=$this->state();
        try{app(OrderNumberService::class)->reserve($ctx);$this->fail();}catch(LogicException){}
        $this->assertSame($before,$this->state());
    }
}
