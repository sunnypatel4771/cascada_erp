<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Settings Controller for Ramos Module
 *
 * Manages scheduled automation configuration
 */
class Settings extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!staff_can('view', RAMOS_MODULE_NAME)) {
            access_denied();
        }
    }

    /**
     * Default index - redirect to automation schedule
     */
    public function index(): void
    {
        redirect(admin_url('ramos/settings/automation_schedule'));
    }

    /**
     * Redirect to the canonical automation settings page.
     * This action is kept for backward compatibility with any bookmarks or
     * links that point to the old ramos/settings/automation_schedule URL.
     */
    public function automation_schedule(): void
    {
        redirect(admin_url('ramos/automation/settings'));
    }
}
