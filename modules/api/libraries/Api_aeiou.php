<?php defined('BASEPATH') || exit('No direct script access allowed');

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../third_party/node.php';
use Firebase\JWT\JWT as api_JWT;
use Firebase\JWT\Key as api_Key;
use WpOrg\Requests\Requests as api_Requests;

class api_aeiou
{
    private static $bearer = 'k5ua8qyjLZI3mZ21kISqbh3B3v6UUaFw';

    public static function getPurchaseData($code)
    {
        return false;
    }

    public static function verifyPurchase($code)
    {
        return true;
    }

    public function validatePurchase($module_name)
    {
        return true;
    }
}
