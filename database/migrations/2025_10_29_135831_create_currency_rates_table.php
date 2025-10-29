<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->char('from_currency', 3)->index();
            $table->char('to_currency', 3)->index();
            $table->decimal('rate', 18, 8);
            $table->string('source', 50);
            $table->timestamp('last_updated')->index();
            $table->timestamps();

            $table->unique(['from_currency', 'to_currency'], 'currency_pair_unique');
            $table->index(['from_currency', 'to_currency', 'last_updated'], 'currency_pair_updated');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};