<?php
/**
 * Train API Endpoints
 * Handles all train-related REST API endpoints
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
                'per_page' => ['default' => 50, 'sanitize_callback' => 'absint'],
                'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
                'search' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        // Get single train by ID
        register_rest_route($this->parent::API_NAMESPACE, '/trains/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_train'],
            'permission_callback' => '__return_true',
        ]);

        // Search trains by route or name
        register_rest_route($this->parent::API_NAMESPACE, '/trains/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search_trains'],
            'permission_callback' => '__return_true',
            'args' => [
                'from' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'to' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'query' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        // Get train coaches
        register_rest_route($this->parent::API_NAMESPACE, '/trains/(?P<train_id>\d+)/coaches', [
            'methods' => 'GET',
            'callback' => [$this, 'get_train_coaches'],
            'permission_callback' => '__return_true',
        ]);

        // Get specific train coach by code
        register_rest_route($this->parent::API_NAMESPACE, '/trains/(?P<train_id>\d+)/coaches/(?P<coach_code>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_train_coach_by_code'],
            'permission_callback' => '__return_true',
        ]);

        // Get train seats (alias for coaches with seat data)
        register_rest_route($this->parent::API_NAMESPACE, '/trains/(?P<train_id>\d+)/seats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_train_seats'],
            'permission_callback' => '__return_true',
            'args' => [
                'from' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'to' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'coach' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        // Search trains by route code
        register_rest_route($this->parent::API_NAMESPACE, '/trains/route/(?P<route_code>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_train_by_route_code'],
            'permission_callback' => '__return_true',
            'args' => [
                'direction' => ['default' => 'forward', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    /**
     * List all trains
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
            $trains[] = $this->format_train_data($train);
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

        return rest_ensure_response($this->format_train_data($train, true));
    }

    /**
     * Search trains by route or name/number
     */
    public function search_trains($request) {
        $from = trim($request['from']);
        $to = trim($request['to']);
        $query = trim($request['query']);

        $args = [
            'post_type' => $this->parent::TRAIN,
            'posts_per_page' => 50,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        $meta_query = [];

        // Search by route (from/to stations)
        if (!empty($from) && !empty($to)) {
            // Try to find by route codes first
            $route_code_forward = strtoupper($from) . '_TO_' . strtoupper($to);
            $route_code_reverse = strtoupper($to) . '_TO_' . strtoupper($from);
            
            // Search by route codes
            $meta_query = [
                'relation' => 'OR',
                [
                    'key' => 'code_from_to',
                    'value' => $route_code_forward,
                    'compare' => 'LIKE',
                ],
                [
                    'key' => 'code_to_from',
                    'value' => $route_code_reverse,
                    'compare' => 'LIKE',
                ],
            ];

            // Also try by station names
            $from_station_id = $this->find_station_by_name_or_code($from);
            $to_station_id = $this->find_station_by_name_or_code($to);

            if ($from_station_id && $to_station_id) {
                $meta_query['relation'] = 'OR';
                $meta_query[] = [
                    'relation' => 'AND',
                    [
                        'key' => 'origin_station',
                        'value' => $from_station_id,
                        'compare' => '=',
                    ],
                    [
                        'key' => 'destination_station',
                        'value' => $to_station_id,
                        'compare' => '=',
                    ],
                ];
                $meta_query[] = [
                    'relation' => 'AND',
                    [
                        'key' => 'origin_station',
                        'value' => $to_station_id,
                        'compare' => '=',
                    ],
                    [
                        'key' => 'destination_station',
                        'value' => $from_station_id,
                        'compare' => '=',
                    ],
                ];
            }
        }

        // Search by train name or number
        if (!empty($query)) {
            $args['s'] = $query;
        }

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        $wp_query = new WP_Query($args);
        $trains = [];

        foreach ($wp_query->posts as $train) {
            $train_data = $this->format_train_data($train);
            
            // Add route code information
            $train_data['code_from_to'] = get_field('code_from_to', $train->ID);
            $train_data['code_to_from'] = get_field('code_to_from', $train->ID);
            
            $trains[] = $train_data;
        }

        return rest_ensure_response([
            'trains' => $trains,
            'total' => intval($wp_query->found_posts),
        ]);
    }

    /**
     * Format train data for API response
     */
    private function format_train_data($train, $detailed = false) {
        $origin_id = intval(get_field('origin_station', $train->ID));
        $dest_id = intval(get_field('destination_station', $train->ID));

        $train_data = [
            'id' => $train->ID,
            'name' => get_the_title($train->ID),
            'train_name' => get_the_title($train->ID),
            'from_station' => $origin_id ? get_the_title($origin_id) : '',
            'to_station' => $dest_id ? get_the_title($dest_id) : '',
            'code_from_to' => (string) get_field('code_from_to', $train->ID),
            'code_to_from' => (string) get_field('code_to_from', $train->ID),
        ];

        if ($detailed) {
            $routes = get_field('routes', $train->ID) ?: [];
            $train_data['routes'] = $this->format_routes($routes);
            $train_data['train_classes'] = $this->format_train_classes(get_field('train_classes', $train->ID));
        }

        return $train_data;
    }

    /**
     * Format routes data
     */
    private function format_routes($routes) {
        $formatted_routes = [];

        foreach ($routes as $route) {
            $station_id = intval($route['station']);
            if ($station_id) {
                $formatted_routes[] = [
                    'station' => [
                        'id' => $station_id,
                        'name' => get_the_title($station_id),
                        'code' => get_field('station_code', $station_id),
                    ],
                ];
            }
        }

        return $formatted_routes;
    }

    /**
     * Format train classes data
     */
    private function format_train_classes($classes) {
        if (!$classes || !is_array($classes)) return [];

        $formatted_classes = [];

        foreach ($classes as $class_data) {
            $class_id = intval($class_data['class_ref']);
            $coaches = [];

            if (!empty($class_data['coaches']) && is_array($class_data['coaches'])) {
                foreach ($class_data['coaches'] as $coach_data) {
                    $coach_id = intval($coach_data['coach_ref']);
                    if ($coach_id) {
                        $seat_config = $this->parent->calculate_seat_directions($coach_id);
                        $coaches[] = [
                            'coach_id' => $coach_id,
                            'coach_code' => get_field('coach_code', $coach_id),
                            'total_seats' => $seat_config['total_seats'],
                            'front_facing_seats' => $seat_config['front_facing_seats'],
                            'back_facing_seats' => $seat_config['back_facing_seats'],
                        ];
                    }
                }
            }

            $formatted_classes[] = [
                'class_id' => $class_id,
                'class_name' => $class_id ? get_the_title($class_id) : '',
                'class_short' => $class_id ? get_field('short_code', $class_id) : '',
                'coaches' => $coaches,
            ];
        }

        return $formatted_classes;
    }

    /**
     * Get train coaches
     */
    public function get_train_coaches($request) {
        $train_id = $request['train_id'];

        $train = get_post($train_id);
        if (!$train || $train->post_type !== $this->parent::TRAIN) {
            return new WP_Error('train_not_found', 'Train not found', array('status' => 404));
        }

        // Get train classes and extract coaches
        $train_classes = get_field('train_classes', $train_id) ?: [];
        $coaches = array();
        $position = 1;

        foreach ($train_classes as $class_data) {
            $class_id = intval($class_data['class_ref']);
            $class_name = $class_id ? get_the_title($class_id) : '';
            $class_short = $class_id ? get_field('short_code', $class_id) : '';

            if (!empty($class_data['coaches']) && is_array($class_data['coaches'])) {
                foreach ($class_data['coaches'] as $coach_data) {
                    $coach_id = intval($coach_data['coach_ref']);
                    if ($coach_id) {
                        $seat_config = $this->parent->calculate_seat_directions($coach_id);
                        $coaches[] = array(
                            'coach_id' => $coach_id,
                            'coach_code' => get_field('coach_code', $coach_id),
                            'type' => $class_short,
                            'class_name' => $class_name,
                            'total_seats' => $seat_config['total_seats'],
                            'position' => $position,
                            'front_facing_seats' => $seat_config['front_facing_seats'],
                            'back_facing_seats' => $seat_config['back_facing_seats'],
                        );
                        $position++;
                    }
                }
            }
        }

        return array(
            'coaches' => $coaches,
            'train_id' => intval($train_id),
            'train_name' => $train->post_title,
            'train_number' => get_field('train_number', $train_id),
            'count' => count($coaches)
        );
    }

    /**
     * Get specific train coach by code
     */
    public function get_train_coach_by_code($request) {
        $train_id = intval($request['train_id']);
        $coach_code = strtoupper(trim($request['coach_code']));

        $train = get_post($train_id);
        if (!$train || $train->post_type !== $this->parent::TRAIN) {
            return new WP_Error('train_not_found', 'Train not found', ['status' => 404]);
        }

        // Get train classes and find the specific coach
        $train_classes = get_field('train_classes', $train_id) ?: [];
        $position = 1;

        foreach ($train_classes as $class) {
            if (!empty($class['coaches'])) {
                foreach ($class['coaches'] as $coach_data) {
                    $coach_id = $coach_data['coach_id'];
                    $coach = get_post($coach_id);

                    if ($coach && $coach->post_type === $this->parent::COACH) {
                        $stored_coach_code = strtoupper(get_field('coach_code', $coach_id) ?: $coach->post_title);

                        if ($stored_coach_code === $coach_code) {
                            $seat_config = $this->parent->calculate_seat_directions($coach_id);

                            return rest_ensure_response([
                                'id' => $coach_id,
                                'code' => $stored_coach_code,
                                'type' => $class['class_short'] ?? 'UNKNOWN',
                                'total_seats' => $seat_config['total_seats'],
                                'front_facing_seats' => $seat_config['front_facing_seats'],
                                'back_facing_seats' => $seat_config['back_facing_seats'],
                                'front_facing_count' => count($seat_config['front_facing_seats']),
                                'back_facing_count' => count($seat_config['back_facing_seats']),
                                'position' => $position,
                                'train_id' => $train_id,
                                'train_name' => get_the_title($train_id),
                                'train_number' => get_field('train_number', $train_id) ?: strval($train_id),
                            ]);
                        }
                        $position++;
                    }
                }
            }
        }

        return new WP_Error('coach_not_found', 'Coach not found in this train', ['status' => 404]);
    }

    /**
     * Get train seats with route-based direction support
     */
    public function get_train_seats($request) {
        $train_id = intval($request['train_id']);
        $from_station = trim($request['from']);
        $to_station = trim($request['to']);
        $filter_coach = trim($request['coach']);

        $train = get_post($train_id);
        if (!$train || $train->post_type !== $this->parent::TRAIN) {
            return new WP_Error('train_not_found', 'Train not found', array('status' => 404));
        }

        // Get train route information
        $origin_station = get_field('origin_station', $train_id);
        $dest_station = get_field('destination_station', $train_id);
        $origin_name = $origin_station ? get_the_title($origin_station) : '';
        $dest_name = $dest_station ? get_the_title($dest_station) : '';

        // Determine direction based on route
        $is_reverse_direction = false;
        if (!empty($from_station) && !empty($to_station)) {
            // Check if this matches the reverse direction
            if (strcasecmp($from_station, $dest_name) === 0 && strcasecmp($to_station, $origin_name) === 0) {
                $is_reverse_direction = true;
            }
        }

        // Get train classes and extract coaches
        $train_classes = get_field('train_classes', $train_id) ?: [];
        $coaches = array();
        $position = 1;

        foreach ($train_classes as $class_data) {
            $class_id = intval($class_data['class_ref']);
            $class_name = $class_id ? get_the_title($class_id) : '';
            $class_short = $class_id ? get_field('short_code', $class_id) : '';

            if (!empty($class_data['coaches']) && is_array($class_data['coaches'])) {
                foreach ($class_data['coaches'] as $coach_data) {
                    $coach_id = intval($coach_data['coach_ref']);
                    if ($coach_id) {
                        $coach_code = get_field('coach_code', $coach_id);
                        
                        // Filter by coach if specified
                        if (!empty($filter_coach) && strcasecmp($coach_code, $filter_coach) !== 0) {
                            continue;
                        }

                        $seat_config = $this->get_seat_layout_for_direction($coach_id, $is_reverse_direction);
                        
                        $coaches[] = array(
                            'coach_id' => $coach_id,
                            'coach_code' => $coach_code,
                            'type' => $class_short,
                            'class_name' => $class_name,
                            'total_seats' => $seat_config['total_seats'],
                            'position' => $position,
                            'seat_layout' => $seat_config['seat_layout'],
                            'direction' => $is_reverse_direction ? 'reverse' : 'forward',
                            'route_code' => $is_reverse_direction ? 
                                get_field('code_to_from', $train_id) : 
                                get_field('code_from_to', $train_id),
                        );
                        $position++;
                    }
                }
            }
        }

        return array(
            'coaches' => $coaches,
            'train_id' => $train_id,
            'train_name' => $train->post_title,
            'train_number' => get_field('train_number', $train_id),
            'direction' => $is_reverse_direction ? 'reverse' : 'forward',
            'route' => array(
                'from' => $is_reverse_direction ? $dest_name : $origin_name,
                'to' => $is_reverse_direction ? $origin_name : $dest_name,
                'code' => $is_reverse_direction ? 
                    get_field('code_to_from', $train_id) : 
                    get_field('code_from_to', $train_id),
            ),
            'count' => count($coaches)
        );
    }

    /**
     * Generate seat layout based on direction (forward vs reverse)
     */
    private function get_seat_layout_for_direction($coach_id, $is_reverse = false) {
        $total_seats = intval(get_field('total_seats', $coach_id)) ?: 50;
        
        // Generate seat layout with 5 seats per row, 10 rows each section
        $layout = array();
        
        if ($is_reverse) {
            // B to A direction - seats face backwards (green seats on right, gray on left)
            for ($row = 0; $row < 10; $row++) {
                $row_seats = array();
                // Gray seats (right side in reverse)
                for ($col = 0; $col < 5; $col++) {
                    $seat_num = ($row * 5) + $col + 26; // 26-50 gray
                    $row_seats[] = array(
                        'number' => $seat_num,
                        'type' => 'back_facing',
                        'color' => 'gray'
                    );
                }
                // Green seats (left side in reverse) - reversed numbering
                for ($col = 4; $col >= 0; $col--) {
                    $seat_num = ((9 - $row) * 5) + $col + 1; // 25-1 green (reversed)
                    $row_seats[] = array(
                        'number' => $seat_num,
                        'type' => 'front_facing',
                        'color' => 'green'
                    );
                }
                $layout[] = $row_seats;
            }
        } else {
            // A to B direction - seats face forward (green seats on left, gray on right)
            for ($row = 0; $row < 10; $row++) {
                $row_seats = array();
                // Green seats (left side)
                for ($col = 0; $col < 5; $col++) {
                    $seat_num = ($row * 5) + $col + 1; // 1-25 green
                    $row_seats[] = array(
                        'number' => $seat_num,
                        'type' => 'front_facing',
                        'color' => 'green'
                    );
                }
                // Gray seats (right side)
                for ($col = 0; $col < 5; $col++) {
                    $seat_num = ((9 - $row) * 5) + (4 - $col) + 26; // 50-26 gray (reversed)
                    $row_seats[] = array(
                        'number' => $seat_num,
                        'type' => 'back_facing',
                        'color' => 'gray'
                    );
                }
                $layout[] = $row_seats;
            }
        }

        return array(
            'total_seats' => $total_seats,
            'seat_layout' => $layout
        );
    }

    /**
     * Get train by route code
     */
    public function get_train_by_route_code($request) {
        $route_code = trim($request['route_code']);
        $direction = trim($request['direction']);

        if (empty($route_code)) {
            return new WP_Error('missing_route_code', 'Route code is required', ['status' => 400]);
        }

        // Search for trains by route code
        $meta_key = ($direction === 'reverse') ? 'code_to_from' : 'code_from_to';
        
        $trains = get_posts([
            'post_type' => $this->parent::TRAIN,
            'meta_query' => [
                [
                    'key' => $meta_key,
                    'value' => $route_code,
                    'compare' => '=',
                ],
            ],
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);

        if (empty($trains)) {
            return new WP_Error('train_not_found', 'No train found for this route code', ['status' => 404]);
        }

        $train = $trains[0];
        $train_data = $this->format_train_data($train, true);

        // Add route-specific seat layouts
        $is_reverse_direction = ($direction === 'reverse');
        $enhanced_classes = [];

        foreach ($train_data['train_classes'] as $class_data) {
            $enhanced_coaches = [];
            
            foreach ($class_data['coaches'] as $coach_data) {
                $coach_id = $coach_data['coach_id'];
                $seat_config = $this->get_seat_layout_for_direction($coach_id, $is_reverse_direction);
                
                $enhanced_coaches[] = array_merge($coach_data, [
                    'seat_layout' => $seat_config['seat_layout'],
                    'direction' => $is_reverse_direction ? 'reverse' : 'forward',
                    'route_code' => $route_code,
                ]);
            }
            
            $enhanced_classes[] = array_merge($class_data, [
                'coaches' => $enhanced_coaches,
            ]);
        }

        $train_data['train_classes'] = $enhanced_classes;
        $train_data['direction'] = $is_reverse_direction ? 'reverse' : 'forward';
        $train_data['route_code'] = $route_code;

        return rest_ensure_response($train_data);
    }

    /**
     * Find station by name or code
     */
    private function find_station_by_name_or_code($search_term) {
        // Try by exact title match first
        $station = get_page_by_title($search_term, OBJECT, $this->parent::STATION);
        if ($station) {
            return $station->ID;
        }

        // Try by station code
        $stations = get_posts([
            'post_type' => $this->parent::STATION,
            'meta_query' => [
                [
                    'key' => 'station_code',
                    'value' => $search_term,
                    'compare' => '=',
                ],
            ],
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        return !empty($stations) ? intval($stations[0]) : 0;
    }
}