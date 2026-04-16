<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_122 extends App_module_migration
{
	public function up()
	{
		if (option_exists('omni_hide_guest_product_catalog')) {
			update_option('omni_hide_guest_product_catalog', 1);
		} else {
			add_option('omni_hide_guest_product_catalog', 1);
		}
	}
}
