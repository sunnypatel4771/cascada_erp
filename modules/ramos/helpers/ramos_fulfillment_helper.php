<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Extension point for route-level packaging readiness.
 * Return false when a future packaging module marks the route as not ready to depart.
 *
 * @param int $routeId Ramos route id
 * @return bool True if packaging requirements are satisfied (default: no extra gate)
 */
function ramos_route_packaging_complete(int $routeId): bool
{
    return true;
}
