<?php
/**
 * Coach API Endpoints
 * Handles all coach-related REST API endpoints
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
    }

    /**
     * List all coaches with pagination and filtering
     */
    public function list_coaches($request) {
        $per_page = intval($request['per_page']);
        $page = intval($request['page']);
        $search = trim($request['search']);
        $code = trim($request['code']);

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

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        $query = new WP_Query($args);
        $coaches = [];

        foreach ($query->posts as $coach) {
            $coaches[] = $this->format_coach_data($coach);
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

        return rest_ensure_response($this->format_coach_data($coach));
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

        return rest_ensure_response($this->format_coach_data($coaches[0]));
    }

    /**
     * Format coach data for API response
     */
    private function format_coach_data($coach) {
        $seat_config = $this->parent->calculate_seat_directions($coach->ID);

        return [
            'id' => $coach->ID,
            'code' => get_field('coach_code', $coach->ID) ?: get_the_title($coach->ID),
            'name' => get_the_title($coach->ID),
            'total_seats' => $seat_config['total_seats'],
            'front_facing_seats' => $seat_config['front_facing_seats'],
            'back_facing_seats' => $seat_config['back_facing_seats'],
            'slug' => $coach->post_name,
        ];
    }
}