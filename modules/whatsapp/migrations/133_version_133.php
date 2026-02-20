<?php
defined('BASEPATH') || exit('No direct script access allowed');

class Migration_Version_133 extends App_module_migration
{
    protected $CI;

    public function __construct()
    {
        parent::__construct();
        $this->CI = &get_instance(); // Initialize the CI instance
    }

    public function up()
    {
    }

    public function down()
    {
        
    }
}
