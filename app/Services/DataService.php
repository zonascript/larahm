<?php

namespace App\Services;

use App\Models\FakeHistory;
use App\Models\FakeUser;
use App\Models\History;
use Carbon\Carbon;

class DataService
{
    public function deposits()
    {
        $fakes = FakeHistory::where('type', 1)
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get()
            ->transform(function($item) {
                return [
                    'username' => $item->user->username,
                    'amount' => $item->amount,
                    'payment' => $item->payment,
                    'time' => $item->created_at,
                ];
            });
        $histories = History::select(['user_id', 'amount', 'ec', 'date'])
            ->where('type', 'deposit')
            ->orderBy('date', 'desc')
            ->take(20)
            ->get()
            ->transform(function($item) {
                return [
                    'username' => $item->user->username ?? 'EmmNisen',
                    'amount' => $item->amount,
                    'payment' => $item->ec,
                    'time' => $item->date,
                ];
            });
        return $fakes->union($histories)->sortBy('time');
    }

    public function payouts()
    {
        $fakes = FakeHistory::where('type', 1)
            ->orderBy('time', 'desc')
            ->take(20)
            ->get()
            ->transform(function($item) {
                return [
                    'username' => $item->user->username,
                    'amount' => $item->amount,
                    'payment' => $item->payment,
                    'time' => $item->created_at,
                ];
            });
        $histories = History::select(['user_id', 'amount', 'ec', 'date'])
            ->where('type', 'withdrawal')
            ->orderBy('date', 'desc')
            ->take(20)
            ->get()
            ->transform(function($item) {
                return [
                    'username' => $item->user->username,
                    'amount' => $item->amount,
                    'payment' => $item->ec,
                    'time' => $item->date,
                ];
            });
        return $fakes->union($histories)->sortBy('time');
    }

    public function fakeDeposit()
    {
        $user = FakeUser::inRandomOrder()->first();
        $history = FakeHistory::create([
            'user_id' => $user->id,
            'amount' => $this->generateAmount(),
            'payment' => $user->payment ?: $this->generatePayment(),
            'type' => 1,
            'created_at' => Carbon::now(),
        ]);
        $user->payment = $history->payment;
        $user->amount = $history->amount;
        $user->save();
    }

    public function fakePayout()
    {
        if (FakeUser::where('type', 1)->count() < 100) {
            return;
        }
        $user = FakeUser::where('amount', '>', 0)->first();
        if (! $user) {
            return;
        }
        $history = FakeHistory::create([
            'user_id' => $user->id,
            'amount' => max($user->amount * mt_rand(1, 10) / 10, 0.1),
            'payment' => $user->payment,
            'type' => 1,
            'created_at' => Carbon::now(),
        ]);
        $user->amount = $user->amount - $history->amount;
        $user->save();
    }

    public function generateAmount()
    {
        return collect([
            array_fill(0, 50, 1),
            array_fill(0, 50, 2),
            array_fill(0, 50, 3),
            array_fill(0, 50, 4),
            array_fill(0, 200, 5),
            array_fill(0, 2000, 10),
            array_fill(0, 1000, 20),
            array_fill(0, 200, 50),
            array_fill(0, 100, 100),
            array_fill(0, 20, 500),
            array_fill(0, 10, 1000),
        ])->flatten()->shuffle()->random(mt_rand(1, 10))->sum();
    }

    public function generatePayment()
    {
        return collect([
            array_fill(0, 1, 10),
            array_fill(0, 2, 5),
            array_fill(0, 3, 2),
        ])->flatten()->shuffle()->random();
    }
}
