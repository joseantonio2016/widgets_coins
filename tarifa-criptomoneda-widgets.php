<?php
/**
 * Plugin Name: 		Tarifa Criptomonedas Widgets
 * Plugin URI:          
 * Description: 		Widgets para Wordpress. Selecciona criptomonedas y aplica tarifas personalizables
 * Author: 				Jose Huillca
 * Author URI:			
 * Version: 			1.0.0
 * Requires at least:   4.3.0
 * Requires PHP:        5.6
 * Tested up to:        5.7.2
 * License: 			GPL v3
 * Text Domain:			tarifa-criptomoneda-widgets
 * Domain Path: 		/languages
 * Copyright 2020 Blocksera Technologies
**/

if (!defined('ABSPATH')) {
    exit;
}

define('TCW_VERSION', '1.0.0');
define('TCW_PATH', plugin_dir_path(__FILE__));
define('TCW_URL', plugin_dir_url(__FILE__));

require_once TCW_PATH . 'includes/all.php';

if (!class_exists('TarifaCripto')) {

    class TarifaCripto {

        public function __construct() {
            

            $data = new TarifaCriptoData();            

            //$this->config = array_merge($data->config, get_option('tcw_config', array()));
            $this->config = $data->config;
            $this->fonts = $data->fonts;
            $this->changelly = $data->changelly;
            $this->options = array_merge($data->options, get_option('tcw_tcripto', array()));
            $this->providers = $data->providers;
            //$this->options['config'] = apply_filters('tcw_get_config', $this->config);

            $this->init();
            $this->create_post_type_cryptoc();

            register_activation_hook(__FILE__, array($this, 'activate'));
            register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        }

        public function fetch_reset($config) {
            //add_filter( 'admin_footer_text', function( $ft ) { return '<strong>' . $ft .' fetch reset</strong>'; } );
            $cache = get_transient('tcw-datatime');
           // add_filter( 'admin_footer_text', function( $ft ) { return '<strong>' . $ft .' fetch reset</strong>'; } );
            $api_interval = ($config['api'] == 'coingecko') ? 900 : $config['api_interval'];
            $ccnum_fetch  = $config['ccnum_fetch'];
            //add_filter( 'admin_footer_text', function( $ft ) use ($ccnum_fetch) { return '<strong>' . $ft .' fetch reset'.$ccnum_fetch.'</strong>'; } );
            if ($cache === false || $cache < (time() - $api_interval)) {
                
                switch ($config['api']) {

                    case 'coingecko':
                        
                        $request = wp_remote_get(
                            'https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&order=market_cap_rank&per_page='
                            .$ccnum_fetch.'&page=1');

                        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
                            /* $this->wpdb->get_results("SELECT `slug` FROM `{$this->tablenam}`");

                            if ($this->wpdb->num_rows > 0) {
                                set_transient('tcw-datatime', time(), 60);
                            } */
                            return false;
                        }

                        $body = wp_remote_retrieve_body($request);
                        $data = json_decode($body);
    
                        if (!empty($data)) {
                                
                
                                foreach ($data as $coin) {
                                    $coin->image = strpos($coin->image, 'coingecko.com') ? strtok($coin->image, '?') : TCW_URL . 'assets/public/img/missing.png';
                                   // $cc_id=$this->criptomoneda_existe($coin->name)
                                    if($cc_id=$this->criptomoneda_existe($coin->id)){
                                        $ccpost = get_post($cc_id);
                                        $postcontent = json_decode($ccpost->post_content, true);

                                        //solo permanece comventa y comcompra
                                        $args = array(
                                            'ID' => $cc_id,
                                            'post_title'    => $coin->name,
                                            'post_content'  => wp_json_encode(array(
                                                'symbol' => strtoupper($coin->symbol),
                                                'logo' => esc_url_raw($coin->image),
                                                'api' => $config['api'],
                                                'comventa'=>$postcontent['comventa'],
                                                'comcompra'=>$postcontent['comcompra']
                                            )),
                                            'post_name'     => $coin->id,
                                            'post_status'   => 'publish',
                                            'post_author'   => 1,
                                            'post_excerpt'  => floatval($coin->current_price),
                                            'post_type' => 'cryptoc',
                                            'post_mime_type' => 'application/json',
                                            'meta_input'   => array(
                                                'ccrank' => intval($coin->market_cap_rank),
                                            ),
                                        );
                                          $post_id = wp_update_post($args,true);
                                          if(is_wp_error($post_id)){
                                            //there was an error in the post insertion, 
                                            wp_die($post_id->get_error_message(),
                                            'Error al actualizar criptomoneda'); 
                                          }

                                    }else{
                                        $args = array(
                                            'post_title'    => $coin->name,
                                            'post_content'  => wp_json_encode(array(
                                                'symbol' => strtoupper($coin->symbol),
                                                'logo' => esc_url_raw($coin->image),
                                                'api' => $config['api'],
                                                'comventa'=>$config['comventa'],
                                                'comcompra'=>$config['comcompra']
                                            )),
                                            'post_name'     => $coin->id,
                                            'post_status'   => 'publish',
                                            'post_author'   => 1,
                                            'post_excerpt'  => floatval($coin->current_price),
                                            'post_type' => 'cryptoc',
                                            'post_mime_type' => 'application/json',
                                            'meta_input'   => array(
                                                'ccrank' => intval($coin->market_cap_rank),
                                            ),
                                        );
                                          $post_id = wp_insert_post($args,true);
                                          if(is_wp_error($post_id)){
                                            //there was an error in the post insertion, 
                                            wp_die($post_id->get_error_message(),
                                            'Error al insertar nueva criptomoneda'); 
                                          }

                                    }
                                      
                                    }
                                 }
                                set_transient('tcw-datatime', time());
                        
    
                        break;
    
                    case 'coinmarketcap':
    
                        /* $request = wp_remote_get('https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest?limit=5000', array('headers' => array('X-CMC_PRO_API_KEY' => $config['api_key'])));

                        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
                            $this->wpdb->get_results("SELECT `slug` FROM `{$this->tablename}`");

                            if ($this->wpdb->num_rows > 0) {
                                set_transient('tcw-datatime', time(), 60);
                            }
                            return false;
                        }

                        $body = wp_remote_retrieve_body($request);
                        $data = json_decode($body);
    
                        if (!empty($data)) {
            
                            if ($data->status->error_code == 0) {
            
                                $this->wpdb->query("TRUNCATE `{$this->tablename}`");
            
                                $btc_price = $data->data[0]->quote->USD->price;
            
                                $values = [];
            
                                foreach($data->data as $coin) {
                                    if ($coin->cmc_rank !== null) {
                                        $coin->price_btc = $coin->quote->USD->price / $btc_price;
                                        $coin->image = 'https://s2.coinmarketcap.com/static/img/coins/64x64/' . $coin->id . '.png';
                                        $values[] = array($coin->name, strtoupper($coin->symbol), $coin->slug, $coin->image, $coin->cmc_rank, floatval($coin->quote->USD->price), floatval($coin->price_btc), floatval($coin->quote->USD->volume_24h), floatval($coin->quote->USD->market_cap), 0.00, 0.00, floatval($coin->circulating_supply), floatval($coin->max_supply), 0.00, strtotime('now'), 0.00, floatval($coin->quote->USD->percent_change_1h), floatval($coin->quote->USD->percent_change_24h), floatval($coin->quote->USD->percent_change_7d), null, gmdate("Y-m-d H:i:s"));
                                    }
                                }
            
                                $values = array_chunk($values, 100, true);
            
                                foreach($values as $chunk) {
                                    $placeholder = "(%s, %s, %s, %s, %d, %f, %f, %f, %f, %f, %f, %f, %f, %f, %d, %f, %f, %f, %f, %f, %s)";
                                    $query = "INSERT IGNORE INTO `{$this->tablenam}` (`name`, `symbol`, `slug`, `img`, `rank`, `price_usd`, `price_btc`, `volume_usd_24h`, `market_cap_usd`, `high_24h`, `low_24h`, `available_supply`, `total_supply`, `ath`, `ath_date`, `price_change_24h`, `percent_change_1h`, `percent_change_24h`, `percent_change_7d`, `percent_change_30d`, `weekly_expire`) VALUES ";
                                    $query .= implode(", ", array_fill(0, count($chunk), $placeholder));
                                    $this->wpdb->query($this->wpdb->prepare($query, call_user_func_array('array_merge', $chunk)));
                                }
                                set_transient('tcw-datatime', time());
                            }
        
                        }
                    */
                        break; 
                }

            }

        }

        public function actualizarPrecioCrypto(){
            switch ($this->config['api']) {

                case 'coingecko':

                    $tcw_data = get_posts( array(
                        'post_type' => 'cryptoc',
                        'numberposts' => -1,
                        'meta_key' => 'ccrank',
                        'order_by' => 'meta_value_num'
                        ) );

                    foreach($tcw_data as $tcoin) {
                        $request = wp_remote_get('https://api.coingecko.com/api/v3/simple/price?ids='
                        .$tcoin->post_name.'&vs_currencies=usd');
                        if (!is_wp_error($request) && 
                        wp_remote_retrieve_response_code($request) == 200){
                         $body = wp_remote_retrieve_body($request);
                        $data = json_decode($body);
                        if (!empty($data)) {
                            $post = array(
                                'ID' => $tcoin->ID,
                                'post_excerpt'  => floatval($data->{$tcoin->post_name}->usd),
                                'post_author'   => 1,
                            );
                            wp_update_post($post);
                        }   
                        }    
                    }
                    exit(0);
                }
                    

        }

        public function create_post_type_cryptoc() {
            function hide_title_cryptoc() {
                remove_post_type_support('cryptoc', 'title');
            }
            function create_post_type_cryptoc() {
                $labels = array(
                    'name'                  => _x('Crytocurrencies', 'Post Type General Name', 'tarifa-criptomoneda-widgets'),
                    'singular_name'         => _x('Cryptocurrency', 'Post Type Singular Name', 'tarifa-criptomoneda-widgets'),
                    'menu_name'             => __('Crytocurrency', 'tarifa-criptomoneda-widgets'),
                    'name_admin_bar'        => __('Post Type', 'tarifa-criptomoneda-widgets'),
                    'archives'              => __('Widget Archives', 'tarifa-criptomoneda-widgets'),
                    'attributes'            => __('Widget Attributes', 'tarifa-criptomoneda-widgets'),
                    'parent_item_colon'     => __('Parent Widget:', 'tarifa-criptomoneda-widgets'),
                    'all_items'             => __('All coin', 'tarifa-criptomoneda-widgets'),
                    'add_new_item'          => __('Add New coin', 'tarifa-criptomoneda-widgets'),
                    'add_new'               => __('Add New', 'tarifa-criptomoneda-widgets'),
                    'new_item'              => __('New coin', 'tarifa-criptomoneda-widgets'),
                    'edit_item'             => __('Edit coin', 'tarifa-criptomoneda-widgets'),
                    'view_item'             => __('View coin', 'tarifa-criptomoneda-widgets'),
                    'view_items'            => __('View coins', 'tarifa-criptomoneda-widgets'),
                    'search_items'          => __('Search coins', 'tarifa-criptomoneda-widgets'),
                    'not_found'             => __('Not found', 'tarifa-criptomoneda-widgets'),
                    'not_found_in_trash'    => __('Not found in trash', 'tarifa-criptomoneda-widgets'),
                    'featured_image'        => __('Featured Image', 'tarifa-criptomoneda-widgets'),
                    'set_featured_image'    => __('Set featured image', 'tarifa-criptomoneda-widgets'),
                    'remove_featured_image' => __('Remove featured image', 'tarifa-criptomoneda-widgets'),
                    'use_featured_image'    => __('Use as featured image', 'tarifa-criptomoneda-widgets'),
                    'insert_into_item'      => __('Insert into coin', 'tarifa-criptomoneda-widgets'),
                    'uploaded_to_this_item' => __('Uploaded to this coin', 'tarifa-criptomoneda-widgets'),
                    'items_list'            => __('Coins list', 'tarifa-criptomoneda-widgets'),
                    'items_list_navigation' => __('Coins list navigation', 'tarifa-criptomoneda-widgets'),
                    'filter_items_list'     => __('Filter coins list', 'tarifa-criptomoneda-widgets'),
                );
                $args = array(
                    'label'                 => __('Crytocurrencies', 'tarifa-criptomoneda-widgets'),
                    'description'           => __('Post Type Description', 'tarifa-criptomoneda-widgets'),
                    'labels'                => $labels,
                    'taxonomies'            => array(''),
                    'hierarchical'          => false,
                    'public' 				=> false,
                    'show_ui'               => true,
                    'show_in_rest'          => true,
                    'show_in_nav_menus' 	=> false,
                    'menu_position'         => 5,
                    'show_in_admin_bar'     => true,
                    'show_in_nav_menus'     => true,
                    'can_export'            => true,
                    'has_archive' 			=> false,
                    'rewrite' 				=> false,
                    'exclude_from_search'   => true,
                    'publicly_queryable'    => false,
                    'query_var'				=> false,
                    'rest_base'             => 'cryptocoins',
                    'rest_controller_class' => 'WP_REST_Posts_Controller',
                    'supports'              => array( 'title', 'excerpt', 'editor','custom-fields'),
                    'menu_icon'           	=> 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" style="isolation:isolate" viewBox="426.356 267.342 61.288 50.316" width="20" height="20"><path d=" M 468.061 267.342 C 469.884 270.488 471.42 273.219 473.045 275.898 C 473.621 276.847 473.54 277.594 473.004 278.524 C 465.589 291.375 458.206 304.246 450.502 317.658 C 448.583 314.271 446.838 311.195 445.098 308.116 C 444.755 307.508 445.124 307.024 445.406 306.534 C 452.879 293.604 460.353 280.675 468.061 267.342 Z " fill-rule="evenodd" fill="rgb(150,150,150)"/><path d=" M 449.605 267.343 C 451.383 270.402 452.912 273.147 454.566 275.815 C 455.231 276.885 455.038 277.694 454.461 278.684 C 449.145 287.825 443.85 296.98 438.59 306.155 C 437.997 307.191 437.299 307.625 436.097 307.597 C 433.052 307.527 430.005 307.575 426.356 307.575 C 434.199 294.002 441.83 280.798 449.605 267.343 Z " fill-rule="evenodd" fill="rgb(150,150,150)"/><path d=" M 487.644 307.57 C 483.868 307.57 480.736 307.548 477.604 307.584 C 476.633 307.594 475.976 307.266 475.561 306.374 C 475.28 305.771 474.902 305.215 474.569 304.638 C 469.55 295.933 469.55 295.933 475.897 287.272 C 479.756 293.941 483.532 300.464 487.644 307.57 Z " fill-rule="evenodd" fill="rgb(150,150,150)"/></svg>'),
                    /* 'capability_type'       => 'page',*/
                    'capabilities'          => array(
                                            'create_posts' => 'do_not_allow'
                    )
                );
                register_post_type('cryptoc', $args);
            }

            function my_sortable_cryptoc_column( $columns ) {
                //To make a column 'un-sortable' remove it from the array
                //unset($columns['date']);
                $columns['ccrank'] = __('Ranking', 'tarifa-criptomoneda-widgets');
                return $columns;
            }
            function get_ganancias( $data ) {
                $gains = get_option('tcw_config',array(
                    'per_gain_venta' => 9.1,
                    'per_gain_venta' => -8.1
                ));
                $ret=new stdClass();
                $ret->ventagain = floatval($gains['per_gain_venta']);
                $ret->compragain = floatval($gains['per_gain_compra']);
                return $ret;
              }
            function get_price_ccoin( $data ) {
                $coin = $data->get_param( 'coin' );
                if(empty($coin)){
                    $cmax = $data->get_param( 'cmax' );
                    $nrpc = empty($cmax)?10:intval($cmax);
                    $tcw_data = get_posts( array(
                        'post_type' => 'cryptoc',
                        'numberposts' => $nrpc,
                        'meta_key' => 'ccrank',
                        'order_by' => 'meta_value_num',
                        'order' => 'ASC'
                        ) );
                        $ret = array();
                    foreach($tcw_data as $tcoin) {
                        $ret[]= (object)array($tcoin->post_name=>floatval($tcoin->post_excerpt));
                    }
                    if(empty($ret)) $ret[]=(object)array("error"=>"Sin criptomonedas");
                    return $ret;
                }else{
                    $cc_id=TarifaCripto::criptomoneda_existe($coin);
                    $post = get_post($cc_id);
                    if ( empty( $post) ) return "";
                return floatval($post->post_excerpt);
                }
                
              }
              function get_content_ccoin( $data ) {
                $coin = $data->get_param( 'coin' );
                
                if(empty($coin)){
                    $cmax = $data->get_param( 'cmax' );
                    $nrpc = empty($cmax)?10:intval($cmax);
                    
                    $tcw_data = get_posts( array(
                        'post_type' => 'cryptoc',
                        'numberposts' => $nrpc,
                        'meta_key' => 'ccrank',
                        'order_by' => 'meta_value_num',
                        'order' => 'ASC'
                        ) );
                        $ret = array();
                    foreach($tcw_data as $tcoin) {
                        $pc = json_decode($tcoin->post_content, true);
                        $ret[]= (object)array(
                            'slug'=>$tcoin->post_name,
                            'name'=>$tcoin->post_title,
                            'usdprecio'=> floatval($tcoin->post_excerpt),
                            'symbol' => $pc['symbol'],
                            'logo' => str_replace('large/','thumb'.'/',$pc['logo']),
                            'cventa' => floatval($pc['comventa']),
                            'ccompra' => floatval($pc['comcompra'])
                        );
                    }
                    if(empty($ret)) $ret[]=(object)array("error"=>"Sin criptomonedas");
                    return $ret;
                }else{
                    $cc_id=TarifaCripto::criptomoneda_existe($coin);
                    $tcoin = get_post($cc_id);
                    if ( empty( $tcoin) ) {
                       return (object)array("error"=>"Sin criptomoneda"); 
                    }
                    $pc = json_decode($tcoin->post_content, true);
                        $ret[]= (object)array(
                            'slug'=>$tcoin->post_name,
                            'name'=>$tcoin->post_title,
                            'usdprecio'=> floatval($tcoin->post_excerpt),
                            'symbol' => $pc['symbol'],
                            'logo' => str_replace('large/','thumb'.'/',$pc['logo']),
                            'cventa' => floatval($pc['comventa']),
                            'ccompra' => floatval($pc['comcompra'])
                        );
                        return $ret;
                }
                
              }

              function get_exchange($data) {
                $tipo_compra = get_transient('pen_usd_compra');
                $tipo_venta = get_transient('pen_usd_venta');
                if (empty($tipo_compra)||empty($tipo_venta)) {
                    $request = wp_remote_get('https://api.blocksera.com/v1/exrates');
                    if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
                        return array('error'=>'api_externa');
                    }
                    $body = wp_remote_retrieve_body($request);
                    $exrates = json_decode($body);
                    if (empty($exrates)) return array('error'=>'api_externa');
                    $pen_val = 0;
                    foreach($exrates as $key => $value) {
                        if($key == 'PEN'){
                            $pen_val = $value;
                        }
                    }
                    $tipo_compra = floatval($pen_val)+0.05;
                    $tipo_venta = floatval($pen_val)-0.05;
                    set_transient('pen_usd_compra', $tipo_compra, DAY_IN_SECONDS);
                    set_transient('pen_usd_venta', $tipo_venta, DAY_IN_SECONDS);
                }
                return (object)array(
                    'tipocambcompra' => floatval($tipo_compra),
                    'tipocambventa' => floatval($tipo_venta) );                            
            }

              function api_get_precio() {
                register_rest_route( 'twccripto/v1', '/precio/', array(
                  'methods' => 'GET',
                  'callback' => 'get_price_ccoin',
                ) );
                register_rest_route( 'twccripto/v1', '/ganancias/', array(
                    'methods' => 'GET',
                    'callback' => 'get_ganancias',
                  ) );
                  register_rest_route( 'twccripto/v1', '/contenido/', array(
                    'methods' => 'GET',
                    'callback' => 'get_content_ccoin',
                  ) );
                  register_rest_route( 'twccripto/v1', '/exchange/', array(
                    'methods' => 'GET',
                    'callback' => 'get_exchange',
                  ) );
              }

              function set_bulk_actions($bulk_actions) {
                $bulk_actions['delete-cryptocoin'] = __('Delete Coin', 'tarifa-criptomoneda-widgets');
                return $bulk_actions;
              }
              function handle_bulk_actions($redirect_url, $action, $post_ids) {
                if ($action == 'delete-cryptocoin') {
                    foreach ($post_ids as $post_id) {
                    wp_delete_post( $post_id->ID, true );
                    }
                    $redirect_url = add_query_arg('delete-cryptocoin', count($post_ids), $redirect_url);
                }
                return $redirect_url;
            }

            
           function admin_order_list_top_bar_button( $which ) {
                global $typenow;
                if ( 'cryptoc' === $typenow && 'top' === $which ) {
                    $cc = wp_count_posts('cryptoc');
                    if($cc->publish < 1){
                       ?>
                    <div class="alignleft actions custom">
                        <button id="import_crypto"  style="height:32px;" class="button" value=""><?php
                            _e( 'Import cryptos', 'tarifa-criptomoneda-widgets' ); ?></button>
                    <input id="adm_url" type="hidden" value="<?php echo admin_url('admin-ajax.php');?>" />
                </div>
                    <script>
                        (function( $ ) {
                                'use strict';

                        $("#import_crypto").click(function(event){
                            event.preventDefault();
                            var data = {
                                action: 'import_cryptos'
                            };
                            jQuery.post($("#adm_url").val(), data, function(response) {
                                $("#import_crypto").html(response);
                            });
                        });

                            })( jQuery );
                        
                    </script>
                    <?php 
                    }
                    
                }
            }

                

            add_action('init', 'create_post_type_cryptoc');
            add_action('admin_init', 'hide_title_cryptoc');
            //add_action('admin_init', array($this, 'display_notices'));
            add_action('admin_menu', array($this, 'register_menu'), 12);
            //p add_action('add_meta_boxes', array($this, 'meta_boxes'));
            add_filter('manage_cryptoc_posts_columns', array($this, 'posts_columns'));
            add_action('manage_cryptoc_posts_custom_column', array($this, 'posts_columns_content'), 10, 2);
            add_filter('manage_edit-cryptoc_sortable_columns', 'my_sortable_cryptoc_column' );
            //p add_action('save_post', array($this, 'save_coin'));
            //add_filter('bulk_actions-edit-cryptoc', 'set_bulk_actions');
            //add_filter('handle_bulk_actions-edit-cryptoc', 'handle_bulk_actions' , 10, 3);
            add_action( 'rest_api_init', 'api_get_precio' );
            add_action( 'manage_posts_extra_tablenav', 'admin_order_list_top_bar_button', 20, 1 );

        }

        

        public function register_menu() {
          // add_submenu_page('edit.php?post_type=cryptoc', __('Widgets', 'tarifa-criptomoneda-widgets'), 'Widgets', 'manage_options', 'tcw-widgets', array($this, 'widgets_page'));
            add_submenu_page('edit.php?post_type=cryptoc', __('Settings', 'tarifa-criptomoneda-widgets'), 'Settings', 'manage_options', 'tcw-settings', array($this, 'settings_page'));
            //add_submenu_page('edit.php?post_type=tcw', __('Extensions', 'tarifa-criptomoneda-widgets'), 'Extensions', 'manage_options', 'tcw-extensions', array($this, 'extensions_page'));
        }

        public function settings_page() {
            $config = array_merge($this->config, get_option('tcw_config', array()));
            include_once(TCW_PATH . '/includes/settings.php');
        }

        public function widgets_page() {
            $config = array_merge($this->config, get_option('tcw_config', array()));  
            $options =  $this->options;
            wp_nonce_field(plugin_basename(__FILE__), 'tcw_widgets_nonce');
            include_once(TCW_PATH . '/includes/tcripto.php');
        }

        public function save_settings() {

            $us = array();
            
            if(!empty($_POST['per_gain_compra'])) $us['per_gain_compra']=floatval($_POST['per_gain_compra']);
            if(!empty($_POST['per_gain_venta'])) $us['per_gain_venta']=floatval($_POST['per_gain_venta']);
            if(!empty($_POST['comcompra'])) $us['comcompra']=floatval($_POST['comcompra']);
            if(!empty($_POST['comventa'])) $us['comventa']=floatval($_POST['comventa']);
            if(!empty($_POST['ccnum_fetch'])) $us['ccnum_fetch']=absint($_POST['ccnum_fetch']);
            if(!empty($_POST['numformat'])) $us['numformat']=sanitize_text_field($_POST['numformat']);
            if(!empty($_POST['fonts'])) $us['fonts']=$_POST['fonts'];
            if(!empty($_POST['custom_css'])) $us['custom_css']=sanitize_textarea_field($_POST['custom_css']);
            if(!empty($_POST['api'])) $us['api']=sanitize_text_field($_POST['api']);
            if(!empty($_POST['api_key'])) $us['api_key']=sanitize_text_field($_POST['api_key']);
            if(!empty($_POST['api_interval'])) $us['api_interval']=intval($_POST['api_interval']);
            if(!empty($_POST['currency_format'])) $us['currency_format']=esc_sql($_POST['currency_format']);
            if(!empty($_POST['default_currency_format'])) $us['default_currency_format']=esc_sql($_POST['default_currency_format']);
            if(!empty($_POST['pen_usd_compra'])) set_transient('pen_usd_compra',floatval($_POST['pen_usd_compra']),DAY_IN_SECONDS);
            if(!empty($_POST['pen_usd_venta'])) set_transient('pen_usd_venta',floatval($_POST['pen_usd_venta']),DAY_IN_SECONDS);
            $config = array_merge($this->config, $us);
            update_option('mcw_config', $config);
            wp_redirect(admin_url('edit.php?post_type=cryptoc&page=tcw-settings&success=true'));
        }

        

        public function posts_columns($columns) {
            $ncolumns = array();
            foreach($columns as $key => $title) {
                if ($key=='date') {
                    $ncolumns['price_usd'] = __('Precio.USD', 'tarifa-criptomoneda-widgets');
                    $ncolumns['c_venta'] = __('Venta', 'tarifa-criptomoneda-widgets');
                    $ncolumns['c_compra'] = __('Compra', 'tarifa-criptomoneda-widgets');
                    $ncolumns['ccrank'] = __('Ranking', 'tarifa-criptomoneda-widgets');
                    $ncolumns['cclast'] = __('Ultimo', 'tarifa-criptomoneda-widgets');
                }
                $ncolumns[$key] = $title;
            }
            return $ncolumns;
        }

        public function posts_columns_content($column, $post_id) {
            $post = get_post($post_id);
            $pcontent = json_decode($post->post_content, true);
            switch ($column) {
                case 'price_usd':
                    echo $post->post_excerpt;
                    break;
                case 'c_venta':
                    echo $pcontent['comventa'];
                    break;
                case 'c_compra':
                    echo $pcontent['comcompra'];
                    break;
                case 'ccrank':
                    $rank = get_post_meta($post_id, 'ccrank', true);
                    echo $rank;
                    break;
                case 'cclast':
                    echo $post->post_modified;
                    break;
            }
        }

        public function meta_boxes() {
            add_meta_box('tcw-editor', __('Cripto Moneda', 'tarifa-criptomoneda-widgets'), array($this, 'meta_coin_settings'), 'cryptoc', 'normal', 'high');
           // add_meta_box('crypto_widget_shortcode', __('Shortcode', 'tarifa-criptomoneda-widgets'), array($this, 'meta_shortcode'), 'cryptoc', 'side', 'high');
        }

        public function meta_coin_settings($post) {
            wp_nonce_field(plugin_basename(__FILE__), 'tcw_coin_nonce');
            require_once(TCW_PATH . 'includes/admin.php');
        }

        public function meta_shortcode($post) {
            echo '<div class="tcw-shortcode"><span class="shortcode-hint">Copied!</span>Paste this shortcode anywhere like page, post or widgets<br><br>';
            echo '<input type="text" id="tcwshortcode" data-clipboard-target="#tcwshortcode" readonly="readonly" class="selectize-input" value="' . esc_attr('[tcrypto id="' . $post->ID . '"]') . '" /></div>';
        }

       /*  public function meta_coinpress($post) {
            echo '<a href="https://coinpress.blocksera.com" target="_blank"><img src="' . TCW_URL . 'assets/admin/img/coinpress.png" style="max-width: 100%" /></a>';
        }

        public function meta_links($post) {
            echo '<div class="tcw-links">';
            echo '<ul>';
            echo '<li><a href="https://codecanyon.net/item/tarifa-criptomoneda-widgets/22093978" target="_blank"><i class="micon-star"></i> '. __("Rate us 5 stars", "tarifa-criptomoneda-widgets") . '</a></li>';
            echo '<li><a href="https://TarifaCriptopro.blocksera.com" target="_blank"><i class="micon-world"></i> '. __("Visit homepage", "tarifa-criptomoneda-widgets") . '</a></li>';
            echo '<li><a href="https://blocksera.ticksy.com/" target="_blank"><i class="micon-envelope"></i> '. __("Contact support", "tarifa-criptomoneda-widgets") . '</a></li>';
            echo '</ul>';
            echo '</div>';
        } */

        public function save_widgets() {

            /* if (!isset($_POST['tcw_widgets_nonce'])) {
                wp_die('No save widgets isset');
                return;
            }

            if (!wp_verify_nonce($_POST['tcw_widgets_nonce'], plugin_basename( __FILE__ ))) {
                wp_die('No save widgets verify');
                return;
            }

            if (!current_user_can('manage_options')) {
                wp_die('No save widgets manage');
                return;
            } */

            $tcripto_opt = [
                'type' => sanitize_key($_POST['type']),
                'coins' => isset($_POST['coins']) ? $_POST['coins'] : array(),
                'numcoins' => intval($_POST['numcoins']),
                'theme' => sanitize_key($_POST['theme']),
                'ticker_design' => intval($_POST['ticker_design']),
                'ticker_speed' => intval($_POST['ticker_speed']),
                'ticker_columns' => isset($_POST['ticker_columns']) ? $_POST['ticker_columns'] : array(),
                'font' => sanitize_text_field($_POST['font']),
                'price_format' => intval($_POST['price_format']),
                'currency' => strtoupper(sanitize_key($_POST['currency'])),
                'currency2' => strtoupper(sanitize_key($_POST['currency2'])),
                'currency3' => strtoupper(sanitize_key($_POST['currency3'])),
                'text_color' => $_POST['text_color'],
                'background_color' => $_POST['background_color'],
                'real_time' =>  sanitize_key($_POST['real_time'])
            ];
            $tcripto_opt = array_merge($this->options, $tcripto_opt);
            update_option('tcw_tcripto', $tcripto_opt);
            wp_redirect(admin_url('edit.php?post_type=cryptoc&page=tcw-widgets&success=true'));
        }

        public function save_coin($post_id) {
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            if (!isset($_POST['tcw_coin_nonce'])) {
                return;
            }

            if (!wp_verify_nonce($_POST['tcw_coin_nonce'], plugin_basename( __FILE__ ))) {
                return;
            }

            if (!current_user_can('edit_page', $post_id)) {
                return;
            }

            $postcontent = [
                'symbol' => ($_POST['symbol']),
                'logo' => esc_url_raw($_POST['logo']),
                'comventa' => floatval($_POST['comventa']),
                'comcompra' => floatval($_POST['comcompra'])
            ];

            remove_action('save_post', array($this, 'save_coin'));

            $post = array(
                'ID' => $post_id,
                'post_content' => wp_json_encode($postcontent),
                'post_mime_type' => 'application/json'
            );
            wp_update_post($post);
            add_action('save_post', array($this, 'save_coin'));
        }
        
        public function init() {

            add_shortcode('tcripto', array($this, 'shortcode'));

            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
            add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'), 99999);
            add_action('wp_ajax_tcw_clear_cache', array($this, 'clear_cache'));
            add_action('wp_ajax_import_cryptos', array($this, 'import_cryptos'));
            add_action('admin_post_tcw_save_settings', array($this, 'save_settings'));
            add_action('admin_post_tcw_save_widgets', array($this, 'save_widgets'));
            add_action('actualizar_preciocrypto', array($this, 'actualizarPrecioCrypto'));
            load_plugin_textdomain('tarifa-criptomoneda-widgets', false,
             dirname(plugin_basename(__FILE__)) . '/languages' );
            //add_filter('tcw_coin_img', array($this, 'change_coin_imgurl'), 10, 2);
            add_action('tcw_fetch_reset', array($this, 'fetch_reset'), 10, 1);
        }
        
        public function admin_scripts() {

            $screen = get_current_screen();

            if ($screen->post_type === 'cryptoc') {
                wp_enqueue_code_editor( array('type' => 'text/css'));
                wp_enqueue_style('tcw-crypto-select', TCW_URL . 'assets/public/css/selectize.custom.css', array(), TCW_VERSION);
                wp_enqueue_style('tcw-editor', TCW_URL . 'assets/admin/css/style.css', array(), TCW_VERSION);
                wp_enqueue_script('tcw-crypto-select', TCW_URL . 'assets/public/js/selectize.min.js', array('jquery-ui-sortable'), '0.12.4', true);
                wp_enqueue_script('tcwa-vendor', TCW_URL . 'assets/admin/js/vendor.min.js', array('jquery'), TCW_VERSION, true);
                wp_enqueue_script('tcwa-crypto-common', TCW_URL . 'assets/admin/js/common.js', array('tcwa-vendor'), TCW_VERSION, true);
            }
            //echo json_encode($screen);
            if ($screen->post_type === 'cryptoc' && $screen->base === 'cryptoc_page_tcw-widgets') {
                $this->frontend_scripts();
            }

        }

        public function frontend_scripts() {
            $config = array_merge($this->config, get_option('tcw_config', array()));
            if (count($config['fonts']) > 0) {
                wp_enqueue_style('tcw-google-fonts', 'https://fonts.googleapis.com/css?family=' . implode('|', $config['fonts']));
            }

            wp_enqueue_style('tcw-crypto', TCW_URL . 'assets/public/css/style.css', array(), TCW_VERSION);
            wp_enqueue_style('tcw-converter', TCW_URL . 'assets/public/css/tcw-converter.css', array(), TCW_VERSION);
            wp_enqueue_style('tcw-crypto-select', TCW_URL . 'assets/public/css/selectize.custom.css', array(), TCW_VERSION);
            //wp_enqueue_style('tcw-crypto-datatable', TCW_URL . 'assets/public/css/jquery.dataTables.min.css', array(), '1.10.16');
            wp_register_script('tcw-crypto-common', TCW_URL . 'assets/public/js/common.js', array('jquery'), TCW_VERSION, true);
            wp_enqueue_script('tcw-converter', TCW_URL . 'assets/public/js/tcw-converter.js', array(), '1.0.0', true);
            wp_enqueue_script('tcw-crypto-socket-io', TCW_URL . 'assets/public/js/socket.io.js', array(), '2.1.0', true);
            wp_enqueue_script('tcw-crypto-es5',	'https://cdnjs.cloudflare.com/ajax/libs/es5-shim/2.0.8/es5-shim.min.js', array(), '2.0.8', true);
            wp_script_add_data('tcw-crypto-es5', 'conditional', 'lt IE 9' );
            wp_enqueue_script('tcw-crypto-select', TCW_URL . 'assets/public/js/selectize.min.js',array('jquery'), '0.12.4',true);

            $atts = array(
                'url' => TCW_URL,
                'ajax_url' => admin_url('admin-ajax.php'),
                'currency_format' => array_column($config['currency_format'], null, 'iso'),
                'default_currency_format' => $config['default_currency_format'],
                'text' => array(
                    'previous' => __('Previous', 'tarifa-criptomoneda-widgets'),
                    'next' => __('Next', 'tarifa-criptomoneda-widgets'),
                    'lengthmenu' => sprintf(__('Coins per page: %s', 'tarifa-criptomoneda-widgets'), '_MENU_')
                )
            );

            wp_localize_script('tcw-crypto-common', 'tcw', $atts);
            wp_enqueue_script('tcw-crypto-common');
            
        }
/**
 * No crearemos otra tabla tcw_coins
 */
        public function activate() {
            add_option('tcw_config', $this->config);
        }

        public function deactivate() {
            delete_option('tcw_config');
            delete_transient('tcw-datatime');
            delete_transient('tcw-currencies');
            delete_transient('tcw-data-time');
        }

        public function import_cryptos(){
            $config = array_merge($this->config, get_option('tcw_config', array()));
            do_action('tcw_fetch_reset', $config);
            wp_redirect(admin_url('edit.php?post_type=cryptoc'));
            wp_die();
        }

        public function get_currencies() {
            $exrates = get_transient('tcw-currencies');

            if (empty($exrates)) {
                $request = wp_remote_get('https://api.blocksera.com/v1/exrates');
                if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
                    return false;
                }
                $body = wp_remote_retrieve_body($request);
                $exrates = apply_filters('block_exrates', json_decode($body));
                //$xrates = array('');
                if (!empty($exrates)) {
                    set_transient('tcw-currencies', $exrates, DAY_IN_SECONDS);
                    return $exrates;
                }
            }
            return $exrates;
        }

        public function tcw_coinsyms() {
            
			$tras_coinsyms = get_transient('tcw-coinsyms');
			if(empty($tras_coinsyms)){
                //$config = array_merge($this->config, get_option('tcw_config', array()));
				//do_action('tcw_fetch_reset', $config);
				//$tcw_data = $this->wpdb->get_results("SELECT `name`, `symbol`, `slug` FROM `{$this->tablenam}` ORDER BY `rank` ASC");
				$tcw_data = get_posts( array(
					'post_type' => 'cryptoc',
					'numberposts' => -1,
					'meta_key' => 'ccrank',
					'order_by' => 'meta_value_num',
                    'order'=>'ASC'
					) );

				$tcw_coinsyms = array();

				foreach($tcw_data as $tcw_each_data) {
					$pc = json_decode($tcw_each_data->post_content, true);
					$tcw_coinsyms[$tcw_each_data->post_name] = array('name' => $tcw_each_data->post_title, 'symbol' => $pc['symbol']);
				}
                set_transient('tcw-coinsyms', wp_json_encode($tcw_coinsyms), DAY_IN_SECONDS);
				return $tcw_coinsyms;
			}else
                return json_decode($tras_coinsyms,true);
            
        }
        
        public function shortcode($atts) {
            $config = array_merge($this->config, get_option('tcw_config', array()));
            //do_action('tcw_fetch_reset', $config);
            $shortcode = new TarifaCripto_Shortcodes();
            $shortcode->config = $config;
            $shortcode->changelly = array_merge($this->changelly['fiat'], $this->changelly['crypto']);

            $atts = shortcode_atts(array(
                'type'=>'',
                'coin' => false,
                'currency' => 'USD',
                'info' => 'price',
                'realtime' => 'on',
                'format' => 'number',
                'coinpress' => 'false',
                'multiply' => 1
            ), $atts, 'tcrypto');


            $options = $this->options;

            $options['tcw_currencies'] = $this->get_currencies();

            if ($atts['coinpress'] == 'true' && get_query_var('coin')) {
                array_unshift($options['coins'], get_query_var('coin'));
            }
            
            if ($atts['coin']) {
                $options['coins'] = array($atts['coin']);
            }
            
            if (sizeof($options['coins']) == 0 && intval($options['numcoins']) == 0 && !in_array($options['type'], ['changelly', 'news'])) {

                return 'No coins selected';

            }

            wp_register_style('tcw-custom', false);
            wp_enqueue_style('tcw-custom');
            wp_add_inline_style("tcw-custom", $config['custom_css']);


            $list_post = array();
                $my_posts = array();
                if (count($options['coins']) > 0) {
                    $my_posts = get_posts( array( 
                        'post_type' => 'cryptoc',
                        'post_name__in' => $options['coins'],
                        'orderby'	=> 'name',
                        'order' => 'ASC'
                        ) );
                } else {
                    $my_posts = get_posts( array(
                        'post_type' => 'cryptoc',
                        'numberposts' => intval($options['numcoins']),
                        'meta_key' => 'ccrank',
                        'orderby' => 'meta_value_num',
                        'order' => 'ASC'
                        ) );
                }


            if ($atts['type'] === 'ticker') {
                
                foreach ( $my_posts as $p ){
                    $pc = json_decode($p->post_content, true);
                    $list_post[]=(object)array('name'=>$p->post_title,'symbol'=>$pc['symbol'],
                    'slug'=>$p->post_name,'img'=>$pc['logo'],'price_usd'=>$p->post_excerpt);
                }
                $options['data'] = $list_post;
                return $shortcode->ticker_shortcode($options);
            }
            else{
               foreach ( $my_posts as $p ){
                        $pc = json_decode($p->post_content, true);
                        $list_post[]=(object)array('symbol'=>$pc['symbol'],
                        'slug'=>$p->post_name,'price_usd'=>$p->post_excerpt,
                        'venta'=>$pc['comventa'],'compra'=>$pc['comcompra']);
                    }
                    $options['data'] = $list_post;
                    return $shortcode->converter_shortcode( $options);
            }
 
        }

    /*     public function ticker_sticky() {
                echo apply_filters('tcw_show_ticker', do_shortcode('[tcripto type="ticker"]'));
        } */


        public static function criptomoneda_existe( $post_name ) {
            global $wpdb;
        
            $query = "SELECT ID FROM $wpdb->posts WHERE 1=1";
        
            if ( !empty ( $post_name ) ) {
                 $query .= " AND post_name LIKE '%s' ";
                 $query .= " AND post_type = 'cryptoc' ";
                 return $wpdb->get_var( $wpdb->prepare($query, $post_name) );
            }
            return 0;
        }

        public function clear_cache() {
            //$this->wpdb->query("DROP TABLE IF EXISTS `{$this->tablenam}`");
            delete_option('tcw_config');
            delete_transient('tcw-datatime');
            delete_transient('tcw-currencies');
            //$this->activate();
            wp_redirect(admin_url('edit.php?post_type=cryptoc&page=tcw-settings&success=true'));
            wp_die();
        }

    }
}

$TarifaCripto = new TarifaCripto();
function cron_actualizar_preciocrypto_404515a6() {
    // do stuff
    do_action('actualizar_preciocrypto');
}

add_action( 'actualizar_preciocrypto', 'cron_actualizar_preciocrypto_404515a6', 10, 0 );