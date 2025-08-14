
<?php
/**
 * Station API Endpoints
 * Handles all station-related REST API endpoints
 */

if (!defined('ABSPATH')) exit;

class BD_Railway_Station_Endpoints {
    private $parent;
    
    public function __construct($parent) {
        $this->parent = $parent;
    }
    
    public function register_routes() {
        // List all stations
        register_rest_route($this->parent::API_NAMESPACE, '/stations', [
            'methods' => 'GET',
            'callback' => [$this, 'list_stations'],
            'permission_callback' => '__return_true',
            'args' => [
                'per_page' => ['default' => 100, 'sanitize_callback' => 'absint'],
                'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
                'search' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        
        // Get single station by ID
        register_rest_route($this->parent::API_NAMESPACE, '/stations/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_station'],
            'permission_callback' => '__return_true',
        ]);
        
        // Get station by code
        register_rest_route($this->parent::API_NAMESPACE, '/stations/by-code/(?P<code>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_station_by_code'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    /**
     * List all stations
     */
    public function list_stations($request) {
        $per_page = intval($request['per_page']);
        $page = intval($request['page']);
        $search = trim($request['search']);
        
        $args = [
            'post_type' => $this->parent::STATION,
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
        $stations = [];
        
        foreach ($query->posts as $station) {
            $stations[] = $this->format_station_data($station);
        }
        
        return rest_ensure_response([
            'stations' => $stations,
            'total' => intval($query->found_posts),
            'pages' => intval($query->max_num_pages),
            'page' => $page,
            'per_page' => $per_page,
        ]);
    }
    
    /**
     * Get single station by ID
     */
    public function get_station($request) {
        $station_id = intval($request['id']);
        $station = get_post($station_id);
        
        if (!$station || $station->post_type !== $this->parent::STATION) {
            return new WP_Error('station_not_found', 'Station not found', ['status' => 404]);
        }
        
        return rest_ensure_response($this->format_station_data($station));
    }
    
    /**
     * Get station by code
     */
    public function get_station_by_code($request) {
        $code = strtoupper(trim($request['code']));
        
        $stations = get_posts([
            'post_type' => $this->parent::STATION,
            'meta_query' => [
                [
                    'key' => 'station_code',
                    'value' => $code,
                    'compare' => '=',
                ],
            ],
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);
        
        if (empty($stations)) {
            return new WP_Error('station_not_found', 'Station not found', ['status' => 404]);
        }
        
        return rest_ensure_response($this->format_station_data($stations[0]));
    }
    
    /**
     * Format station data for API response
     */
    private function format_station_data($station) {
        return [
            'id' => $station->ID,
            'name' => get_the_title($station->ID),
            'title' => get_the_title($station->ID),
            'code' => get_field('station_code', $station->ID) ?: '',
            'slug' => $station->post_name,
        ];
    }
}
