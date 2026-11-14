<?php

namespace Tests\Feature;

use App\Data\Orders\CheckoutCommand;
use App\Models\OperationReceipt;
use App\Services\OrderOperationExecutor;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FinancialTestCase;

class FinancialIntegrityTest extends FinancialTestCase
{
    private function linked(): void
    {
        $cash=$this->cash();$p=$this->product();$cmd=CheckoutCommand::fromArray($this->input($p,$cash));
        app(OrderOperationExecutor::class)->execute($this->actor,$cmd,fn($ctx)=>$this->prepare($ctx,$cmd));
    }

    private function rejected(callable $probe, ?string $constraint = null): void
    {
        $before=$this->state();DB::beginTransaction();$caught=false;
        try{$probe();}
        catch(QueryException $e){$caught=true;if($constraint!==null)$this->assertStringContainsString($constraint,$e->getMessage());}
        finally{DB::rollBack();}
        $this->assertTrue($caught,'Real database constraint must reject the probe');
        $this->assertSame($before,$this->state());
        $this->assertSame([],DB::select('PRAGMA foreign_key_check'));
    }

    public static function identityFields(): array{return [['operation','cash.close'],['request_hash', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb']];}

    #[DataProvider('identityFields')]
    public function test_financial_identity_cannot_diverge_from_registry(string $field,string $value): void
    {
        $this->linked();
        $this->rejected(fn()=>DB::table('financial_operations')->update([$field=>$value]));
    }

    public function test_registry_key_is_unique_even_for_nonfinancial_record(): void
    {
        $this->linked();$row=(array)DB::table('operation_receipts')->sole();unset($row['id']);
        $row['financial_operation_id']=null;$row['operation']='order.quote.create';$row['result']='{}';
        $this->rejected(fn()=>DB::table('operation_receipts')->insert($row));
    }

    public function test_composite_identity_rejects_other_key_without_relying_on_duplicate_financial_id(): void
    {
        $this->linked();
        $this->rejected(function(){
            $financial=(array)DB::table('financial_operations')->sole();unset($financial['id']);
            $financial['idempotency_key']=(string)Str::uuid();
            $id=DB::table('financial_operations')->insertGetId($financial);
            $row=(array)DB::table('operation_receipts')->sole();unset($row['id']);
            $row['financial_operation_id']=$id;
            $row['idempotency_key']=(string)Str::uuid();
            DB::table('operation_receipts')->insert($row);
        });
    }

    public function test_cash_link_rejects_payment_from_different_order(): void
    {
        $this->linked();
        $this->rejected(function(){
            $other=DB::table('orders')->insertGetId(['number'=>'2026-999991','status'=>'paid']);
            $part=DB::table('order_payments')->insertGetId(['order_id'=>$other,'method'=>'pix','amount'=>'10.00']);
            $row=(array)DB::table('order_cash_effects')->sole();
            $move=(array)DB::table('cash_movements')->sole();unset($move['id']);
            $row['cash_movement_id']=DB::table('cash_movements')->insertGetId($move);
            $row['order_payment_id']=$part;$row['effect_key']='payment:'.$part;
            DB::table('order_cash_effects')->insert($row);
        });
    }

    public function test_cash_link_rejects_other_cash_even_when_movement_exists(): void
    {
        $this->linked();
        $this->rejected(function(){
            $other=DB::table('cash_registers')->insertGetId(['status'=>'closed','opening_amount'=>'0.00']);
            $move=(array)DB::table('cash_movements')->sole();unset($move['id']);$move['cash_register_id']=$other;
            $id=DB::table('cash_movements')->insertGetId($move);
            $row=(array)DB::table('order_cash_effects')->sole();
            $row['cash_movement_id']=$id;$row['cash_register_id']=$other;$row['order_payment_id']=null;
            $row['effect']='automatic_withdrawal';$row['effect_key']='automatic_withdrawal';
            DB::table('order_cash_effects')->insert($row);
        });
    }

    public function test_stock_link_rejects_movement_of_another_product(): void
    {
        $this->linked();
        $other=$this->product();
        $this->rejected(function()use($other){
            $move=(array)DB::table('stock_movements')->sole();unset($move['id']);$move['product_id']=$other->id;
            $id=DB::table('stock_movements')->insertGetId($move);
            $row=(array)DB::table('order_stock_effects')->sole();
            $row['stock_movement_id']=$id;$row['effect']='return';
            DB::table('order_stock_effects')->insert($row);
        });
    }

    public function test_stock_effect_is_unique_per_operation_and_item(): void
    {
        $this->linked();
        $this->rejected(function(){
            $move=(array)DB::table('stock_movements')->sole();unset($move['id']);
            $id=DB::table('stock_movements')->insertGetId($move);
            $row=(array)DB::table('order_stock_effects')->sole();$row['stock_movement_id']=$id;
            DB::table('order_stock_effects')->insert($row);
        });
    }

    public function test_settlement_is_unique_for_same_order_across_different_command_keys(): void
    {
        $this->linked();
        $this->rejected(function(){
            $fin=(array)DB::table('financial_operations')->sole();unset($fin['id']);
            $fin['idempotency_key']=(string)Str::uuid();
            $finId=DB::table('financial_operations')->insertGetId($fin);
            $r=(array)DB::table('operation_receipts')->sole();unset($r['id']);
            $r['idempotency_key']=$fin['idempotency_key'];$r['financial_operation_id']=$finId;
            $rId=DB::table('operation_receipts')->insertGetId($r);
            $s=(array)DB::table('order_settlements')->sole();unset($s['id']);
            $s['operation_receipt_id']=$rId;$s['financial_operation_id']=$finId;
            DB::table('order_settlements')->insert($s);
        });
    }

    public static function scalarChecks(): array
    {
        return [
            ['order_settlements','total_cents',0,'os_total_ck'],
            ['order_settlements','total_cents',1.5,'os_total_ck'],
            ['order_settlements','total_cents',10000000000,'os_total_ck'],
            ['order_item_snapshots','total_cents',-1,'ois_total_ck'],
            ['order_item_snapshots','qty',0,'ois_qty_ck'],
            ['order_item_snapshots','qty',2147483648,'ois_qty_ck'],
            ['order_item_snapshots','stock_policy','bad','ois_stock_policy_ck'],
            ['document_sequences','last_value',1000000,'ds_sequence_ck'],
        ];
    }

    #[DataProvider('scalarChecks')]
    public function test_database_checks_are_effective(string $table,string $field,mixed $value,string $constraint): void
    {
        $this->linked();
        $this->rejected(fn()=>DB::table($table)->update([$field=>$value]),$constraint);
    }

    public static function protectedParents(): array{return [['orders'],['order_payments'],['cash_movements'],['stock_movements'],['financial_operations'],['operation_receipts']];}

    #[DataProvider('protectedParents')]
    public function test_posted_history_cannot_be_deleted(string $table): void
    {
        $this->linked();
        $this->rejected(fn()=>DB::table($table)->delete());
    }

    public function test_read_models_do_not_offer_uncoordinated_mutation(): void
    {
        $this->linked();$before=$this->state();
        $model=OperationReceipt::sole();$model->operation='cash.close';
        try{$model->save();$this->fail();}catch(LogicException){}
        $this->assertSame($before,$this->state());
    }
}
