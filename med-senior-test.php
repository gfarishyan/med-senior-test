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
  private $options;
  
  public function __construct() {
    $this->init();
    $this->options = get_option('med_senior_test');
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
    
    if (is_admin()) {
      add_action('admin_init', array($this, 'admin_init'));
      add_action('admin_menu', array($this, 'admin_menu'));
      add_action('wp_ajax_med_senior_test_data_sync_data', array($this, 'remote_sync_data'));
    }
    //add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
  }
  
  public function admin_init() {
    register_setting( 'med_senior_test', 'med_senior_test', array($this, 'validateSettings'));
    add_settings_section( 'med_senior_test_section', 
      __('Settings', MED_SENIOR_TEST_TEXT_DOMAIN), 
      array($this, 'section_text'),  'med-senior-test');
      
    add_settings_field('nrows_per_page', 
      __('Number of rows to display per page', MED_SENIOR_TEST_TEXT_DOMAIN), 
      array($this, 'nrows_settings'), 
      'med-senior-test',
      'med_senior_test_section'
    );
    
    add_settings_section( 'med_senior_test_section_data_import', 
      __('Data Import', MED_SENIOR_TEST_TEXT_DOMAIN), 
      array($this, 'section_data_import'),  'med-senior-test');
    
  }
  
  
  public function remote_sync_data() {
    
    global $wpdb;
    //check do we have data to process.
    $data = get_transient('med_senior_temp_data');
    
    if (empty($data)) {
      $response = wp_remote_get('https://restcountries.eu/rest/v2/all');
      
      if (is_wp_error($response)) {
        wp_send_json_error( $response  );
      }
      
      $body = wp_remote_retrieve_body($response);
      if (is_wp_error($body)) {
        wp_send_json_error( $body );
      }
      
      $data['content'] = json_decode($body, true);
      $data['total'] = sizeof($data['content']);
      $data['processed'] = 0;
    }
    
    if ($data['total'] == 0 || sizeof($data['content']) == 0) {
      //something is wrong 
      delete_transient('med_senior_temp_data');
      wp_send_json_success(array(
        'completed' => 1,
        'total_rows' => 0,
        'processed' => 0,
      ));
    }
    
    //we do with batches 
    $batch_size = 30;
    $i = 0;
    foreach (array_slice($data['content'], 0, $batch_size) as $item) {
      //check do we have this data, if so update it
      
      array_walk_recursive($item, function(&$a_i, $a_k) {
        $a_i = trim($a_i);
      });
      
      $query = $wpdb->prepare('SELECT id, md5(CONCAT_WS("", `id`, `name`, `capital`, `region`, `population`, `timezones`, `languages`)) as row_hash FROM ' . $wpdb->prefix . self::$table_name .' WHERE name=%s ', array($item['name']));
      
      $row = $wpdb->get_row($query, ARRAY_A, 0);
      
      $row_fields = array(
          'name' => $item['name'],
          'capital' => $item['capital'],
          'region' => $item['region'],
          'population' => $item['population'],
          'timezones' => implode(',', $item['timezones']),
          'languages' => implode(',', array_column($item['languages'], 'name')),
       );
      
      if (!empty($row['row_hash'])) {
        if (md5(implode('', $row['row_hash'])) == $row['row_hash']) {
          $data['processed']++;
         continue; 
        }
      }
      
      $row_fields_formats = array('%s', '%s', '%s', '%d', '%s', '%s');
      
      if (empty($row)) {
        $wpdb->insert($wpdb->prefix . self::$table_name, $row_fields, $row_fields_formats);
      } else {
        $wpdb->update($wpdb->prefix . self::$table_name, $row_fields, array('id' => $row['id']), $row_fields_formats, array('%d'));
      }
      
      $data['processed']++;
    }
    
    array_splice($data['content'], 0, $batch_size);
    
    if (sizeof($data['content']) == 0) {
      delete_transient('med_senior_temp_data');
    } else {
      set_transient('med_senior_temp_data', $data, 30 * 60 );
    }
    
    $completed = ($data['processed'] / $data['total']);
    wp_send_json_success(array(
      'completed' => $completed,
      'total_rows' => $data['total'],
      'processed' => $data['processed'],
    ));
  }
  
  public function validateSettings($input) {
    return $input;
  }
  
  public function nrows_settings() {
    print sprintf('<input type="text" name="med_senior_test[nrows_per_page]" value="%s">', esc_attr($this->options['nrows_per_page']));
  }
  
  public function section_text() {
    print '';
  }
  
  public function section_data_import() {
    $settings = array(
      'ajax_url' => admin_url('admin-ajax.php'),
    );
    wp_enqueue_script('med-senior-test-admin');
    wp_localize_script('med-senior-test-admin', 'medSeniorTestAdminSettings', $settings);
    $btn_text = __('Sync Data', MED_SENIOR_TEST_TEXT_DOMAIN);
    print sprintf('<button class="button button-secondary med-senior-test-sync-data" data-btn-text="%s">%s</button>', esc_attr($btn_text), esc_html($btn_text));
  }
  
  public function admin_menu() {
    $page_title = $menu_title = __('Med Senior test', MED_SENIOR_TEST_TEXT_DOMAIN);
    
    //add_menu_page($page_title, $menu_title, 'manage_options', 'med-senior-test', '', '', 6);
    add_options_page($page_title, $menu_title, 'manage_options', "med-senior-test", array($this, 'render_settings_page'));
  }
  
  
  public function render_settings_page() {
    print sprintf('<div class="wrap">
  <h2>%s</h2>', esc_html(__('General Settings', MED_SENIOR_TEST_TEXT_DOMAIN)));
  
  settings_errors();
  print '<form method="post" action="options.php">';
  settings_fields('med_senior_test');
  do_settings_sections( 'med-senior-test' );
  submit_button();
  print '</form>
  </div>';
  }
  
  public function register_scripts() {
    wp_register_script('datatableset-js', '//cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js', array(
      'jquery'
     ), null, true);
    //wp_register_style('datatableset-css', '//cdn.datatables.net/1.10.22/css/jquery.dataTables.min.css');
    wp_register_script('med-senior-test-js', plugins_url('js/med-senior-test.js', __FILE__), array(
      'datatableset-js'
    ), null, true);
    
    wp_register_script('med-senior-test-admin', plugins_url('js/med-senior-test-admin.js', __FILE__), array(
      'jquery',
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


