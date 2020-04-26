<?php

use Illuminate\Database\Schema\Blueprint;;
use Illuminate\Support\Facades\Schema;;

/**
 * Migration for users table
 */
class CreateUsersTable
{
    /**
     * Migrate the table
     */
    public function up()
    {
        Schema::create('users', function (BluePrint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->timestamp('email_verified_at');
            $table->string('password');
            $table->string('remember_token');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
        });
    }
}
