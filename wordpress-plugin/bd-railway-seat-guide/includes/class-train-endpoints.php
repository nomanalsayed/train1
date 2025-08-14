<?php
/**
 * Train API Endpoints
 * Handles all train-related REST API endpoints with enhanced seat direction logic
 */

if (!defined('ABSPATH')) exit;

class BD_Railway_Train_Endpoints {
    private $parent;
    
    public function __construct($parent) {
        $this->parent = $parent;
    }
    
    public function register_routes() {
        // List all trains
        register_rest_route($this->parent::API_NAMESPACE, '/trains', [
            'methods' => 'GET',
            'callback' => [$this, 'list_trains'],
            'permission_callback' => '__return_true',
            'args' => [
                'per_page' => ['default' => 20, 'sanitize_callback' => 'absint'],
                'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
                'search' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        
        register_rest_route($this->parent::API_NAMESPACE, '/trains/(?P<number>[0-9]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_train_by_number'],
            'permission_callback' => '__return_true',
            'args' => [
                'from' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'to' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'direction' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        
        // Get all coaches for a specific train
        register_rest_route($this->parent::API_NAMESPACE, '/trains/(?P<id>\d+)/coaches', [
            'methods' => 'GET',
            'callback' => [$this, 'get_train_coaches'],
            'permission_callback' => '__return_true',
        ]);
        
        // Get specific coach for a specific train
        register_rest_route($this->parent::API_NAMESPACE, '/trains/(?P<id>\d+)/coaches/(?P<coach_code>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_train_coach'],
            'permission_callback' => '__return_true',
            'args' => [
                'from' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'to' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        
        register_rest_route($this->parent::API_NAMESPACE, '/trains/(?P<number>[0-9]+)/coaches', [
            'methods' => 'GET',
            'callback' => [$this, 'get_train_coaches_by_number'],
            'permission_callback' => '__return_true',
            'args' => [
                'from' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'to' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        
        register_rest_route($this->parent::API_NAMESPACE, '/trains/(?P<number>[0-9]+)/coaches/(?P<coach_code>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_train_coach_by_number'],
            'permission_callback' => '__return_true',
            'args' => [
                'from' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'to' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        
        // Search trains by route
        register_rest_route($this->parent::API_NAMESPACE, '/trains/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search_trains'],
            'permission_callback' => '__return_true',
            'args' => [
                'from' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'to' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'query' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'limit' => ['default' => 10, 'sanitize_callback' => 'absint'],
            ],
        ]);
        
        // Get train seat directions
        register_rest_route($this->parent::API_NAMESPACE, '/trains/direction', [
            'methods' => 'GET',
            'callback' => [$this, 'get_train_directions'],
            'permission_callback' => '__return_true',
            'args' => [
                'train' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'coach' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'from' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'to' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }
    
    /**
     * List all trains with pagination and search
     */
    public function list_trains($request) {
        $per_page = intval($request['per_page']);
        $page = intval($request['page']);
        $search = trim($request['search']);
        
        $args = [
            'post_type' => $this->parent::TRAIN,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        $query = new WP_Query($args);
        $trains = [];
        
        foreach ($query->posts as $train) {
            $origin_id = intval(get_field('origin_station', $train->ID));
            $dest_id = intval(get_field('destination_station', $train->ID));
            
            $trains[] = [
                'id' => $train->ID,
                'name' => get_the_title($train->ID),
                'train_number' => get_field('train_number', $train->ID),
                'from_station' => $origin_id ? [
                    'id' => $origin_id,
                    'title' => get_the_title($origin_id),
                    'code' => get_field('station_code', $origin_id),
                ] : null,
                'to_station' => $dest_id ? [
                    'id' => $dest_id,
                    'title' => get_the_title($dest_id),
                    'code' => get_field('station_code', $dest_id),
                ] : null,
            ];
        }
        
        return rest_ensure_response([
            'trains' => $trains,
            'total' => intval($query->found_posts),
            'pages' => intval($query->max_num_pages),
            'page' => $page,
            'per_page' => $per_page,
        ]);
    }
    
    /**
     * Get single train by ID
     */
    public function get_train($request) {
        $train_id = intval($request['id']);
        $train = get_post($train_id);
        
        if (!$train || $train->post_type !== $this->parent::TRAIN) {
            return new WP_Error('train_not_found', 'Train not found', ['status' => 404]);
        }
        
        return rest_ensure_response($this->format_train_data($train));
    }
    
    /**
     * Get train by number with bidirectional logic and seat direction flipping
     */
    public function get_train_by_number($request) {
        $train_number = trim($request['number']);
        $from = trim($request['from']);
        $to = trim($request['to']);
        $direction = trim($request['direction']);
        
        error_log("BD Railway: Looking for train number: " . $train_number);
        
        $all_trains = get_posts([
            'post_type' => $this->parent::TRAIN,
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        
        $existing_numbers = [];
        foreach ($all_trains as $train) {
            $primary = get_field('train_number', $train->ID);
            $reverse = get_field('reverse_train_number', $train->ID);
            $existing_numbers[] = [
                'id' => $train->ID,
                'title' => $train->post_title,
                'primary' => $primary,
                'reverse' => $reverse
            ];
        }
        
        error_log("BD Railway: Available trains in database: " . json_encode($existing_numbers));
        
        $train = $this->find_train_by_number($train_number);
        
        if (!$train) {
            if (empty($all_trains)) {
                error_log("BD Railway: No trains found in database, creating sample data");
                $this->create_sample_train_data();
                
                // Force refresh the train search after creating sample data
                wp_cache_flush();
                $train = $this->find_train_by_number($train_number);
                
                if (!$train) {
                    error_log("BD Railway: Sample data creation failed or train still not found");
                    // Try one more time with direct database check
                    $this->debug_database_state();
                }
            }
            
            if (!$train) {
                $available_numbers = [];
                foreach ($existing_numbers as $train_info) {
                    if ($train_info['primary']) $available_numbers[] = $train_info['primary'];
                    if ($train_info['reverse']) $available_numbers[] = $train_info['reverse'];
                }
                
                if (empty($available_numbers)) {
                    error_log("BD Railway: Force creating sample data as no trains exist");
                    $this->force_create_sample_data();
                    
                    // Try one final time
                    $train = $this->find_train_by_number($train_number);
                    if ($train) {
                        error_log("BD Railway: Successfully found train after force creation");
                    } else {
                        error_log("BD Railway: Failed to create or find train even after force creation");
                    }
                }
                
                if (!$train) {
                    return new WP_Error('train_not_found', 
                        'Train number ' . $train_number . ' not found. Available train numbers: ' . implode(', ', $available_numbers), 
                        ['status' => 404, 'available_numbers' => $available_numbers]
                    );
                }
            }
        }
        
        error_log("BD Railway: Found train: " . $train->post_title . " (ID: " . $train->ID . ")");
        
        $direction_info = $this->determine_train_direction($train, $train_number, $from, $to, $direction);
        
        $train_data = $this->format_train_data($train);
        
        $train_data['train_number'] = $train_number;
        $train_data['primary_number'] = get_field('train_number', $train->ID);
        $train_data['reverse_number'] = get_field('reverse_train_number', $train->ID);
        $train_data['is_reverse_direction'] = $direction_info['is_reverse'];
        $train_data['direction_info'] = $direction_info;
        
        $train_data['coaches'] = $this->process_train_coaches($train->ID, $direction_info['is_reverse']);
        
        return rest_ensure_response($train_data);
    }
    
    /**
     * Get all coaches for a specific train - more resource efficient
     */
    public function get_train_coaches($request) {
        $train_id = intval($request['id']);
        $train = get_post($train_id);
        
        if (!$train || $train->post_type !== $this->parent::TRAIN) {
            return new WP_Error('train_not_found', 'Train not found', ['status' => 404]);
        }
        
        $coaches = $this->process_train_coaches($train_id, false);
        
        return rest_ensure_response([
            'coaches' => $coaches,
            'train_id' => $train_id,
            'train_name' => get_the_title($train_id),
            'train_number' => get_field('train_number', $train_id),
            'total' => count($coaches),
        ]);
    }
    
    /**
     * Get specific coach for a specific train with seat directions
     */
    public function get_train_coach($request) {
        $train_id = intval($request['id']);
        $coach_code = strtoupper(trim($request['coach_code']));
        $from = trim($request['from']);
        $to = trim($request['to']);
        
        $train = get_post($train_id);
        
        if (!$train || $train->post_type !== $this->parent::TRAIN) {
            return new WP_Error('train_not_found', 'Train not found', ['status' => 404]);
        }
        
        // Determine direction based on route
        $should_reverse = $this->should_reverse_direction($train, $from, $to);
        $coaches = $this->process_train_coaches($train_id, $should_reverse);
        
        // Find the specific coach
        $target_coach = null;
        foreach ($coaches as $coach) {
            if (strtoupper($coach['code']) === $coach_code) {
                $target_coach = $coach;
                break;
            }
        }
        
        if (!$target_coach) {
            $available_coaches = array_map(function($c) { return $c['code']; }, $coaches);
            return new WP_Error('coach_not_found', 
                'Coach not found for this train. Available coaches: ' . implode(', ', $available_coaches), 
                ['status' => 404]
            );
        }
        
        // Add additional metadata
        $target_coach['train_id'] = $train_id;
        $target_coach['train_name'] = get_the_title($train_id);
        $target_coach['train_number'] = get_field('train_number', $train_id);
        $target_coach['direction'] = $should_reverse ? 'reverse' : 'forward';
        $target_coach['front_facing_count'] = count($target_coach['front_facing_seats']);
        $target_coach['back_facing_count'] = count($target_coach['back_facing_seats']);
        
        return rest_ensure_response($target_coach);
    }
    
    /**
     * Get all coaches for a train by number with direction support
     */
    public function get_train_coaches_by_number($request) {
        $train_number = trim($request['number']);
        $from = trim($request['from']);
        $to = trim($request['to']);
        
        $train = $this->find_train_by_number($train_number);
        
        if (!$train) {
            return new WP_Error('train_not_found', 'Train not found', ['status' => 404]);
        }
        
        $direction_info = $this->determine_train_direction($train, $train_number, $from, $to);
        $coaches = $this->process_train_coaches($train->ID, $direction_info['is_reverse']);
        
        return rest_ensure_response([
            'coaches' => $coaches,
            'train_number' => $train_number,
            'train_name' => get_the_title($train->ID),
            'is_reverse_direction' => $direction_info['is_reverse'],
            'direction_info' => $direction_info,
            'total' => count($coaches),
        ]);
    }

    /**
     * Get specific coach for a train by number with direction support
     */
    public function get_train_coach_by_number($request) {
        $train_number = trim($request['number']);
        $coach_code = strtoupper(trim($request['coach_code']));
        $from = trim($request['from']);
        $to = trim($request['to']);
        
        $train = $this->find_train_by_number($train_number);
        
        if (!$train) {
            return new WP_Error('train_not_found', 'Train not found', ['status' => 404]);
        }
        
        $direction_info = $this->determine_train_direction($train, $train_number, $from, $to);
        $coaches = $this->process_train_coaches($train->ID, $direction_info['is_reverse']);
        
        // Find the specific coach
        $target_coach = null;
        foreach ($coaches as $coach) {
            if (strtoupper($coach['code']) === $coach_code) {
                $target_coach = $coach;
                break;
            }
        }
        
        if (!$target_coach) {
            $available_coaches = array_map(function($c) { return $c['code']; }, $coaches);
            return new WP_Error('coach_not_found', 
                'Coach not found for this train. Available coaches: ' . implode(', ', $available_coaches), 
                ['status' => 404]
            );
        }
        
        // Add additional metadata
        $target_coach['train_number'] = $train_number;
        $target_coach['train_name'] = get_the_title($train->ID);
        $target_coach['is_reverse_direction'] = $direction_info['is_reverse'];
        $target_coach['direction_info'] = $direction_info;
        $target_coach['front_facing_count'] = count($target_coach['front_facing_seats']);
        $target_coach['back_facing_count'] = count($target_coach['back_facing_seats']);
        
        return rest_ensure_response($target_coach);
    }

    /**
     * Get train by number with seat directions
     */
    public function get_train_directions($request) {
        $train_number = trim($request['train']);
        $coach_filter = trim($request['coach']);
        $from = trim($request['from']);
        $to = trim($request['to']);
        
        // Find train
        $trains = get_posts([
            'post_type' => $this->parent::TRAIN,
            'meta_query' => [
                [
                    'key' => 'train_number',
                    'value' => $train_number,
                    'compare' => '=',
                ],
            ],
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);
        
        if (empty($trains)) {
            return new WP_Error('train_not_found', 'Train not found', ['status' => 404]);
        }
        
        $train = $trains[0];
        $should_reverse = $this->should_reverse_direction($train, $from, $to);
        $coaches = $this->process_train_coaches($train->ID, $should_reverse);
        
        // Filter by coach if specified
        if (!empty($coach_filter)) {
            $coaches = array_filter($coaches, function($coach) use ($coach_filter) {
                return stripos($coach['code'], $coach_filter) !== false;
            });
            $coaches = array_values($coaches);
        }
        
        return rest_ensure_response([
            'directions' => $coaches,
            'train_number' => $train_number,
            'direction' => $should_reverse ? 'reverse' : 'forward',
        ]);
    }
    
    /**
     * Search trains by route (from/to stations)
     */
    public function search_trains($request) {
        $from = trim($request['from']);
        $to = trim($request['to']);
        $query = trim($request['query']);
        $limit = intval($request['limit']);
        
        $matching_trains = [];
        
        // Get all trains
        $trains = get_posts([
            'post_type' => $this->parent::TRAIN,
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        
        foreach ($trains as $train) {
            $train_data = $this->format_train_data($train);
            $should_include = false;
            
            // Route-based search
            if (!empty($from) && !empty($to)) {
                $match = $this->check_route_match($train, $from, $to);
                if ($match) {
                    $train_data['match_info'] = $match;
                    $should_include = true;
                }
            }
            
            // Query-based search (train name or number)
            if (!empty($query)) {
                $train_name = get_the_title($train->ID);
                $train_number = get_field('train_number', $train->ID);
                $reverse_number = get_field('reverse_train_number', $train->ID);
                
                if (stripos($train_name, $query) !== false || 
                    stripos($train_number, $query) !== false ||
                    ($reverse_number && stripos($reverse_number, $query) !== false)) {
                    $should_include = true;
                }
            }
            
            // If no specific search criteria, include all
            if (empty($from) && empty($to) && empty($query)) {
                $should_include = true;
            }
            
            if ($should_include) {
                $train_data['number'] = get_field('train_number', $train->ID);
                $train_data['reverse_number'] = get_field('reverse_train_number', $train->ID);
                $matching_trains[] = $train_data;
                
                if (count($matching_trains) >= $limit) {
                    break;
                }
            }
        }
        
        return rest_ensure_response([
            'trains' => $matching_trains,
            'total' => count($matching_trains),
            'search_params' => [
                'from' => $from,
                'to' => $to,
                'query' => $query,
            ],
        ]);
    }
    
    /**
     * Format train data for API response
     */
    private function format_train_data($train) {
        $origin_id = intval(get_field('origin_station', $train->ID));
        $dest_id = intval(get_field('destination_station', $train->ID));
        
        return [
            'id' => $train->ID,
            'name' => get_the_title($train->ID),
            'train_name' => get_the_title($train->ID),
            'train_number' => get_field('train_number', $train->ID),
            'from_station' => $origin_id ? [
                'id' => $origin_id,
                'title' => get_the_title($origin_id),
                'code' => get_field('station_code', $origin_id),
            ] : null,
            'to_station' => $dest_id ? [
                'id' => $dest_id,
                'title' => get_the_title($dest_id),
                'code' => get_field('station_code', $dest_id),
            ] : null,
            'intermediate_stations' => $this->get_intermediate_stations($train->ID),
        ];
    }
    
    /**
     * Get intermediate stations for a train
     */
    private function get_intermediate_stations($train_id) {
        $stations = get_field('intermediate_stations', $train_id);
        if (!$stations || !is_array($stations)) {
            return [];
        }
        
        $formatted_stations = [];
        foreach ($stations as $station_data) {
            $station_id = intval($station_data['station']);
            if ($station_id) {
                $formatted_stations[] = [
                    'id' => $station_id,
                    'title' => get_the_title($station_id),
                    'code' => get_field('station_code', $station_id),
                    'order' => intval($station_data['order']),
                ];
            }
        }
        
        return $formatted_stations;
    }
    
    /**
     * Check if train route matches from/to search
     */
    private function check_route_match($train, $from, $to) {
        $origin_id = intval(get_field('origin_station', $train->ID));
        $dest_id = intval(get_field('destination_station', $train->ID));
        $intermediate_stations = $this->get_intermediate_stations($train->ID);
        
        // Build complete station list
        $all_stations = [];
        
        if ($origin_id) {
            $all_stations[] = [
                'id' => $origin_id,
                'title' => get_the_title($origin_id),
                'code' => get_field('station_code', $origin_id),
                'order' => 0,
            ];
        }
        
        foreach ($intermediate_stations as $station) {
            $all_stations[] = $station;
        }
        
        if ($dest_id) {
            $all_stations[] = [
                'id' => $dest_id,
                'title' => get_the_title($dest_id),
                'code' => get_field('station_code', $dest_id),
                'order' => 999,
            ];
        }
        
        // Sort by order
        usort($all_stations, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        // Find matching stations
        $from_matches = [];
        $to_matches = [];
        
        foreach ($all_stations as $index => $station) {
            if ($this->station_matches($station, $from)) {
                $from_matches[] = ['index' => $index, 'station' => $station];
            }
            if ($this->station_matches($station, $to)) {
                $to_matches[] = ['index' => $index, 'station' => $station];
            }
        }
        
        // Check forward direction
        foreach ($from_matches as $from_match) {
            foreach ($to_matches as $to_match) {
                if ($from_match['index'] < $to_match['index']) {
                    return [
                        'direction' => 'forward',
                        'from_station' => $from_match['station'],
                        'to_station' => $to_match['station'],
                    ];
                }
            }
        }
        
        // Check reverse direction
        foreach ($from_matches as $from_match) {
            foreach ($to_matches as $to_match) {
                if ($from_match['index'] > $to_match['index']) {
                    return [
                        'direction' => 'reverse',
                        'from_station' => $from_match['station'],
                        'to_station' => $to_match['station'],
                    ];
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if station matches search term
     */
    private function station_matches($station, $search_term) {
        $search_lower = strtolower(trim($search_term));
        $title_lower = strtolower($station['title']);
        $code_lower = strtolower($station['code']);
        
        return strpos($title_lower, $search_lower) !== false || 
               strpos($code_lower, $search_lower) !== false ||
               strpos($search_lower, $title_lower) !== false ||
               strpos($search_lower, $code_lower) !== false;
    }
    
    /**
     * Determine if direction should be reversed
     */
    private function should_reverse_direction($train, $from, $to, $direction_override = null) {
        // If direction is explicitly specified
        if ($direction_override === 'reverse') {
            return true;
        }
        if ($direction_override === 'forward') {
            return false;
        }
        
        // If from/to stations are provided, check route
        if (!empty($from) && !empty($to)) {
            $match = $this->check_route_match($train, $from, $to);
            if ($match && $match['direction'] === 'reverse') {
                return true;
            }
        }
        
        // Default: check train number (even = reverse, odd = forward)
        $train_number = get_field('train_number', $train->ID);
        if ($train_number && is_numeric($train_number)) {
            return (intval($train_number) % 2) === 0;
        }
        
        return false;
    }
    
    /**
     * Process train coaches with bidirectional seat flipping
     */
    private function process_train_coaches($train_id, $should_reverse = false) {
        $train_coaches = get_field('train_coaches', $train_id);
        if (!$train_coaches || !is_array($train_coaches)) {
            // Return default coaches if no coaches configured
            return $this->get_default_coaches($should_reverse);
        }
        
        $processed_coaches = [];
        
        foreach ($train_coaches as $coach_data) {
            $coach_id = intval($coach_data['coach_reference']);
            if (!$coach_id) continue;
            
            $coach_code = get_field('coach_code', $coach_id);
            $seat_config = $this->parent->calculate_seat_directions($coach_id, $coach_data);
            
            $coach_info = [
                'id' => $coach_id,
                'code' => $coach_code,
                'type' => get_field('coach_type', $coach_id),
                'position' => intval($coach_data['position']),
                'total_seats' => $seat_config['total_seats'],
                'front_facing_seats' => $seat_config['front_facing_seats'],
                'back_facing_seats' => $seat_config['back_facing_seats'],
                'direction_flipped' => $should_reverse,
            ];
            
            if ($should_reverse) {
                $original_front = $coach_info['front_facing_seats'];
                $original_back = $coach_info['back_facing_seats'];
                
                $coach_info['front_facing_seats'] = $original_back;
                $coach_info['back_facing_seats'] = $original_front;
            }
            
            $processed_coaches[] = $coach_info;
        }
        
        // Sort by position
        usort($processed_coaches, function($a, $b) {
            return $a['position'] - $b['position'];
        });
        
        return $processed_coaches;
    }

    /**
     * Get default coaches with bidirectional support
     */
    private function get_default_coaches($should_reverse = false) {
        // Get available coaches from database
        $coaches = get_posts([
            'post_type' => $this->parent::COACH,
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        
        $default_coaches = [];
        
        foreach ($coaches as $coach) {
            $coach_code = get_field('coach_code', $coach->ID);
            $seat_config = $this->parent->calculate_seat_directions($coach->ID);
            
            $coach_info = [
                'id' => $coach->ID,
                'code' => $coach_code,
                'type' => get_field('coach_type', $coach->ID),
                'position' => 1,
                'total_seats' => $seat_config['total_seats'],
                'front_facing_seats' => $seat_config['front_facing_seats'],
                'back_facing_seats' => $seat_config['back_facing_seats'],
                'direction_flipped' => $should_reverse,
            ];
            
            if ($should_reverse) {
                $original_front = $coach_info['front_facing_seats'];
                $original_back = $coach_info['back_facing_seats'];
                
                $coach_info['front_facing_seats'] = $original_back;
                $coach_info['back_facing_seats'] = $original_front;
            }
            
            $default_coaches[] = $coach_info;
        }
        
        return $default_coaches;
    }

    /**
     * Find train by number supporting both primary and reverse numbers
     */
    private function find_train_by_number($train_number) {
        // First try primary train number
        $trains = get_posts([
            'post_type' => $this->parent::TRAIN,
            'meta_query' => [
                [
                    'key' => 'train_number',
                    'value' => $train_number,
                    'compare' => '=',
                ],
            ],
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);
        
        if (!empty($trains)) {
            return $trains[0];
        }
        
        // Try reverse train number
        $trains = get_posts([
            'post_type' => $this->parent::TRAIN,
            'meta_query' => [
                [
                    'key' => 'reverse_train_number',
                    'value' => $train_number,
                    'compare' => '=',
                ],
            ],
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);
        
        if (!empty($trains)) {
            return $trains[0];
        }
        
        return null;
    }

    /**
     * Determine train direction and if seats should be flipped
     */
    private function determine_train_direction($train, $requested_number, $from = '', $to = '', $direction_override = null) {
        $primary_number = get_field('train_number', $train->ID);
        $reverse_number = get_field('reverse_train_number', $train->ID);
        
        $is_reverse = false;
        $reason = 'default_forward';
        
        // Check if requested number is the reverse number
        if ($reverse_number && $requested_number === $reverse_number) {
            $is_reverse = true;
            $reason = 'reverse_number_requested';
        }
        
        // Override with explicit direction
        if ($direction_override === 'reverse') {
            $is_reverse = true;
            $reason = 'explicit_reverse';
        } elseif ($direction_override === 'forward') {
            $is_reverse = false;
            $reason = 'explicit_forward';
        }
        
        // Check route direction if from/to provided
        if (!empty($from) && !empty($to)) {
            $route_match = $this->check_route_match($train, $from, $to);
            if ($route_match && $route_match['direction'] === 'reverse') {
                $is_reverse = true;
                $reason = 'route_analysis';
            }
        }
        
        return [
            'is_reverse' => $is_reverse,
            'reason' => $reason,
            'requested_number' => $requested_number,
            'primary_number' => $primary_number,
            'reverse_number' => $reverse_number,
            'from_station' => $from,
            'to_station' => $to,
        ];
    }

    /**
     * Create sample train data for testing
     */
    private function create_sample_train_data() {
        error_log("BD Railway: Starting sample data creation");
        
        // Create sample stations first
        $dhaka_id = $this->create_sample_station('DHAKA', 'DHA');
        $panchagarh_id = $this->create_sample_station('PANCHAGARH', 'PCG');
        
        if (!$dhaka_id || !$panchagarh_id) {
            error_log("BD Railway: Failed to create sample stations");
            return false;
        }
        
        error_log("BD Railway: Created stations - Dhaka: $dhaka_id, Panchagarh: $panchagarh_id");
        
        // Create sample train
        $train_id = wp_insert_post([
            'post_title' => 'EKOTA EXPRESS',
            'post_type' => $this->parent::TRAIN,
            'post_status' => 'publish',
        ]);
        
        if (!$train_id || is_wp_error($train_id)) {
            error_log("BD Railway: Failed to create sample train: " . print_r($train_id, true));
            return false;
        }
        
        error_log("BD Railway: Created train with ID: $train_id");
        
        // Set train fields with error checking
        $field_updates = [
            'train_number' => '705',
            'reverse_train_number' => '706',
            'origin_station' => $dhaka_id,
            'destination_station' => $panchagarh_id,
        ];
        
        foreach ($field_updates as $field => $value) {
            $result = update_field($field, $value, $train_id);
            error_log("BD Railway: Updated field '$field' with value '$value': " . ($result ? 'SUCCESS' : 'FAILED'));
        }
        
        // Create sample coaches
        $this->create_sample_coaches_for_train($train_id);
        
        // Verify the data was saved
        $saved_train_number = get_field('train_number', $train_id);
        $saved_reverse_number = get_field('reverse_train_number', $train_id);
        
        error_log("BD Railway: Verification - Train number: '$saved_train_number', Reverse: '$saved_reverse_number'");
        
        return $train_id;
    }

    /**
     * Force create sample data with direct database operations
     */
    private function force_create_sample_data() {
        error_log("BD Railway: Force creating sample data with direct database operations");
        
        // Create train post
        $train_id = wp_insert_post([
            'post_title' => 'EKOTA EXPRESS',
            'post_type' => $this->parent::TRAIN,
            'post_status' => 'publish',
        ]);
        
        if (!$train_id || is_wp_error($train_id)) {
            error_log("BD Railway: Failed to force create train");
            return false;
        }
        
        // Use direct meta updates as fallback
        add_post_meta($train_id, 'train_number', '705', true);
        add_post_meta($train_id, 'reverse_train_number', '706', true);
        add_post_meta($train_id, '_train_number', 'field_train_number', true);
        add_post_meta($train_id, '_reverse_train_number', 'field_reverse_train_number', true);
        
        error_log("BD Railway: Force created train with direct meta updates");
        
        return $train_id;
    }

    /**
     * Debug database state
     */
    private function debug_database_state() {
        global $wpdb;
        
        // Check if ACF is active
        $acf_active = function_exists('get_field');
        error_log("BD Railway: ACF Active: " . ($acf_active ? 'YES' : 'NO'));
        
        // Check train posts
        $train_posts = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'bd_train' AND post_status = 'publish'");
        error_log("BD Railway: Train posts in database: " . json_encode($train_posts));
        
        // Check meta data
        if (!empty($train_posts)) {
            foreach ($train_posts as $post) {
                $meta = $wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d", $post->ID));
                error_log("BD Railway: Meta for train {$post->ID}: " . json_encode($meta));
            }
        }
        
        // Check if train number meta exists
        $train_number_meta = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'train_number'");
        error_log("BD Railway: Train number meta entries: " . json_encode($train_number_meta));
    }

    /**
     * Create sample station
     */
    private function create_sample_station($name, $code) {
        $existing = get_posts([
            'post_type' => $this->parent::STATION,
            'title' => $name,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);
        
        if (!empty($existing)) {
            return $existing[0]->ID;
        }
        
        $station_id = wp_insert_post([
            'post_title' => $name,
            'post_type' => $this->parent::STATION,
            'post_status' => 'publish',
        ]);
        
        if ($station_id && !is_wp_error($station_id)) {
            update_field('station_code', $code, $station_id);
            error_log("BD Railway: Created sample station: " . $name . " (" . $code . ")");
        }
        
        return $station_id;
    }

    /**
     * Create sample coaches for train
     */
    private function create_sample_coaches_for_train($train_id) {
        // Create UMA coach
        $uma_coach_id = wp_insert_post([
            'post_title' => 'UMA Coach',
            'post_type' => $this->parent::COACH,
            'post_status' => 'publish',
        ]);
        
        if ($uma_coach_id && !is_wp_error($uma_coach_id)) {
            update_field('coach_code', 'UMA', $uma_coach_id);
            update_field('coach_type', 'S_CHAIR', $uma_coach_id);
            update_field('total_seats', 60, $uma_coach_id);
            update_field('front_facing_seats', range(1, 30), $uma_coach_id);
            update_field('back_facing_seats', range(31, 60), $uma_coach_id);
        }
        
        // Create CHA coach
        $cha_coach_id = wp_insert_post([
            'post_title' => 'CHA Coach',
            'post_type' => $this->parent::COACH,
            'post_status' => 'publish',
        ]);
        
        if ($cha_coach_id && !is_wp_error($cha_coach_id)) {
            update_field('coach_code', 'CHA', $cha_coach_id);
            update_field('coach_type', 'S_CHAIR', $cha_coach_id);
            update_field('total_seats', 60, $cha_coach_id);
            update_field('front_facing_seats', range(31, 60), $cha_coach_id);
            update_field('back_facing_seats', range(1, 30), $cha_coach_id);
        }
        
        // Link coaches to train
        $train_coaches = [
            [
                'coach_reference' => $uma_coach_id,
                'position' => 1,
            ],
            [
                'coach_reference' => $cha_coach_id,
                'position' => 2,
            ]
        ];
        
        update_field('train_coaches', $train_coaches, $train_id);
        
        error_log("BD Railway: Created sample coaches UMA and CHA for train ID: " . $train_id);
    }
}
