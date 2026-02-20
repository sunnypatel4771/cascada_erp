<?php

defined('BASEPATH') || exit('No direct script access allowed');

class Migration_Version_108 extends App_module_migration
{
    protected $CI;

    public function __construct()
    {
        parent::__construct();
        $this->CI =& get_instance();
        $this->CI->load->database(); // Load the database library
    }

    public function up()
    {
       
    }

    public function down()
    {
        // Reverse the changes if needed
        // For example, if you want to drop the 'type' and 'type_id' columns
        // You can implement the down() method to perform the rollback
        // This is useful for reverting changes during rollback of migrations
    }
}
