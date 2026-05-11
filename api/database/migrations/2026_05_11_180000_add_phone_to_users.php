<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('country_code', 8)->nullable()->after('email');
            $table->string('phone', 24)->nullable()->after('country_code');
            $table->string('fname')->nullable()->after('phone');
            $table->string('lname')->nullable()->after('fname');
            $table->index(['country_code', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['country_code', 'phone']);
            $table->dropColumn(['country_code', 'phone', 'fname', 'lname']);
        });
    }
};
