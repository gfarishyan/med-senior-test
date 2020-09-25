<?php
/*
   Plugin Name: Med Senior Test
   Plugin URI: http://localhost/
   Support URI: https://github.com/mailpoet/wp-mail-logging/issues
   Version: 0.1
   Author: Grigor Farishyan
   Author URI: https://localhost/
   Description: Loads large set of data into custom table and show on frontend.
   Text Domain: med-senior-test
   License: GPLv3
  */
  
if ( ! defined( 'ABSPATH' ) ) 
  exit;

define('MED_SENIOR_TEST_TEXT_DOMAIN', 'med-senior-test');

class MedSeniorTest {
  public static $instance;
  protected static $table_name = 'med_senior_test_countries';
  
  public function __construct() {
    $this->init();
  }
  
  public static function getInstance() {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }
  
  public function init() {
    add_action('init', array($this, 'register_scripts'));
    add_shortcode('med-senior-test', array($this, 'show_data_table'));
    add_action('wp_ajax_med_senior_test_data', array($this, 'retrieve_data'));
    add_action('wp_ajax_nopriv_med_senior_test_data', array($this, 'retrieve_data'));
    
    //add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
  }
  
  
  public function register_scripts() {
    wp_register_script('datatableset-js', '//cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js', array(
      'jquery'
     ), null, true);
    //wp_register_style('datatableset-css', '//cdn.datatables.net/1.10.22/css/jquery.dataTables.min.css');
    wp_register_script('med-senior-test-js', plugins_url('js/med-senior-test.js', __FILE__), array(
      'datatableset-js'
    ), null, true);
  }
  
  public function show_data_table($atts) {
    static $css_included;
    static $settings;
    if (!isset($css_included)) {
      wp_enqueue_style('datatableset-css');
      $css_included = true;
    }
    
    static $target;
    
    if (!isset($target)) {
      $target = 0;
    }
    
    $target++;
    
    $shortcode_atts = shortcode_atts(array(
      'rows_count' => 10,
      'id' => 'datatable-' . $target,
      'sorts' => 'sorts',
      'columns' => 'id,name,capital,region,population,timezones,language',
      'search' => 'name,capital,region,population,timezones,languages',
      'columnNames' => 'Id,Name,Capital,Region,Population,Timezones,Languages'
    ),
    $atts);
    
    $settings[$target] = array();
    //$shortcode_atts['columns'] = trim($shortcode_atts['columns']);
    
    //convert comma list to array
    $columns = explode(',', $shortcode_atts['columns']);
    $settings[$target]['columns'] = $columns;
    $settings[$target]['id'] = '#' . $shortcode_atts['id'];
    $wrapper_class = $shortcode_atts['id'] . '-wrapper';
    
    wp_enqueue_script('med-senior-test-js');
    wp_localize_script('med-senior-test-js', 'medSeniorTestSettings', array(
      'ajax_url' => admin_url('admin-ajax.php'),
      'items' => $settings,
    ));
    
    $thead = '';
    foreach (explode(",", $shortcode_atts['columnNames']) as $name) {
      $thead .= sprintf('<th>%s</th>', esc_html($name));
    }
    
    $data = sprintf('<div class="%s">
    <table id="%s" class="display">
      <thead>
        <tr>%s</tr>
      </thead>
      <tbody>
      </tbody>
      </table></div>', $wrapper_class, $shortcode_atts['id'], $thead);
    print $data;
  }
  
  public function retrieve_data() {
    $data = [
      (object)['id' => 1,
       'name' => 'name',
       'capital' => 'demo', 
       'region' => 15, 
       'population' => 25, 
       'timezones' => 35, 
       'language' => 'en, fr'
      ],
    ];
    
    $num_records = sizeof($data);
    $filtered_records_count = $num_records;
    
    $return = array(
      'draw' => 1,
      'recordsTotal' => $num_records,
      'recordsFiltered' => $filtered_records_count,
      'data' => $data
    );
   
    print json_encode($return);
    wp_die();
  }
  
  
  public static function setup() {
    global $wpdb;
    $table = $wpdb->prefix . self::$table_name;
    $charset = $wpdb->get_charset_collate();
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS `$table` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `capital` varchar(255) NOT NULL DEFAULT '',
    `region` varchar(255) NOT NULL DEFAULT '',
    `population` int(11) UNSIGNED DEFAULT '0',
    `timezones` varchar(255) NOT NULL DEFAULT '',
    `languages` varchar(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`)
    ) DEFAULT CHARACTER SET = utf8 DEFAULT COLLATE utf8_general_ci;";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
  }
}

register_activation_hook( __FILE__, array('MedSeniorTest', 'setup'));


MedSeniorTest::getInstance();


