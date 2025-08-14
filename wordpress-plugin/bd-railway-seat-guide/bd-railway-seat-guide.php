<?php
/**
 * Plugin Name: BD Railway Headless (ACF Pro) — Minimal Routes + Coach & Class CPTs
 * Description: Trains/Stations CPTs, single Routes, Coach & Travel Class CPTs selectable on Train, CSV seats in API. No dummy data, sortable station picker, class default layouts.
 * Version:     2.0.0
 */

if (!defined('ABSPATH')) exit;

class BD_Railway_Headless_WithCoachClass {
  const TRAIN   = 'train';
  const STATION = 'station';
  const COACH   = 'coach';
  const TCLASS  = 'travel_class';
  const API_NAMESPACE = 'rail/v1';

  private $train_endpoints;
  private $station_endpoints;
  private $coach_endpoints;
  private $search_endpoints;

  public function __construct() {
    add_action('init', [$this, 'register_cpts']);
    add_action('acf/include_fields', [$this, 'register_acf_groups']);
    add_action('rest_api_init', [$this, 'register_rest_routes']);

    // Include endpoint classes
    $this->include_endpoint_classes();

    // Save hooks
    add_action('acf/save_post', [$this, 'sync_routes_on_save'], 20);
    add_action('acf/save_post', [$this, 'sync_station_title_on_save'], 20);
    add_action('acf/save_post', [$this, 'sync_coach_title_on_save'], 20);
    add_action('acf/save_post', [$this, 'sync_tclass_short_normalize'], 20);
  }

  private function include_endpoint_classes() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-train-endpoints.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-station-endpoints.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-coach-endpoints.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-search-endpoints.php';

    $this->train_endpoints = new BD_Railway_Train_Endpoints($this);
    $this->station_endpoints = new BD_Railway_Station_Endpoints($this);
    $this->coach_endpoints = new BD_Railway_Coach_Endpoints($this);
    $this->search_endpoints = new BD_Railway_Search_Endpoints($this);
  }

  /** CPTs */
  public function register_cpts() {
    // Station
    register_post_type(self::STATION, [
      'label' => 'Stations',
      'public' => true,
      'show_in_rest' => true,
      'supports' => ['title'], // title mirrors station_code
      'rewrite' => ['slug' => 'station'],
      'menu_icon' => 'dashicons-location',
    ]);

    // Train
    register_post_type(self::TRAIN, [
      'label' => 'Trains',
      'public' => true,
      'show_in_rest' => true,
      'supports' => ['title','editor','thumbnail'],
      'rewrite' => ['slug' => 'train'],
      'menu_icon' => 'dashicons-admin-site-alt3',
    ]);

    // Coach
    register_post_type(self::COACH, [
      'label' => 'Coaches',
      'public' => true,
      'show_in_rest' => true,
      'supports' => ['title'], // title mirrors coach_code
      'rewrite' => ['slug' => 'coach'],
      'menu_icon' => 'dashicons-id-alt',
    ]);

    // Travel Class
    register_post_type(self::TCLASS, [
      'label' => 'Travel Classes',
      'public' => true,
      'show_in_rest' => true,
      'supports' => ['title'], // title = Full Class Name
      'rewrite' => ['slug' => 'travel-class'],
      'menu_icon' => 'dashicons-welcome-learn-more',
    ]);
  }

  /** ACF (requires ACF Pro) */
  public function register_acf_groups() {
    if (!function_exists('acf_add_local_field_group')) return;

    /* Station fields */
    acf_add_local_field_group([
      'key' => 'group_station',
      'title' => 'Station Fields',
      'show_in_rest' => 1,
      'fields' => [
        ['key'=>'st_code','label'=>'Station Code','name'=>'station_code','type'=>'text','instructions'=>'e.g., DAC','required'=>1],
      ],
      'location' => [[['param'=>'post_type','operator'=>'==','value'=>self::STATION]]],
    ]);

    /* Coach fields */
    acf_add_local_field_group([
      'key' => 'group_coach',
      'title' => 'Coach Fields',
      'show_in_rest' => 1,
      'fields' => [
        ['key'=>'coach_code','label'=>'Coach Code','name'=>'coach_code','type'=>'text','instructions'=>'e.g., UMA','required'=>1],
        ['key'=>'coach_total','label'=>'Total Seats','name'=>'total_seats','type'=>'number','min'=>1,'required'=>1],
        ['key'=>'coach_f_start','label'=>'Front-facing Start','name'=>'front_start','type'=>'number','min'=>1],
        ['key'=>'coach_f_end','label'=>'Front-facing End','name'=>'front_end','type'=>'number','min'=>1],
        ['key'=>'coach_b_start','label'=>'Back-facing Start (optional)','name'=>'back_start','type'=>'number','min'=>1],
        ['key'=>'coach_b_end','label'=>'Back-facing End (optional)','name'=>'back_end','type'=>'number','min'=>1],
        ['key'=>'coach_auto_b','label'=>'Auto-fill Back-facing from remaining','name'=>'auto_back_fill','type'=>'true_false','ui'=>1,'default_value'=>1],
      ],
      'location' => [[['param'=>'post_type','operator'=>'==','value'=>self::COACH]]],
    ]);

    /* Travel Class fields (now with default seat template) */
    acf_add_local_field_group([
      'key' => 'group_tclass',
      'title' => 'Travel Class Fields',
      'show_in_rest' => 1,
      'fields' => [
        ['key'=>'tclass_short','label'=>'Short Code','name'=>'short_code','type'=>'text','instructions'=>'e.g., AC_B','required'=>1],
        ['key'=>'tclass_sep','label'=>'Default Seat Template (optional)','type'=>'message','message'=>'If a Coach has no template and there is no per-train override, these defaults apply.'],
        ['key'=>'tclass_total','label'=>'Default Total Seats','name'=>'default_total_seats','type'=>'number','min'=>1],
        ['key'=>'tclass_f_start','label'=>'Default Front-facing Start','name'=>'default_front_start','type'=>'number','min'=>1],
        ['key'=>'tclass_f_end','label'=>'Default Front-facing End','name'=>'default_front_end','type'=>'number','min'=>1],
        ['key'=>'tclass_b_start','label'=>'Default Back-facing Start (optional)','name'=>'default_back_start','type'=>'number','min'=>1],
        ['key'=>'tclass_b_end','label'=>'Default Back-facing End (optional)','name'=>'default_back_end','type'=>'number','min'=>1],
        ['key'=>'tclass_auto_b','label'=>'Auto-fill Back-facing from remaining','name'=>'default_auto_back_fill','type'=>'true_false','ui'=>1,'default_value'=>1],
      ],
      'location' => [[['param'=>'post_type','operator'=>'==','value'=>self::TCLASS]]],
    ]);

    /* Train fields */
    acf_add_local_field_group([
      'key' => 'group_train',
      'title' => 'Train Fields',
      'show_in_rest' => 1,
      'location' => [[['param'=>'post_type','operator'=>'==','value'=>self::TRAIN]]],
      'fields' => [
        ['key'=>'tab_basic','label'=>'Basic Info','type'=>'tab','placement'=>'top'],
        [
          'key'=>'tr_from','label'=>'From Station','name'=>'origin_station','type'=>'post_object',
          'post_type'=>[self::STATION],'return_format'=>'id','ui'=>1,'required'=>1,
        ],
        [
          'key'=>'tr_to','label'=>'To Station','name'=>'destination_station','type'=>'post_object',
          'post_type'=>[self::STATION],'return_format'=>'id','ui'=>1,'required'=>1,
        ],
        ['key'=>'code_ft','label'=>'Route Code (From → To)','name'=>'code_from_to','type'=>'text'],
        ['key'=>'code_tf','label'=>'Route Code (To → From)','name'=>'code_to_from','type'=>'text'],

        ['key'=>'tab_routes','label'=>'Routes','type'=>'tab','placement'=>'top'],
        [
          'key'=>'routes_picker','label'=>'Middle Stops (Picker)','name'=>'routes_picker','type'=>'relationship',
          'post_type'=>[self::STATION],'return_format'=>'id','filters'=>['search'],'elements'=>['featured_image'],
          'instructions'=>'Select only the stations between From and To. Drag to sort. Leave empty if you prefer CSV or manual repeater.',
        ],
        [
          'key'=>'routes_csv','label'=>'Bulk Stations (CSV)','name'=>'routes_csv','type'=>'textarea',
          'instructions'=>'Paste station codes or titles (comma-separated) for middle stops only. Endpoints auto-managed.',
        ],
        [
          'key'=>'routes','label'=>'Routes','name'=>'routes','type'=>'repeater','layout'=>'row','button_label'=>'Add Station',
          'instructions' => 'Add only stations between From and To (endpoints are auto-added on save).',
          'sub_fields'=>[
            ['key'=>'rt_station','label'=>'Station','name'=>'station','type'=>'post_object','post_type'=>[self::STATION],'return_format'=>'id','ui'=>1,'required'=>1],
          ],
        ],

        ['key'=>'tab_classes','label'=>'Travel Classes & Coaches','type'=>'tab','placement'=>'top'],
        [
          'key'=>'train_classes','label'=>'Train Classes','name'=>'train_classes','type'=>'repeater','layout'=>'row','button_label'=>'Add Class',
          'sub_fields'=>[
            [
              'key'=>'cls_ref','label'=>'Class','name'=>'class_ref','type'=>'post_object',
              'post_type'=>[self::TCLASS],'return_format'=>'id','ui'=>1,'required'=>1,
            ],
            [
              'key'=>'coaches','label'=>'Coaches','name'=>'coaches','type'=>'repeater','layout'=>'row','button_label'=>'Add Coach',
              'sub_fields'=>[
                [
                  'key'=>'coach_ref','label'=>'Coach','name'=>'coach_ref','type'=>'post_object',
                  'post_type'=>[self::COACH],'return_format'=>'id','ui'=>1,'required'=>1,
                ],
                ['key'=>'override_flag','label'=>'Override Coach Seats for this Train','name'=>'override_seats','type'=>'true_false','ui'=>1,'default_value'=>0],
                ['key'=>'ch_total','label'=>'Total Seats (override)','name'=>'total_seats','type'=>'number','min'=>1,'conditional_logic'=>[['field'=>'override_flag','operator'=>'==','value'=>1]]],
                ['key'=>'ch_f_start','label'=>'Front-facing Start (override)','name'=>'front_start','type'=>'number','min'=>1,'conditional_logic'=>[['field'=>'override_flag','operator'=>'==','value'=>1]]],
                ['key'=>'ch_f_end','label'=>'Front-facing End (override)','name'=>'front_end','type'=>'number','min'=>1,'conditional_logic'=>[['field'=>'override_flag','operator'=>'==','value'=>1]]],
                ['key'=>'ch_b_start','label'=>'Back-facing Start (override)','name'=>'back_start','type'=>'number','min'=>1,'conditional_logic'=>[['field'=>'override_flag','operator'=>'==','value'=>1]]],
                ['key'=>'ch_b_end','label'=>'Back-facing End (override)','name'=>'back_end','type'=>'number','min'=>1,'conditional_logic'=>[['field'=>'override_flag','operator'=>'==','value'=>1]]],
                ['key'=>'ch_auto_b','label'=>'Auto-fill Back-facing (override)','name'=>'auto_back_fill','type'=>'true_false','ui'=>1,'default_value'=>1,'conditional_logic'=>[['field'=>'override_flag','operator'=>'==','value'=>1]]],
              ],
            ],
          ],
        ],
      ],
    ]);
  }

  /** REST */
  public function register_rest_routes() {
    // Register all endpoint routes through their respective classes
    if ($this->train_endpoints) {
      $this->train_endpoints->register_routes();
    }
    if ($this->station_endpoints) {
      $this->station_endpoints->register_routes();
    }
    if ($this->coach_endpoints) {
      $this->coach_endpoints->register_routes();
    }
    if ($this->search_endpoints) {
      $this->search_endpoints->register_routes();
    }
  }

  /** Save hooks */
  public function sync_routes_on_save($post_id) {
    if (get_post_type($post_id) !== self::TRAIN) return;

    $origin = intval(get_field('origin_station', $post_id));
    $dest   = intval(get_field('destination_station', $post_id));
    if (!$origin || !$dest) return;

    // 1) Relationship picker (takes precedence if used)
    $picked = get_field('routes_picker', $post_id);
    if (is_array($picked) && !empty($picked)) {
      $middle = [];
      foreach ($picked as $sid) {
        $sid = intval($sid);
        if ($sid && $sid !== $origin && $sid !== $dest) $middle[] = ['station'=>$sid];
      }
      update_field('routes', $middle, $post_id);
      update_field('routes_picker', [], $post_id); // clear to avoid reapplying later
    }

    // 2) CSV bulk input (if provided)
    $csv = (string) get_field('routes_csv', $post_id);
    if (trim($csv) !== '') {
      $tokens = preg_split('/[,\n]+/', $csv);
      $tokens = array_values(array_filter(array_map('trim', $tokens), fn($t) => $t !== ''));

      $middle = [];
      foreach ($tokens as $tok) {
        $sid = $this->find_station_id_by_code_or_title($tok);
        if ($sid && $sid !== $origin && $sid !== $dest) $middle[] = ['station'=>$sid];
      }
      update_field('routes', $middle, $post_id);
      update_field('routes_csv', '', $post_id);
    }

    // 3) Ensure endpoints wrap the middle stops
    $rows = get_field('routes', $post_id) ?: [];
    $rows = $this->ensure_endpoints_single($rows, $origin, $dest);
    update_field('routes', $rows, $post_id);
  }

  public function sync_station_title_on_save($post_id) {
    if (get_post_type($post_id) !== self::STATION) return;
    $code = trim((string) get_field('station_code', $post_id));
    if ($code === '') return;
    $p = get_post($post_id); if (!$p) return;
    if ($p->post_title !== $code) {
      wp_update_post(['ID'=>$post_id,'post_title'=>$code,'post_name'=>sanitize_title($code)]);
    }
  }

  public function sync_coach_title_on_save($post_id) {
    if (get_post_type($post_id) !== self::COACH) return;
    $code = trim((string) get_field('coach_code', $post_id));
    if ($code === '') return;
    $p = get_post($post_id); if (!$p) return;
    if ($p->post_title !== $code) {
      wp_update_post(['ID'=>$post_id,'post_title'=>$code,'post_name'=>sanitize_title($code)]);
    }
  }

  public function sync_tclass_short_normalize($post_id) {
    if (get_post_type($post_id) !== self::TCLASS) return;
    $short = get_field('short_code', $post_id);
    if (is_string($short) && $short !== '') {
      $short_up = strtoupper(trim($short));
      if ($short_up !== $short) update_field('short_code', $short_up, $post_id);
    }
  }

  /** ---------- Helpers ---------- */

  private function ensure_endpoints_single($rows, $startId, $endId) {
    $rows = is_array($rows) ? array_values($rows) : [];
    $filtered = [];
    foreach ($rows as $r) {
      $sid = intval($r['station'] ?? 0);
      if ($sid === $startId || $sid === $endId) continue;
      $filtered[] = ['station'=>$sid];
    }
    array_unshift($filtered, ['station'=>$startId]);
    $filtered[] = ['station'=>$endId];
    return $filtered;
  }

  private function find_station_id_by_code_or_title($token) {
    $token = trim($token);
    if ($token === '') return 0;
    // by station_code
    $q = get_posts([
      'post_type' => self::STATION,
      'posts_per_page' => 1,
      'fields' => 'ids',
      'meta_query' => [[ 'key'=>'station_code','value'=>$token,'compare'=>'=' ]],
    ]);
    if (!empty($q)) return intval($q[0]);
    // by title
    $p = get_page_by_title($token, OBJECT, self::STATION);
    return $p ? intval($p->ID) : 0;
  }

  /** Seat calculation helper methods */
  public function calculate_seat_directions($coach_id) {
    $coach_id = intval($coach_id);
    if (!$coach_id) return ['total_seats' => 0, 'front_facing_seats' => [], 'back_facing_seats' => []];

    $total = intval(get_field('total_seats', $coach_id));
    if ($total <= 0) return ['total_seats' => 0, 'front_facing_seats' => [], 'back_facing_seats' => []];

    $fs = intval(get_field('front_start', $coach_id));
    $fe = intval(get_field('front_end', $coach_id));
    $front = ($fs && $fe) ? range($fs, $fe) : [];

    $bs = intval(get_field('back_start', $coach_id));
    $be = intval(get_field('back_end', $coach_id));
    $auto = !empty(get_field('auto_back_fill', $coach_id));

    if ($bs && $be) {
      $back = range($bs, $be);
    } else {
      $back = $auto ? array_values(array_diff(range(1, $total), $front)) : [];
    }

    return [
      'total_seats' => $total,
      'front_facing_seats' => $front,
      'back_facing_seats' => $back,
    ];
  }
}

new BD_Railway_Headless_WithCoachClass();