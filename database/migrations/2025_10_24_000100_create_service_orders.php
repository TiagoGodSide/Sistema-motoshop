<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('service_orders', function (Blueprint $t) {
      $t->id();
      $t->string('number')->unique();          // OS-000001
      $t->unsignedBigInteger('customer_id')->nullable();
      $t->string('vehicle')->nullable();       // ex.: Moto/Modelo
      $t->string('plate')->nullable();
      $t->enum('status',['opened','approved','in_service','ready','delivered','canceled'])->default('opened');
      $t->date('due_date')->nullable();        // previsão
      $t->decimal('labor_total',10,2)->default(0);
      $t->decimal('parts_total',10,2)->default(0);
      $t->decimal('discount',10,2)->default(0);
      $t->decimal('total',10,2)->default(0);
      $t->text('notes')->nullable();
      $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // responsável
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('service_orders'); }
};
