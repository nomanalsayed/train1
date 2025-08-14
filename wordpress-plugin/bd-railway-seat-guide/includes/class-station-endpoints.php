<?php
/**
 * Station API Endpoints
 * Handles all station-related REST API endpoints with enhanced search capabilities
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
                'per_page' => ['default' => 200, 'sanitize_callback' => 'absint'],
                'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
                'search' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'code' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'division' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        
        // Get single station by ID
        register_rest_route($this->parent::API_NAMESPACE, '/stations/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_station'],
            'permission_callback' => '__return_true',
        ]);
        
        // Search stations with autocomplete
        register_rest_route($this->parent::API_NAMESPACE, '/stations/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search_stations'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'limit' => ['default' => 10, 'sanitize_callback' => 'absint'],
            ],
        ]);
        
        // Get station by code
        register_rest_route($this->parent::API_NAMESPACE, '/stations/by-code/(?P<code>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_station_by_code'],
            'permission_callback' => '__return_true',
        ]);
        
        // Get popular stations
        register_rest_route($this->parent::API_NAMESPACE, '/stations/popular', [
            'methods' => 'GET',
            'callback' => [$this, 'get_popular_stations'],
            'permission_callback' => '__return_true',
            'args' => [
                'limit' => ['default' => 20, 'sanitize_callback' => 'absint'],
            ],
        ]);
    }
    
    /**
     * List all stations with pagination and filtering
     */
    public function list_stations($request) {
        $per_page = intval($request['per_page']);
        $page = intval($request['page']);
        $search = trim($request['search']);
        $code = trim($request['code']);
        $division = trim($request['division']);
        
        // Try cache first
        $cache_key = 'bd_railway_stations_' . md5($search . $code . $division . $per_page . $page);
        $cached_results = wp_cache_get($cache_key, 'bd_railway');
        
        if ($cached_results !== false) {
            return rest_ensure_response($cached_results);
        }
        
        $args = [
            'post_type' => $this->parent::STATION,
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
                'key' => 'station_code',
                'value' => $code,
                'compare' => 'LIKE',
            ];
        }
        
        if (!empty($division)) {
            $meta_query[] = [
                'key' => 'division',
                'value' => $division,
                'compare' => 'LIKE',
            ];
        }
        
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
            if (count($meta_query) > 1) {
                $args['meta_query']['relation'] = 'AND';
            }
        }
        
        $query = new WP_Query($args);
        $stations = [];
        
        foreach ($query->posts as $station) {
            $stations[] = $this->format_station_data($station);
        }
        
        $results = [
            'stations' => $stations,
            'total' => intval($query->found_posts),
            'pages' => intval($query->max_num_pages),
            'page' => $page,
            'per_page' => $per_page,
        ];
        
        // Cache for 30 minutes
        wp_cache_set($cache_key, $results, 'bd_railway', 1800);
        
        return rest_ensure_response($results);
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
        
        $station_data = $this->format_station_data($station);
        
        // Add additional details for single station view
        $station_data['description'] = get_the_content(null, false, $station);
        $station_data['trains_from_here'] = $this->get_trains_from_station($station_id);
        $station_data['trains_to_here'] = $this->get_trains_to_station($station_id);
        
        return rest_ensure_response($station_data);
    }
    
    /**
     * Search stations with autocomplete functionality
     */
    public function search_stations($request) {
        $query = trim($request['q']);
        $limit = intval($request['limit']);
        
        if (empty($query) || strlen($query) < 2) {
            return rest_ensure_response(['stations' => []]);
        }
        
        // Cache search results
        $cache_key = 'bd_railway_station_search_' . md5($query . $limit);
        $cached_results = wp_cache_get($cache_key, 'bd_railway');
        
        if ($cached_results !== false) {
            return rest_ensure_response($cached_results);
        }
        
        // Search by title and station code
        $args = [
            'post_type' => $this->parent::STATION,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'orderby' => 'relevance',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'station_code',
                    'value' => $query,
                    'compare' => 'LIKE',
                ],
            ],
            's' => $query,
        ];
        
        $stations = get_posts($args);
        $formatted_stations = [];
        
        foreach ($stations as $station) {
            $station_data = $this->format_station_data($station);
            
            // Add relevance score for better sorting
            $station_code = strtolower($station_data['code']);
            $station_title = strtolower($station_data['title']);
            $query_lower = strtolower($query);
            
            $relevance = 0;
            if (strpos($station_code, $query_lower) === 0) {
                $relevance += 100; // Exact code match at start
            } elseif (strpos($station_code, $query_lower) !== false) {
                $relevance += 50; // Code contains query
            }
            
            if (strpos($station_title, $query_lower) === 0) {
                $relevance += 80; // Title starts with query
            } elseif (strpos($station_title, $query_lower) !== false) {
                $relevance += 30; // Title contains query
            }
            
            $station_data['relevance'] = $relevance;
            $formatted_stations[] = $station_data;
        }
        
        // Sort by relevance
        usort($formatted_stations, function($a, $b) {
            return $b['relevance'] - $a['relevance'];
        });
        
        // Remove relevance from final output
        foreach ($formatted_stations as &$station) {
            unset($station['relevance']);
        }
        
        $results = ['stations' => $formatted_stations];
        
        // Cache for 15 minutes
        wp_cache_set($cache_key, $results, 'bd_railway', 900);
        
        return rest_ensure_response($results);
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
     * Get popular stations (most frequently used in train routes)
     */
    public function get_popular_stations($request) {
        $limit = intval($request['limit']);
        
        // Try cache first
        $cache_key = 'bd_railway_popular_stations_' . $limit;
        $cached_results = wp_cache_get($cache_key, 'bd_railway');
        
        if ($cached_results !== false) {
            return rest_ensure_response($cached_results);
        }
        
        // Get station usage statistics
        $station_usage = [];
        
        // Count origin stations
        $origin_stations = get_posts([
            'post_type' => $this->parent::TRAIN,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
        ]);
        
        foreach ($origin_stations as $train_id) {
            $origin_id = intval(get_field('origin_station', $train_id));
            $dest_id = intval(get_field('destination_station', $train_id));
            
            if ($origin_id) {
                $station_usage[$origin_id] = ($station_usage[$origin_id] ?? 0) + 1;
            }
            if ($dest_id) {
                $station_usage[$dest_id] = ($station_usage[$dest_id] ?? 0) + 1;
            }
            
            // Count intermediate stations
            $intermediate = get_field('intermediate_stations', $train_id);
            if ($intermediate && is_array($intermediate)) {
                foreach ($intermediate as $station_data) {
                    $station_id = intval($station_data['station']);
                    if ($station_id) {
                        $station_usage[$station_id] = ($station_usage[$station_id] ?? 0) + 1;
                    }
                }
            }
        }
        
        // Sort by usage
        arsort($station_usage);
        
        // Get top stations
        $popular_stations = [];
        $count = 0;
        
        foreach ($station_usage as $station_id => $usage_count) {
            if ($count >= $limit) break;
            
            $station = get_post($station_id);
            if ($station && $station->post_type === $this->parent::STATION) {
                $station_data = $this->format_station_data($station);
                $station_data['usage_count'] = $usage_count;
                $popular_stations[] = $station_data;
                $count++;
            }
        }
        
        $results = ['stations' => $popular_stations];
        
        // Cache for 1 hour
        wp_cache_set($cache_key, $results, 'bd_railway', 3600);
        
        return rest_ensure_response($results);
    }
    
    /**
     * Format station data for API response
     */
    private function format_station_data($station) {
        return [
            'id' => $station->ID,
            'title' => get_the_title($station->ID),
            'code' => get_field('station_code', $station->ID) ?: '',
            'district' => get_field('district', $station->ID) ?: '',
            'division' => get_field('division', $station->ID) ?: '',
            'latitude' => floatval(get_field('latitude', $station->ID)),
            'longitude' => floatval(get_field('longitude', $station->ID)),
            'slug' => $station->post_name,
        ];
    }
    
    /**
     * Get trains departing from this station
     */
    private function get_trains_from_station($station_id) {
        $trains = get_posts([
            'post_type' => $this->parent::TRAIN,
            'meta_query' => [
                [
                    'key' => 'origin_station',
                    'value' => $station_id,
                    'compare' => '=',
                ],
            ],
            'posts_per_page' => 10,
            'post_status' => 'publish',
        ]);
        
        $formatted_trains = [];
        foreach ($trains as $train) {
            $formatted_trains[] = [
                'id' => $train->ID,
                'name' => get_the_title($train->ID),
                'train_number' => get_field('train_number', $train->ID),
            ];
        }
        
        return $formatted_trains;
    }
    
    /**
     * Get trains arriving at this station
     */
    private function get_trains_to_station($station_id) {
        $trains = get_posts([
            'post_type' => $this->parent::TRAIN,
            'meta_query' => [
                [
                    'key' => 'destination_station',
                    'value' => $station_id,
                    'compare' => '=',
                ],
            ],
            'posts_per_page' => 10,
            'post_status' => 'publish',
        ]);
        
        $formatted_trains = [];
        foreach ($trains as $train) {
            $formatted_trains[] = [
                'id' => $train->ID,
                'name' => get_the_title($train->ID),
                'train_number' => get_field('train_number', $train->ID),
            ];
        }
        
        return $formatted_trains;
    }
}
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
    }
    
    /**
     * List all stations
     */
    public function list_stations($request) {
        return rest_ensure_response([
            'stations' => [],
            'total' => 0,
            'message' => 'Station endpoints not yet implemented',
        ]);
    }
}
