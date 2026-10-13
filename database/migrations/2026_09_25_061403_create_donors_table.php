<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
           
            $table->string('blood_group')->nullable(); 
            $table->string('gender')->nullable();
            $table->string('address')->nullable(); 
            $table->date('last_donation_date')->nullable();
            $table->boolean('is_available')->default(true);
            
            $table->boolean('hep_b')->nullable();
            $table->boolean('hep_c')->nullable();
            $table->boolean('hiv')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donors');
    }
};