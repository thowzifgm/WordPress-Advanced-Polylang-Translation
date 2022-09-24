<?php


use Hyyan\WPI\Tools\FlashMessages;
use Hyyan\WPI\MessagesInterface;


class AitWpiPlugin extends Hyyan\WPI\Plugin
{

	public static function canActivate()
	{
		return (isset($GLOBALS['polylang']) and $GLOBALS['polylang'] and defined('WOOCOMMERCE_VERSION'));
	}



	public function activate()
	{
		if(static::canActivate()){
			$this->registerCore();
		}
	}



    protected function registerCore()
    {
        new Hyyan\WPI\Emails();
        // new Hyyan\WPI\Admin\Settings();
        new Hyyan\WPI\Cart();
        new Hyyan\WPI\Login();
        new Hyyan\WPI\Order();
        new Hyyan\WPI\Pages();
        new Hyyan\WPI\Endpoints();
        new Hyyan\WPI\Product\Product();
        new Hyyan\WPI\Taxonomies\Taxonomies();
        new Hyyan\WPI\Media();
        new Hyyan\WPI\Permalinks();
        new Hyyan\WPI\Language();
        new Hyyan\WPI\Coupon();
        new Hyyan\WPI\Reports();
        new Hyyan\WPI\Widgets\SearchWidget();
        new Hyyan\WPI\Widgets\LayeredNav();
        new Hyyan\WPI\Gateways();
        new Hyyan\WPI\Shipping();
        new Hyyan\WPI\Breadcrumb();
    }
}