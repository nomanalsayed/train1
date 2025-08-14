<?php
/**
 * BD Railway Seat Guide
 * 
 * Plugin Name: BD Railway Seat Guide
 * Plugin URI: https://github.com/example/bd-railway-seat-guide
 * Description: A comprehensive WordPress plugin for Bangladesh Railway seat directions and coach information
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL2
 * Text Domain: bd-railway
 */

if (!defined('ABSPATH')) exit;

class BD_Railway_Seat_Guide {
    const VERSION = '1.0.0';
    const API_NAMESPACE = 'bd-railway/v1';
    const TRAIN = 'bd_train';
    const COACH = 'bd_coach';
    const STATION = 'bd_station';
    
    private $train_endpoints;
    private $coach_endpoints;
    private $station_endpoints;
    private $search_endpoints;
    
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('rest_api_init', [$this, 'register_api_routes']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    public function init() {
        $this->register_post_types();
        $this->setup_custom_fields();
        $this->load_dependencies();
    }
    
    public function activate() {
        $this->register_post_types();
        flush_rewrite_rules();
        $this->create_sample_data();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function register_post_types() {
        // Register Train post type
        register_post_type(self::TRAIN, [
            'labels' => [
                'name' => __('Trains', 'bd-railway'),
                'singular_name' => __('Train', 'bd-railway'),
            ],
            'public' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor'],
            'menu_icon' => 'dashicons-location-alt',
        ]);
        
        // Register Coach post type
        register_post_type(self::COACH, [
            'labels' => [
                'name' => __('Coaches', 'bd-railway'),
                'singular_name' => __('Coach', 'bd-railway'),
            ],
            'public' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor'],
            'menu_icon' => 'dashicons-admin-multisite',
        ]);
        
        // Register Station post type
        register_post_type(self::STATION, [
            'labels' => [
                'name' => __('Stations', 'bd-railway'),
                'singular_name' => __('Station', 'bd-railway'),
            ],
            'public' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor'],
            'menu_icon' => 'dashicons-building',
        ]);
    }
    
    private function setup_custom_fields() {
        if (!function_exists('acf_add_local_field_group')) return;
        
        // Train fields
        acf_add_local_field_group([
            'key' => 'group_train_details',
            'title' => 'Train Details',
            'fields' => [
                [
                    'key' => 'field_train_number',
                    'label' => 'Train Number',
                    'name' => 'train_number',
                    'type' => 'text',
                    'required' => 1,
                ],
                [
                    'key' => 'field_reverse_train_number',
                    'label' => 'Reverse Train Number',
                    'name' => 'reverse_train_number',
                    'type' => 'text',
                ],
                [
                    'key' => 'field_origin_station',
                    'label' => 'Origin Station',
                    'name' => 'origin_station',
                    'type' => 'post_object',
                    'post_type' => [self::STATION],
                ],
                [
                    'key' => 'field_destination_station',
                    'label' => 'Destination Station',
                    'name' => 'destination_station',
                    'type' => 'post_object',
                    'post_type' => [self::STATION],
                ],
                [
                    'key' => 'field_train_coaches',
                    'label' => 'Train Coaches',
                    'name' => 'train_coaches',
                    'type' => 'repeater',
                    'sub_fields' => [
                        [
                            'key' => 'field_coach_reference',
                            'label' => 'Coach',
                            'name' => 'coach_reference',
                            'type' => 'post_object',
                            'post_type' => [self::COACH],
                        ],
                        [
                            'key' => 'field_coach_position',
                            'label' => 'Position',
                            'name' => 'position',
                            'type' => 'number',
                        ],
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => self::TRAIN,
                    ],
                ],
            ],
        ]);
        
        // Coach fields
        acf_add_local_field_group([
            'key' => 'group_coach_details',
            'title' => 'Coach Details',
            'fields' => [
                [
                    'key' => 'field_coach_code',
                    'label' => 'Coach Code',
                    'name' => 'coach_code',
                    'type' => 'text',
                    'required' => 1,
                ],
                [
                    'key' => 'field_coach_type',
                    'label' => 'Coach Type',
                    'name' => 'coach_type',
                    'type' => 'select',
                    'choices' => [
                        'S_CHAIR' => 'Shovan Chair',
                        'AC_S' => 'AC Seat',
                        'AC_B' => 'AC Berth',
                        'F_SEAT' => 'First Class Seat',
                        'F_BERTH' => 'First Class Berth',
                        'SHULOV' => 'Shulov',
                        'CHA' => 'Chair Car',
                    ],
                    'default_value' => 'S_CHAIR',
                ],
                [
                    'key' => 'field_total_seats',
                    'label' => 'Total Seats',
                    'name' => 'total_seats',
                    'type' => 'number',
                    'default_value' => 60,
                ],
                [
                    'key' => 'field_seat_layout',
                    'label' => 'Seat Layout',
                    'name' => 'seat_layout',
                    'type' => 'select',
                    'choices' => [
                        'mixed' => 'Mixed (Front and Back facing)',
                        'all_front' => 'All Front Facing',
                        'all_back' => 'All Back Facing',
                        'custom' => 'Custom Layout',
                    ],
                    'default_value' => 'mixed',
                ],
                [
                    'key' => 'field_front_facing_seats',
                    'label' => 'Front Facing Seats',
                    'name' => 'front_facing_seats',
                    'type' => 'textarea',
                    'instructions' => 'Comma separated seat numbers (e.g., 1,2,3,4,5)',
                ],
                [
                    'key' => 'field_back_facing_seats',
                    'label' => 'Back Facing Seats',
                    'name' => 'back_facing_seats',
                    'type' => 'textarea',
                    'instructions' => 'Comma separated seat numbers (e.g., 31,32,33,34,35)',
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => self::COACH,
                    ],
                ],
            ],
        ]);
        
        // Station fields
        acf_add_local_field_group([
            'key' => 'group_station_details',
            'title' => 'Station Details',
            'fields' => [
                [
                    'key' => 'field_station_code',
                    'label' => 'Station Code',
                    'name' => 'station_code',
                    'type' => 'text',
                    'required' => 1,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => self::STATION,
                    ],
                ],
            ],
        ]);
    }
    
    private function load_dependencies() {
        require_once plugin_dir_path(__FILE__) . 'includes/class-train-endpoints.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-coach-endpoints.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-station-endpoints.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-search-endpoints.php';
        
        $this->train_endpoints = new BD_Railway_Train_Endpoints($this);
        $this->coach_endpoints = new BD_Railway_Coach_Endpoints($this);
        $this->station_endpoints = new BD_Railway_Station_Endpoints($this);
        $this->search_endpoints = new BD_Railway_Search_Endpoints($this);
    }
    
    public function register_api_routes() {
        if ($this->train_endpoints) $this->train_endpoints->register_routes();
        if ($this->coach_endpoints) $this->coach_endpoints->register_routes();
        if ($this->station_endpoints) $this->station_endpoints->register_routes();
        if ($this->search_endpoints) $this->search_endpoints->register_routes();
    }
    
    /**
     * Calculate seat directions for a coach with robust fallback logic
     */
    public function calculate_seat_directions($coach_id, $coach_data = null) {
        $total_seats = intval(get_field('total_seats', $coach_id)) ?: 60;
        $seat_layout = get_field('seat_layout', $coach_id) ?: 'mixed';
        
        // Get custom seat configurations
        $front_facing_raw = get_field('front_facing_seats', $coach_id);
        $back_facing_raw = get_field('back_facing_seats', $coach_id);
        
        $front_facing_seats = [];
        $back_facing_seats = [];
        
        // Parse custom seat numbers if provided
        if (!empty($front_facing_raw)) {
            $front_facing_seats = array_map('intval', array_filter(explode(',', $front_facing_raw)));
        }
        
        if (!empty($back_facing_raw)) {
            $back_facing_seats = array_map('intval', array_filter(explode(',', $back_facing_raw)));
        }
        
        // If no custom seats defined, generate based on layout and coach code
        if (empty($front_facing_seats) && empty($back_facing_seats)) {
            $coach_code = get_field('coach_code', $coach_id);
            $result = $this->generate_seats_by_layout($total_seats, $seat_layout, $coach_code);
            $front_facing_seats = $result['front_facing_seats'];
            $back_facing_seats = $result['back_facing_seats'];
        }
        
        // Ensure all seats are accounted for
        $all_assigned_seats = array_merge($front_facing_seats, $back_facing_seats);
        $missing_seats = [];
        
        for ($i = 1; $i <= $total_seats; $i++) {
            if (!in_array($i, $all_assigned_seats)) {
                $missing_seats[] = $i;
            }
        }
        
        // Assign missing seats based on layout preference
        if (!empty($missing_seats)) {
            if ($seat_layout === 'all_front' || (count($front_facing_seats) >= count($back_facing_seats))) {
                $front_facing_seats = array_merge($front_facing_seats, $missing_seats);
            } else {
                $back_facing_seats = array_merge($back_facing_seats, $missing_seats);
            }
        }
        
        // Sort the arrays
        sort($front_facing_seats);
        sort($back_facing_seats);
        
        return [
            'total_seats' => $total_seats,
            'front_facing_seats' => $front_facing_seats,
            'back_facing_seats' => $back_facing_seats,
        ];
    }
    
    /**
     * Generate seats based on layout and coach code
     */
    private function generate_seats_by_layout($total_seats, $layout, $coach_code = '') {
        $front_facing_seats = [];
        $back_facing_seats = [];
        
        switch ($layout) {
            case 'all_front':
                $front_facing_seats = range(1, $total_seats);
                break;
                
            case 'all_back':
                $back_facing_seats = range(1, $total_seats);
                break;
                
            case 'mixed':
            default:
                // Generate mixed layout based on coach code or default pattern
                $half = ceil($total_seats / 2);
                
                if (in_array(strtoupper($coach_code), ['CHA', 'SCHA'])) {
                    // CHA and SCHA have specific patterns
                    if (strtoupper($coach_code) === 'CHA') {
                        $front_facing_seats = range(1, $half);
                        $back_facing_seats = range($half + 1, $total_seats);
                    } else { // SCHA
                        $back_facing_seats = range(1, $half);
                        $front_facing_seats = range($half + 1, $total_seats);
                    }
                } elseif (strtoupper($coach_code) === 'UMA') {
                    // UMA is typically all front facing
                    $front_facing_seats = range(1, $total_seats);
                } elseif (strtoupper($coach_code) === 'JA') {
                    // JA is typically all back facing
                    $back_facing_seats = range(1, $total_seats);
                } else {
                    // Default mixed pattern
                    $front_facing_seats = range(1, $half);
                    $back_facing_seats = range($half + 1, $total_seats);
                }
                break;
        }
        
        return [
            'front_facing_seats' => $front_facing_seats,
            'back_facing_seats' => $back_facing_seats,
        ];
    }
    
    /**
     * Create comprehensive sample data
     */
    private function create_sample_data() {
        // Check if data already exists
        $existing_trains = get_posts([
            'post_type' => self::TRAIN,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);
        
        if (!empty($existing_trains)) {
            return; // Data already exists
        }
        
        // Create stations
        $dhaka_id = $this->create_station('DHAKA', 'DHA');
        $panchagarh_id = $this->create_station('PANCHAGARH', 'PCG');
        $chittagong_id = $this->create_station('CHITTAGONG', 'CTG');
        $sylhet_id = $this->create_station('SYLHET', 'SYL');
        
        // Create coaches with proper seat data
        $coaches_data = [
            ['code' => 'CHA', 'type' => 'S_CHAIR', 'total' => 60, 'layout' => 'mixed'],
            ['code' => 'SCHA', 'type' => 'S_CHAIR', 'total' => 60, 'layout' => 'mixed'],
            ['code' => 'UMA', 'type' => 'S_CHAIR', 'total' => 48, 'layout' => 'all_front'],
            ['code' => 'JA', 'type' => 'S_CHAIR', 'total' => 48, 'layout' => 'all_back'],
        ];
        
        $coach_ids = [];
        foreach ($coaches_data as $coach_data) {
            $coach_id = $this->create_coach($coach_data);
            if ($coach_id) {
                $coach_ids[] = $coach_id;
            }
        }
        
        // Create trains
        $trains_data = [
            [
                'name' => 'EKOTA EXPRESS',
                'number' => '705',
                'reverse' => '706',
                'origin' => $dhaka_id,
                'destination' => $panchagarh_id,
            ],
            [
                'name' => 'MOHANAGAR GODHULI',
                'number' => '707',
                'reverse' => '708',
                'origin' => $dhaka_id,
                'destination' => $chittagong_id,
            ],
            [
                'name' => 'SURMA MAIL',
                'number' => '709',
                'reverse' => '710',
                'origin' => $dhaka_id,
                'destination' => $sylhet_id,
            ],
        ];
        
        foreach ($trains_data as $train_data) {
            $this->create_train($train_data, $coach_ids);
        }
    }
    
    private function create_station($name, $code) {
        $station_id = wp_insert_post([
            'post_title' => $name,
            'post_type' => self::STATION,
            'post_status' => 'publish',
        ]);
        
        if ($station_id && !is_wp_error($station_id)) {
            update_field('station_code', $code, $station_id);
        }
        
        return $station_id;
    }
    
    private function create_coach($coach_data) {
        $coach_id = wp_insert_post([
            'post_title' => $coach_data['code'] . ' Coach',
            'post_type' => self::COACH,
            'post_status' => 'publish',
        ]);
        
        if ($coach_id && !is_wp_error($coach_id)) {
            update_field('coach_code', $coach_data['code'], $coach_id);
            update_field('coach_type', $coach_data['type'], $coach_id);
            update_field('total_seats', $coach_data['total'], $coach_id);
            update_field('seat_layout', $coach_data['layout'], $coach_id);
            
            // Generate and save seat assignments
            $seat_config = $this->generate_seats_by_layout(
                $coach_data['total'], 
                $coach_data['layout'], 
                $coach_data['code']
            );
            
            update_field('front_facing_seats', implode(',', $seat_config['front_facing_seats']), $coach_id);
            update_field('back_facing_seats', implode(',', $seat_config['back_facing_seats']), $coach_id);
        }
        
        return $coach_id;
    }
    
    private function create_train($train_data, $coach_ids) {
        $train_id = wp_insert_post([
            'post_title' => $train_data['name'],
            'post_type' => self::TRAIN,
            'post_status' => 'publish',
        ]);
        
        if ($train_id && !is_wp_error($train_id)) {
            update_field('train_number', $train_data['number'], $train_id);
            update_field('reverse_train_number', $train_data['reverse'], $train_id);
            update_field('origin_station', $train_data['origin'], $train_id);
            update_field('destination_station', $train_data['destination'], $train_id);
            
            // Assign coaches to train
            $train_coaches = [];
            foreach ($coach_ids as $index => $coach_id) {
                $train_coaches[] = [
                    'coach_reference' => $coach_id,
                    'position' => $index + 1,
                ];
            }
            update_field('train_coaches', $train_coaches, $train_id);
        }
        
        return $train_id;
    }
}

// Initialize the plugin
new BD_Railway_Seat_Guide();
