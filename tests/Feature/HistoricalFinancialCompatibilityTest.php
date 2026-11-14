<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\FinancialCoordinator;
use App\Services\OperationReceiptStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class HistoricalFinancialCompatibilityTest extends TestCase
{
    public static function versions(): array {return [[41],[42]];}

    #[DataProvider('versions')]
    public function test_genuine_legacy_schema_upgrade_and_read_only_recovery(int $version): void
    {
        $this->assertSame('testing',app()->environment());
        $this->assertSame('sqlite',config('database.default'));
        $this->assertSame(':memory:',config('database.connections.sqlite.database'));
        config(['database.connections.mysql'=>null,'database.connections.sqlite.url'=>null,
            'cache.default'=>'array','session.driver'=>'array','logging.default'=>'null']);
        $stage=glob(database_path('migrations/2026_11_12_0001*.php'));
        $legacy=array_values(array_diff(glob(database_path('migrations/*.php')),$stage));
        if($version===41)$legacy=array_values(array_filter($legacy,fn($p)=>basename($p)!=='2026_11_12_000001_add_results_to_financial_operations.php'));
        Artisan::call('migrate',['--path'=>$legacy,'--realpath'=>true,'--force'=>true]);
        $this->assertDatabaseCount('migrations',$version===41?26:27);
        $actor=User::factory()->create(['active'=>true]);
        $product=Product::create(['name'=>'Legacy','sku'=>'HISTORY','price'=>'10.00','stock'=>9]);
        $cash=DB::table('cash_registers')->insertGetId(['id'=>701,'status'=>'closed','opening_amount'=>'10.00','closing_amount'=>$version===41?'10.00':'11.00','user_opened_id'=>$actor->id]);
        $key=(string)Str::uuid();
        $hash=fn($payload)=>hash('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $insert=function($identity,$operation,$payload,$result=null,$movementId=null)use($actor,$cash,$hash,$version){
            $row=['idempotency_key'=>$identity,'operation'=>$operation,'user_id'=>$actor->id,'request_hash'=>$hash($payload),
                'cash_register_id'=>$cash,'created_at'=>DB::raw('CURRENT_TIMESTAMP')];
            if($version===42)$row+=['result'=>$result===null?null:json_encode($result,JSON_THROW_ON_ERROR),'cash_movement_id'=>$movementId];
            DB::table('financial_operations')->insert($row);
        };
        $insert($key,'cash.open',['opening_amount'=>'10.00','open_notes'=>null]);
        $movementKey=(string)Str::uuid();$closeKey=(string)Str::uuid();
        $movementPayload=['cash_register_id'=>$cash,'type'=>'IN','amount'=>'1.00','reason'=>'Legacy','payment_method'=>'pix'];
        $closePayload=['cash_register_id'=>$cash,'close_notes'=>'Legacy close'];
        if($version===42){
            $movement=DB::table('cash_movements')->insertGetId(['cash_register_id'=>$cash,'type'=>'IN','amount'=>'1.00','reason'=>'Legacy','payment_method'=>'pix','user_id'=>$actor->id]);
            $insert($movementKey,'cash.movement',$movementPayload,['cash_register_id'=>$cash,'cash_movement_id'=>(int)$movement,'amount'=>'1.00','type'=>'IN'],$movement);
            $insert($closeKey,'cash.close',$closePayload,['cash_register_id'=>$cash,'closing_amount'=>'11.00']);
        }
        $tables=['cash_registers','cash_movements','financial_operations','products','users'];
        $read=function()use($tables){
            $data=[];
            foreach($tables as $t){
                $data[$t]=DB::table($t)->orderBy('id')->get()->map(function($r)use($t){
                    if($t==='products')unset($r->stock_policy);
                    return (array)$r;
                })->all();
            }
            return json_encode($data,JSON_THROW_ON_ERROR);
        };
        $before=$read();$ddl=[];
        DB::listen(function($query)use(&$ddl){$ddl[]=$query->sql;});
        Artisan::call('migrate',['--path'=>$stage,'--realpath'=>true,'--force'=>true]);
        $this->assertSame($before,$read());
        foreach($ddl as $sql)$this->assertDoesNotMatchRegularExpression('/__temp__|drop table|rename to/i',$sql);
        $this->assertNull(DB::table('products')->where('id',$product->id)->value('stock_policy'));
        $this->assertDatabaseCount('operation_receipts',0);
        $c=app(FinancialCoordinator::class);
        $r=$c->open($actor,['idempotency_key'=>strtoupper($key),'opening_amount'=>'0010.0']);
        $this->assertSame(['cash_register_id'=>$cash,'replayed'=>true],$r);
        if($version===42){
            $m=$c->movement($actor,['idempotency_key'=>$movementKey]+$movementPayload);
            $closed=$c->close($actor,['idempotency_key'=>$closeKey]+$closePayload);
            $this->assertTrue($m['replayed']);
            $this->assertSame('11.00',$closed['closing_amount']);
            $this->assertTrue($closed['replayed']);
        }
        try{
            $c->coordinate(fn()=>app(OperationReceiptStore::class)->recover($c->context(),$actor,$key,'order.quote.create',str_repeat('a',64)));
            $this->fail('Historical UUID must remain reserved');
        }catch(ConflictHttpException){}
        $this->assertDatabaseCount('operation_receipts',0);
        $this->assertDatabaseCount('document_sequences',0);
        $this->assertSame($before,$read());
        $this->assertSame([],DB::select('PRAGMA foreign_key_check'));
    }
}
