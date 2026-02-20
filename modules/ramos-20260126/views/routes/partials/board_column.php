<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$routeId          = (int) ($route['id'] ?? 0);
$state            = $route['board_state'] ?? [];
$startDisplay     = !empty($route['start_time']) ? $route['start_time'] : '--';
$totalStops       = (int) ($route['total_stops'] ?? 0);
$completedStops   = (int) ($route['completed_stops'] ?? 0);
$pendingStops     = max(0, $totalStops - $completedStops);
$stops            = $route['stops'] ?? [];
$progressTemplate = $progress_template ?? _l('ramos_routes_board_progress_label');
$pendingTemplate  = $pending_template ?? _l('ramos_routes_board_pending_label');
$emptyText        = _l('ramos_routes_board_empty_route');
?>
<div class="col-lg-3 col-md-4 col-sm-6 ramos-route-column tw-flex-none" data-route-id="<?php echo $routeId; ?>">
    <div class="panel_s tw-h-full tw-flex tw-flex-col">
        <div class="panel-heading">
            <div class="tw-flex tw-justify-between tw-items-start tw-gap-3">
                <div>
                    <h5 class="tw-text-base tw-font-semibold tw-mb-1">
                        <?php echo html_escape($route['vehicle_label'] ?? _l('ramos_routes_default_vehicle', $routeId)); ?>
                    </h5>
                    <div class="tw-text-xs tw-text-slate-500">
                        <?php echo _d($route['route_date'] ?? date('Y-m-d')); ?> &bull; <?php echo html_escape($startDisplay); ?>
                    </div>
                </div>
                <div class="tw-text-right">
                    <span class="label <?php echo html_escape($state['badge_class'] ?? 'label-default'); ?>" data-route-state data-route-id="<?php echo $routeId; ?>">
                        <?php echo html_escape($state['label'] ?? ''); ?>
                    </span>
                    <div class="tw-text-xs tw-text-slate-500" data-route-progress data-route-id="<?php echo $routeId; ?>" data-template="<?php echo html_escape($progressTemplate); ?>">
                        <?php echo sprintf($progressTemplate, $completedStops, $totalStops); ?>
                    </div>
                    <div class="tw-text-xs tw-text-slate-400" data-route-pending data-route-id="<?php echo $routeId; ?>" data-template="<?php echo html_escape($pendingTemplate); ?>">
                        <?php echo sprintf($pendingTemplate, $pendingStops); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="panel-body tw-flex-grow">
            <ul class="list-unstyled ramos-stop-list" data-route-id="<?php echo $routeId; ?>" data-empty-text="<?php echo html_escape($emptyText); ?>">
                <?php if (empty($stops)) : ?>
                    <li class="ramos-stop-empty"><?php echo html_escape($emptyText); ?></li>
                <?php else : ?>
                    <?php foreach ($stops as $stop) :
                        $priority      = $stop['priority'] ?? '';
                        $priorityLabel = $priority !== '' ? (ramos_order_priorities()[$priority] ?? ucfirst($priority)) : '';
                        $statusLabel   = ucfirst(str_replace('_', ' ', $stop['status'] ?? 'pending'));
                        ?>
                        <li class="ramos-stop-card" data-stop-id="<?php echo (int) ($stop['id'] ?? 0); ?>">
                            <div class="tw-flex tw-justify-between tw-gap-3 tw-items-start">
                                <div>
                                    <div class="tw-text-sm tw-font-semibold tw-text-slate-700">
                                        <?php echo html_escape($stop['order_number'] ?? ('#' . (int) ($stop['order_id'] ?? 0))); ?>
                                    </div>
                                    <div class="tw-text-xs tw-text-slate-500 tw-break-words">
                                        <?php echo html_escape($stop['customer_name'] ?? ''); ?>
                                    </div>
                                </div>
                                <?php if ($priorityLabel !== '') : ?>
                                    <span class="label <?php echo html_escape(ramos_order_priority_badge_class($priority)); ?>"><?php echo html_escape($priorityLabel); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="tw-text-xs tw-text-slate-500 tw-mt-2 tw-break-words">
                                <i class="fa-regular fa-location-dot tw-mr-1"></i><?php echo html_escape($stop['delivery_address'] ?? ''); ?>
                            </div>
                            <div class="tw-flex tw-justify-between tw-items-center tw-mt-2 tw-text-xs tw-text-slate-500">
                                <span>
                                    <i class="fa-regular fa-clock tw-mr-1"></i>
                                    <?php echo !empty($stop['eta']) ? html_escape(_dt($stop['eta'])) : '--'; ?>
                                </span>
                                <span><?php echo html_escape($statusLabel); ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
