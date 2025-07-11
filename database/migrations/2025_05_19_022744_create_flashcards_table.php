<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('flashcards', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('front');
        $table->text('back');
        $table->string('category');
        $table->timestamp('last_reviewed')->nullable();
        $table->timestamp('next_review')->nullable();
        $table->timestamps();
    });
}

};
