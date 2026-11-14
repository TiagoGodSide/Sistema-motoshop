<?php

namespace Tests\Feature;

use App\Data\Orders\CheckoutCommand;
use App\Data\Orders\FinalizeOrderCommand;
use App\Data\Orders\QuoteCommand;
use App\Data\Orders\OrderResult;
use App\Data\Orders\PreparedOrderOperation;
use App\Models\OperationReceipt;
use App\Models\OrderItemSnapshot;
use App\Models\User;
use App\Services\FinancialContext;
use App\Services\FinancialCoordinator;
use App\Services\OrderOperationExecutor;
use App\Services\QuoteVersion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\Support\FinancialTestCase;

class OrderOperationInfrastructureTest extends FinancialTestCase
{
    public function test_equal_method_parts_are_not_merged_or_posted_twice(): void
    {
        $cash=$this->cash();$p=$this->product();$input=$this->input($p,$cash);
        $input['payments']=[['method'=>'pix','amount'=>'5.00'],['method'=>'pix','amount'=>'5.00']];
        $cmd=CheckoutCommand::fromArray($input);
        $result=app(OrderOperationExecutor::class)->execute($this->actor,$cmd,fn($ctx)=>$this->prepare($ctx,$cmd));
        $this->assertCount(2,$result['result']['payments']);
        $this->assertDatabaseCount('order_payments',2);$this->assertDatabaseCount('order_cash_effects',2);
        $this->assertDatabaseCount('financial_operations',1);$this->assertDatabaseCount('order_settlements',1);
    }

    public function test_untracked_policy_is_frozen_without_stock_effect(): void
    {
        $cash=$this->cash();$p=$this->product();$p->forceFill(['stock_policy'=>'untracked','stock'=>0])->save();
        $this->assertSame('untracked',$p->fresh()->effectiveStockPolicy());
        $cmd=CheckoutCommand::fromArray($this->input($p,$cash));
        $r=app(OrderOperationExecutor::class)->execute($this->actor,$cmd,fn($ctx)=>$this->prepare($ctx,$cmd));
        $this->assertSame([],$r['result']['stock_movement_ids']);
        $this->assertDatabaseCount('stock_movements',0);$this->assertDatabaseCount('order_stock_effects',0);
        $this->assertSame('untracked',DB::table('order_item_snapshots')->value('stock_policy'));
    }

    public function test_projected_balance_limit_rolls_back_the_whole_prepared_operation(): void
    {
        $cash=$this->cash();DB::table('cash_registers')->where('id',$cash->id)->update(['opening_amount'=>'99999999.99']);
        $p=$this->product();$cmd=CheckoutCommand::fromArray($this->input($p,$cash));$before=$this->state();
        try{app(OrderOperationExecutor::class)->execute($this->actor,$cmd,fn($ctx)=>$this->prepare($ctx,$cmd));$this->fail();}
        catch(ConflictHttpException){}
        $this->assertSame($before,$this->state());
    }

    public static function withdrawalCounts(): array {return [[1],[2]];}

    #[DataProvider('withdrawalCounts')]
    public function test_withdrawal_links_once_and_rejects_duplicate_effects(int $count): void
    {
        $cash=$this->cash();$p=$this->product();$input=$this->input($p,$cash);
        $input['payments']=[['method'=>'dinheiro','amount'=>'10.00']];$cmd=CheckoutCommand::fromArray($input);
        $before=$this->state();
        try{
            app(OrderOperationExecutor::class)->execute($this->actor,$cmd,function($ctx)use($cmd,$count,$cash){
                $prepared=$this->prepare($ctx,$cmd);$data=$prepared->result->data;
                for($i=0;$i<$count;$i++)$data['cash_movement_ids'][]=(int)DB::table('cash_movements')->insertGetId([
                    'cash_register_id'=>$cash->id,'order_id'=>$data['order_id'],'user_id'=>$this->actor->id,
                    'type'=>'OUT','amount'=>'1.00','payment_method'=>'dinheiro','reason'=>'Synthetic automatic withdrawal',
                ]);
                return new PreparedOrderOperation(OrderResult::fromArray($data),$prepared->snapshots,$prepared->stockEffects);
            });
            $this->assertSame(1,$count);
            $this->assertSame(1,DB::table('order_cash_effects')->where('effect','automatic_withdrawal')->count());
        }catch(InvalidArgumentException $e){
            $this->assertSame(2,$count);$this->assertStringContainsString('Mais de uma sangria',$e->getMessage());
            $this->assertSame($before,$this->state());
        }
    }

    public function test_quote_replay_across_recife_new_year_preserves_original_number(): void
    {
        try{
            \Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::parse('2027-01-01 02:59:59 UTC'));
            $p=$this->product();$q=QuoteCommand::fromArray($this->input($p));$executor=app(OrderOperationExecutor::class);
            $first=$executor->execute($this->actor,$q,fn($ctx)=>$this->prepare($ctx,$q));
            $this->assertSame('2026-000001',$first['result']['number']);
            \Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::parse('2027-01-01 03:00:00 UTC'));
            $before=$this->state();$again=$executor->execute($this->actor,$q,fn()=>throw new LogicException('No reallocation'));
            $this->assertSame($first['result'],$again['result']);$this->assertSame($before,$this->state());
            $this->assertSame(0,DB::table('document_sequences')->where('business_year',2027)->count());
        }finally{\Carbon\CarbonImmutable::setTestNow();}
    }

    public static function cashActions(): array {return [['open'],['close'],['movement']];}

    #[DataProvider('cashActions')]
    public function test_cash_registry_append_failure_rolls_back_both_receipts_and_finance(string $action): void
    {
        $c=app(FinancialCoordinator::class);$id=null;
        if($action!=='open')$id=$c->open($this->actor,['idempotency_key'=>(string)Str::uuid()])['cash_register_id'];
        $payload=['idempotency_key'=>(string)Str::uuid()];
        if($action==='open')$payload['opening_amount']='10.00';
        else $payload['cash_register_id']=$id;
        if($action==='movement')$payload+=['type'=>'IN','amount'=>'1.00'];
        $once=true;
        DB::connection()->beforeExecuting(function($sql)use(&$once){
            if($once&&str_starts_with($sql,'insert into "operation_receipts"')){$once=false;throw new PDOException('Injected global registry failure');}
        });
        $before=$this->state();
        try{$c->$action($this->actor,$payload);$this->fail();}catch(ServiceUnavailableHttpException){}
        $this->assertSame($before,$this->state());$this->assertSame(0,DB::connection()->transactionLevel());
        $r=$c->$action($this->actor,$payload);$this->assertFalse($r['replayed']);
        $after=$this->state();$this->assertTrue($c->$action($this->actor,$payload)['replayed']);$this->assertSame($after,$this->state());
    }

    public function test_synthetic_split_operation_binds_all_records_without_duplicate_result(): void
    {
        $cash=$this->cash();$product=$this->product();$input=$this->input($product,$cash);
        $input['payments']=[['method'=>'dinheiro','amount'=>'4.00'],['method'=>'pix','amount'=>'6.00']];
        $cmd=CheckoutCommand::fromArray($input);
        $r=app(OrderOperationExecutor::class)->execute($this->actor,$cmd,fn($ctx)=>$this->prepare($ctx,$cmd));
        $this->assertFalse($r['replayed']);
        $this->assertSame('10.00',$r['result']['total']);
        $this->assertSame(19,$product->fresh()->stock);
        foreach(['operation_receipts'=>1,'financial_operations'=>1,'order_settlements'=>1,'order_cash_effects'=>2,
            'order_stock_effects'=>1,'order_item_snapshots'=>1,'order_payments'=>2] as $t=>$n)$this->assertDatabaseCount($t,$n);
        $receipt=OperationReceipt::sole();
        $this->assertNull($receipt->result);
        $this->assertSame(1000,$receipt->settlement->total_cents);
        $this->assertCount(2,$receipt->settlement->cashEffects);
        $snapshot=OrderItemSnapshot::forItem($receipt->id,DB::table('order_items')->value('id'));
        $this->assertSame(1000,$snapshot->unit_price_cents);
        $this->assertSame([],DB::select('PRAGMA foreign_key_check'));
        $before=$this->state();
        $again=app(OrderOperationExecutor::class)->execute($this->actor,$cmd,fn()=>throw new LogicException('No replay work'));
        $this->assertTrue($again['replayed']);$this->assertSame($r['result'],$again['result']);
        $this->assertSame($before,$this->state());
    }

    public function test_quote_without_cash_has_only_command_receipt_and_snapshots(): void
    {
        $p=$this->product();$cmd=QuoteCommand::fromArray($this->input($p));
        $r=app(OrderOperationExecutor::class)->execute($this->actor,$cmd,fn($ctx)=>$this->prepare($ctx,$cmd));
        $this->assertSame('draft',$r['result']['status']);
        $this->assertSame(20,$p->fresh()->stock);
        $this->assertDatabaseCount('operation_receipts',1);
        $this->assertNotNull(OperationReceipt::sole()->result);
        foreach(['cash_registers','financial_operations','order_payments','cash_movements','stock_movements','order_settlements','order_stock_effects','order_cash_effects'] as $t)$this->assertDatabaseCount($t,0);
        $before=$this->state();
        try{
            app(FinancialCoordinator::class)->open($this->actor,['idempotency_key'=>$cmd->key(),'opening_amount'=>'0.00']);
            $this->fail('Quote UUID cannot become cash UUID');
        }catch(ConflictHttpException){}
        $this->assertSame($before,$this->state());
    }

    public function test_cash_uuid_cannot_become_order_uuid(): void
    {
        $key=(string)Str::uuid();$p=$this->product();
        app(FinancialCoordinator::class)->open($this->actor,['idempotency_key'=>$key]);
        $input=$this->input($p);$input['idempotency_key']=$key;$cmd=QuoteCommand::fromArray($input);
        $before=$this->state();
        try{app(OrderOperationExecutor::class)->execute($this->actor,$cmd,fn()=>throw new LogicException('Must reject before work'));$this->fail();}
        catch(ConflictHttpException){}
        $this->assertSame($before,$this->state());
    }

    public function test_replay_after_catalog_cash_and_order_state_changes_does_not_recalculate(): void
    {
        $cash=$this->cash();$p=$this->product();$cmd=CheckoutCommand::fromArray($this->input($p,$cash));
        $executor=app(OrderOperationExecutor::class);
        $first=$executor->execute($this->actor,$cmd,fn($ctx)=>$this->prepare($ctx,$cmd));
        DB::table('cash_registers')->where('id',$cash->id)->update(['status'=>'closed']);
        DB::table('products')->where('id',$p->id)->update(['is_active'=>false,'price'=>'99.00']);
        DB::table('orders')->where('id',$first['result']['order_id'])->update(['total'=>'99.00']);
        $this->cash();$before=$this->state();
        $r=$executor->execute($this->actor,$cmd,fn()=>throw new LogicException('Replay must skip work'));
        $this->assertTrue($r['replayed']);$this->assertSame($first['result'],$r['result']);
        $this->assertSame($before,$this->state());
    }

    public static function mismatches(): array {return [['actor'],['content'],['operation'],['cash']];}

    #[DataProvider('mismatches')]
    public function test_global_identity_mismatches_do_not_write(string $kind): void
    {
        $cash=$this->cash();$p=$this->product();$input=$this->input($p,$cash);$cmd=CheckoutCommand::fromArray($input);
        $executor=app(OrderOperationExecutor::class);$executor->execute($this->actor,$cmd,fn($ctx)=>$this->prepare($ctx,$cmd));
        $actor=$this->actor;
        if($kind==='actor'){$actor=User::factory()->create(['active'=>true]);$actor->assignRole('admin');}
        if($kind==='content'){$input['customer_name']='Other customer';}
        if($kind==='cash'){$input['cash_register_id']=$this->cash()->id;}
        if($kind==='operation'){$input=$this->input($p);$input['idempotency_key']=$cmd->key();$other=QuoteCommand::fromArray($input);}
        else $other=CheckoutCommand::fromArray($input);
        $before=$this->state();
        try{$executor->execute($actor,$other,fn()=>throw new LogicException('No mismatched work'));$this->fail();}
        catch(ConflictHttpException){}
        $this->assertSame($before,$this->state());
    }

    public static function cashStates(): array{return [['none'],['closed'],['wrong'],['multiple']];}

    #[DataProvider('cashStates')]
    public function test_new_financial_commands_require_explicit_unique_active_cash(string $kind): void
    {
        $cash=$this->cash();$p=$this->product();$input=$this->input($p,$cash);
        if($kind==='none')DB::table('cash_registers')->delete();
        if($kind==='closed')DB::table('cash_registers')->update(['status'=>'closed']);
        if($kind==='wrong')$input['cash_register_id']=999999;
        if($kind==='multiple')$this->cash();
        $cmd=CheckoutCommand::fromArray($input);$before=$this->state();
        try{app(OrderOperationExecutor::class)->execute($this->actor,$cmd,fn()=>throw new LogicException('No work'));$this->fail();}
        catch(ConflictHttpException){}
        $this->assertSame($before,$this->state());
    }

    public function test_finalization_contract_validates_version_and_one_settlement_without_new_number(): void
    {
        $p=$this->product();$quote=QuoteCommand::fromArray($this->input($p));$executor=app(OrderOperationExecutor::class);
        $qr=$executor->execute($this->actor,$quote,fn($ctx)=>$this->prepare($ctx,$quote));
        $coordinator=app(FinancialCoordinator::class);
        $hash=$coordinator->orderTransaction(fn($ctx)=>app(QuoteVersion::class)->current($ctx,$qr['result']['order_id']));
        $cash=$this->cash();
        $cmd=FinalizeOrderCommand::fromArray(['contract_version'=>1,'idempotency_key'=>(string)Str::uuid(),
            'order_id'=>$qr['result']['order_id'],'expected_order_hash'=>$hash,'cash_register_id'=>$cash->id,
            'payments'=>[['method'=>'pix','amount'=>'10.00']]]);
        $r=$executor->execute($this->actor,$cmd,fn($ctx)=>$this->prepare($ctx,$cmd));
        $this->assertSame($qr['result']['number'],$r['result']['number']);
        $this->assertDatabaseCount('orders',1);$this->assertDatabaseCount('order_settlements',1);
        $this->assertSame(1,(int)DB::table('document_sequences')->value('last_value'));
        $this->assertSame(19,$p->fresh()->stock);
        $before=$this->state();
        $again=$executor->execute($this->actor,$cmd,fn()=>throw new LogicException('No replay work'));
        $this->assertSame($r['result'],$again['result']);$this->assertSame($before,$this->state());
        $newInput=['contract_version'=>1,'idempotency_key'=>(string)Str::uuid(),'order_id'=>$qr['result']['order_id'],
            'expected_order_hash'=>$hash,'cash_register_id'=>$cash->id,'payments'=>[['method'=>'pix','amount'=>'10.00']]];
        try{$executor->execute($this->actor,FinalizeOrderCommand::fromArray($newInput),fn()=>throw new LogicException('No second liquidation'));$this->fail();}
        catch(ConflictHttpException){}
        $this->assertSame($before,$this->state());
    }

    public function test_changed_quote_version_is_rejected_before_preparation(): void
    {
        $p=$this->product();$q=QuoteCommand::fromArray($this->input($p));
        $r=app(OrderOperationExecutor::class)->execute($this->actor,$q,fn($ctx)=>$this->prepare($ctx,$q));
        $cash=$this->cash();$cmd=FinalizeOrderCommand::fromArray(['contract_version'=>1,'idempotency_key'=>(string)Str::uuid(),
            'order_id'=>$r['result']['order_id'],'expected_order_hash'=>str_repeat('0',64),'cash_register_id'=>$cash->id,
            'payments'=>[['method'=>'pix','amount'=>'10.00']]]);
        $before=$this->state();
        try{app(OrderOperationExecutor::class)->execute($this->actor,$cmd,fn()=>throw new LogicException('No work'));$this->fail();}
        catch(ConflictHttpException){}
        $this->assertSame($before,$this->state());
    }

    public static function failureTables(): array
    {
        return array_map(fn($t)=>[$t],['financial_operations','operation_receipts','order_item_snapshots','order_settlements','order_cash_effects','order_stock_effects']);
    }

    #[DataProvider('failureTables')]
    public function test_failure_at_every_append_rolls_back_then_original_key_can_retry(string $table): void
    {
        $cash=$this->cash();$p=$this->product();$cmd=CheckoutCommand::fromArray($this->input($p,$cash));
        $once=true;
        DB::connection()->beforeExecuting(function($sql)use(&$once,$table){
            if($once && str_starts_with($sql,'insert into "'.$table.'"')){$once=false;throw new PDOException('Injected append failure');}
        });
        $before=$this->state();$executor=app(OrderOperationExecutor::class);
        try{$executor->execute($this->actor,$cmd,fn($ctx)=>$this->prepare($ctx,$cmd));$this->fail();}
        catch(ServiceUnavailableHttpException $e){$this->assertSame(503,$e->getStatusCode());}
        $this->assertSame(0,DB::connection()->transactionLevel());$this->assertSame($before,$this->state());
        $r=$executor->execute($this->actor,$cmd,fn($ctx)=>$this->prepare($ctx,$cmd));
        $this->assertFalse($r['replayed']);$this->assertDatabaseCount('orders',1);
        $after=$this->state();$executor->execute($this->actor,$cmd,fn()=>throw new LogicException('No replay'));
        $this->assertSame($after,$this->state());
    }

    public static function databaseErrors(): array {return [['Deadlock found when trying to get lock',40001],['Lock wait timeout exceeded; try restarting transaction',1205]];}

    #[DataProvider('databaseErrors')]
    public function test_simulated_retries_keep_key_and_rollback_context(string $message,int $code): void
    {
        $cash=$this->cash();$p=$this->product();$cmd=CheckoutCommand::fromArray($this->input($p,$cash));
        $attempts=0;$blocked=true;
        DB::connection()->beforeExecuting(function($sql)use(&$attempts,&$blocked,$message,$code){
            if($blocked&&str_starts_with($sql,'insert into "operation_receipts"')){$attempts++;throw new PDOException($message,$code);}
        });
        $before=$this->state();$executor=app(OrderOperationExecutor::class);
        try{$executor->execute($this->actor,$cmd,fn($ctx)=>$this->prepare($ctx,$cmd));$this->fail();}
        catch(ServiceUnavailableHttpException){}
        $this->assertSame(3,$attempts);$this->assertSame($before,$this->state());
        $this->assertSame(0,DB::connection()->transactionLevel());
        $blocked=false;$r=$executor->execute($this->actor,$cmd,fn($ctx)=>$this->prepare($ctx,$cmd));
        $this->assertSame($cmd->key(),$r['result']['operation_key']);
    }

    public function test_corrupt_result_is_not_rebuilt_or_overwritten(): void
    {
        $cash=$this->cash();$p=$this->product();$cmd=CheckoutCommand::fromArray($this->input($p,$cash));$executor=app(OrderOperationExecutor::class);
        $executor->execute($this->actor,$cmd,fn($ctx)=>$this->prepare($ctx,$cmd));
        DB::table('financial_operations')->update(['result'=>'{"total":"999.00"}']);
        $before=$this->state();
        try{$executor->execute($this->actor,$cmd,fn()=>throw new LogicException('No repair'));$this->fail();}
        catch(ServiceUnavailableHttpException){}
        $this->assertSame($before,$this->state());
    }

    public function test_context_cannot_escape_transaction_and_legacy_coordinate_contract_is_preserved(): void
    {
        $c=app(FinancialCoordinator::class);
        $this->assertSame('unchanged',$c->coordinate(fn()=>'unchanged'));
        $ctx=$c->coordinate(fn()=>$c->context());
        try{$ctx->assertActive();$this->fail();}catch(LogicException){}
        DB::beginTransaction();
        try{$c->orderTransaction(fn()=>null);$this->fail();}catch(LogicException){}
        finally{DB::rollBack();}
        $this->expectException(LogicException::class);$c->context();
    }

    public static function deniedActors(): array {return [['none'],['inactive'],['estoque']];}

    #[DataProvider('deniedActors')]
    public function test_new_infrastructure_does_not_grant_access(string $profile): void
    {
        $user=User::factory()->create(['active'=>$profile!=='inactive','role'=>'admin']);
        if($profile==='inactive')$user->assignRole('admin');
        if($profile==='estoque')$user->assignRole('estoque');
        $cmd=QuoteCommand::fromArray($this->input($this->product()));$before=$this->state();
        try{app(OrderOperationExecutor::class)->execute($user,$cmd,fn()=>throw new LogicException('No unauthorized work'));$this->fail();}
        catch(AuthorizationException){}
        $this->assertSame($before,$this->state());
    }
}
