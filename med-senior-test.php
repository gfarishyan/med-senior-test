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
    wp_register_script('datatableset-js', '//cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js');
    wp_register_style('datatableset-css', '//cdn.datatables.net/1.10.22/css/jquery.dataTables.min.css');
    wp_register_script('med-senior-test-js', plugins_url('js/med-senior-test.js', __FILE__), array(
      'jquery',
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
      'target' => '#datatable-' . $target,
      'rows_count' => 10,
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
    array_walk($columns, function(&$item){
      $item = (object) array("data" => $item);
    });
    $settings[$target]['columns'] = $columns;
    $settings[$target]['target'] = $shortcode_atts['target'];
    $wrapper_class = ltrim($shortcode_atts['target'], '#.') . '-wrapper';
    
    wp_enqueue_script('med-senior-test-js');
    wp_localize_script('med-senior-test-js', 'medSeniorTestSettings', array(
      'ajax_url' => admin_url('admin-ajax.php'),
      'items' => $settings,
    ));
    
    $thead = '';
    foreach (explode(",", $shortcode_atts['columnNames']) as $name) {
      $thead .= sprintf('<th>%s</th>', esc_html($name));
    }
    
    $data = sprintf('<div class="%s"><table id="%s">
      <thead>
        <tr>%s</tr>
      </thead>
      <tbody>
      </tbody>
      </table>', $wrapper_class, $shortcode_atts['target'], $thead);
    print $data;
  }
  
  public function retrieve_data() {
    $data = [
      ['demo', 15, 25, 35, 45, 55],
    ];
    print json_encode($data);
    wp_die();
  }
}

MedSeniorTest::getInstance();
