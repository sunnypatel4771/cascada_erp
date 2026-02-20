<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (empty($routes)) : ?>
    <tr>
        <td colspan="7">
            <span class="tw-text-sm tw-text-slate-500"><?php echo _l('ramos_routes_none_created'); ?></span>
        </td>
    </tr>
<?php else : ?>
    <?php foreach ($routes as $route) :
        $badge = ramos_route_status_badge_class($route['status']);
        $label = ramos_route_statuses()[$route['status']] ?? $route['status'];
        $completed = (int) ($route['completed_stops'] ?? 0);
        $total     = (int) ($route['total_stops'] ?? 0);
        ?>
        <tr data-route-id="<?php echo (int) $route['id']; ?>">
            <td><?php echo html_escape($route['vehicle_label'] ?: _l('ramos_routes_default_vehicle', $route['id'])); ?></td>
            <td><?php echo $route['start_time'] ? html_escape($route['start_time']) : '--'; ?></td>
            <td><?php echo (int) $route['capacity']; ?></td>
            <td><?php echo $total; ?></td>
            <td><?php echo $total === 0 ? '--' : ($completed . ' / ' . $total); ?></td>
            <td>
                <span class="label <?php echo $badge; ?>" data-route-status><?php echo html_escape($label); ?></span>
            </td>
            <td class="tw-text-right">
                <a href="<?php echo admin_url('ramos/routes/view/' . $route['id']); ?>" class="btn btn-default btn-icon">
                    <i class="fa-regular fa-eye"></i>
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
