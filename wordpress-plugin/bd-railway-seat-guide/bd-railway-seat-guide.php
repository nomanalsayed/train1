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

  public function __construct() {
    add_action('init', [$this, 'register_cpts']);
    add_action('acf/include_fields', [$this, 'register_acf_groups']);
    add_action('rest_api_init', [$this, 'register_rest_routes']);

    // Save hooks
    add_action('acf/save_post', [$this, 'sync_routes_on_save'], 20);
    add_action('acf/save_post', [$this, 'sync_station_title_on_save'], 20);
    add_action('acf/save_post', [$this, 'sync_coach_title_on_save'], 20);
    add_action('acf/save_post', [$this, 'sync_tclass_short_normalize'], 20);
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
    // Trains endpoint
    register_rest_route('rail/v1', '/train/(?P<id>\d+)', [
      'methods'=>'GET','callback'=>[$this,'get_train'],'permission_callback'=>'__return_true',
    ]);
    register_rest_route('rail/v1', '/trains', [
      'methods'=>'GET','callback'=>[$this,'list_trains'],'permission_callback'=>'__return_true',
      'args'=>['per_page'=>['default'=>50],'page'=>['default'=>1]],
    ]);

    // Travel Classes
    register_rest_route('rail/v1', '/classes', [
      'methods'=>'GET','callback'=>[$this,'list_classes'],'permission_callback'=>'__return_true',
      'args'=>[
        'per_page'=>['default'=>100],
        'page'    =>['default'=>1],
        'search'  =>['default'=>''],
        'short'   =>['default'=>''], // short_code exact match
      ],
    ]);
    register_rest_route('rail/v1', '/class/(?P<id>\d+)', [
      'methods'=>'GET','callback'=>[$this,'get_class'],'permission_callback'=>'__return_true',
    ]);

    // Coaches
    register_rest_route('rail/v1', '/coaches', [
      'methods'=>'GET','callback'=>[$this,'list_coaches'],'permission_callback'=>'__return_true',
      'args'=>[
        'per_page'=>['default'=>100],
        'page'    =>['default'=>1],
        'search'  =>['default'=>''],
        'code'    =>['default'=>''], // coach_code exact match
      ],
    ]);
    register_rest_route('rail/v1', '/coach/(?P<id>\d+)', [
      'methods'=>'GET','callback'=>[$this,'get_coach'],'permission_callback'=>'__return_true',
    ]);

    // Stations
    register_rest_route('rail/v1', '/stations', [
      'methods'=>'GET','callback'=>[$this,'list_stations'],'permission_callback'=>'__return_true',
      'args'=>[
        'per_page'=>['default'=>200],
        'page'    =>['default'=>1],
        'search'  =>['default'=>''], // search by title (code)
        'code'    =>['default'=>''], // station_code exact match
      ],
    ]);
    register_rest_route('rail/v1', '/station/(?P<id>\d+)', [
      'methods'=>'GET','callback'=>[$this,'get_station'],'permission_callback'=>'__return_true',
    ]);
  }

  /** ---------- Train endpoints ---------- */
  public function get_train($request) {
    $id = intval($request['id']);
    $post = get_post($id);
    if (!$post || $post->post_type !== self::TRAIN) {
      return new WP_Error('not_found','Train not found',['status'=>404]);
    }

    $origin = intval(get_field('origin_station', $id));
    $dest   = intval(get_field('destination_station', $id));
    $routes = get_field('routes', $id) ?: [];

    $payload = [
      'id'                 => $id,
      'train_name'         => get_the_title($id),
      'from_station'       => $origin ? ['id'=>$origin,'title'=>get_the_title($origin),'code'=>get_field('station_code',$origin)] : null,
      'to_station'         => $dest ? ['id'=>$dest,'title'=>get_the_title($dest),'code'=>get_field('station_code',$dest)] : null,
      'code_from_to'       => (string) get_field('code_from_to', $id),
      'code_to_from'       => (string) get_field('code_to_from', $id),
      'route_from_to'      => $this->build_direction($routes, 'forward'),
      'route_to_from'      => $this->build_direction($routes, 'reverse'),
      'train_classes'      => $this->format_classes_csv(get_field('train_classes',$id)),
      'train_classes_reverse' => $this->reverse_classes_csv(get_field('train_classes',$id)),
    ];

    return rest_ensure_response($payload);
  }

  public function list_trains($request) {
    $per_page = max(1, intval($request['per_page']));
    $page     = max(1, intval($request['page']));
    $q = new WP_Query([
      'post_type'=>self::TRAIN,'posts_per_page'=>$per_page,'paged'=>$page,
      'orderby'=>'title','order'=>'ASC','no_found_rows'=>false,
    ]);

    $items = [];
    foreach ($q->posts as $p) {
      $origin = intval(get_field('origin_station', $p->ID));
      $dest   = intval(get_field('destination_station', $p->ID));
      $items[] = [
        'id'=>$p->ID,
        'title'=>get_the_title($p),
        'train_name'=>get_the_title($p),
        'from_station'=>$origin ? ['id'=>$origin,'title'=>get_the_title($origin),'code'=>get_field('station_code',$origin)] : null,
        'to_station'=>$dest ? ['id'=>$dest,'title'=>get_the_title($dest),'code'=>get_field('station_code',$dest)] : null,
        'code_from_to'=>(string) get_field('code_from_to',$p->ID),
        'code_to_from'=>(string) get_field('code_to_from',$p->ID),
      ];
    }

    return rest_ensure_response([
      'items'=>$items,'total'=>intval($q->found_posts),'pages'=>intval($q->max_num_pages),
      'page'=>$page,'per_page'=>$per_page,
    ]);
  }

  /** ---------- Travel Class endpoints ---------- */
  public function list_classes($request) {
    $per_page = max(1, intval($request['per_page']));
    $page     = max(1, intval($request['page']));
    $search   = is_string($request['search']) ? trim($request['search']) : '';
    $short    = is_string($request['short'])  ? strtoupper(trim($request['short'])) : '';

    $args = [
      'post_type'      => self::TCLASS,
      'posts_per_page' => $per_page,
      'paged'          => $page,
      'orderby'        => 'title',
      'order'          => 'ASC',
      's'              => $search,
      'no_found_rows'  => false,
    ];

    if ($short !== '') {
      $args['meta_query'] = [[
        'key'     => 'short_code',
        'value'   => $short,
        'compare' => '=',
      ]];
    }

    $q = new WP_Query($args);

    $items = [];
    foreach ($q->posts as $p) {
      $items[] = [
        'id'          => $p->ID,
        'class_name'  => get_the_title($p->ID),
        'class_short' => (string) get_field('short_code', $p->ID),
        'slug'        => $p->post_name,
      ];
    }

    return rest_ensure_response([
      'items'    => $items,
      'total'    => intval($q->found_posts),
      'pages'    => intval($q->max_num_pages),
      'page'     => $page,
      'per_page' => $per_page,
    ]);
  }

  public function get_class($request) {
    $id = intval($request['id']);
    $p  = get_post($id);
    if (!$p || $p->post_type !== self::TCLASS) {
      return new WP_Error('not_found', 'Class not found', ['status' => 404]);
    }
    return rest_ensure_response([
      'id'          => $p->ID,
      'class_name'  => get_the_title($p->ID),
      'class_short' => (string) get_field('short_code', $p->ID),
      'slug'        => $p->post_name,
    ]);
  }

  /** ---------- Coach endpoints ---------- */
  public function list_coaches($request) {
    $per_page = max(1, intval($request['per_page']));
    $page     = max(1, intval($request['page']));
    $search   = is_string($request['search']) ? trim($request['search']) : '';
    $code     = is_string($request['code'])   ? trim($request['code'])   : '';

    $args = [
      'post_type'      => self::COACH,
      'posts_per_page' => $per_page,
      'paged'          => $page,
      'orderby'        => 'title',
      'order'          => 'ASC',
      's'              => $search,
      'no_found_rows'  => false,
    ];

    if ($code !== '') {
      $args['meta_query'] = [[
        'key'     => 'coach_code',
        'value'   => $code,
        'compare' => '=',
      ]];
    }

    $q = new WP_Query($args);

    $items = [];
    foreach ($q->posts as $p) {
      $coach_id = $p->ID;
      $coach_code = (string) get_field('coach_code', $coach_id);
      $total = null;
      $csv   = $this->compute_from_coach($coach_id, $total);

      $items[] = [
        'id'           => $coach_id,
        'coach_code'   => $coach_code,
        'total_seats'  => $total,
        'front_facing' => $csv['front_csv'], // CSV strings
        'back_facing'  => $csv['back_csv'],  // CSV strings
        'slug'         => $p->post_name,
      ];
    }

    return rest_ensure_response([
      'items'=>$items,'total'=>intval($q->found_posts),'pages'=>intval($q->max_num_pages),
      'page'=>$page,'per_page'=>$per_page,
    ]);
  }

  public function get_coach($request) {
    $id = intval($request['id']);
    $p  = get_post($id);
    if (!$p || $p->post_type !== self::COACH) {
      return new WP_Error('not_found', 'Coach not found', ['status' => 404]);
    }

    $total = null;
    $csv   = $this->compute_from_coach($id, $total);

    return rest_ensure_response([
      'id'           => $p->ID,
      'coach_code'   => (string) get_field('coach_code', $p->ID),
      'total_seats'  => $total,
      'front_facing' => $csv['front_csv'],
      'back_facing'  => $csv['back_csv'],
      'slug'         => $p->post_name,
    ]);
  }

  /** ---------- Station endpoints ---------- */
  public function list_stations($request) {
    $per_page = max(1, intval($request['per_page']));
    $page     = max(1, intval($request['page']));
    $search   = is_string($request['search']) ? trim($request['search']) : '';
    $code     = is_string($request['code'])   ? trim($request['code'])   : '';

    $args = [
      'post_type'      => self::STATION,
      'posts_per_page' => $per_page,
      'paged'          => $page,
      'orderby'        => 'title',
      'order'          => 'ASC',
      's'              => $search,
      'no_found_rows'  => false,
    ];

    if ($code !== '') {
      $args['meta_query'] = [[
        'key'     => 'station_code',
        'value'   => $code,
        'compare' => '=',
      ]];
    }

    $q = new WP_Query($args);

    $items = [];
    foreach ($q->posts as $p) {
      $items[] = [
        'id'    => $p->ID,
        'title' => get_the_title($p->ID),               // mirrors code
        'code'  => (string) get_field('station_code', $p->ID),
        'slug'  => $p->post_name,
      ];
    }

    return rest_ensure_response([
      'items'=>$items,'total'=>intval($q->found_posts),'pages'=>intval($q->max_num_pages),
      'page'=>$page,'per_page'=>$per_page,
    ]);
  }

  public function get_station($request) {
    $id = intval($request['id']);
    $p  = get_post($id);
    if (!$p || $p->post_type !== self::STATION) {
      return new WP_Error('not_found', 'Station not found', ['status' => 404]);
    }
    return rest_ensure_response([
      'id'    => $p->ID,
      'title' => get_the_title($p->ID),
      'code'  => (string) get_field('station_code', $p->ID),
      'slug'  => $p->post_name,
    ]);
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

  private function build_direction($rows, $dir = 'forward') {
    if (!$rows || !is_array($rows)) return [];
    $seq = ($dir === 'reverse') ? array_reverse($rows) : $rows;
    $out = [];
    foreach ($seq as $row) {
      $sid = intval($row['station'] ?? 0);
      if (!$sid) continue;
      $out[] = [
        'station' => [
          'id'    => $sid,
          'title' => get_the_title($sid),
          'code'  => get_field('station_code', $sid),
        ],
      ];
    }
    return $out;
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

  /** Seats helpers */
  private function clamp($n, $min, $max) { return max($min, min($max, $n)); }
  private function range_list($start, $end) {
    $start = intval($start); $end = intval($end);
    if ($start <= 0 || $end <= 0) return [];
    if ($end < $start) [$start,$end] = [$end,$start];
    return range($start, $end);
  }
  private function list_to_csv($arr) {
    $arr = array_values(array_unique(array_map('intval', $arr)));
    sort($arr, SORT_NUMERIC);
    return implode(',', $arr);
  }

  /** Compute seats from a COACH post (template) */
  private function compute_from_coach($coach_id, &$out_total = null) {
    $coach_id = intval($coach_id);
    if (!$coach_id) return ['front_csv'=>'','back_csv'=>''];
    $total = intval(get_field('total_seats', $coach_id));
    $out_total = $total ?: null;
    if ($total <= 0) return ['front_csv'=>'','back_csv'=>''];

    $fs = intval(get_field('front_start', $coach_id));
    $fe = intval(get_field('front_end', $coach_id));
    $front = ($fs && $fe) ? $this->range_list($this->clamp($fs,1,$total), $this->clamp($fe,1,$total)) : [];

    $bs = intval(get_field('back_start', $coach_id));
    $be = intval(get_field('back_end', $coach_id));
    $auto = !empty(get_field('auto_back_fill', $coach_id));

    if ($bs && $be) {
      $back = $this->range_list($this->clamp($bs,1,$total), $this->clamp($be,1,$total));
    } else {
      $back = $auto ? array_values(array_diff(range(1,$total), $front)) : [];
    }

    return ['front_csv'=>$this->list_to_csv($front),'back_csv'=>$this->list_to_csv($back)];
  }

  /** Compute seats from Travel Class defaults */
  private function compute_from_tclass($class_id, &$out_total = null) {
    $class_id = intval($class_id);
    if (!$class_id) return ['front_csv'=>'','back_csv'=>''];
    $total = intval(get_field('default_total_seats', $class_id));
    $out_total = $total ?: null;
    if ($total <= 0) return ['front_csv'=>'','back_csv'=>''];

    $fs = intval(get_field('default_front_start', $class_id));
    $fe = intval(get_field('default_front_end', $class_id));
    $front = ($fs && $fe) ? $this->range_list($this->clamp($fs,1,$total), $this->clamp($fe,1,$total)) : [];

    $bs = intval(get_field('default_back_start', $class_id));
    $be = intval(get_field('default_back_end', $class_id));
    $auto = !empty(get_field('default_auto_back_fill', $class_id));

    if ($bs && $be) {
      $back = $this->range_list($this->clamp($bs,1,$total), $this->clamp($be,1,$total));
    } else {
      $back = $auto ? array_values(array_diff(range(1,$total), $front)) : [];
    }

    return ['front_csv'=>$this->list_to_csv($front),'back_csv'=>$this->list_to_csv($back)];
  }

  /** Compute seats from train-level override fields */
  private function compute_from_override($row) {
    $total = isset($row['total_seats']) ? intval($row['total_seats']) : 0;
    if ($total <= 0) return ['front_csv'=>'','back_csv'=>'','total'=>null];

    $fs = $this->clamp(intval($row['front_start'] ?? 0), 1, $total);
    $fe = $this->clamp(intval($row['front_end'] ?? 0),   1, $total);
    $front = ($fs && $fe) ? $this->range_list($fs, $fe) : [];

    $bs = intval($row['back_start'] ?? 0);
    $be = intval($row['back_end'] ?? 0);
    $auto = !empty($row['auto_back_fill']);

    if ($bs && $be) {
      $back = $this->range_list($this->clamp($bs,1,$total), $this->clamp($be,1,$total));
    } else {
      $back = $auto ? array_values(array_diff(range(1,$total), $front)) : [];
    }

    return ['front_csv'=>$this->list_to_csv($front),'back_csv'=>$this->list_to_csv($back),'total'=>$total];
  }

  /** Build classes array with CSV seats, honoring precedence: override > coach > class */
  private function format_classes_csv($rows) {
    if (!$rows || !is_array($rows)) return [];
    $classes = [];
    foreach ($rows as $classRow) {
      $class_id = intval($classRow['class_ref'] ?? 0);
      $class_name  = $class_id ? get_the_title($class_id) : '';
      $class_short = $class_id ? (string) get_field('short_code', $class_id) : '';

      $coachesOut = [];
      if (!empty($classRow['coaches']) && is_array($classRow['coaches'])) {
        foreach ($classRow['coaches'] as $coachRow) {
          $coach_id = intval($coachRow['coach_ref'] ?? 0);
          $coach_code = $coach_id ? (string) get_field('coach_code', $coach_id) : '';

          // Seats: override > coach template > travel class defaults
          if (!empty($coachRow['override_seats'])) {
            $calc = $this->compute_from_override($coachRow);
            $total = $calc['total'];
          } else {
            $calc = $this->compute_from_coach($coach_id, $total);
            if ((!$calc['front_csv'] && !$calc['back_csv']) || !$total) {
              $calc = $this->compute_from_tclass($class_id, $total);
            }
          }

          $coachesOut[] = [
            'coach_id'     => $coach_id ?: null,
            'coach_code'   => $coach_code,
            'total_seats'  => $total ?: null,
            'front_facing' => $calc['front_csv'], // CSV strings
            'back_facing'  => $calc['back_csv'],  // CSV strings
          ];
        }
      }

      $classes[] = [
        'class_id'    => $class_id ?: null,
        'class_short' => $class_short,
        'class_name'  => $class_name,
        'coaches'     => $coachesOut,
      ];
    }
    return $classes;
  }

  private function reverse_classes_csv($rows) {
    $normal = $this->format_classes_csv($rows);
    $reverse = [];
    foreach ($normal as $cls) {
      $revCoaches = [];
      foreach ($cls['coaches'] as $c) {
        $revCoaches[] = [
          'coach_id'     => $c['coach_id'],
          'coach_code'   => $c['coach_code'],
          'total_seats'  => $c['total_seats'],
          'front_facing' => $c['back_facing'],
          'back_facing'  => $c['front_facing'],
        ];
      }
      $reverse[] = [
        'class_id'    => $cls['class_id'],
        'class_short' => $cls['class_short'],
        'class_name'  => $cls['class_name'],
        'coaches'     => $revCoaches
      ];
    }
    return $reverse;
  }
}

new BD_Railway_Headless_WithCoachClass();