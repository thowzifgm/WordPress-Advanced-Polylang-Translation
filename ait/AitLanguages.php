<?php


class AitLanguages
{

	static public $pageSlug;


	public static function before()
	{
		static::maybeAddDefaultOptions();
		static::maybeUpdateOptionsFor20();
	}



	public static function after()
	{
		AitOltManager::run();

		static::modifyLanguageList();
		static::adminBarLanguageSwitcher();
		static::adminMenu();
		static::enqueueAssets();
		static::afterDemoContentImport();
		static::clearThemeCache();
		static::migrateTo20();
		static::handleUserLang();
		static::removeSomeModules();
		static::changeAdminPageUlr();
		static::updateUserLocaleToFirstLang();
		static::adminNotices();
		static::wcAjaxEndpoint();
		static::langParamInRestTermQuery();
	}



	public static function maybeUpdateOptionsFor20()
	{
		$options = get_option('polylang', array());
		if(!empty($options['version']) and version_compare($options['version'], '1.4-dev', '<=')){
			update_option('polylang_13x', $options); // backup old options just for case

			// Change some default settings of polylang, they are needed for WooPoly corect behaviour
			$options['force_lang'] = 1;

			// Add WooCommerce CPTs and Tax
			if(!in_array('product', $options['post_types'])){
				$options['post_types'][] = 'product';
			}

			if(!in_array('product_cat', $options['taxonomies'])){
				$options['taxonomies'][] = 'product_cat';
			}

			if(!in_array('product_tag', $options['taxonomies'])){
				$options['taxonomies'][] = 'product_tag';
			}

			if(!in_array('product_shipping_class', $options['taxonomies'])){
				$options['taxonomies'][] = 'product_shipping_class';
			}

			$options['post_types'] = array_unique($options['post_types']);
			$options['taxonomies'] = array_unique($options['taxonomies']);

			$options = apply_filters('ait-languages-options', $options);

			update_option('polylang', $options);
			update_option('_ait-languages_should_migrate', 'yes');
		}
	}



	protected static function modifyLanguageList()
	{
		add_filter('pll_predefined_languages', function($languages){

			$supportedByAit = apply_filters('ait-supported-languages', array(
				'bg_BG',
				'cs_CZ',
				'da_DK',
				'de_DE',
				'el',
				'en_US',
				'es_ES',
				'fi',
				'fr_FR',
				'hi_IN',
				'hr',
				'hu_HU',
				'id_ID',
				'it_IT',
				'nl_NL',
				'pl_PL',
				'pt_BR',
				'pt_PT',
				'ro_RO',
				'ru_RU',
				'sk_SK',
				'sr_RS',
				'sq',
				'sv_SE',
				'tr_TR',
				'uk',
				'vi',
				'zh_CN',
				'zh_TW',
			));

			$aitLanguages = array();

			foreach($languages as $locale => $lang){
				if(!in_array($locale, $supportedByAit)){
					continue;
				}

				$aitLanguages[$locale] = $lang;

				if($locale == 'zh_CN'){
					$aitLanguages[$locale][0] = 'cn';
				}
				if($locale == 'zh_TW'){
					$aitLanguages[$locale][0] = 'tw';
				}
				if($locale == 'pt_BR'){
					$aitLanguages[$locale][0] = 'br';
				}
			}

			return $aitLanguages;
		});
	}



	protected static function maybeAddDefaultOptions()
	{
		add_filter('pre_update_option_polylang', function($options, $oldOptions){

			// when plugin is activated for the first time - it does not have any options yet in DB
			if(empty($oldOptions)){

				// Add all translatable AIT CPTs and WooCommerce CPTs to options
				if(class_exists('AitToolkit')){

					$aitCpts = AitToolkit::getManager('cpts')->getTranslatable('list');
					// $options['poyst_types'] contains all non-builtin public CPTs
					foreach($options['post_types'] as $i => $cpt){
						if(substr($cpt, 0, 4) === 'ait-'){
							if(!in_array($cpt, $aitCpts)){
								unset($options['post_types'][$i]); // unset all AIT non-translatable CPTs if they are set
							}
						}
					}
					$options['post_types'] = array_unique(array_merge($options['post_types'], $aitCpts)); // add translatable AIT CPTs

					$pllTaxs = $options['taxonomies'];
					$aitCpts = AitToolkit::getManager('cpts')->getAll();

					$aitTaxs = array();
					foreach($aitCpts as $cpt){
						$aitTaxs = array_merge($aitTaxs, $cpt->getTranslatableTaxonomyList());
					}

					foreach($pllTaxs as $i => $tax){
						if(substr($tax, 0, 4) === 'ait-'){
							if(!in_array($tax, $aitTaxs)){
								unset($options['taxonomies'][$i]);
							}
						}
					}
					$options['taxonomies'] = array_unique(array_merge($options['taxonomies'], $aitTaxs));
				}

				// Change some default settings of Polylang
				$options['browser'] = 0;
				$options['hide_default'] = 1;
				$options['force_lang'] = 1;
				$options['redirect_lang'] = 1;
				$options['rewrite'] = 1;
			}else{
				// on every save override these settings
				$options['hide_default'] = 1;
				$options['force_lang'] = 1;
				$options['redirect_lang'] = 1;
				$options['rewrite'] = 1;
			}

			return apply_filters('ait-languages-options', $options);

		}, 10, 2);
	}



	protected static function adminBarLanguageSwitcher()
	{
		add_action('admin_bar_menu', function($wp_admin_bar){

			if(!is_admin() or count(PLL()->model->get_languages_list()) < 2) return;

			$locale = function_exists('get_user_locale') ? get_user_locale() : get_locale();

			$currentLang = PLL()->model->get_language($locale);

			if(!$currentLang){
				$currentLang = isset(PLL()->options['default_lang']) && ($lang = PLL()->model->get_language(PLL()->options['default_lang'])) ? $lang : false;
			}

			if(!$currentLang) return;

			if(empty($currentLang->flag)){
				$title = esc_html(__('Admin language', 'polylang')) . ': ' . $currentLang->name;
			}else{
				$title = esc_html(__('Admin language', 'polylang')) . ': ' . $currentLang->flag . '&nbsp;' . esc_html($currentLang->name);
			}

			$wp_admin_bar->add_node(array(
				'id' => 'ait-admin-languages-switcher',
				'title'  =>  $title,
				'parent' => 'top-secondary',
				'href' => '#',
			));

			foreach (PLL()->model->get_languages_list() as $lang){
				if ($currentLang->slug == $lang->slug) continue;

				$wp_admin_bar->add_menu(array(
					'parent' => 'ait-admin-languages-switcher',
					'id'     => "ait-lang-{$lang->slug}",
					'title'  => empty($lang->flag) ? esc_html($lang->name) : $lang->flag .'&nbsp;'. esc_html($lang->name),
					'href'   => '#',
					'meta' => array('class' => "ait-admin-lang {$lang->locale}"),
				));
			}
		}, 2014);

		add_action('wp_ajax_switch_user_locale', array(__CLASS__, 'updateUserLocale'));
		static::userLangSwitcherScript();
	}



	protected static function userLangSwitcherScript()
	{
		add_action('admin_head', function(){
			if(is_admin_bar_showing() and PLL()->model->get_languages_list()){
				$ajaxUrl = admin_url('admin-ajax.php');
				?>
				<script>
				jQuery(function($){
					$('#wp-admin-bar-ait-admin-languages-switcher li.ait-admin-lang').on('click', function(e){
						e.preventDefault();
						var lang = 'en_US';
						var classes = jQuery(this).attr('class').split(/\s+/);
						if(classes.length == 2 ){
							lang = classes[1];
						}
						$.post('<?php echo $ajaxUrl ?>', {'action': 'switch_user_locale', 'user_locale': lang}, function(response){
							window.location.reload();
						});
					});
				});
				</script>
				<?php
			}
		});
	}



	protected static function adminMenu()
	{
		add_action('admin_menu', function(){
			if(current_theme_supports('ait-languages-plugin')){

				if(!function_exists('PLL')) return;

				remove_submenu_page('options-general.php', 'mlang');

				$c = __CLASS__;
				$c::$pageSlug = add_submenu_page(
					'ait-theme-options',
					__('Languages', 'polylang'),
					__('Languages', 'polylang'),
					apply_filters('ait-languages-menu-permission', 'manage_options'),
					'mlang',
					array(PLL(), 'languages_page')
				);
			}
		}, 20);
	}



	protected static function enqueueAssets()
	{
		add_action('plugins_loaded', function(){
			if(!function_exists('PLL')) return;

			remove_action('admin_enqueue_scripts', array(PLL(), 'admin_enqueue_scripts'));

			add_action('admin_enqueue_scripts', function() {

				// copy&paste PLL_Admin::admin_enqueue_scripts()

				$screen = get_current_screen();

				if (empty($screen))
					return;

				$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
				$suffix = "";
				// for each script:
				// 0 => the pages on which to load the script
				// 1 => the scripts it needs to work
				// 2 => 1 if loaded even if languages have not been defined yet, 0 otherwise
				// 3 => 1 if loaded in footer
				// FIXME: check if I can load more scripts in footer
				$scripts = array(
					'admin' => array( array('settings_page_mlang'), array('jquery', 'wp-ajax-response', 'postbox'), 1 , 0),
					'post'  => array( array('post', 'media', 'async-upload', 'edit'),  array('jquery', 'wp-ajax-response', 'post', 'jquery-ui-autocomplete'), 0 , 1),
					'media' => array( array('upload'), array('jquery'), 0 , 1),
					'term'  => array( array('edit-tags', 'term'), array('jquery', 'wp-ajax-response', 'jquery-ui-autocomplete'), 0, 1),
					'user'  => array( array('profile', 'user-edit'), array('jquery'), 0 , 0),
				);

				// script for block-editor
				if ( ! empty( $screen->post_type ) && PLL()->model->is_translated_post_type( $screen->post_type ) ) {
					if ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() && ! self::useBlockEditorPlugin() ) {
						$scripts['block-editor'] = array( array( 'post' ), array( 'jquery', 'wp-ajax-response', 'wp-api-fetch' ), 0, 1 );
					}
				}

				foreach ($scripts as $script => $v){
					if ((in_array($screen->base, $v[0]) or strpos($screen->base, '_mlang') !== false) && ($v[2] || PLL()->model->get_languages_list())){
						wp_enqueue_script('pll_'.$script, POLYLANG_URL .'/js/'.$script.$suffix.'.js', $v[1], POLYLANG_VERSION, $v[3]);
					}
				}

				wp_enqueue_style('polylang_admin', POLYLANG_URL .'/css/admin'.$suffix.'.css', array(), POLYLANG_VERSION);

				wp_enqueue_script('jquery-ui-selectmenu');

				wp_enqueue_script( 'pll_admin', POLYLANG_URL .'/js/admin'.$suffix.'.js', array( 'jquery', 'wp-ajax-response', 'postbox', 'jquery-ui-selectmenu' ), POLYLANG_VERSION );
				wp_localize_script( 'pll_admin', 'pll_flag_base_url', POLYLANG_URL . '/flags/' );


				if(strpos($screen->base, '_mlang') !== false){
					wp_enqueue_style( 'pll_selectmenu', POLYLANG_URL .'/css/selectmenu'.$suffix.'.css', array(), POLYLANG_VERSION );
					wp_enqueue_style( 'ait-languages-admin', POLYLANG_URL .'/ait/assets/ait-admin.css', array('polylang_admin', 'pll_selectmenu'), POLYLANG_VERSION );
				}
			});
		});

		add_action('admin_head', function(){
			$screen = get_current_screen();
			if(empty($screen)) return;

			if(strpos($screen->base, '_mlang') !== false){
				// make lang_locale input read-only, it can cuase more demage then it is usefull for simple users
				// hide rtl radios, we do not support rtl languages for now
				?>
				<script>
					jQuery(function(){
						var $langLocale = jQuery('#lang_locale');
						if($langLocale.length){
							$langLocale.attr('readonly', true);
						}
						var $rtl = jQuery('input[name="rtl"]').closest('div.form-field');
						if($rtl.length){
							$rtl.css('display', 'none');
						}
					});
				</script>
				<?php if(apply_filters('ait_languages_enable_url_settings', false)): ?>
				<style>
					form#options-lang table.form-table tr.hidden {
						display: table-row;
					}
				</style>
				<?php endif ?>
				<?php
			}

		}, 20);
	}



	protected static function afterDemoContentImport()
	{
		add_action('ait-after-import', function($whatToImport, $results = ''){
			delete_transient('pll_languages_list');
		}, 10, 2);
	}



	protected static function clearThemeCache()
	{
		$c = __CLASS__;
		add_action('updated_user_meta', function($meta_id, $user_id, $meta_key, $_meta_value) use($c){
			if($meta_key === 'user_lang' or $meta_key === 'locale'){
				$c::clearCacheByUserId();
			}
		}, 10, 4);

		add_action('delete_transient_pll_languages_list', function(){
			if(class_exists('AitCache')){
				AitCache::clean();
			}
		});

		register_activation_hook(POLYLANG_BASENAME, array(__CLASS__, 'clearCacheByUserId'));
		register_deactivation_hook(POLYLANG_BASENAME, array(__CLASS__, 'clearCacheByUserId'));
	}



	protected static function shouldMigrate()
	{
		return (get_option('_ait-languages_should_migrate', 'no') === 'yes');
	}



	protected static function migrateTo20()
	{
		if(static::shouldMigrate()){
			add_action('wp_loaded', function(){
					if(defined('DOING_AJAX') and DOING_AJAX) return;
					flush_rewrite_rules(true);
			}, 15);

			add_action('admin_init', function(){
				if(defined('DOING_AJAX') and DOING_AJAX) return;
				$options = get_option('polylang');
				$adminModel = new PLL_Admin_Model($options);
				if($nolang = $adminModel->get_objects_with_no_lang() and isset($options['default_lang'])){
					if(!empty($nolang['posts'])){
						$adminModel->set_language_in_mass('post', $nolang['posts'], $options['default_lang']);
					}
					if(!empty($nolang['terms'])){
						$adminModel->set_language_in_mass('term', $nolang['terms'], $options['default_lang']);
					}
				}
				unset($adminModel);
				delete_option('_ait-languages_should_migrate');
			}, 15);
		}
	}



	public static function clearCacheByUserId()
	{
		if(class_exists('AitCache')){
			$user_id = get_current_user_id();
			AitCache::remove("@raw-config-$user_id");
			AitCache::remove("@processed-config-$user_id");
		}
	}



	protected static function handleUserLang()
	{

		add_action('pll_add_language', function($args){
			$lang_list = PLL()->model->get_languages_list(array('fields' => 'locale'));
			if(count($lang_list) === 1){ // first language
				update_user_meta(get_current_user_id(), 'locale', $args['locale']);
			}
		});

		add_filter("delete_user_metadata", function($null, $object_id, $meta_key, $meta_value, $delete_all){
			if($meta_key === 'user_lang' or $meta_key === 'locale'){
				if($locales = PLL()->model->get_languages_list(array('fields' => 'locale'))){
					$locale = get_option('WPLANG', 'en_US');
					foreach($locales as $l){
						if($l !== $meta_value){
							$locale = $l;
							break;
						}
					}
					update_user_meta(get_current_user_id(), 'locale', $locale);
					return $locale;
				}
			}
			return $null;
		}, 10, 5);
	}



	protected static function removeSomeModules()
	{
		add_filter('pll_settings_modules', function($modules){
			$remove = array(
				'PLL_Settings_Share_Slug', // Pro module
				'PLL_Settings_Translate_Slugs', // Pro module
				'PLL_Settings_Licenses',
				'PLL_Settings_Url'
			);
			foreach($remove as $r){
				$index = array_search($r, $modules);
				if($index !== false){
					unset($modules[$index]);
				}
			}
			return $modules;
		});
	}



	protected static function changeAdminPageUlr()
	{
		add_filter('admin_url', function($url, $path, $blog_id){
			if(strpos($path, 'options-general.php?page=mlang') !== FALSE){ // contains
				return str_replace('options-general.php', 'admin.php', $url);
			}
			return $url;
		}, 10, 3);
	}



	protected static function updateUserLocaleToFirstLang()
	{
		add_action('pll_add_language', function($args){
			$lang_list = PLL()->model->get_languages_list(array('fields' => 'locale'));
			if(count($lang_list) === 1){ // first language
				update_user_meta(get_current_user_id(), 'locale', $args['locale']);
			}
		});
	}



	public static function updateUserLocale($locale = null)
	{
		$user_locale = (isset($_POST['user_locale']) and in_array($_POST['user_locale'], PLL()->model->get_languages_list(array('fields' => 'locale')))) ? $_POST['user_locale'] : 'en_US';
		if(!$user_locale and $locale) $user_locale = $locale;

		update_user_meta(get_current_user_id(), 'locale', $user_locale);
	}



	protected static function adminNotices()
	{
		add_action('all_admin_notices', function(){
			$screen = get_current_screen();
			if(strpos($screen->base, '_mlang') !== false){
				settings_errors();
			}
		});
	}



	protected static function wcAjaxEndpoint()
	{
		add_filter('woocommerce_ajax_get_endpoint', function($url, $request){
			if(function_exists('wc') and version_compare(wc()->version, '3.2', '<')) return $url;

			if(function_exists('PLL') and version_compare(wc()->version, '3.5', '<')){
				return PLL()->links_model->add_language_to_link($url, PLL()->curlang);
			}
			if(function_exists('PLL')){
				global $polylang;
		        return parse_url($polylang->filters_links->links->get_home_url($polylang->curlang), PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY);
			}
			return $url;
		}, 10, 2);
	}



	protected static function useBlockEditorPlugin()
	{
		return class_exists( 'PLL_Block_Editor_Plugin' ) && apply_filters( 'pll_use_block_editor_plugin', ! defined( 'PLL_USE_BLOCK_EDITOR_PLUGIN' ) || PLL_USE_BLOCK_EDITOR_PLUGIN );
	}



	protected static function langParamInRestTermQuery()
	{
		add_filter("rest_category_query", function($prepared_args, $request){
			$prepared_args['lang'] = $request->get_param('lang');
			return $prepared_args;
		}, 10, 2);

		add_filter("rest_tag_query", function($prepared_args, $request){
			$prepared_args['lang'] = $request->get_param('lang');
			return $prepared_args;
		}, 10, 2);
	}

}

