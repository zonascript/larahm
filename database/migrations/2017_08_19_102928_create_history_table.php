<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Migration auto-generated by Sequel Pro Laravel Export
 * @see https://github.com/cviebrock/sequel-pro-laravel-export
 */
class CreateHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('history', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->float('amount', 10, 5)->nullable();
            $table->enum('type', ['deposit', 'bonus', 'penality', 'earning', 'withdrawal', 'commissions', 'early_deposit_release', 'early_deposit_charge', 'release_deposit', 'add_funds', 'withdraw_pending', 'exchange_in', 'exchange_out', 'internal_transaction_spend', 'internal_transaction_receive'])->nullable();
            $table->text('description');
            $table->float('actual_amount', 10, 5)->nullable();
            $table->dateTime('date')->default('2017-01-01 00:00:00');
            $table->string('str', 40);
            $table->integer('ec');
            $table->integer('deposit_id');
            $table->string('payment_batch_num', 15)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('history');
    }
}
