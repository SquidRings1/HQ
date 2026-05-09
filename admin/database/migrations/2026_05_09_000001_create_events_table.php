<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('about')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->date('date');
            $table->string('starttime')->nullable();
            $table->string('endtime')->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->foreignId('created_by_admin_id')->nullable();
            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
