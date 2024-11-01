<?php
/*
Plugin Name: WooCommerce Product Slider
Description: A simple WooCommerce product slider plugin.
Author: subhansanjaya
Version: 1.4
Plugin URI: http://wordpress.org/plugins/woocommerce-product-slider/
Author URI: http://www.weaveapps.com
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=BXBCGCKDD74UE
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
if(! defined( 'ABSPATH' )) exit; // Exit if accessed directly

class Woocommerce_Product_Slider {

	//default settings
	private $defaults = array(
		'settings' => array(
			'jquery' => false,
			'transit' => true,
			'magnific_popup' => true,
			'lazyload' => true,
			'caroufredsel' => true,
			'touchswipe' => true,
			'loading_place' => 'footer',
			'deactivation_delete' => false
		),
		'version' => '1.4'
	);

	private $options = array();
	private $tabs = array();

	public function __construct() {

		//activation and deactivation hooks
		register_activation_hook(__FILE__, array(&$this, 'wa_wps_multisite_activation') );
		register_deactivation_hook(__FILE__, array(&$this, 'wa_wps_multisite_deactivation'));

		//define plugin path
		define( 'WA_WPS_SLIDER_PLUGIN_PATH', plugin_dir_path(__FILE__) );

		//define theme directory
		define( 'WA_WPS_SLIDER_PLUGIN_TEMPLATE_DIRECTORY_NAME', 'themes' );
		define( 'WA_WPS_PLUGIN_TEMPLATE_DIRECTORY', WA_WPS_SLIDER_PLUGIN_PATH .WA_WPS_SLIDER_PLUGIN_TEMPLATE_DIRECTORY_NAME. DIRECTORY_SEPARATOR );

		//define view directory
		define( 'WA_WPS_SLIDER_PLUGIN_TEMPLATE_DIRECTORY_NAME_VIEW', 'views' );
		define( 'WA_WPS_PLUGIN_VIEW_DIRECTORY', WA_WPS_SLIDER_PLUGIN_PATH .WA_WPS_SLIDER_PLUGIN_TEMPLATE_DIRECTORY_NAME_VIEW. DIRECTORY_SEPARATOR );
	
		add_action('admin_init', array(&$this, 'register_settings'));

		//register post type
		add_action('init', array(&$this, 'wa_wps_init'));

		// metaboxes 
		add_action( 'add_meta_boxes', array( $this, 'wa_wps_add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'wa_wps_save_metabox_data' ) );

		//update messages and help text
		add_action('post_updated_messages', array(&$this, 'wa_wps_updated_messages'));
	
		//load defaults
		add_action('plugins_loaded', array(&$this, 'load_defaults'));

		//register shortcode button to the TinyMCE toolbar
		add_action('init',  array(&$this, 'wa_wps_shortcode_button_init'));

		//update plugin version
		update_option('wa_wps_version', $this->defaults['version'], '', 'no');

		//set settings
		$this->options['settings'] = array_merge($this->defaults['settings'], (($array = get_option('wa_wps_settings')) === FALSE ? array() : $array));
		
		add_action('wp_enqueue_scripts', array(&$this, 'wa_wps_load_scripts'));
		add_shortcode( 'wa-wps', array(&$this, 'wa_wps_shortcode') );

		if (is_admin()){
		add_action( 'admin_menu',array(&$this, 'wa_wps_pre_add_to_menu' ) );
		}

		add_action('admin_enqueue_scripts', array(&$this, 'admin_include_scripts'));

		//add text domain for localization
		add_action('plugins_loaded', array(&$this, 'wa_wps_load_textdomain'));

		// create widget
		include_once('includes/class-wa-wps-widget.php');
		$wawps_widget = new WA_WPS_Widget();

		//add settings link
		add_filter('plugin_action_links', array(&$this, 'wa_wps_settings_link'), 2, 2);

		//add ajax on admin to display related select post types
		add_action( 'admin_footer', array(&$this, 'wa_wps_related_select'));

		add_action('wp_ajax_nopriv_wa_wps_action', array(&$this, 'wa_wps_action_callback'));
		add_action('wp_ajax_wa_wps_action',  array(&$this, 'wa_wps_action_callback'));

		//remove publish box
		add_action( 'admin_menu', array(&$this, 'wa_wps_remove_publish_box'));

		add_action('admin_print_scripts', array(&$this, 'wa_wps_disable_autosave'));
	}

	//multisite activation
	public function wa_wps_multisite_activation($networkwide) {

		if(is_multisite() && $networkwide)
		{
			global $wpdb;

			$activated_blogs = array();
			$current_blog_id = $wpdb->blogid;
			$blogs_ids = $wpdb->get_col($wpdb->prepare('SELECT blog_id FROM '.$wpdb->blogs, ''));

			foreach($blogs_ids as $blog_id)
			{
				switch_to_blog($blog_id);
				$this->activate_single();
				$activated_blogs[] = (int)$blog_id;
			}

			switch_to_blog($current_blog_id);
			update_site_option('wa_wps_activated_blogs', $activated_blogs, array());
		}
		else
			$this->activate_single();
	}

	public function activate_single() {

		add_option('wa_wps_settings', $this->defaults['settings'], '', 'no');
		add_option('wa_wps_version', $this->defaults['version'], '', 'no');

	}

	//deactivation hook
	public function wa_wps_multisite_deactivation($networkwide) {

		if(is_multisite() && $networkwide) {
			global $wpdb;

			$current_blog_id = $wpdb->blogid;
			$blogs_ids = $wpdb->get_col($wpdb->prepare('SELECT blog_id FROM '.$wpdb->blogs, ''));

			if(($activated_blogs = get_site_option('wa_wps_activated_blogs', FALSE, FALSE)) === FALSE)
				$activated_blogs = array();

			foreach($blogs_ids as $blog_id)
			{
				switch_to_blog($blog_id);
				$this->deactivate_single(TRUE);

				if(in_array((int)$blog_id, $activated_blogs, TRUE))
					unset($activated_blogs[array_search($blog_id, $activated_blogs)]);
			}

			switch_to_blog($current_blog_id);
			update_site_option('wa_wps_activated_blogs', $activated_blogs);
		}
		else
			$this->deactivate_single();
	}


	public function deactivate_single($multi = FALSE) {

		if($multi === TRUE)
		{
			$options = get_option('wa_wps_settings');
			$check = $options['deactivation_delete'];
		}
		else
			$check = $this->options['settings']['deactivation_delete'];

		if($check === TRUE)
		{
			delete_option('wa_wps_settings');
			delete_option('wa_wps_version');
		}
	}

	//settings link in plugin management screen
	public function wa_wps_settings_link($actions, $file) {

		if(false !== strpos($file, 'woocommerce-product-slider-pro'))
		 $actions['settings'] = '<a href="edit.php?post_type=wa_wps&page=wa_wps">Settings</a>';
		return $actions; 

	}

	public function wa_wps_related_select() {	?>
	<script type="text/javascript" >
	jQuery(document).ready(function($) {

		 var posts_taxonomy = $("#wa_wps_query_posts_taxonomy option:selected").attr('value');

		 var posts_terms = $("#wa_wps_query_posts_terms option:selected").attr('value'); 

		 var posts_tags = $("#wa_wps_query_posts_tags option:selected").attr('value'); 

		 var product_type = $("#wa_wps_query_product_type option:selected").attr('value'); 

		 var post_type = $("#wa_wps_query_posts_post_type option:selected").attr('value'); 

		 var content_type = $("#wa_wps_query_content_type option:selected").attr('value'); 


		 if(post_type!='product') { 

			$("#wa_wps_query_product_type").closest('tr').hide(); //hide product type
			$("#wa_wps_product_categories_position").closest('p').hide();
			$("#wa_wps_show_add_to_cart").closest('p').hide(); 
			$("#wa_wps_show_sale_text").closest('p').hide();  
			$("#wa_wps_show_price").closest('p').hide(); 
			$("#wa_wps_show_rating").closest('p').hide(); 

		} 

		 if(post_type!='post') { 

			$("#wa_wps_query_content_type").closest('tr').hide(); //hide content type

		} 

		if(post_type=='post'||post_type=='product') {

			$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 

		}

		// category
		if(product_type&&product_type!='category') { 

			$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 
			
			$("#wa_wps_query_posts_terms").closest('tr').hide(); 

			$("#wa_wps_query_posts_terms").removeAttr('required'); 

		} 

		if(content_type&&content_type!='category') { 

			$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 
			
			$("#wa_wps_query_posts_terms").closest('tr').hide(); 

			$("#wa_wps_query_posts_terms").removeAttr('required'); 

		}

		if(post_type=='product'&&product_type=='category') {

				$("select#wa_wps_query_posts_terms").removeAttr("disabled");
				$("#wa_wps_query_posts_terms").closest('tr').show(); 
				$("#wa_wps_query_posts_terms").attr("required","required");

		} 

		if(post_type=='post'&&content_type=='category') {

				$("select#wa_wps_query_posts_terms").removeAttr("disabled");
				$("#wa_wps_query_posts_terms").closest('tr').show(); 
				$("#wa_wps_query_posts_terms").attr("required","required");
		}


		//tags
		if(product_type&&product_type!='tag') { 

			$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 
			
			$("#wa_wps_query_posts_tags").closest('tr').hide(); 

			$("#wa_wps_query_posts_tags").removeAttr('required'); 

		} 

		if(content_type&&content_type!='tag') { 

			$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 
			
			$("#wa_wps_query_posts_tags").closest('tr').hide(); 

			$("#wa_wps_query_posts_tags").removeAttr('required'); 

		}

		if(post_type=='product'&&product_type=='tag') {

				$("select#wa_wps_query_posts_tags").removeAttr("disabled");
				$("#wa_wps_query_posts_tags").closest('tr').show(); 
				$("#wa_wps_query_posts_tags").attr("required","required");

		} 

		if(post_type=='post'&&content_type=='tag') {

				$("select#wa_wps_query_posts_tags").removeAttr("disabled");
				$("#wa_wps_query_posts_tags").closest('tr').show(); 
				$("#wa_wps_query_posts_tags").attr("required","required");
		}


		 //disabled taxonomy field
		if(!posts_taxonomy) {

			$("select#wa_wps_query_posts_taxonomy").attr("disabled","disabled");
			$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 
		}

		//disabled terms field
		if(!posts_terms) {
			
			$("select#wa_wps_query_posts_terms").attr("disabled","disabled");
			$("#wa_wps_query_posts_terms").closest('tr').hide(); 

	

		}

		//disabled tags field
		if(!posts_tags) {

			$("select#wa_wps_query_posts_tags").attr("disabled","disabled");
			$("#wa_wps_query_posts_tags").closest('tr').hide(); 

		}

		//select terms based on product type
		$("select#wa_wps_query_content_type").change(function() {


		var content_type = $("#wa_wps_query_content_type option:selected").attr('value');

			var post_type = jQuery("select#wa_wps_query_posts_post_type option:selected").attr('value');
			var tax = "category";

			var data = {
				'action': 'wa_wps_action',
				'post_type': post_type,
				'tax': tax,
				'product_type': content_type
			};

			$.post(ajaxurl, data, function(response) {
				 $("select#wa_wps_query_posts_terms").removeAttr("disabled");
				 $("select#wa_wps_query_posts_terms").html(response);
			});

					$.post(ajaxurl, data, function(response) {
				 $("select#wa_wps_query_posts_tags").removeAttr("disabled");
				 $("select#wa_wps_query_posts_tags").html(response);
			});
		
		});


		$("select#wa_wps_query_content_type").change(function(){

		var content_type = $("#wa_wps_query_content_type option:selected").attr('value');

		var post_type = $("#wa_wps_query_posts_post_type option:selected").attr('value');

		if(post_type=='post'&&content_type!='category') {

			$("#wa_wps_query_posts_terms").removeAttr('required'); 
			$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 
			$("#wa_wps_query_posts_terms").closest('tr').hide(); 

			$("#wa_wps_query_posts_tags").removeAttr('required'); 
			$("#wa_wps_query_posts_tags").closest('tr').hide(); 
			
		} else {

			$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 
			$("#wa_wps_query_posts_terms").closest('tr').show(); 
			$("#wa_wps_query_posts_terms").attr("required","required");


			$("#wa_wps_query_posts_tags").removeAttr('required'); 
			$("#wa_wps_query_posts_tags").closest('tr').hide(); 


		}


		if(post_type=='post'&&content_type=='tag') {

			$("#wa_wps_query_posts_tags").closest('tr').show(); 
			$("#wa_wps_query_posts_tags").attr("required","required");

		}





	});

		//select terms based on product type
		$("select#wa_wps_query_product_type").change(function() {

			var post_type = jQuery("select#wa_wps_query_posts_post_type option:selected").attr('value');
			var tax = "product_cat";

			var product_type = $("#wa_wps_query_product_type option:selected").attr('value');

			var data = {
				'action': 'wa_wps_action',
				'post_type': post_type,
				'tax': tax,
				'product_type': product_type
			};

			$.post(ajaxurl, data, function(response) {
				 $("select#wa_wps_query_posts_terms").removeAttr("disabled");
				 $("select#wa_wps_query_posts_terms").html(response);
			});



				$.post(ajaxurl, data, function(response) {
				 $("select#wa_wps_query_posts_tags").removeAttr("disabled");
				 $("select#wa_wps_query_posts_tags").html(response);
			});



						if(post_type=='product'&&content_type!='tag') {

			$("#wa_wps_query_posts_terms").removeAttr('required'); 
			$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 
			$("#wa_wps_query_posts_terms").closest('tr').hide(); 

			$("#wa_wps_query_posts_tags").removeAttr('required'); 
			$("#wa_wps_query_posts_tags").closest('tr').hide(); 
			
		} else {

			$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 
			$("#wa_wps_query_posts_terms").closest('tr').show(); 
			$("#wa_wps_query_posts_terms").attr("required","required");


			$("#wa_wps_query_posts_tags").removeAttr('required'); 
			$("#wa_wps_query_posts_tags").closest('tr').hide(); 


		}


			if(post_type=='product'&&product_type=='tag') {
				 $("select#wa_wps_query_posts_tags").removeAttr("disabled");
				$("#wa_wps_query_posts_tags").closest('tr').show(); 
				$("#wa_wps_query_posts_tags").attr("required","required");
			}
		
		});

		//select taxonomies based on post type
		$("select#wa_wps_query_posts_post_type").change(function() {

		$("#wa_wps_query_posts_terms").attr("required","required");

		$("#wa_wps_query_posts_tags").attr("required","required");

		$("select#wa_wps_query_posts_terms").attr("disabled","disabled");

		$("select#wa_wps_query_posts_tags").attr("disabled","disabled");

		$("select#wa_wps_query_posts_taxonomy").attr("disabled","disabled");

		$("#wa_wps_query_posts_terms").closest('tr').hide(); 

		$("#wa_wps_query_posts_tags").closest('tr').hide(); 

		$("#wa_wps_query_posts_taxonomy").closest('tr').hide();

		var post_type = jQuery("select#wa_wps_query_posts_post_type option:selected").attr('value');
			var data = {
				'action': 'wa_wps_action',
				'post_type': post_type
			};

			$.post(ajaxurl, data, function(response) {

				if(response=="null"){

					$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 
					$("#wa_wps_query_posts_terms").closest('tr').hide(); 
					$("#wa_wps_query_posts_terms").removeAttr('required'); 
					$("#wa_wps_query_posts_tags").closest('tr').hide(); 
					$("#wa_wps_query_posts_tags").removeAttr('required'); 
					$("#wa_wps_query_posts_taxonomy").removeAttr('required'); 

				} else {

					if(post_type!='product'&&post_type!='post') { 

						$("#wa_wps_query_posts_taxonomy").closest('tr').show(); 
						$("select#wa_wps_query_posts_taxonomy").removeAttr("disabled");
						$("select#wa_wps_query_posts_taxonomy").html(response);
						$("select#wa_wps_query_posts_terms").attr("disabled","disabled");
						$("#wa_wps_query_posts_terms").closest('tr').hide(); 
						$("#wa_wps_query_posts_tags").closest('tr').hide(); 

					}
				}

			});
		});

		//select terms based on post types and taxonomy
		$("select#wa_wps_query_posts_taxonomy").change(function(){

			var post_type = jQuery("select#wa_wps_query_posts_post_type option:selected").attr('value');
			var tax = jQuery("select#wa_wps_query_posts_taxonomy option:selected").attr('value');

			var data = {
				'action': 'wa_wps_action',
				'post_type': post_type,
				'tax': tax
			};

			$.post(ajaxurl, data, function(response) {
				
				 $("#wa_wps_query_posts_terms").attr("required","required");
				 $("select#wa_wps_query_posts_terms").removeAttr("disabled");
				 $("#wa_wps_query_posts_terms").closest('tr').show(); 
				 $("select#wa_wps_query_posts_terms").html(response);

			});
		});


		$("select#wa_wps_query_product_type").change(function(){

		var product_type = $("#wa_wps_query_product_type option:selected").attr('value');

		var post_type = $("#wa_wps_query_posts_post_type option:selected").attr('value');

		if(post_type=='product'&&product_type!='category') {

			$("#wa_wps_query_posts_terms").removeAttr('required'); 
			$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 
			$("#wa_wps_query_posts_terms").closest('tr').hide(); 
			
		} else {

			$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 
			$("#wa_wps_query_posts_terms").closest('tr').show(); 
			$("#wa_wps_query_posts_terms").attr("required","required");

		}

	});

	$("select#wa_wps_query_posts_post_type").change(function(){

		var post_type = $("#wa_wps_query_posts_post_type option:selected").attr('value');

		var content_type = $("#wa_wps_query_content_type option:selected").attr('value'); 


		if(post_type=='product'){

			$("#wps_product_heading").show();
			$("#wps_product_settings").show();
			$("#wa_wps_query_content_type").removeAttr('required'); 
			$("#wa_wps_query_product_type").attr("required","required");
			$("#wa_wps_query_product_type").closest('tr').show(); //hide product type
			$("#wa_wps_product_categories_position").closest('p').show();
			$("#wa_wps_show_add_to_cart").closest('p').show(); 
			$("#wa_wps_show_sale_text").closest('p').show();  
			$("#wa_wps_show_price").closest('p').show(); 
			$("#wa_wps_show_rating").closest('p').show(); 

			$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 

				if(product_type!='category') {

					$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 
					$("#wa_wps_query_posts_terms").closest('tr').hide(); 
					$("#wa_wps_query_posts_tags").closest('tr').hide()
					$("#wa_wps_query_posts_terms").removeAttr('required'); 

				}

			} else {

			$("#wps_product_heading").hide();
			$("#wps_product_settings").hide();
			$("#wa_wps_query_product_type").closest('tr').hide(); //hide product type
			$("#wa_wps_product_categories_position").closest('p').hide();
			
			$("#wa_wps_show_add_to_cart").closest('p').hide(); 
			$("#wa_wps_show_sale_text").closest('p').hide();  
			$("#wa_wps_show_price").closest('p').hide(); 
			$("#wa_wps_show_rating").closest('p').hide(); 

			}

			if(post_type=='post') { 

			$("#wa_wps_query_product_type").removeAttr('required'); 
			$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 
			$("#wa_wps_query_content_type").attr("required","required");
			$("#wa_wps_query_content_type").closest('tr').show(); //hide product type

				if(content_type!='category') {

					$("#wa_wps_query_posts_taxonomy").closest('tr').hide(); 
					$("#wa_wps_query_posts_terms").closest('tr').hide(); 
						$("#wa_wps_query_posts_tags").closest('tr').hide()
					$("#wa_wps_query_posts_terms").removeAttr('required'); 

				}

			} else {

				$("#wa_wps_query_content_type").closest('tr').hide(); //hide product type

			}

		});

	});
	</script> 

	<?php
	}

	//ajax action call back
	public function wa_wps_action_callback() {

		if(isset($_POST['post_type'])&&isset($_POST['product_type'])&&$_POST['product_type']=="tag") { 
		
			echo $this->showTags($_POST['post_type'],$_POST['product_type']);

		} else if(isset($_POST['post_type'])&&isset($_POST['tax'])) { 
		
			echo $this->showTerms($_POST['post_type'],$_POST['tax']);

		} else if(isset($_POST['post_type'])) { 

			echo $this->showTax($_POST['post_type']);

		} else {

			echo $type;
		}

		die(); // this is required to terminate immediately and return a proper response

	}

	//show all taxonomies for given post type
	public function showTax($post_type) {
	 
	$type = '<option value="">choose...</option>';
	          $taxonomy_names = get_object_taxonomies( $post_type );

	          if(empty($taxonomy_names)) {
	          	 $type = "null"; return  $type; die(); }

	            foreach ($taxonomy_names as $key => $value) {
	                $type .= '<option value="' .$value . '" >' . $value . '</option>';
	           }
	
		return  $type;

	}

	//show terms to post type and tax
	public function showTerms($post_type,$tax) {

		$type = '<option value="">choose...</option>';
	    $categories = get_terms($tax, array('post_type' => array($post_type),'fields' => 'all'));

	    foreach ($categories as $key => $value) {
	        $type .= '<option value="' .$value->slug . '">' . $value->name . '</option>';
	    }

		return  $type;

	}

	//show tags to post type and tax
	public function showTags($post_type,$tax) {

		if($post_type=='product') {

			$tax = 'product_tag'; 

		} else if($post_type=='post') {

			$tax = 'post_tag';
		} 

		$type = '<option value="">choose...</option>';
	    $categories = get_terms($tax, array('post_type' => array($post_type),'fields' => 'all'));

	    foreach ($categories as $key => $value) {
	        $type .= '<option value="' .$value->slug . '">' . $value->name . '</option>';
	    }

		return  $type;

	}

	//template function
	public function wa_wps($atts) {

		$arr = array();
		$arr["id"]=$atts;
		echo wa_wps_shortcode($arr);
	}

	// load text domain for localization
	public function wa_wps_load_textdomain() {

		load_plugin_textdomain('wps', FALSE, dirname(plugin_basename(__FILE__)).'/lang/');

	}

	//load front e8nd scripts
	public function wa_wps_load_scripts($jquery_true) {

		wp_register_style('wa_wps_css_file', plugins_url('/assets/css/custom-style.css',__FILE__));
		wp_enqueue_style('wa_wps_css_file');

		if($this->options['settings']['jquery'] === TRUE) {

			wp_register_script('wa_wps_jquery',plugins_url('/assets/js/caroufredsel/jquery-1.8.2.min.js', __FILE__),array('jquery'),'',($this->options['settings']['loading_place'] === 'header' ? false : true));
		    wp_enqueue_script('wa_wps_jquery'); 

		}

		if($this->options['settings']['transit'] === TRUE) {

			wp_register_script('wa_wps_transit',plugins_url('/assets/js/caroufredsel/jquery.transit.min.js',__FILE__),array('jquery'),'',($this->options['settings']['loading_place'] === 'header' ? false : true));
			wp_enqueue_script('wa_wps_transit');

		 }

		if($this->options['settings']['lazyload'] === TRUE) {

			wp_register_script('wa_wps_lazyload',plugins_url('/assets/js/caroufredsel/jquery.lazyload.min.js', __FILE__),array('jquery'),'',($this->options['settings']['loading_place'] === 'header' ? false : true));
		    wp_enqueue_script('wa_wps_lazyload'); 

		}

		if($this->options['settings']['magnific_popup'] === TRUE) {

			wp_register_style('wa_wps_magnific_style', plugins_url('/assets/css/magnific-popup/magnific-popup.css',__FILE__ ));
			wp_enqueue_style('wa_wps_magnific_style'); 

			wp_register_script('wa_wps_magnific_script',plugins_url('/assets/js/magnific-popup/jquery.magnific-popup.min.js', __FILE__),array('jquery'),'',($this->options['settings']['loading_place'] === 'header' ? false : true));
		    wp_enqueue_script('wa_wps_magnific_script');

		}

		if($this->options['settings']['caroufredsel'] === TRUE) {

		    wp_register_script('wa_wps_caroufredsel_script',plugins_url('/assets/js/caroufredsel/jquery.carouFredSel-6.2.1-packed.js', __FILE__),array('jquery'),'',($this->options['settings']['loading_place'] === 'header' ? false : true));
		    wp_enqueue_script('wa_wps_caroufredsel_script');

		}

		if($this->options['settings']['touchswipe'] === TRUE) {

		    wp_register_script('wa_wps_touch_script',plugins_url('/assets/js/caroufredsel/jquery.touchSwipe.min.js', __FILE__),array('jquery'),'',($this->options['settings']['loading_place'] === 'header' ? false : true));
		    wp_enqueue_script('wa_wps_touch_script'); 

		}

	}

	//include admin scripts
	public function admin_include_scripts() {

		wp_register_style('wa_wps_admin_css',plugins_url('assets/css/admin.css', __FILE__));
		wp_enqueue_style('wa_wps_admin_css');

		//add spectrum colour picker
		wp_register_style('wa-wps-admin-spectrum',plugins_url('assets/css/spectrum/spectrum.css', __FILE__));
		wp_enqueue_style('wa-wps-admin-spectrum');

		wp_register_script('wa-wps-admin-spectrum-js',plugins_url('assets/js/spectrum/spectrum.js', __FILE__));
		wp_enqueue_script('wa-wps-admin-spectrum-js');

		wp_register_style('wa-wps-date-picker',plugins_url('assets/css/jquery-ui.min.css', __FILE__));
		wp_enqueue_style('wa-wps-date-picker');

		//add date picker
		wp_enqueue_script('jquery-ui-datepicker');

		wp_register_script('wa-wps-admin-script',plugins_url('assets/js/admin-script.js', __FILE__));
		wp_enqueue_script('wa-wps-admin-script');

	}

	//get excerpt
	public function wa_wps_clean($excerpt, $substr) {

		$string = $excerpt;
		$string = strip_shortcodes(wp_trim_words( $string, (int)$substr ));
		return $string;

	}

	//get post thumbnail
	public	function wa_wps_get_post_image($post_content, $post_image_id, $img_type, $img_size, $slider_id) {

	  if($img_type=='featured_image'){
	  			if (has_post_thumbnail( $post_image_id ) ): 
				 $img_arr = wp_get_attachment_image_src( get_post_thumbnail_id( $post_image_id ), $img_size ); $first_img = $img_arr[0];
				endif; 
		}else  if($img_type=='first_image'){
		$output = preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $post_content, $matches);
	 	 $first_img = isset($matches[1][0])?$matches[1][0]:'';
		}else  if($img_type=='last_image'){
			$output = preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $post_content, $matches);
	  		$first_img = isset($matches[1][count($matches[0])-1])?$matches[1][count($matches[0])-1]:''; 
		}
		  if(empty($first_img)) {
		  	$options = get_post_meta( $slider_id, 'options', true ); //options settings

		  	if(!empty($options['default_img'])) {

		  		 $first_img = $options['default_img'];

		  	} else {
		   		 $first_img = plugins_url()."/woocommerce-product-slider/assets/images/default-image.jpg";

		   	}

		  }
		  return $first_img;
	}

	//get related posts
	public function wa_get_related_posts( $post_id, $related_count, $args = array() ) {
	    $args = wp_parse_args( (array) $args, array(
	        'orderby' => 'rand',
	        'return'  => 'query', // Valid values are: 'query' (WP_Query object), 'array' (the arguments array)
	    ) );
	 
	    $related_args = array(
	        'post_type'      => get_post_type( $post_id ),
	        'posts_per_page' => $related_count,
	        'post_status'    => 'publish',
	        'post__not_in'   => array( $post_id ),
	        'orderby'        => $args['orderby'],
	        'tax_query'      => array()
	    );
	 
	    $post       = get_post( $post_id );
	    $taxonomies = get_object_taxonomies( $post, 'names' );
	 
	    foreach( $taxonomies as $taxonomy ) {
	        $terms = get_the_terms( $post_id, $taxonomy );
	        if ( empty( $terms ) ) continue;
	        $term_list = wp_list_pluck( $terms, 'slug' );
	        $related_args['tax_query'][] = array(
	            'taxonomy' => $taxonomy,
	            'field'    => 'slug',
	            'terms'    => $term_list
	        );
	    }
	 
	    if( count( $related_args['tax_query'] ) > 1 ) {
	        $related_args['tax_query']['relation'] = 'OR';
	    }
	 
	    if( $args['return'] == 'query' ) {
	        return $related_args ;
	    } else {
	        return $related_args;
	    }
	}

	//add admin menu
	public function wa_wps_pre_add_to_menu() {
		
		add_submenu_page( 'edit.php?post_type=wa_wps', 'Settings', 'Settings', 'manage_options', 'wa_wps', array(&$this, 'options_page') );

	}

	//set lazy load image
	public function get_lazy_load_image($image) {

		  	if(!empty($image)) {

		  		 $lazy_load_image_url = $image;

		  	} else {
		   		 $lazy_load_image_url = plugins_url()."/woocommerce-product-slider/assets/images/loader.gif";

		   	}

		   	return $lazy_load_image_url;
	}

	//schedule sliders
	public function get_status_of_schedule($start_date, $end_date) {

		$status = '';

		$startDate = strtotime($start_date);
		$endDate = strtotime($end_date);
		$currentDate = strtotime(date('m/d/Y'));
		

		if(empty($start_date)&&empty($end_date)) {

			$status = 1;

		}

		if (($currentDate >= $startDate) && ($currentDate <= $endDate)) {

			$status = 2;

		} else  if (($currentDate <= $startDate) && ($currentDate >= $endDate)) {

			$status = 3;

		}

 		 if($status==1||$status==2){

			return true;

		}

	}

	//display slider
	public function wa_wps_shortcode($atts) {

		global $wps, $wpdb, $post;

		if ( ! is_array( $atts ) )	{
			return '';
		}
		$id = $atts['id'];
		$options = get_post_meta( apply_filters( 'translate_object_id', $id, get_post_type( $id ), true ), 'options', true ); //options settings


		if(empty($options)) { return false; }

		$wa_wps_auto = isset($options['auto_scroll']) ? 'true' : 'false';
		$wa_wps_show_controls = isset($options['show_controls']) ? $options['show_controls'] :'';
		$wa_wps_show_paging = isset($options['show_paging']) ? $options['show_paging'] : ''; //display paging
		$wa_wps_query_posts_image_type = isset($options['image_type']) ? $options['image_type'] :''; //display image type
		$wa_wps_query_posts_item_width = isset($options['item_width']) ? $options['item_width'] : ''; //item width
		$wa_wps_query_posts_item_height = isset($options['item_height']) ? $options['item_height'] : ''; //item height
		$wa_wps_query_posts_fx = isset($options['fx']) ? $options['fx'] : ''; // transition effects type
		$c_min_items = isset($options['show_posts_per_page']) ? $options['show_posts_per_page'] :'4'; // min items 
		$c_items = isset($options['items_to_be_slide']) ? $options['items_to_be_slide'] :'0'; //no of items per page
		$c_easing = $options['easing_effect']; //easing effect
		$c_duration = isset($options['duration']) ? $options['duration'] :'500';//duration
		$c_time_out = isset($options['timeout']) ? $options['timeout'] :'3000';//time out
		$qp_showposts = isset($options['show_posts']) ? $options['show_posts'] :'20'; //no of posts to display
		$qp_orderby= isset($options['posts_order_by']) ? $options['posts_order_by'] :'id'; //order by
		$qp_order= isset($options['post_order']) ? $options['post_order'] :'asc';; //order
		$qp_category= isset($options['post_ids']) ? $options['post_ids'] : ''; // post type
		$qp_post_type= isset($options['post_type']) ? $options['post_type'] :'';	//post type
		$wps_pre_direction = isset($options['direction']) ? $options['direction'] :'';	//posts direction
		$slider_template = isset($options['template']) ? $options['template'] : '';	//slider template
		$wps_pre_align = isset($options['align_items']) ? $options['align_items'] : '';	//align
		$wa_wps_circular = isset($options['circular']) ? 'true' : 'false';	//circular
		$wa_wps_infinite = isset($options['infinite']) ? 'true' : 'false';	//infinite
		$taxonomy= isset($options['post_taxonomy']) ? $options['post_taxonomy'] : '';	//taxonomy
		$terms= isset($options['post_terms']) ? $options['post_terms'] : '';	//terems
		$tags= isset($options['post_tags']) ? $options['post_tags'] : '';	//tags
		$wa_wps_query_font_colour =  isset($options['font_colour']) ? $options['font_colour'] : '';//font colour
		$control_colour = isset($options['control_colour']) ? $options['control_colour'] : ''; //direction arrows colour
		$control_bg_colour = isset($options['control_bg_colour']) ? $options['control_bg_colour'] : '' ; //direction arrows background colour
		$arrows_hover_colour = isset($options['arrows_hover_colour']) ? $options['arrows_hover_colour'] : '' ; //direction arrows hover colour
		$size_arrows = isset($options['size_arrows']) ? $options['size_arrows'] : '' ;
		$title_font_size = isset($options['title_font_size']) ? $options['title_font_size'] : ''; //title font size
		$font_size = isset($options['font_size']) ? $options['font_size'] : ''; //general font size
		$custom_css = isset($options['custom_css']) ? $options['custom_css'] : ''; //custom styles
		$product_type = $options['product_type']; //product type
		$content_type= isset($options['content_type']) ? $options['content_type'] :'';	//post type
		$wa_wps_query_lazy_loading = isset($options['lazy_loading']) ? $options['lazy_loading'] : '' ;	//lazy loading enable
		$wa_wps_query_posts_lightbox = isset($options['lightbox']) ? $options['lightbox'] : '' ;	//lightbox
		$wa_wps_query_animate_controls = isset($options['animate_controls']) ? $options['animate_controls'] : '' ;//animate
		$wa_wps_query_css_transitions = isset($options['css_transitions']) ? $options['css_transitions'] : '' ;//css3 transitions
		$wa_wps_query_pause_on_hover = isset($options['pause_on_hover']) ? $options['pause_on_hover'] : '' ; //pause on hover
		$wa_wps_image_hover_effect = isset($options['image_hover_effect']) ? $options['image_hover_effect'] : '' ;	//image hover
		$lazy_img = $this->get_lazy_load_image($options['lazy_load_image']); //lazy load image
		$wa_wps_query_start_date = isset($options['start_date']) ? $options['start_date'] : '' ;//start date
		$wa_wps_query_end_date = isset($options['end_date']) ? $options['end_date'] : '' ;	//end date


		//data required for the template files.
		$wa_wps_query_posts_display_excerpt = isset($options['show_excerpt']) ? $options['show_excerpt'] : '' ; //display excerpt type boolean
		$wa_wps_query_posts_display_ratings = isset($options['show_rating']) ? $options['show_rating'] : '' ; //display rating  boolean
		$wa_wps_query_posts_display_read_more = isset($options['show_read_more_text']) ? $options['show_read_more_text'] : '' ;//display read more type boolean
		$wa_wps_query_posts_title =  isset($options['show_title']) ? $options['show_title'] : '' ;//display title type boolean
		$wa_wps_query_posts_image_height = isset($options['post_image_height']) ? $options['post_image_height'] : '' ; //thumbnail height string
		$wa_wps_query_posts_image_width =  isset($options['post_image_width']) ? $options['post_image_width'] : '' ; //thumbnail width string
		$wa_wps_read_more = isset($options['read_more_text']) ? $options['read_more_text'] : '' ; //read more text string
		$displayimage =   isset($options['show_image']) ? $options['show_image'] : '' ;//display image type boolean
		$word_imit = isset($options['word_limit']) ? $options['word_limit'] : '10' ;//word limit integer
		$wa_wps_query_display_sale_text_over_product_image = isset($options['show_sale_text_over_image']) ? $options['show_sale_text_over_image'] : '' ;//display sale text over pro image.
		$wa_wps_query_display_add_to_cart = isset($options['show_add_to_cart']) ? $options['show_add_to_cart'] : '' ; //display add to cart
		$wa_wps_query_display_price =  isset($options['show_price']) ? $options['show_price'] : '' ;//display price
		$wa_wps_query_display_quantity_input =  isset($options['show_quantity_input']) ? $options['show_quantity_input'] : '' ;//display quantity input
		$wa_wps_query_display_from_excerpt =   isset($options['excerpt_type']) ? $options['excerpt_type'] : '' ;//display text in excerpt field
		$wa_wps_query_show_categories =  isset($options['show_cats']) ? $options['show_cats'] : '' ;//show categories


		$wa_wps_query_image_size = isset($options['image_size']) ? $options['image_size'] : 'thumbnail' ; //image size

		// if($wa_wps_query_image_size == 'other') {

		// 	$wa_wps_query_image_size = array($wa_wps_query_posts_image_width,$wa_wps_query_posts_image_height);

		// }

		$wa_wps_text_align = isset($options['text_align']) ? $options['text_align'] : 'left';	//text align
		$wa_wps_image_size = isset($options['image_size']) ? $options['image_size'] : 'left';	//image align


		//schedule sliders
		$status = $this->get_status_of_schedule($wa_wps_query_start_date,$wa_wps_query_end_date);

		if($status==false) { return false;}

				$slider_gallery = '';

		$slider_gallery .= '<style>';

		 if(!empty($custom_css)) { echo $custom_css;  } 

		$slider_gallery .= '#wa_wps_slider_title'.$id.' { 

			color: '.$options['font_colour'].';

			font-size: '.$options['title_font_size'].'px;
		}';

		if($wa_wps_image_hover_effect=='hover_image'){ 

		$slider_gallery .= '.wa_wps_post_link {

			position: relative;

			display: block;
			
		}

		 .wa_featured_img .wa_wps_post_link .wa_wps_overlay {

			position: absolute;

			top: 0;

			left: 0;

			width: 100%;

			height: 100%;

			background: url('.$options['hover_image_url'].') 50% 50% no-repeat;

			background-color: '.$options['hover_image_bg'].';

			opacity: 0;
		}

		.wa_featured_img .wa_wps_post_link:hover .wa_wps_overlay  {

			opacity: 1;

			-moz-opacity: 1;

			filter: alpha(opacity=1);

		}';

		 } 

		$slider_gallery .= '#wa_wps_image_carousel'.$id.' {

			color: '.$options['font_colour'].';

			font-size: '.$options['font_size'].'px;';

			if($wps_pre_direction=="up"||$wps_pre_direction=="down") { 

		$slider_gallery .= 'width: '.$wa_wps_query_posts_item_width.'px;'; 

			 } 

		$slider_gallery .= '}';

		
		$slider_gallery .= '#wa_wps_image_carousel'.$id.' .wa_wps_text_overlay_caption:hover::before {

   			 background-color: '.$options['hover_image_bg'].'!important;
		}';
		
		$slider_gallery .= '#wa_wps_image_carousel'.$id.' .wa_wps_prev, #wa_wps_image_carousel'.$id.' .wa_wps_next,#wa_wps_image_carousel'.$id.' .wa_wps_prev_v, #wa_wps_image_carousel'.$id.' .wa_wps_next_v  {

			background: '.$options['control_bg_colour'].';

			color: '.$options['control_colour'].';

			font-size: '.$options['size_arrows'].'px;

			line-height: '.($options['size_arrows']+7).'px;

			width: '.($options['size_arrows']+10).'px;

			height: '.($options['size_arrows']+10).'px;

			margin-top: -'.$options['size_arrows'].'px;';

			if($wa_wps_query_animate_controls==1) {  if($wps_pre_direction=="left"||$wps_pre_direction=="right") { 

			$slider_gallery .= 'opacity: 0;';

			 } }

		$slider_gallery .= '}';

		$slider_gallery .= '#wa_wps_image_carousel'.$id.' .wa_wps_prev:hover, #wa_wps_image_carousel'.$id.' .wa_wps_next:hover {

			color: '.$options['arrows_hover_colour'].';

		}

		#wa_wps_pager_'.$id.' a {

			background: '.$options['arrows_hover_colour'].';

		}

		#wps_rating_'.$id.' {

			color: '.$options['arrows_hover_colour'].';

		}

		#wa_wps_image_carousel'.$id.' li img {';

			

			if($wa_wps_image_hover_effect=='grayscale'||$wa_wps_image_hover_effect=='saturate'||$wa_wps_image_hover_effect=='sepia') {  

			$slider_gallery .=  'filter: url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\'><filter id=\'grayscale\'><feColorMatrix type=\'matrix\' values=\'0.3333 0.3333 0.3333 0 0 0.3333 0.3333 0.3333 0 0 0.3333 0.3333 0.3333 0 0 0 0 0 1 0\'/></filter></svg>#grayscale"); /* Firefox 3.5+ */
			
			filter: gray; /* IE6-9 */

			-webkit-filter:'.$wa_wps_image_hover_effect.'(100%); /* Chrome 19+ & Safari 6+ */';

			 } 

		$slider_gallery .= '}';


		$slider_gallery .= '#wa_wps_image_carousel'.$id.' li .wa_featured_img {

			text-align: '.$options['image_align'].';

		}

		#wa_wps_image_carousel'.$id.' li  {

			text-align: '.$options['text_align'].';

		}

		#wa_wps_image_carousel'.$id.' li img:hover {';

			 if(!empty($wa_wps_image_hover_effect)) { 

			if($wa_wps_image_hover_effect=='border') { 

			$slider_gallery .= 'border : solid 1px'.$options['control_bg_colour'].';';

				} else if($wa_wps_image_hover_effect=='grayscale'||$wa_wps_image_hover_effect=='saturate'||$wa_wps_image_hover_effect=='sepia') { 

			$slider_gallery .= 'filter: none;
			-webkit-filter: '.$wa_wps_image_hover_effect.'(0%);';

			 } } 

		$slider_gallery .= '}';
		$slider_gallery .= '</style>';

		
		
		if($wps_pre_direction=="up"||$wps_pre_direction=="down") {

			$wps_pre_responsive = "0";

		}	else {

			$wps_pre_responsive = isset($options['responsive']) ? $options['responsive'] : '';

		}


		$data_to_be_passed = array(
			'id' => $id,
			'wps_pre_responsive' => $wps_pre_responsive ? $wps_pre_responsive : 0,
			'wa_wps_pre_direction' => $wps_pre_direction,
			'wps_pre_align' => $wps_pre_align,
			'wa_wps_auto' => $wa_wps_auto,
			'wa_wps_timeout' => $c_time_out,
			'c_items' => $c_items,
			'wa_wps_query_posts_fx' => $wa_wps_query_posts_fx,
			'c_easing' => $c_easing,
			'c_duration' => $c_duration,
			'wa_wps_query_pause_on_hover' => $wa_wps_query_pause_on_hover,
			'wa_wps_infinite' => $wa_wps_infinite,
			'wa_wps_circular' => $wa_wps_circular,
			'wa_wps_query_lazy_loading' => $wa_wps_query_lazy_loading,
			'wa_wps_query_posts_item_width' => $wa_wps_query_posts_item_width,
			'c_min_items' => $c_min_items,
			'wa_wps_query_css_transitions' => $wa_wps_query_css_transitions,
			'wa_wps_query_posts_lightbox' => $wa_wps_query_posts_lightbox,
			'wa_wps_query_animate_controls' => $wa_wps_query_animate_controls,
		);

		// add custom js
		$data_json_str = json_encode($data_to_be_passed);

		$slider_gallery .= '<script>';
		$slider_gallery .= "jQuery(document).ready(function($) {

		    var wa_vars = ".$data_json_str.";

		    //lazy loading
		    if (wa_vars.wa_wps_query_lazy_loading) {
		        function loadImage() {
		            jQuery('img.wa_lazy').lazyload({
		                container: jQuery('#wa_wps_image_carousel' + wa_vars.id)
		            });
		        }

		    }

		    $('#wa_wps_foo' + wa_vars.id).carouFredSel({
		            responsive: (wa_vars.wps_pre_responsive == 1) ? true : false,
		            direction: wa_vars.wa_wps_pre_direction,
		            align: wa_vars.wps_pre_align,
		            width: (wa_vars.wps_pre_responsive != 1) ? '100%' : '',
		            auto: {
		                play: wa_vars.wa_wps_auto,
		                timeoutDuration: wa_vars.wa_wps_timeout
		            },
		            scroll: {
		                items: (wa_vars.c_items && wa_vars.c_items != 0) ? wa_vars.c_items : '',
		                fx: wa_vars.wa_wps_query_posts_fx,
		                easing: wa_vars.c_easing,
		                duration: wa_vars.c_duration,
		                pauseOnHover: (wa_vars.wa_wps_query_pause_on_hover == 1) ? true : false,
		            },
		            infinite: wa_vars.wa_wps_infinite,
		            circular: wa_vars.wa_wps_circular,
		            onCreate: function(data) {
		                if (wa_vars.wa_wps_query_lazy_loading) {
		                    loadImage();
		                }
		            },
		            prev: {
		                onAfter: function(data) {
		                    if (wa_vars.wa_wps_query_lazy_loading) {
		                        loadImage();
		                    }
		                },

		                button: '#foo' + wa_vars.id + '_prev'
		            },
		            next: {
		                onAfter: function(data) {
		                    if (wa_vars.wa_wps_query_lazy_loading) {
		                        loadImage();
		                    }
		                },
		                button: '#foo' + wa_vars.id + '_next'
		            },
		            items: {
		                width: (wa_vars.wps_pre_responsive == 1) ? wa_vars.wa_wps_query_posts_item_width : '',
		                visible: (wa_vars.wps_pre_responsive == 0 && wa_vars.wa_wps_pre_direction == 'up' || wa_vars.wa_wps_pre_direction == 'down') ? wa_vars.c_min_items : '',

		                visible: {
		                    min: (wa_vars.wps_pre_responsive == 1) ? 1 : '',
		                    max: (wa_vars.wps_pre_responsive == 1) ? wa_vars.c_min_items : '',
		                }
		            },
		            pagination: {
		                container: '#wa_wps_pager_' + wa_vars.id
		            }
		        }

		        , {
		            transition: (wa_vars.wa_wps_query_css_transitions == 1) ? true : false
		        }


		    );
		    
		    //touch swipe
		    if (wa_vars.wa_wps_pre_direction == 'up' || wa_vars.wa_wps_pre_direction == 'down') {

		        $('#wa_wps_foo' + wa_vars.id).swipe({
		            excludedElements: 'button, input, select, textarea, .noSwipe',
		            swipeUp
		            : function() {
		                $('#wa_wps_foo' + wa_vars.id).trigger('next', 'auto');
		            },
		            swipeDown
		            : function() {
		                $('#wa_wps_foo' + wa_vars.id).trigger('prev', 'auto');
		                console.log('swipeRight');
		            },
		            tap: function(event, target) {
		                $(target).closest('.wa_wps_slider_title').find('a').click();
		            }
		        })

		    } else {
		        $('#wa_wps_foo' + wa_vars.id).swipe({
		            excludedElements: 'button, input, select, textarea, .noSwipe',
		            swipeLeft: function() {
		                $('#wa_wps_foo' + wa_vars.id).trigger('next', 'auto');
		            },
		            swipeRight: function() {
		                $('#wa_wps_foo' + wa_vars.id).trigger('prev', 'auto');
		                console.log('swipeRight');
		            },
		            tap: function(event, target) {
		                $(target).closest('.wa_wps_slider_title').find('a').click();
		            }
		        })
		    }

		    //magnific popup
		    if (wa_vars.wa_wps_query_posts_lightbox) {

		        jQuery('#wa_wps_foo' + $id).magnificPopup({
		            delegate: 'li .wa_featured_img > a', // child items selector, by clicking on it popup will open
		            type: 'image'
		            // other options
		        });
		    }

		    //animation for next and prev
		    if (wa_vars.wa_wps_query_animate_controls == 1) {
		        if (wa_vars.wa_wps_pre_direction == 'left' || wa_vars.wa_wps_pre_direction == 'right') {

		            jQuery('#wa_wps_image_carousel' + wa_vars.id)
		                .hover(function() {
		                    jQuery('#wa_wps_image_carousel' + wa_vars.id + ' .wa_wps_prev').animate({
		                        'left': '1.2%',
		                        'opacity': 1
		                    }), 300;
		                    jQuery('#wa_wps_image_carousel' + wa_vars.id + ' .wa_wps_next').animate({
		                        'right': '1.2%',
		                        'opacity': 1
		                    }), 300;
		                }, function() {
		                    jQuery('#wa_wps_image_carousel' + wa_vars.id + ' .wa_wps_prev').animate({
		                        'left': 0,
		                        'opacity': 0
		                    }), 'fast';
		                    jQuery('#wa_wps_image_carousel' + wa_vars.id + ' .wa_wps_next').animate({
		                        'right': 0,
		                        'opacity': 0
		                    }), 'fast';
		                });

		        }
		    }

		});";

		$slider_gallery .= '</script>';

		if($qp_order=="rand") {  

			$qp_orderby="rand"; 
		}

		$post_ids=explode(',', $qp_category);

		$args = array( 'numberposts' => $qp_showposts, 'suppress_filters' => false,  'post__in' => $post_ids,'post_status' => 'publish', 'order'=> $qp_order,  'orderby' => $qp_orderby,  'post_type' => $qp_post_type);
		
		$args_custom_post_type_only =  array( 'posts_per_page' => $qp_showposts, 'suppress_filters' => false, 'post_status' => 'publish', 'product_cat' => '', 'order'=> $qp_order, 'orderby' => $qp_orderby, 'post_type' => $qp_post_type);	
		
		$args_custom = array(
		 	'posts_per_page' => $qp_showposts,
		    'post_type' => $qp_post_type,
		    'order'=> $qp_order, 
		    'suppress_filters' => false,
		    'orderby' => $qp_orderby,
		    'post_status'  => 'publish',
		    'tax_query' => array(
		                array(
		                    'taxonomy' => $taxonomy,
		                    'field' => 'slug',
		                    'terms' => $terms
		                )
		            )
		    );

		//get specific posts
		if($qp_category&&$qp_post_type) {

			$myposts_posts = get_posts($args);
		
		}

		if($qp_post_type=='product') {

			if($product_type=='featured') {

				$args = array(  
				'post_type' => 'product',   
				'suppress_filters' => false,
				'posts_per_page' => $qp_showposts,
				'order'=> $qp_order, 
				'orderby' => $qp_orderby,
				'post_status'  => 'publish',				'meta_query' =>array(array(
    'key'       => '_stock_status',
    'value'     => 'outofstock',
    'compare'   => 'NOT IN'
)),
				'tax_query' => array(
                 array(
                     'taxonomy' => 'product_visibility',
                     'field'    => 'name',
                     'terms'    => 'featured',
                     'operator' => 'IN'
                 ),
          ));   
	  
				$myposts_custom = get_posts($args); 

			} else if($product_type=='best_selling') {

				$args = array(  
				'post_type' => 'product',  
				'meta_key' => 'total_sales',
				'orderby' => 'meta_value_num',
				'posts_per_page' => $qp_showposts,
				'order'=> $qp_order, 
				'suppress_filters' => false,
				'orderby' => $qp_orderby,
				'post_status'  => 'publish',
				'stock' => 1,
				'meta_query' =>array(array(
    'key'       => '_stock_status',
    'value'     => 'outofstock',
    'compare'   => 'NOT IN'
))
				);  
	  
				$myposts_custom = get_posts( $args );

			} else if($product_type=='newest') {

				$args = array(  
				'post_type' => 'product', 
				'suppress_filters' => false, 
				'orderby' =>'date','order' => 'DESC',
				'posts_per_page' => $qp_showposts,
				'post_status'  => 'publish',
				'stock' => 1,
								'meta_query' =>array(array(
    'key'       => '_stock_status',
    'value'     => 'outofstock',
    'compare'   => 'NOT IN'
))
				);  
	  
				$myposts_custom = get_posts( $args );

			} else if ($product_type=='on_sale') {

				$args = array(
				'post_type'      => 'product',
				'order'          => 'ASC',
				'suppress_filters' => false,
				'posts_per_page' => $qp_showposts,
				'orderby' => $qp_orderby,
				'post_status'  => 'publish',
				'meta_query'     => array(
				array(
				'key'           => '_sale_price',
				'value'         => 0,
				'compare'       => '>',
				'type'          => 'numeric'
				)
				)
				);

				$myposts_custom = get_posts( $args );

			} else if($product_type=='up_sells') {


				$pro_id = get_the_ID();

				if(empty($pro_id)) { return false; }

				//woocommerce get data
				if ( function_exists( 'get_product' ) ) {
				$product = get_product( get_the_ID() );
				} else {
					//check if woocommerce active
					if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		   				$product = new WC_Product( get_the_ID() );
					}

				}

				$upsells = $product->get_upsells();

				if (!$upsells)
        		return;

				$meta_query = WC()->query->get_meta_query();

				$args = array(
				'post_type'           => 'product',
				'ignore_sticky_posts' => 1,
				'no_found_rows'       => 1,
				'suppress_filters' => false,
				'posts_per_page' => $qp_showposts,
				'orderby' => $qp_orderby,
				'post__in'            => $upsells,
				'post_status'  => 'publish',
				'meta_query'          => $meta_query
				);

				$myposts_custom = get_posts( $args );



			} else if($product_type=='related') {

			$cats_array = wp_get_post_terms( get_the_ID(),'product_cat', array("fields" => "slugs") );
			$tags_array =  wp_get_post_tags( get_the_ID(),'product_cat', array("fields" => "slugs") );

			if(!empty($related_terms )||!empty($related_terms )) { return false; }

			$args_custom = array(
			 	'posts_per_page' => $qp_showposts,
			    'post_type' => $qp_post_type,
			    'order'=> $qp_order, 
			    'suppress_filters' => false,
			    'orderby' => $qp_orderby,
			    'post_status'  => 'publish',
			    'tax_query' => array(
	   					'relation' => 'OR',
                        array(
                                'taxonomy' => 'product_cat',
                                'field' => 'slug',
                                'terms' => $cats_array
                        ),
                        array(
                                'taxonomy' => 'product_tag',
                                'field' => 'slug',
                                'terms' => $tags_array
                        )
			            )
		    );

			$myposts_custom = get_posts( $args_custom );
							
			} else if($product_type=='category') {

				if($qp_orderby=='_price') {

					$args_custom = array(
					'posts_per_page' => $qp_showposts,
					'post_type' => $qp_post_type,
					'order'=> $qp_order, 
					'suppress_filters' => false,
					'meta_key' =>$qp_orderby,
					'orderby' => 'meta_value_num',
					'post_status'  => 'publish',
					'tax_query' => array(
					array(
					'taxonomy' => 'product_cat',
					'field' => 'slug',
					'terms' => $terms
					)
					)
					);

				} else {

					$args_custom = array(
					'posts_per_page' => $qp_showposts,
					'post_type' => $qp_post_type,
					'order'=> $qp_order, 
					'suppress_filters' => false,
					'orderby' => $qp_orderby,
					'post_status'  => 'publish',
					'tax_query' => array(
					array(
					'taxonomy' => 'product_cat',
					'field' => 'slug',
					'terms' => $terms
					)
					)
					);
				}

			$myposts_custom = get_posts( $args_custom );

			} else if($product_type=='top_rated') {

				global $woocommerce;

				add_filter( 'posts_clauses',  array( $woocommerce->query, 'order_by_rating_post_clauses' ) );
				$query_args = array('posts_per_page' => $qp_showposts, 'no_found_rows' => 1, 'post_status' => 'publish', 'post_type' => 'product' );
				$query_args['meta_query'] = $woocommerce->query->get_meta_query();

				$myposts_custom = get_posts( $query_args );


			} else if($product_type=='specific') {

				$myposts_posts = get_posts($args);

			} else if($product_type=='tag') {


								if($qp_orderby=='_price') {

					$args_custom = array(
					'posts_per_page' => $qp_showposts,
					'post_type' => $qp_post_type,
					'order'=> $qp_order, 
					'suppress_filters' => false,
					'meta_key' =>$qp_orderby,
					'orderby' => 'meta_value_num',
					'post_status'  => 'publish',
								    'tax_query' => array(
	   					'relation' => 'OR',
                        array(
                                'taxonomy' => 'product_tag',
                                'field' => 'slug',
                                'terms' => $tags
                        )
			            )
					);

				} else {

					$args_custom = array(
					'posts_per_page' => $qp_showposts,
					'post_type' => $qp_post_type,
					'order'=> $qp_order, 
					 'order'=> $qp_order, 
			    'suppress_filters' => false,
			    'orderby' => $qp_orderby,
			    'post_status'  => 'publish',
			    'tax_query' => array(
	   					'relation' => 'OR',
                        array(
                                'taxonomy' => 'product_tag',
                                'field' => 'slug',
                                'terms' => $tags
                        )
			            )
					);
				}

			$myposts_custom = get_posts( $args_custom );


			}

		} else 	if($qp_post_type=='post') {

			 if($content_type=='newest') {

				$args = array(  
				'post_type' => $qp_post_type, 
				'suppress_filters' => false, 
				'orderby' =>'date','order' => 'DESC',
				'posts_per_page' => $qp_showposts,
				'post_status'  => 'publish',
				'stock' => 1
				);  
	  
				$myposts_custom = get_posts( $args );

			} else if($content_type=='related') {


			$myposts_custom = get_posts($this->wa_get_related_posts(get_the_ID(),$qp_showposts ));

							
			} else if($content_type=='most_viewed') {

						$args_custom = array(
					'posts_per_page' => $qp_showposts,
					'post_type' => $qp_post_type,
					'order'=> 'DESC', 
					'suppress_filters' => false,
					'meta_key' =>'post_views_count',
					'orderby' => 'meta_value_num',
					'post_status'  => 'publish'
					);
				$myposts_custom = get_posts( $args_custom );

			} else if($content_type=='category') {
			
					$args_custom = array(
					'posts_per_page' => $qp_showposts,
					'post_type' => $qp_post_type,
					'order'=> $qp_order, 
					'suppress_filters' => false,
					'orderby' => $qp_orderby,
					'post_status'  => 'publish',
					'tax_query' => array(
					array(
					'taxonomy' => 'category',
					'field' => 'slug',
					'terms' => $terms
					)
					)
					);
				

				$myposts_custom = get_posts( $args_custom );

			} else if($content_type=='specific') {

				$myposts_posts = get_posts($args);

			} else if($content_type=='tag') {



				$args_custom = array(
					'posts_per_page' => $qp_showposts,
					'post_type' => $qp_post_type,
					'order'=> $qp_order, 
					'suppress_filters' => false,
					'orderby' => $qp_orderby,
					'post_status'  => 'publish',
								    'tax_query' => array(
	   					'relation' => 'OR',
                        array(
                                'taxonomy' => 'post_tag',
                                'field' => 'slug',
                                'terms' => $tags
                        )
			            )
					);
				

				$myposts_custom = get_posts( $args_custom );


			}

		} else {


			if($qp_post_type&&$taxonomy&&$terms) {

				$myposts_custom = get_posts( $args_custom );

			} else if($qp_post_type&&!$taxonomy&&!$qp_category) {

				$myposts_custom = get_posts($args_custom_post_type_only);

			}

		}

		if(isset($myposts_posts)&&isset($myposts_custom)) {

			$myposts = array_merge($myposts_posts,$myposts_custom );

		}else if(isset($myposts_posts)) {

			$myposts = $myposts_posts;

		}else if(isset($myposts_custom)) {

			$myposts = $myposts_custom;

		}
			
		if(!isset($myposts)||empty($myposts)){ 

			return false;
		}
		

		//include theme
		include $this->wa_wps_file_path($slider_template);

		wp_reset_postdata();


		if( $slider_template != 'variation') {

		return $slider_gallery;

		}
			
	}

	// view path for the theme files
	public function wa_wps_file_path( $view_name, $is_php = true ) {
		
		$temp_path = get_stylesheet_directory().'/woocommerce-product-slider/themes/';

		if(file_exists($temp_path)) {

			if ( strpos( $view_name, '.php' ) === FALSE && $is_php )
		return $temp_path.'/'.$view_name.'/'.$view_name.'.php';
		return $temp_path . $view_name;

		} else {

			if ( strpos( $view_name, '.php' ) === FALSE && $is_php )
		return WA_WPS_PLUGIN_TEMPLATE_DIRECTORY.'/'.$view_name.'/'.$view_name.'.php';
		return WA_WPS_PLUGIN_TEMPLATE_DIRECTORY . $view_name;
		}

	}

	//remove default auto save
	function wa_wps_disable_autosave() {

	    global $post;
	    if(isset($post->ID)&&get_post_type($post->ID) == 'wa_wps'){
	        wp_dequeue_script('autosave');
	    }
	}

	//remove default publish box of the custom post type 
	function wa_wps_remove_publish_box() {

    	remove_meta_box( 'submitdiv', 'wa_wps', 'side' );
	
	}

	//add metaboxes to the page
	function wa_wps_add_meta_boxes() {

		add_meta_box('wa_wps_custom_publish_meta_box',__( 'Save', 'wps' ),array( $this, 'wa_wps_custom_publish_meta_box' ),'wa_wps','side');
		add_meta_box('wa_wps_shortcode_meta_box',__( 'Shortcode', 'wps' ),array( $this, 'wa_wps_shortcode_meta_box' ),'wa_wps','side');
		add_meta_box('wa_wps_options_metabox',__( 'Options', 'wps' ),array( $this, 'wa_wps_options_meta_box' ),'wa_wps');

	}

	//custom publish meta box
	function wa_wps_custom_publish_meta_box( $post ) {

		$slider_id = $post->ID;
		$post_status = get_post_status( $slider_id );
		$delete_link = get_delete_post_link( $slider_id );
		$nonce = wp_create_nonce( 'ssp_slider_nonce' );
		include $this->wa_wps_view_path( __FUNCTION__ );
	}


	//publish meta box
	function wa_rs_custom_publish_meta_box( $post ) {

		$slider_id = $post->ID;
		$post_status = get_post_status( $slider_id );
		$delete_link = get_delete_post_link( $slider_id );
		$nonce = wp_create_nonce( 'ssp_slider_nonce' );
		include $this->wa_wps_view_path( __FUNCTION__ );
	}

	//short code meta box
	function wa_wps_shortcode_meta_box( $post ) {
		$slider_id = $post->ID;
		if ( get_post_status( $slider_id ) !== 'publish' ) {

			echo __( '<p>Please, fill the required fields. Then click on the Create Slider button to get the slider shortcode.</p>', 'wps' );
			return;
		}
		$slider_title = get_the_title( $slider_id );
		$shortcode = sprintf( "[%s id='%s']", 'wa-wps', $slider_id, $slider_title );
		$template_code = sprintf( "<?php echo do_shortcode('[%s id=%s]');?>", 'wa-wps', $slider_id, $slider_title );
		include $this->wa_wps_view_path( __FUNCTION__ );
	}

	//set options meta box
	function wa_wps_options_meta_box( $post ) {
		$slider_id = $post->ID;

		$slider_options = get_post_meta( $slider_id, 'options', true );

		if ( ! $slider_options )
			$slider_options = self::default_options();

		include $this->wa_wps_view_path( __FUNCTION__ );
	}


	//view path for the template files
	function wa_wps_view_path( $view_name, $is_php = true ) {

	if ( strpos( $view_name, '.php' ) === FALSE && $is_php )
		return WA_WPS_PLUGIN_VIEW_DIRECTORY.$view_name.'.php';
		
		return WA_WPS_PLUGIN_VIEW_DIRECTORY . $view_name;
	}

	//register setting for admin page 
	public function register_settings() {

		register_setting('wa_wps_settings', 'wa_wps_settings', array(&$this, 'validate_options'));
		//general settings
		add_settings_section('wa_wps_settings', __('', 'wps'), '', 'wa_wps_settings');
		add_settings_field('wa_wps_loading_place', __('Loading place:', 'wps'), array(&$this, 'wa_wps_loading_place'), 'wa_wps_settings', 'wa_wps_settings');
		add_settings_field('wa_wps_jquery', __('Load jQuery:', 'wps'), array(&$this, 'wa_wps_jquery'), 'wa_wps_settings', 'wa_wps_settings');
		add_settings_field('wa_wps_transit', __('Load transit:', 'wps'), array(&$this, 'wa_wps_transit'), 'wa_wps_settings', 'wa_wps_settings');
		add_settings_field('wa_wps_magnific_popup', __('Magnific popup:', 'wps'), array(&$this, 'wa_wps_magnific_popup'), 'wa_wps_settings', 'wa_wps_settings');
		add_settings_field('wa_wps_caroufredsel', __('CarouFredsel:', 'wps'), array(&$this, 'wa_wps_caroufredsel'), 'wa_wps_settings', 'wa_wps_settings');
		add_settings_field('wa_wps_lazyload', __('Lazyload:', 'wps'), array(&$this, 'wa_wps_lazyload'), 'wa_wps_settings', 'wa_wps_settings');
		add_settings_field('wa_wps_touch_swipe', __('TouchSwipe:', 'wps'), array(&$this, 'wa_wps_touch_swipe'), 'wa_wps_settings', 'wa_wps_settings');
		add_settings_field('wa_wps_deactivation_delete', __('Deactivation:', 'wps'), array(&$this, 'wa_wps_deactivation_delete'), 'wa_wps_settings', 'wa_wps_settings');

	}

	//loading place
	public function wa_wps_loading_place() {
		echo '
		<div id="wa_wps_loading_place" class="wplikebtns">';

		foreach($this->loading_places as $val => $trans)
		{
			$val = esc_attr($val);

			echo '
			<input id="rll-loading-place-'.$val.'" type="radio" name="wa_wps_settings[loading_place]" value="'.$val.'" '.checked($val, $this->options['settings']['loading_place'], false).' />
			<label for="rll-loading-place-'.$val.'">'.esc_html($trans).'</label>';
		}

		echo '
			<p class="description">'.__('Select where all the scripts should be placed.', 'wps').'</p>
		</div>';
	}

	//delete on deactivation
	public function wa_wps_deactivation_delete() {
		echo '
		<div id="rll_deactivation_delete" class="wplikebtns">';

		foreach($this->choices as $val => $trans) {
			echo '
			<input id="rll-deactivation-delete-'.$val.'" type="radio" name="wa_wps_settings[deactivation_delete]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['deactivation_delete'], FALSE).' />
			<label for="rll-deactivation-delete-'.$val.'">'.$trans.'</label>';
		}

		echo '
			<p class="description">'.__('Delete settings on plugin deactivation.', 'wps').'</p>
		</div>';
	}

	//enable jquery
	public function wa_wps_jquery() {
		echo '
		<div id="wa_wps_jquery" class="wplikebtns">';

	foreach($this->choices as $val => $trans)
		{
			$val = esc_attr($val);

			echo '
			<input id="jquery-'.$val.'" type="radio" name="wa_wps_settings[jquery]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['jquery'], false).' />
			<label for="jquery-'.$val.'">'.esc_html($trans).'</label>';
		}

		echo '
			<p class="description">'.__('Enable this option, if you dont have jQuery on your website.', 'wps').'</p>
		</div>';
	}

	//load transit
	public function wa_wps_transit() {
		echo '
		<div id="wa_wps_transit" class="wplikebtns">';

	foreach($this->choices as $val => $trans)
		{
			$val = esc_attr($val);

			echo '
			<input id="transit-'.$val.'" type="radio" name="wa_wps_settings[transit]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['transit'], false).' />
			<label for="transit-'.$val.'">'.esc_html($trans).'</label>';
		}

		echo '
			<p class="description">'.__('Disable this option, if this script has already loaded on your web site.', 'wps').'</p>
		</div>';
	}

	//load magnific popup
	public function wa_wps_magnific_popup() {
		echo '
		<div id="wa_wps_magnific_popup" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			$val = esc_attr($val);

			echo '
			<input id="magnific-popup-'.$val.'" type="radio" name="wa_wps_settings[magnific_popup]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['magnific_popup'], false).' />
			<label for="magnific-popup-'.$val.'">'.esc_html($trans).'</label>';
		}

		echo '
			<p class="description">'.__('Disable this option, if this script has already loaded on your web site.', 'wps').'</p>
		</div>';
	}

	//load caroufredsel
	public function wa_wps_caroufredsel() {
		echo '
		<div id="wa_wps_caroufredsel" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			$val = esc_attr($val);

			echo '
			<input id="caroufredsel-'.$val.'" type="radio" name="wa_wps_settings[caroufredsel]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['caroufredsel'], false).' />
			<label for="caroufredsel'.$val.'">'.esc_html($trans).'</label>';
		}

		echo '
			<p class="description">'.__('Disable this option, if this script has already loaded on your web site.', 'wps').'</p>
		</div>';
	}

	//load lazy load
	public function wa_wps_lazyload() {

		echo '
		<div id="wa_wps_lazyload" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			$val = esc_attr($val);

			echo '
			<input id="lazyload-'.$val.'" type="radio" name="wa_wps_settings[lazyload]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['lazyload'], false).' />
			<label for="lazyload-'.$val.'">'.esc_html($trans).'</label>';
		}

		echo '
			<p class="description">'.__('Disable this option, if this script has already loaded on your web site.', 'wps').'</p>
		</div>';
	}


	//touch swipe
	public function wa_wps_touch_swipe() {

		echo '
		<div id="wa_wps_touch_swipe" class="wplikebtns">';

		foreach($this->choices as $val => $trans)
		{
			$val = esc_attr($val);

			echo '
			<input id="touchswipe-'.$val.'" type="radio" name="wa_wps_settings[touchswipe]" value="'.esc_attr($val).'" '.checked(($val === 'yes' ? TRUE : FALSE), $this->options['settings']['touchswipe'], false).' />
			<label for="touchswipe-'.$val.'">'.esc_html($trans).'</label>';
		}

		echo '
			<p class="description">'.__('Disable this option, if this script has already loaded on your web site.', 'wps').'</p>
		</div>';

	}

	//get all post types
	public function get_post_types() {

		$post_types = get_post_types( '', 'names' ); 

		return $post_types;

	}

	//list of directories
	public function list_themes() {

		$temp_path = get_template_directory().'/woocommerce-product-slider/themes/';

		if(file_exists($temp_path)) {

			$dir = new DirectoryIterator($temp_path);

		} else {

			$dir = new DirectoryIterator(WA_WPS_PLUGIN_TEMPLATE_DIRECTORY);
		}

		foreach ($dir as $fileinfo) {
		if ($fileinfo->isDir() && !$fileinfo->isDot()) {
		$list_of_themes[] = $fileinfo->getFilename();
			}
		}
		return $list_of_themes;

	}

	//get product categories
	public function get_product_category_first_name($qp_post_type, $post_id) {

		$first_cat_name = ' ';
				//get product category name
		if($qp_post_type=='product') {

			$args = array( 'taxonomy' => 'product_cat',);
			$terms = wp_get_post_terms($post_id,'product_cat', $args);

			$first_cat_name = !empty($terms[0]->name)?$terms[0]->name:'';

		} else {

			$category = get_the_category($post_id);
			$first_cat_name = !empty($category) ? $category[0]->cat_name : '';

		}

		return $first_cat_name;
	}

	//get text type to display
	public function get_text_type($wa_post, $wa_wps_query_display_from_excerpt) {

		$text_type = '';

		if($wa_wps_query_display_from_excerpt==1) {

			$text_type = $wa_post->post_excerpt;
		} else {

			$text_type = $wa_post->post_content;

		}

		return $text_type;

	}

	//options page
	public function options_page() {

		$tab_key = (isset($_GET['tab']) ? $_GET['tab'] : 'general-settings');

		echo '<div class="wrap">
			<h2>'.__('WooCommerce product slider', 'wps').'</h2>
			<h2 class="nav-tab-wrapper">';

		foreach($this->tabs as $key => $name) {
			echo '
			<a class="nav-tab '.($tab_key == $key ? 'nav-tab-active' : '').'" href="'.esc_url(admin_url('admin.php?page=woocommerce-product-slider&tab='.$key)).'">'.$name['name'].'</a>';
		}

		echo '
			</h2>
			<div class="wa-wps-settings">
				<div class="wa-credits">
					
					<div class="inside">

					<table>

						<tr>

						<td>'.__('Configuration:  ', 'wps') .'</td>
						<td><a href="http://weaveapps.com/shop/wordpress-plugins/woocommerce-product-slider/#installation" target="_blank" title="'.__(' documentation', 'wps').'">'.__('  Installation', 'wps').'</a></td>

						</tr>

							<tr>

						<td>'.__('Support:  ', 'wps') .'</td>
						<td><a href="https://wordpress.org/support/plugin/woocommerce-product-slider" target="_blank" title="'.__(' documentation', 'wps').'">'.__('  Support', 'wps').'</a></td>

						</tr>

						<tr>

						<td>'.__('Do you like this plugin?  ', 'wps').'</td>
						<td><a href="https://wordpress.org/plugins/woocommerce-product-slider/#reviews" target="_blank" title="'.__('author URI', 'wps').'">'.__('Please rate here', 'wps').'</a></td>

						</tr>

						<tr>

						<td>'.__('Looking for more features?  ', 'wps') .'</td>
						<td><a href="http://weaveapps.com/shop/wordpress-plugins/woocommerce-product-slider-pro/" target="_blank" title="'.__('Upgrade to pro', 'wps').'">'.__('  Upgrade to pro', 'wps').'</a></td>	</tr>

						<tr>

					</table>
					
				</div>
				</div><form action="options.php" method="post">';

		wp_nonce_field('update-options');
		settings_fields($this->tabs[$tab_key]['key']);
		do_settings_sections($this->tabs[$tab_key]['key']);

		echo '<p class="submit">';
		submit_button('', 'primary', $this->tabs[$tab_key]['submit'], FALSE);
		echo ' ';
		echo submit_button(__('Reset to defaults', 'wps'), 'secondary', $this->tabs[$tab_key]['reset'], FALSE);
		echo '</p></form></div><div class="clear"></div></div>';
	}

	//load defaults
	public function load_defaults() {
		
		$this->choices = array(
			'yes' => __('Enable', 'wps'),
			'no' => __('Disable', 'wps')
		);

		$this->loading_places = array(
			'header' => __('Header', 'wps'),
			'footer' => __('Footer', 'wps')
		);

		$this->tabs = array(
			'general-settings' => array(
				'name' => __('General settings', 'wps'),
				'key' => 'wa_wps_settings',
				'submit' => 'save_wps_settings',
				'reset' => 'reset_wps_settings',
			)
		);
	}

	//default options
	public static function default_options() {

		$default_img = plugins_url().'/woocommerce-product-slider/assets/images/default-image.jpg'; // default image
		$loading_img = plugins_url().'/woocommerce-product-slider/assets/images/loader.gif'; // loading image
		$hover_img = plugins_url().'/woocommerce-product-slider/assets/images/hover.png'; // loading image

		$default_options = array(
			'post_type' => '',
			'product_type' => '',
			'post_taxonomy' => '',
			'post_terms' => '',
			'content_type' => '',
			'post_ids' => '',
			'posts_order_by' => 'id',
			'post_order' => 'asc',
			'template' => 'basic',
			'image_hover_effect' => 'none',
			'read_more_text' => 'Read more',
			'word_limit' => '10',
			'show_posts' => '20',
			'show_posts_per_page' => '4',
			'items_to_be_slide' => '0',
			'duration' => '500',
			'item_width' => '200',
			'item_height' => '350',
			'post_image_width' => '',
			'post_image_height' => '',
			'image_type' => 'featured',
			'easing_effect' => 'linear',
			'fx' => 'scroll',
			'align_items' => 'center',
			'font_colour' => '#000',
			'control_colour' => '#fff',
			'control_bg_colour' => '#000',
			'arrows_hover_colour' => '#ccc',
			'size_arrows' => '18',
			'title_font_size' => '14',
			'font_size' => '12',
			'default_image' => $default_img,
			'lazy_load_image' => $loading_img,
			'show_title' => true,
			'show_rating' => false,
			'show_image' => true,
			'show_excerpt' => true,
			'title_top_of_image' => true,
			'show_read_more_text' => true,
			'excerpt_type' => false,
			'responsive' => false,
			'lightbox' => false,
			'lazy_loading' => false,
			'auto_scroll' => true,
			'draggable' => true,
			'circular' => false,
			'infinite' => true,
			'touch_swipe' => true,
			'direction' => 'right',
			'show_controls' => true,
			'animate_controls' => true,
			'show_paging' => true,
			'css_transitions' => true,
			'pause_on_hover' => true,
			'show_price' => true,
			'show_sale_text_over_image'=> true,
			'show_add_to_cart'=> true,
			'show_product_categories' => true,
			'start_date' => '',
			'end_date' => '',
			'timeout' => '3000',
			'hover_image_bg' => 'rgba(40,168,211,.85)',
			'hover_image_url' => $hover_img,
			'text_align' => 'left',
			'image_size' => 'other',
			'image_align' => 'left',
		);

		return apply_filters( 'wa_wps_default_options', $default_options );

	}

	//validate options and register settings
	public function validate_options($input) {

		if(isset($_POST['save_wps_settings'])) {

			// loading place
			$input['loading_place'] = (isset($input['loading_place'], $this->loading_places[$input['loading_place']]) ? $input['loading_place'] : $this->defaults['settings']['loading_place']);

			// checkboxes
			$input['caroufredsel'] = (isset($input['caroufredsel'], $this->choices[$input['caroufredsel']]) ? ($input['caroufredsel'] === 'yes' ? true : false) : $this->defaults['settings']['caroufredsel']);
			$input['magnific_popup'] = (isset($input['magnific_popup'], $this->choices[$input['magnific_popup']]) ? ($input['magnific_popup'] === 'yes' ? true : false) : $this->defaults['settings']['magnific_popup']);
			$input['lazyload'] = (isset($input['lazyload'], $this->choices[$input['lazyload']]) ? ($input['lazyload'] === 'yes' ? true : false) : $this->defaults['settings']['lazyload']);
			$input['touchswipe'] = (isset($input['touchswipe'], $this->choices[$input['touchswipe']]) ? ($input['touchswipe'] === 'yes' ? true : false) : $this->defaults['settings']['touchswipe']);
			$input['jquery'] = (isset($input['jquery'], $this->choices[$input['jquery']]) ? ($input['jquery'] === 'yes' ? true : false) : $this->defaults['settings']['jquery']);
			$input['transit'] = (isset($input['transit'], $this->choices[$input['transit']]) ? ($input['transit'] === 'yes' ? true : false) : $this->defaults['settings']['transit']);
			$input['deactivation_delete'] = (isset($input['deactivation_delete'], $this->choices[$input['deactivation_delete']]) ? ($input['deactivation_delete'] === 'yes' ? true : false) : $this->defaults['settings']['deactivation_delete']);
		

		} else if (isset($_POST['reset_wps_settings'])) {
			$input = $this->defaults['settings'];

			add_settings_error('reset_general_settings', 'general_reset', __('Settings restored to defaults.', 'wps'), 'updated');
		}

		return $input;
	}

	//init process for registering button
	public function wa_wps_shortcode_button_init() {

	      if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') && get_user_option('rich_editing') == 'true')
	           return;
   
	      add_filter("mce_external_plugins", array(&$this, 'wa_wps_register_tinymce_plugin'));

	      add_filter('mce_buttons', array(&$this, 'wa_wps_add_tinymce_button'));
	}


	//registers plugin  to TinyMCE
	public function wa_wps_register_tinymce_plugin($plugin_array) {

	    $plugin_array['wa_wps_button'] = plugins_url('assets/js/shortcode/shortcode.js', __FILE__);

	    return $plugin_array;
	}

	//add button to the toolbar
	public function wa_wps_add_tinymce_button($buttons) {

	    $buttons[] = "wa_wps_button";

	    return $buttons;
	}

	//register post type for the slider
	function wa_wps_init() {

	  $labels = array(
	        'name' => _x('WooCommerce product slider', 'post type general name'),
	        'singular_name' => _x('slider', 'post type singular name'),
	        'add_new' => _x('Add New', 'wa_rs_slider'), 
	        'add_new_item' => __('Add new slider'),
	        'edit_item' => __('Edit slider'),
	        'new_item' => __('New slider'),
	        'view_item' => __('View slider'),
	        'search_items' => __('Search sliders'),
	        'not_found' => __('No records found'),
	        'not_found_in_trash' => __('No records found in Trash'),
	        'parent_item_colon' => '',
	        'menu_name' => 'Product slider'
	    );

	    $args = array(
	        'labels' => $labels,
	        'public' => false,
	        'menu_icon' => plugins_url('/assets/js/shortcode/b_img.png', __FILE__),
	        'publicly_queryable' => false,
	        'show_ui' => true,
	        'show_in_menu' => true,
	        'menu_position' => 5,
	        'query_var' => false,
	        'rewrite' => false,
	        'capability_type' => 'post',
	        'has_archive' => true,
	        'hierarchical' => false,
	        'supports' => array('title')
	    );

	    register_post_type('wa_wps', $args);
	}

	//update messages
	function wa_wps_updated_messages($messages) {

	    global $post, $post_ID;
	    $messages['wa_wps'] = array(
	        0 => '',
	        1 => sprintf(__('Slider updated.'), esc_url(get_permalink($post_ID))),
	        2 => __('Custom field updated.'),
	        3 => __('Custom field deleted.'),
	        4 => __('Slider updated.'),
	        5 => isset($_GET['revision']) ? sprintf(__('Slider restored to revision from %s'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
	        6 => sprintf(__('Slider published.'), esc_url(get_permalink($post_ID))),
	        7 => __('Slider saved.'),
	        8 => sprintf(__('Slider submitted.'), esc_url(add_query_arg('preview', 'true', get_permalink($post_ID)))),
	        9 => sprintf(__('Slider scheduled for: <strong>%1$s</strong>. '), date_i18n(__('M j, Y @ G:i'), strtotime($post->post_date)), esc_url(get_permalink($post_ID))),
	        10 => sprintf(__('Slider draft updated.'), esc_url(add_query_arg('preview', 'true', get_permalink($post_ID)))),
	    );
	    return $messages;

	}


	//save data
	function wa_wps_save_metabox_data ($post_id) {

		if ( ! current_user_can( 'edit_post', $post_id ) )
			return;

		if ( wp_is_post_revision( $post_id ) )
			return;
		
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;
		
		if ( ! isset( $_POST['post_type_is_wa_wps'] ) )
			return;

			$slider_id = $post_id;

			$slider_options_default = self::default_options();
			$slider_options = wp_parse_args( $_POST['slider_options'],   $slider_options_default );
			$slider_options = $_POST['slider_options'];

			foreach ( $slider_options as $key => $option ):
				if ( $option === "true" )
					$slider_options[$key] = true;
				if ( $option === "false" )
					$slider_options[$key] = false;
			endforeach;

			update_post_meta( $slider_id, 'options', $slider_options );
	}

}

$Woocommerce_Product_Slider = new Woocommerce_Product_Slider();