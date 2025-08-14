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
            // Find station IDs by name/code
            $from_station_id = $this->find_station_by_name_or_code($from);
            $to_station_id = $this->find_station_by_name_or_code($to);

            if ($from_station_id && $to_station_id) {
                $meta_query = [
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
            $trains[] = $this->format_train_data($train);
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
        $train_id = intval($request['train_id']);

        $train = get_post($train_id);
        if (!$train || $train->post_type !== $this->parent::TRAIN) {
            return new WP_Error('train_not_found', 'Train not found', ['status' => 404]);
        }

        // Get train classes and their coaches
        $train_classes = get_field('train_classes', $train_id) ?: [];
        $all_coaches = [];
        $position = 1;

        foreach ($train_classes as $class) {
            if (!empty($class['coaches'])) {
                foreach ($class['coaches'] as $coach_data) {
                    $coach_id = $coach_data['coach_id'];
                    $coach = get_post($coach_id);

                    if ($coach && $coach->post_type === $this->parent::COACH) {
                        $seat_config = $this->parent->calculate_seat_directions($coach_id);

                        $all_coaches[] = [
                            'id' => $coach_id,
                            'code' => get_field('coach_code', $coach_id) ?: $coach->post_title,
                            'type' => $class['class_short'] ?? 'UNKNOWN',
                            'total_seats' => $seat_config['total_seats'],
                            'front_facing_seats' => $seat_config['front_facing_seats'],
                            'back_facing_seats' => $seat_config['back_facing_seats'],
                            'position' => $position++,
                        ];
                    }
                }
            }
        }

        return rest_ensure_response([
            'coaches' => $all_coaches,
            'train_id' => $train_id,
            'train_name' => get_the_title($train_id),
            'train_number' => get_field('train_number', $train_id) ?: strval($train_id),
            'count' => count($all_coaches),
        ]);
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