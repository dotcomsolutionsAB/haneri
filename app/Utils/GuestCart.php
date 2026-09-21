<?php

namespace App\Utils;

class GuestCart
{
    /**
     * Guest carts are keyed by a random token (UUID) stored in t_carts.user_id.
     * Registered users are keyed by their numeric id in the same column, so a
     * purely numeric guest id must never be accepted: it would let anyone read,
     * change or take over a registered user's cart.
     */
    public static function valid(mixed $id): bool
    {
        return is_string($id) && preg_match('/^(?!\d+$)[A-Za-z0-9-]{8,64}$/', $id) === 1;
    }
}
