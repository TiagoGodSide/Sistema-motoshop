<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('customers', function (Blueprint $t) {
      $t->id();
      $t->string('name');
      $t->string('phone')->nullable();
      $t->string('document')->nullable();     // CPF/CNPJ
      $t->string('email')->nullable();
      $t->timestamps();
    });
    // se quiser, adicione order_id->customer_id depois (fora do escopo agora)
  }
  public function down(): void { Schema::dropIfExists('customers'); }
};
