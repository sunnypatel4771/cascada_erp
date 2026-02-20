<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
.ramos-metric-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
}
.ramos-metric-card {
    background: #0f172a;
    color: #e2e8f0;
    border-radius: 16px;
    padding: 20px;
    position: relative;
    overflow: hidden;
}
.ramos-metric-card::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.2);
    pointer-events: none;
}
.ramos-metric-card .ramos-metric__label {
    font-size: 14px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 12px;
}
.ramos-metric-card .ramos-metric__value {
    font-size: 36px;
    font-weight: 600;
    color: #f8fafc;
    margin-bottom: 8px;
}
.ramos-metric-card .ramos-metric__meta {
    font-size: 14px;
    color: #cbd5f5;
}
.ramos-panel h5 {
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 16px;
}
.ramos-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.ramos-list li {
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}
.ramos-list li:last-child {
    border-bottom: none;
}
.ramos-tag {
    border-radius: 999px;
    font-size: 12px;
    padding: 3px 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.ramos-tag--danger {
    background: rgba(248, 113, 113, 0.15);
    color: #b91c1c;
}
.ramos-tag--warning {
    background: rgba(251, 191, 36, 0.15);
    color: #b45309;
}
.ramos-tag--info {
    background: rgba(59, 130, 246, 0.15);
    color: #1d4ed8;
}
.ramos-notification {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 14px;
    margin-bottom: 10px;
}
.ramos-notification:last-child {
    margin-bottom: 0;
}
.ramos-notification__title {
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 4px;
}
.ramos-notification__message {
    color: #475569;
    font-size: 13px;
    margin-bottom: 6px;
}
.ramos-notification__meta {
    font-size: 12px;
    color: #94a3b8;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.ramos-notification__actions button {
    border: none;
    background: none;
    color: #64748b;
    font-size: 12px;
    padding: 0 6px;
    cursor: pointer;
}
.ramos-notification__actions button:hover {
    color: #111827;
}
.ramos-quick-links {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.ramos-quick-links a {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #0f172a;
    text-decoration: none;
    transition: border-color 0.2s ease, background 0.2s ease;
}
.ramos-quick-links a:hover {
    border-color: #6366f1;
    background: #eef2ff;
}
.ramos-empty {
    text-align: center;
    padding: 24px 12px;
    color: #94a3b8;
}
.ramos-routes-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.ramos-route-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px;
}
.ramos-route-card__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}
.ramos-route-card__title {
    font-weight: 600;
    color: #0f172a;
}
.ramos-route-card__stats {
    font-size: 12px;
    color: #94a3b8;
    text-transform: uppercase;
}
.ramos-updated-label {
    font-size: 13px;
    color: #94a3b8;
}
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-col tw-gap-6" data-dashboard-root>
                    <div class="tw-flex tw-flex-col md:tw-flex-row tw-justify-between tw-gap-4">
                        <div>
                            <h4 class="tw-text-2xl tw-font-semibold tw-text-slate-900 tw-mb-1">
                                <?php echo html_escape($title); ?>
                            </h4>
                            <p class="tw-text-slate-500 tw-max-w-3xl tw-mb-0">
                                <?php echo html_escape($tagline); ?>
                            </p>
                        </div>
                        <div class="ramos-updated-label" data-dashboard-updated>
                            <?php echo sprintf(_l('ramos_dashboard_updated_at'), _dt($initial_snapshot['generated_at'] ?? date('Y-m-d H:i:s'))); ?>
                        </div>
                    </div>

                    <div class="ramos-metric-grid" data-dashboard-cards>
                        <div class="ramos-metric-card" data-card="orders">
                            <div class="ramos-metric__label"><?php echo _l('ramos_dashboard_card_orders'); ?></div>
                            <div class="ramos-metric__value">--</div>
                            <div class="ramos-metric__meta"></div>
                        </div>
                        <div class="ramos-metric-card" data-card="inventory">
                            <div class="ramos-metric__label"><?php echo _l('ramos_dashboard_card_inventory'); ?></div>
                            <div class="ramos-metric__value">--</div>
                            <div class="ramos-metric__meta"></div>
                        </div>
                        <div class="ramos-metric-card" data-card="purchases">
                            <div class="ramos-metric__label"><?php echo _l('ramos_dashboard_card_purchases'); ?></div>
                            <div class="ramos-metric__value">--</div>
                            <div class="ramos-metric__meta"></div>
                        </div>
                        <div class="ramos-metric-card" data-card="routes">
                            <div class="ramos-metric__label"><?php echo _l('ramos_dashboard_card_routes'); ?></div>
                            <div class="ramos-metric__value">--</div>
                            <div class="ramos-metric__meta"></div>
                        </div>
                    </div>

                    <div class="row tw-gap-4">
                        <div class="col-lg-8 tw-flex tw-flex-col tw-gap-4">
                            <div class="panel_s ramos-panel">
                                <div class="panel-body">
                                    <h5><?php echo _l('ramos_dashboard_low_stock_heading'); ?></h5>
                                    <div data-low-stock-list>
                                        <div class="ramos-empty"><?php echo _l('ramos_dashboard_low_stock_empty'); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="panel_s ramos-panel">
                                <div class="panel-body">
                                    <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
                                        <h5 class="tw-mb-0"><?php echo _l('ramos_dashboard_purchases_heading'); ?></h5>
                                        <span class="tw-text-xs tw-uppercase tw-text-slate-400"><?php echo _l('ramos_dashboard_purchases_pending'); ?></span>
                                    </div>
                                    <div data-purchase-list>
                                        <div class="ramos-empty"><?php echo _l('ramos_dashboard_purchases_empty'); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="panel_s ramos-panel">
                                <div class="panel-body">
                                    <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
                                        <h5 class="tw-mb-0"><?php echo _l('ramos_dashboard_routes_heading'); ?></h5>
                                        <input type="date" class="form-control" style="max-width: 160px;" data-route-date-picker value="<?php echo html_escape($initial_snapshot['routes']['date'] ?? date('Y-m-d')); ?>">
                                    </div>
                                    <div data-route-list>
                                        <div class="ramos-empty"><?php echo _l('no_results_found'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 tw-flex tw-flex-col tw-gap-4">
                            <div class="panel_s ramos-panel">
                                <div class="panel-body">
                                    <h5><?php echo _l('ramos_dashboard_notifications_heading'); ?></h5>
                                    <div data-notification-list>
                                        <div class="ramos-empty"><?php echo _l('ramos_dashboard_notifications_empty'); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="panel_s ramos-panel">
                                <div class="panel-body">
                                    <h5><?php echo _l('ramos_quick_links_heading'); ?></h5>
                                    <div class="ramos-quick-links">
                                        <?php foreach ($quick_links as $link) : ?>
                                            <a href="<?php echo html_escape($link['href']); ?>">
                                                <i class="<?php echo html_escape($link['icon']); ?>"></i>
                                                <span><?php echo html_escape($link['label']); ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
window.RAMOS_DASHBOARD = window.RAMOS_DASHBOARD || {};
window.RAMOS_DASHBOARD.initialSnapshot = <?php echo json_encode($initial_snapshot, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>
<script>
(function () {
    const state = {
        snapshot: window.RAMOS_DASHBOARD.initialSnapshot || {},
        timer: null,
        routeDate: (window.RAMOS_DASHBOARD.initialSnapshot && window.RAMOS_DASHBOARD.initialSnapshot.routes && window.RAMOS_DASHBOARD.initialSnapshot.routes.date) || (new Date()).toISOString().slice(0, 10),
    };

    const translations = {
        progress: <?php echo json_encode(_l('ramos_routes_board_progress_label')); ?>,
    };

    const root = document.querySelector('[data-dashboard-root]');
    if (!root) {
        return;
    }

    const els = {
        updated: root.querySelector('[data-dashboard-updated]'),
        cards: root.querySelector('[data-dashboard-cards]'),
        lowStock: root.querySelector('[data-low-stock-list]'),
        purchases: root.querySelector('[data-purchase-list]'),
        routes: root.querySelector('[data-route-list]'),
        notifications: root.querySelector('[data-notification-list]'),
        routePicker: root.querySelector('[data-route-date-picker]'),
    };

    function escapeHtml(str) {
        if (str === null || str === undefined) {
            return '';
        }
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatTemplate(template, values) {
        let output = template || '';
        values.forEach(function (value) {
            output = output.replace('%s', value);
        });
        return output;
    }

    function formatNumber(value) {
        if (typeof Intl !== 'undefined') {
            return new Intl.NumberFormat().format(value || 0);
        }
        return (value || 0).toString();
    }

    function formatTimestamp(value) {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value || '';
        }
        return date.toLocaleString();
    }

    function renderCards(snapshot) {
        if (!els.cards) {
            return;
        }

        const ordersCard = els.cards.querySelector('[data-card="orders"]');
        if (ordersCard && snapshot.orders) {
            const readyPercent = snapshot.orders.ready_percent || 0;
            ordersCard.querySelector('.ramos-metric__value').textContent = formatNumber(snapshot.orders.total || 0);
            ordersCard.querySelector('.ramos-metric__meta').textContent = readyPercent + '% ready';
        }

        const inventoryCard = els.cards.querySelector('[data-card="inventory"]');
        if (inventoryCard && snapshot.inventory) {
            inventoryCard.querySelector('.ramos-metric__value').textContent = formatNumber(snapshot.inventory.low_count || 0);
            inventoryCard.querySelector('.ramos-metric__meta').textContent = snapshot.inventory.low_count > 0
                ? '<?php echo _l('ramos_dashboard_low_stock_heading'); ?>'
                : '<?php echo _l('ramos_dashboard_low_stock_empty'); ?>';
        }

        const purchaseCard = els.cards.querySelector('[data-card="purchases"]');
        if (purchaseCard && snapshot.purchases) {
            purchaseCard.querySelector('.ramos-metric__value').textContent = formatNumber(snapshot.purchases.pending_total || 0);
            const delayedCount = (snapshot.purchases.delayed || []).length;
            purchaseCard.querySelector('.ramos-metric__meta').textContent =
                '<?php echo _l('ramos_dashboard_purchases_delayed'); ?>: ' + formatNumber(delayedCount);
        }

        const routesCard = els.cards.querySelector('[data-card="routes"]');
        if (routesCard && snapshot.routes) {
            const counts = snapshot.routes.counts || {};
            routesCard.querySelector('.ramos-metric__value').textContent = formatNumber(snapshot.routes.total || 0);
            routesCard.querySelector('.ramos-metric__meta').textContent =
                'G: ' + formatNumber(counts.ready || 0) + ' · Y: ' + formatNumber(counts.partial || 0) + ' · R: ' + formatNumber(counts.delayed || 0);
        }
    }

    function renderLowStock(snapshot) {
        if (!els.lowStock) {
            return;
        }

        const alerts = (snapshot.inventory && snapshot.inventory.alerts) || [];

        if (!alerts.length) {
            els.lowStock.innerHTML = '<div class="ramos-empty"><?php echo _l('ramos_dashboard_low_stock_empty'); ?></div>';
            return;
        }

        const items = alerts.map(function (item) {
            const badgeClass = item.status === 'red' ? 'ramos-tag ramos-tag--danger' : 'ramos-tag ramos-tag--warning';
            return `
                <li>
                    <div class="tw-flex tw-justify-between tw-items-start tw-gap-3">
                        <div>
                            <div class="tw-font-semibold tw-text-slate-800">${escapeHtml(item.item_name)}</div>
                            <div class="tw-text-xs tw-text-slate-500">${formatNumber(item.quantity)} ${escapeHtml(item.unit || '')}</div>
                        </div>
                        <span class="${badgeClass}">${escapeHtml(item.status_label)}</span>
                    </div>
                    <div class="tw-text-xs tw-text-slate-400 tw-mt-1">
                        <?php echo _l('ramos_inventory_form_safety_stock'); ?>: ${formatNumber(item.safety_stock)}
                    </div>
                </li>
            `;
        }).join('');

        els.lowStock.innerHTML = `<ul class="ramos-list">${items}</ul>`;
    }

    function renderPurchases(snapshot) {
        if (!els.purchases) {
            return;
        }

        const delayed = snapshot.purchases && snapshot.purchases.delayed ? snapshot.purchases.delayed : [];
        const recent = snapshot.purchases && snapshot.purchases.recent ? snapshot.purchases.recent : [];

        if (!delayed.length && !recent.length) {
            els.purchases.innerHTML = '<div class="ramos-empty"><?php echo _l('ramos_dashboard_purchases_empty'); ?></div>';
            return;
        }

        const delayedHtml = delayed.map(function (batch) {
            return `
                <li>
                    <div class="tw-flex tw-justify-between tw-items-center tw-gap-3">
                        <div>
                            <div class="tw-font-semibold tw-text-slate-800">${escapeHtml(batch.batch_code)}</div>
                            <div class="tw-text-xs tw-text-slate-500">${escapeHtml(batch.supplier_name || <?php echo json_encode(_l('ramos_purchases_unassigned_supplier')); ?>)}</div>
                        </div>
                        <span class="ramos-tag ramos-tag--danger"><?php echo _l('ramos_dashboard_purchases_delayed'); ?></span>
                    </div>
                    <div class="tw-text-xs tw-text-slate-400 tw-mt-1"><?php echo _l('ramos_purchases_table_status'); ?>: ${escapeHtml(batch.status)}</div>
                </li>
            `;
        }).join('');

        const recentHtml = recent.map(function (batch) {
            return `
                <li>
                    <div class="tw-flex tw-justify-between tw-items-center tw-gap-3">
                        <div>
                            <div class="tw-font-semibold tw-text-slate-800">${escapeHtml(batch.batch_code)}</div>
                            <div class="tw-text-xs tw-text-slate-500">${escapeHtml(batch.supplier_name || <?php echo json_encode(_l('ramos_purchases_unassigned_supplier')); ?>)}</div>
                        </div>
                        <span class="ramos-tag ramos-tag--info">${escapeHtml(batch.status)}</span>
                    </div>
                </li>
            `;
        }).join('');

        els.purchases.innerHTML = `
            ${delayed.length ? '<p class="tw-text-xs tw-text-rose-500 tw-uppercase tw-font-semibold tw-mb-2"><?php echo _l('ramos_dashboard_purchases_delayed'); ?></p><ul class="ramos-list">' + delayedHtml + '</ul>' : ''}
            ${recent.length ? '<p class="tw-text-xs tw-text-slate-400 tw-uppercase tw-font-semibold tw-mt-4 tw-mb-2"><?php echo _l('ramos_purchases_recent_batches'); ?></p><ul class="ramos-list">' + recentHtml + '</ul>' : ''}
        `;
    }

    function renderRoutes(snapshot) {
        if (!els.routes) {
            return;
        }

        const routes = (snapshot.routes && snapshot.routes.routes) || [];
        if (!routes.length) {
            els.routes.innerHTML = '<div class="ramos-empty"><?php echo _l('no_results_found'); ?></div>';
            return;
        }

        const list = routes.slice(0, 5).map(function (route) {
            const state = route.board_state || {};
            return `
                <div class="ramos-route-card">
                    <div class="ramos-route-card__header">
                        <div>
                            <div class="ramos-route-card__title">${escapeHtml(route.vehicle_label || ('#' + route.id))}</div>
                            <div class="tw-text-xs tw-text-slate-500">${escapeHtml(route.route_date || '')} · ${escapeHtml(route.start_time || '--')}</div>
                        </div>
                        <span class="ramos-tag ramos-tag--${(state.key === 'delayed' ? 'danger' : (state.key === 'partial' ? 'warning' : 'info'))}">${escapeHtml(state.label || '')}</span>
                    </div>
                    <div class="tw-flex tw-justify-between tw-text-xs tw-text-slate-500">
                        <span><?php echo _l('ramos_routes_table_progress'); ?></span>
                        <span>${formatTemplate(translations.progress, [formatNumber(route.completed_stops || 0), formatNumber(route.total_stops || 0)])}</span>
                    </div>
                </div>
            `;
        }).join('');

        els.routes.innerHTML = `<div class="ramos-routes-list">${list}</div>`;
    }

    function renderNotifications(snapshot) {
        if (!els.notifications) {
            return;
        }

        const notifications = snapshot.notifications || [];
        if (!notifications.length) {
            els.notifications.innerHTML = '<div class="ramos-empty"><?php echo _l('ramos_dashboard_notifications_empty'); ?></div>';
            return;
        }

        const list = notifications.map(function (notification) {
            const severityClass = notification.severity === 'danger'
                ? 'ramos-tag ramos-tag--danger'
                : notification.severity === 'warning'
                    ? 'ramos-tag ramos-tag--warning'
                    : 'ramos-tag ramos-tag--info';

            const resolved = !!notification.resolved_at;
            const acknowledged = !!notification.acknowledged_at;

            return `
                <div class="ramos-notification" data-notification="${notification.id}">
                    <div class="tw-flex tw-justify-between tw-items-start tw-gap-3">
                        <div class="ramos-notification__title">${escapeHtml(notification.title)}</div>
                        <span class="${severityClass}">${escapeHtml((notification.type || '').replace(/_/g, ' ').toUpperCase())}</span>
                    </div>
                    <div class="ramos-notification__message">${escapeHtml(notification.message)}</div>
                    <div class="ramos-notification__meta">
                        <span>${escapeHtml(notification.time_ago)}</span>
                        <div class="ramos-notification__actions">
                            ${acknowledged ? '' : `<button data-notification-action="acknowledge" data-id="${notification.id}"><?php echo _l('ramos_notifications_mark_read'); ?></button>`}
                            ${resolved ? '' : `<button data-notification-action="resolve" data-id="${notification.id}"><?php echo _l('ramos_notifications_mark_resolved'); ?></button>`}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        els.notifications.innerHTML = list;
    }

    function render(snapshot) {
        if (!snapshot || !snapshot.generated_at) {
            return;
        }

        if (els.updated) {
            els.updated.textContent = '<?php echo _l('ramos_dashboard_updated_at'); ?>'.replace('%s', formatTimestamp(snapshot.generated_at));
        }

        renderCards(snapshot);
        renderLowStock(snapshot);
        renderPurchases(snapshot);
        renderRoutes(snapshot);
        renderNotifications(snapshot);
    }

    function fetchSnapshot() {
        const params = new URLSearchParams();
        if (state.routeDate) {
            params.append('route_date', state.routeDate);
        }

        $.getJSON(admin_url + 'ramos/snapshot?' + params.toString())
            .done(function (response) {
                if (response && response.success) {
                    state.snapshot = response.data;
                    render(state.snapshot);
                }
            });
    }

    function postNotificationAction(id, action) {
        const payload = { action: action };
        if (typeof csrfData !== 'undefined') {
            payload[csrfData.token_name] = csrfData.hash;
        }

        $.ajax({
            url: admin_url + 'ramos/notifications/' + id,
            type: 'POST',
            data: payload,
            dataType: 'json',
        }).done(function (response) {
            if (response && response.csrf && typeof csrfData !== 'undefined') {
                csrfData.token_name = response.csrf.name || csrfData.token_name;
                csrfData.hash = response.csrf.hash;
            }
            fetchSnapshot();
        });
    }

    root.addEventListener('click', function (event) {
        const actionBtn = event.target.closest('[data-notification-action]');
        if (!actionBtn) {
            return;
        }
        event.preventDefault();
        const action = actionBtn.getAttribute('data-notification-action');
        const id = actionBtn.getAttribute('data-id');
        if (!action || !id) {
            return;
        }
        postNotificationAction(id, action);
    });

    if (els.routePicker) {
        els.routePicker.addEventListener('change', function (event) {
            state.routeDate = event.target.value;
            fetchSnapshot();
        });
    }

    render(state.snapshot);
    state.timer = setInterval(fetchSnapshot, 15000);
})();
</script>
<?php init_tail(); ?>
