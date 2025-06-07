<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Order $order)
    {
        return $user->id === $order->user_id || $user->role === User::ROLE_ADMIN;
    }

    public function update(User $user, Order $order)
    {
        return $user->role === User::ROLE_ADMIN;
    }
}