<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password', 100);
            $table->string('name', 100);
            $table->string('recovery_token', 48)->nullable();
            $table->timestamp('recovery_token_expires')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_try')->nullable();
            $table->tinyInteger('failedTries')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
