<?php
/**
 * Coach API Endpoints
 * Handles all coach-related REST API endpoints with enhanced seat configuration
 */

if (!defined('ABSPATH')) exit;

class BD_Railway_Coach_Endpoints {
    private $parent;
    
    public function __construct($parent) {
        $this->parent = $parent;
    }
    
    public function register_routes() {
        // List all coaches
        register_rest_route($this->parent::API_NAMESPACE, '/coaches', [
            'methods' => 'GET',
            'callback' => [$this, 'list_coaches'],
            'permission_callback' => '__return_true',
            'args' => [
                'per_page' => ['default' => 100, 'sanitize_callback' => 'absint'],
                'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
                'search' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'code' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'type' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        
        // Get single coach by ID
        register_rest_route($this->parent::API_NAMESPACE, '/coaches/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_coach'],
            'permission_callback' => '__return_true',
        ]);
        
        // Get coach by code
        register_rest_route($this->parent::API_NAMESPACE, '/coaches/by-code/(?P<code>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_coach_by_code'],
            'permission_callback' => '__return_true',
        ]);
        
        // Get coach types
        register_rest_route($this->parent::API_NAMESPACE, '/coaches/types', [
            'methods' => 'GET',
            'callback' => [$this, 'get_coach_types'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    /**
     * List all coaches with pagination and filtering
     */
    public function list_coaches($request) {
        $per_page = intval($request['per_page']);
        $page = intval($request['page']);
        $search = trim($request['search']);
        $code = trim($request['code']);
        $type = trim($request['type']);
        
        $args = [
            'post_type' => $this->parent::COACH,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        
        $meta_query = [];
        
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        if (!empty($code)) {
            $meta_query[] = [
                'key' => 'coach_code',
                'value' => $code,
                'compare' => 'LIKE',
            ];
        }
        
        if (!empty($type)) {
            $meta_query[] = [
                'key' => 'coach_type',
                'value' => $type,
                'compare' => '=',
            ];
        }
        
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
            if (count($meta_query) > 1) {
                $args['meta_query']['relation'] = 'AND';
            }
        }
        
        $query = new WP_Query($args);
        $coaches = [];
        
        foreach ($query->posts as $coach) {
            $coaches[] = $this->format_basic_coach_data($coach);
        }
        
        return rest_ensure_response([
            'coaches' => $coaches,
            'total' => intval($query->found_posts),
            'pages' => intval($query->max_num_pages),
            'page' => $page,
            'per_page' => $per_page,
        ]);
    }
    
    /**
     * Get single coach by ID
     */
    public function get_coach($request) {
        $coach_id = intval($request['id']);
        $coach = get_post($coach_id);
        
        if (!$coach || $coach->post_type !== $this->parent::COACH) {
            return new WP_Error('coach_not_found', 'Coach not found', ['status' => 404]);
        }
        
        $coach_data = $this->format_detailed_coach_data($coach);
        
        // Add seat layout visualization
        $coach_data['seat_layout'] = $this->generate_seat_layout($coach_data);
        
        return rest_ensure_response($coach_data);
    }
    
    /**
     * Get coach by code
     */
    public function get_coach_by_code($request) {
        $code = strtoupper(trim($request['code']));
        
        $coaches = get_posts([
            'post_type' => $this->parent::COACH,
            'meta_query' => [
                [
                    'key' => 'coach_code',
                    'value' => $code,
                    'compare' => '=',
                ],
            ],
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);
        
        if (empty($coaches)) {
            return new WP_Error('coach_not_found', 'Coach not found', ['status' => 404]);
        }
        
        $coach_data = $this->format_detailed_coach_data($coaches[0]);
        $coach_data['seat_layout'] = $this->generate_seat_layout($coach_data);
        
        return rest_ensure_response($coach_data);
    }
    
    /**
     * Get available coach types
     */
    public function get_coach_types($request) {
        $coach_types = [
            'AC_B' => [
                'code' => 'AC_B',
                'name' => __('AC Berth', 'bd-railway'),
                'description' => __('Air-conditioned berth coach', 'bd-railway'),
            ],
            'AC_S' => [
                'code' => 'AC_S',
                'name' => __('AC Seat', 'bd-railway'),
                'description' => __('Air-conditioned seat coach', 'bd-railway'),
            ],
            'F_BERTH' => [
                'code' => 'F_BERTH',
                'name' => __('First Class Berth', 'bd-railway'),
                'description' => __('First class berth coach', 'bd-railway'),
            ],
            'F_SEAT' => [
                'code' => 'F_SEAT',
                'name' => __('First Class Seat', 'bd-railway'),
                'description' => __('First class seat coach', 'bd-railway'),
            ],
            'S_CHAIR' => [
                'code' => 'S_CHAIR',
                'name' => __('Shovan Chair', 'bd-railway'),
                'description' => __('Shovan chair coach', 'bd-railway'),
            ],
            'SHULOV' => [
                'code' => 'SHULOV',
                'name' => __('Shulov', 'bd-railway'),
                'description' => __('Shulov coach', 'bd-railway'),
            ],
            'CHA' => [
                'code' => 'CHA',
                'name' => __('Chair Car', 'bd-railway'),
                'description' => __('Chair car coach', 'bd-railway'),
            ],
        ];
        
        return rest_ensure_response([
            'types' => array_values($coach_types),
        ]);
    }
    
    /**
     * Format basic coach data for API response (with seat information for consistency)
     */
    private function format_basic_coach_data($coach) {
        $seat_config = $this->parent->calculate_seat_directions($coach->ID);
        
        // Ensure we have valid seat arrays
        $front_facing_seats = $seat_config['front_facing_seats'] ?: [];
        $back_facing_seats = $seat_config['back_facing_seats'] ?: [];
        
        // If both arrays are empty, generate default based on coach code
        if (empty($front_facing_seats) && empty($back_facing_seats)) {
            $coach_code = get_field('coach_code', $coach->ID);
            $total_seats = $seat_config['total_seats'] ?: 60;
            
            if (strtoupper($coach_code) === 'UMA') {
                $front_facing_seats = range(1, $total_seats);
            } elseif (strtoupper($coach_code) === 'JA') {
                $back_facing_seats = range(1, $total_seats);
            } else {
                // Mixed layout for CHA, SCHA, etc.
                $half = ceil($total_seats / 2);
                if (strtoupper($coach_code) === 'CHA') {
                    $front_facing_seats = range(1, $half);
                    $back_facing_seats = range($half + 1, $total_seats);
                } else {
                    $back_facing_seats = range(1, $half);
                    $front_facing_seats = range($half + 1, $total_seats);
                }
            }
        }
        
        return [
            'id' => $coach->ID,
            'code' => get_field('coach_code', $coach->ID) ?: get_the_title($coach->ID),
            'type' => get_field('coach_type', $coach->ID) ?: 'S_CHAIR',
            'type_name' => $this->get_coach_type_name(get_field('coach_type', $coach->ID)),
            'total_seats' => $seat_config['total_seats'],
            'front_facing_seats' => $front_facing_seats,
            'back_facing_seats' => $back_facing_seats,
            'front_facing_count' => count($front_facing_seats),
            'back_facing_count' => count($back_facing_seats),
            'slug' => $coach->post_name,
        ];
    }
    
    /**
     * Format detailed coach data for API response (with seat information)
     */
    private function format_detailed_coach_data($coach) {
        $seat_config = $this->parent->calculate_seat_directions($coach->ID);
        
        // Ensure we have valid seat arrays
        $front_facing_seats = $seat_config['front_facing_seats'] ?: [];
        $back_facing_seats = $seat_config['back_facing_seats'] ?: [];
        
        // If both arrays are empty, generate default based on coach code
        if (empty($front_facing_seats) && empty($back_facing_seats)) {
            $coach_code = get_field('coach_code', $coach->ID);
            $total_seats = $seat_config['total_seats'] ?: 60;
            
            if (strtoupper($coach_code) === 'UMA') {
                $front_facing_seats = range(1, $total_seats);
            } elseif (strtoupper($coach_code) === 'JA') {
                $back_facing_seats = range(1, $total_seats);
            } else {
                // Mixed layout for CHA, SCHA, etc.
                $half = ceil($total_seats / 2);
                if (strtoupper($coach_code) === 'CHA') {
                    $front_facing_seats = range(1, $half);
                    $back_facing_seats = range($half + 1, $total_seats);
                } else {
                    $back_facing_seats = range(1, $half);
                    $front_facing_seats = range($half + 1, $total_seats);
                }
            }
        }
        
        return [
            'id' => $coach->ID,
            'code' => get_field('coach_code', $coach->ID) ?: get_the_title($coach->ID),
            'type' => get_field('coach_type', $coach->ID) ?: 'S_CHAIR',
            'type_name' => $this->get_coach_type_name(get_field('coach_type', $coach->ID)),
            'total_seats' => $seat_config['total_seats'],
            'front_facing_seats' => $front_facing_seats,
            'back_facing_seats' => $back_facing_seats,
            'front_facing_count' => count($front_facing_seats),
            'back_facing_count' => count($back_facing_seats),
            'slug' => $coach->post_name,
        ];
    }
    
    /**
     * Get human-readable coach type name
     */
    private function get_coach_type_name($type_code) {
        $type_names = [
            'AC_B' => __('AC Berth', 'bd-railway'),
            'AC_S' => __('AC Seat', 'bd-railway'),
            'F_BERTH' => __('First Class Berth', 'bd-railway'),
            'F_SEAT' => __('First Class Seat', 'bd-railway'),
            'S_CHAIR' => __('Shovan Chair', 'bd-railway'),
            'SHULOV' => __('Shulov', 'bd-railway'),
            'CHA' => __('Chair Car', 'bd-railway'),
        ];
        
        return $type_names[$type_code] ?? $type_code;
    }
    
    /**
     * Generate seat layout visualization
     */
    private function generate_seat_layout($coach_data) {
        $total_seats = $coach_data['total_seats'];
        $front_seats = $coach_data['front_facing_seats'];
        $back_seats = $coach_data['back_facing_seats'];
        
        $layout = [];
        
        // Determine seats per row based on coach type
        $seats_per_row = $this->get_seats_per_row($coach_data['type']);
        $rows = ceil($total_seats / $seats_per_row);
        
        for ($row = 1; $row <= $rows; $row++) {
            $row_seats = [];
            $start_seat = (($row - 1) * $seats_per_row) + 1;
            $end_seat = min($start_seat + $seats_per_row - 1, $total_seats);
            
            for ($seat = $start_seat; $seat <= $end_seat; $seat++) {
                $direction = 'unknown';
                if (in_array($seat, $front_seats)) {
                    $direction = 'forward';
                } elseif (in_array($seat, $back_seats)) {
                    $direction = 'backward';
                }
                
                $row_seats[] = [
                    'number' => $seat,
                    'direction' => $direction,
                    'position' => $seat - $start_seat + 1,
                ];
            }
            
            $layout[] = [
                'row' => $row,
                'seats' => $row_seats,
            ];
        }
        
        return $layout;
    }
    
    /**
     * Get seats per row based on coach type
     */
    private function get_seats_per_row($coach_type) {
        $seats_per_row_map = [
            'AC_B' => 4,
            'AC_S' => 4,
            'F_BERTH' => 4,
            'F_SEAT' => 4,
            'S_CHAIR' => 5,
            'SHULOV' => 5,
            'CHA' => 5,
        ];
        
        return $seats_per_row_map[$coach_type] ?? 5;
    }
}
