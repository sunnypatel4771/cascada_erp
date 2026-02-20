<?php

defined('BASEPATH') || exit('No direct script access allowed');

class Migration_Version_110 extends App_module_migration
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
        
    }
}
