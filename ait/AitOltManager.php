<?php


class AitOltManager extends PLL_OLT_Manager
{

	public static function run()
	{
		self::instance();
		add_action('plugins_loaded', array(__CLASS__, 'loadAitPluginsTextdomains'));
		add_action('ait-after-framework-load', array(__CLASS__, 'loadAitThemesTextdomains'));
		add_filter('woocommerce_shortcode_products_query', array(__CLASS__, 'addShortcodeLanguageFilter'), 10, 2);
	}



	public static function instance()
	{
		if(empty(self::$instance)){
			self::$instance = new self;
		}

		return self::$instance;
	}



	public static function loadAitPluginsTextdomains()
	{
		$plugins = wp_get_active_and_valid_plugins();
		$network_plugins = is_multisite() ? wp_get_active_network_plugins() : array();

		$all_active_plugins = array_merge($plugins, $network_plugins);

		$locale = (is_admin() and function_exists('get_user_locale')) ? get_user_locale() : get_locale();

		foreach($all_active_plugins as $plugin){
			$slug = dirname(plugin_basename($plugin));
			if(in_array($slug, array('ait-languages'))) continue;
			if(strncmp($slug, "ait-", 4) === 0 or $slug === 'revslider'){ // startsWith
				load_plugin_textdomain($slug, false, "$slug/languages"); // can be in wp-content/languages/plugins/{$slug}
				load_textdomain($slug, POLYLANG_DIR . "/ait/languages/{$slug}/{$slug}-{$locale}.mo");
			}
		}
	}

	public static function addShortcodeLanguageFilter($query_args, $atts)
	{
	    if (function_exists('pll_current_language')) {
	        $query_args['lang'] = isset($query_args['lang']) ? $query_args['lang'] : pll_current_language();
	       
	        return $query_args;
	    }
	}

	public static function loadAitThemesTextdomains()
	{
		global $locale;
		$currentTheme = get_stylesheet();

		$locale = (is_admin() and function_exists('get_user_locale')) ? get_user_locale() : get_locale();

		if(defined('PLL_ADMIN') and PLL_ADMIN){
			$maybeFilteredLocale = apply_filters('theme_locale', $locale, 'ait-admin');
			if(!$maybeFilteredLocale){
				$maybeFilteredLocale = $locale;
			}
			if($themeAdminOverrideFile = aitPath('languages', "/admin-{$maybeFilteredLocale}.mo")){
				load_textdomain('ait-admin', $themeAdminOverrideFile);
			}
			load_textdomain('ait-admin', WP_LANG_DIR . "/themes/{$currentTheme}-admin-{$locale}.mo");
			load_textdomain('ait-admin', POLYLANG_DIR . "/ait/languages/ait-theme/admin-{$maybeFilteredLocale}.mo");
		}else{
			$maybeFilteredLocale = apply_filters('theme_locale', $locale, 'ait');
			if(!$maybeFilteredLocale){
				$maybeFilteredLocale = $locale;
			}
			if($themeOverrideFile = aitPath('languages', "/{$maybeFilteredLocale}.mo")){
				load_textdomain('ait', $themeOverrideFile);
			}
			load_textdomain('ait', WP_LANG_DIR . "/themes/{$currentTheme}-{$locale}.mo");
			load_textdomain('ait', POLYLANG_DIR . "/ait/languages/ait-theme/{$maybeFilteredLocale}.mo");
		}
	}



	public function load_textdomain_mofile($mofile, $domain)
	{
		$this->list_textdomains[] = array(
			'mo' => $mofile,
			'domain' => $domain
		);
		return '';
	}
}