<?php
/**
 * Search API Endpoints
 * Handles comprehensive search functionality across trains, stations, and routes
 */

if (!defined('ABSPATH')) exit;

class BD_Railway_Search_Endpoints {
    private $parent;
    
    public function __construct($parent) {
        $this->parent = $parent;
    }
    
    public function register_routes() {
        // Universal search endpoint
        register_rest_route($this->parent::API_NAMESPACE, '/search', [
            'methods' => 'GET',
            'callback' => [$this, 'universal_search'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'type' => ['default' => 'all', 'sanitize_callback' => 'sanitize_text_field'],
                'limit' => ['default' => 20, 'sanitize_callback' => 'absint'],
            ],
        ]);
        
        // Route search (from/to stations)
        register_rest_route($this->parent::API_NAMESPACE, '/search/routes', [
            'methods' => 'GET',
            'callback' => [$this, 'search_routes'],
            'permission_callback' => '__return_true',
            'args' => [
                'from' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'to' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'limit' => ['default' => 10, 'sanitize_callback' => 'absint'],
            ],
        ]);
        
        // Popular routes
        register_rest_route($this->parent::API_NAMESPACE, '/search/popular-routes', [
            'methods' => 'GET',
            'callback' => [$this, 'get_popular_routes'],
            'permission_callback' => '__return_true',
            'args' => [
                'limit' => ['default' => 20, 'sanitize_callback' => 'absint'],
            ],
        ]);
        
        // Search suggestions
        register_rest_route($this->parent::API_NAMESPACE, '/search/suggestions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_search_suggestions'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'limit' => ['default' => 5, 'sanitize_callback' => 'absint'],
            ],
        ]);
    }
    
    /**
     * Universal search across all content types
     */
    public function universal_search($request) {
        $query = trim($request['q']);
        $type = trim($request['type']);
        $limit = intval($request['limit']);
        
        if (empty($query) || strlen($query) < 2) {
            return rest_ensure_response([
                'results' => [],
                'total' => 0,
                'query' => $query,
            ]);
        }
        
        $results = [];
        
        // Search trains
        if ($type === 'all' || $type === 'trains') {
            $train_results = $this->search_trains($query, $limit);
            $results = array_merge($results, $train_results);
        }
        
        // Search stations
        if ($type === 'all' || $type === 'stations') {
            $station_results = $this->search_stations($query, $limit);
            $results = array_merge($results, $station_results);
        }
        
        // Search coaches
        if ($type === 'all' || $type === 'coaches') {
            $coach_results = $this->search_coaches($query, $limit);
            $results = array_merge($results, $coach_results);
        }
        
        // Sort by relevance
        usort($results, function($a, $b) {
            return $b['relevance'] - $a['relevance'];
        });
        
        // Limit results
        if (count($results) > $limit) {
            $results = array_slice($results, 0, $limit);
        }
        
        // Remove relevance scores from output
        foreach ($results as &$result) {
            unset($result['relevance']);
        }
        
        return rest_ensure_response([
            'results' => $results,
            'total' => count($results),
            'query' => $query,
            'type' => $type,
        ]);
    }
    
    /**
     * Search for routes between stations
     */
    public function search_routes($request) {
        $from = trim($request['from']);
        $to = trim($request['to']);
        $limit = intval($request['limit']);
        
        // Use the train endpoints search functionality
        $train_endpoints = new BD_Railway_Train_Endpoints($this->parent);
        $search_request = new WP_REST_Request('GET', '/rail/v1/trains/search');
        $search_request->set_param('from', $from);
        $search_request->set_param('to', $to);
        $search_request->set_param('limit', $limit);
        
        $response = $train_endpoints->search_trains($search_request);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $data = $response->get_data();
        
        // Format for route search response
        $routes = [];
        foreach ($data['trains'] as $train) {
            $routes[] = [
                'train' => $train,
                'from_station' => $train['match_info']['from_station'] ?? null,
                'to_station' => $train['match_info']['to_station'] ?? null,
                'direction' => $train['match_info']['direction'] ?? 'forward',
            ];
        }
        
        return rest_ensure_response([
            'routes' => $routes,
            'total' => count($routes),
            'search_params' => [
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }
    
    /**
     * Get popular routes based on train frequency
     */
    public function get_popular_routes($request) {
        $limit = intval($request['limit']);
        
        // Try cache first
        $cache_key = 'bd_railway_popular_routes_' . $limit;
        $cached_results = get_transient($cache_key);
        
        if ($cached_results !== false) {
            return rest_ensure_response($cached_results);
        }
        
        // Get all trains and count routes
        $trains = get_posts([
            'post_type' => $this->parent::TRAIN,
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        
        $route_counts = [];
        
        foreach ($trains as $train) {
            $origin_id = intval(get_field('origin_station', $train->ID));
            $dest_id = intval(get_field('destination_station', $train->ID));
            
            if ($origin_id && $dest_id) {
                $route_key = $origin_id . '_' . $dest_id;
                
                if (!isset($route_counts[$route_key])) {
                    $route_counts[$route_key] = [
                        'from_station' => [
                            'id' => $origin_id,
                            'title' => get_the_title($origin_id),
                            'code' => get_field('station_code', $origin_id),
                        ],
                        'to_station' => [
                            'id' => $dest_id,
                            'title' => get_the_title($dest_id),
                            'code' => get_field('station_code', $dest_id),
                        ],
                        'train_count' => 0,
                        'trains' => [],
                    ];
                }
                
                $route_counts[$route_key]['train_count']++;
                $route_counts[$route_key]['trains'][] = [
                    'id' => $train->ID,
                    'name' => get_the_title($train->ID),
                    'train_number' => get_field('train_number', $train->ID),
                ];
            }
        }
        
        // Sort by train count
        uasort($route_counts, function($a, $b) {
            return $b['train_count'] - $a['train_count'];
        });
        
        // Limit results
        $popular_routes = array_slice(array_values($route_counts), 0, $limit);
        
        $results = [
            'routes' => $popular_routes,
            'total' => count($popular_routes),
        ];
        
        // Cache for 1 hour
        set_transient($cache_key, $results, 3600);
        
        return rest_ensure_response($results);
    }
    
    /**
     * Get search suggestions for autocomplete
     */
    public function get_search_suggestions($request) {
        $query = trim($request['q']);
        $limit = intval($request['limit']);
        
        if (empty($query) || strlen($query) < 2) {
            return rest_ensure_response(['suggestions' => []]);
        }
        
        $suggestions = [];
        
        // Get station suggestions
        $stations = get_posts([
            'post_type' => $this->parent::STATION,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            's' => $query,
            'orderby' => 'relevance',
        ]);
        
        foreach ($stations as $station) {
            $suggestions[] = [
                'text' => get_the_title($station->ID),
                'type' => 'station',
                'code' => get_field('station_code', $station->ID),
            ];
        }
        
        // Get train suggestions
        $trains = get_posts([
            'post_type' => $this->parent::TRAIN,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            's' => $query,
            'orderby' => 'relevance',
        ]);
        
        foreach ($trains as $train) {
            $suggestions[] = [
                'text' => get_the_title($train->ID),
                'type' => 'train',
                'number' => get_field('train_number', $train->ID),
            ];
        }
        
        // Limit total suggestions
        if (count($suggestions) > $limit) {
            $suggestions = array_slice($suggestions, 0, $limit);
        }
        
        return rest_ensure_response(['suggestions' => $suggestions]);
    }
    
    /**
     * Search trains by query
     */
    private function search_trains($query, $limit) {
        $trains = get_posts([
            'post_type' => $this->parent::TRAIN,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            's' => $query,
        ]);
        
        $results = [];
        foreach ($trains as $train) {
            $relevance = $this->calculate_relevance($query, get_the_title($train->ID));
            
            $results[] = [
                'id' => $train->ID,
                'title' => get_the_title($train->ID),
                'type' => 'train',
                'train_number' => get_field('train_number', $train->ID),
                'relevance' => $relevance,
            ];
        }
        
        return $results;
    }
    
    /**
     * Search stations by query
     */
    private function search_stations($query, $limit) {
        $stations = get_posts([
            'post_type' => $this->parent::STATION,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            's' => $query,
        ]);
        
        $results = [];
        foreach ($stations as $station) {
            $relevance = $this->calculate_relevance($query, get_the_title($station->ID));
            $station_code = get_field('station_code', $station->ID);
            
            // Boost relevance for code matches
            if (stripos($station_code, $query) !== false) {
                $relevance += 50;
            }
            
            $results[] = [
                'id' => $station->ID,
                'title' => get_the_title($station->ID),
                'type' => 'station',
                'code' => $station_code,
                'relevance' => $relevance,
            ];
        }
        
        return $results;
    }
    
    /**
     * Search coaches by query
     */
    private function search_coaches($query, $limit) {
        $coaches = get_posts([
            'post_type' => $this->parent::COACH,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            's' => $query,
        ]);
        
        $results = [];
        foreach ($coaches as $coach) {
            $relevance = $this->calculate_relevance($query, get_the_title($coach->ID));
            $coach_code = get_field('coach_code', $coach->ID);
            
            // Boost relevance for code matches
            if (stripos($coach_code, $query) !== false) {
                $relevance += 50;
            }
            
            $results[] = [
                'id' => $coach->ID,
                'title' => get_the_title($coach->ID),
                'type' => 'coach',
                'code' => $coach_code,
                'relevance' => $relevance,
            ];
        }
        
        return $results;
    }
    
    /**
     * Calculate relevance score for search results
     */
    private function calculate_relevance($query, $title) {
        $query_lower = strtolower(trim($query));
        $title_lower = strtolower($title);
        
        $relevance = 0;
        
        // Exact match
        if ($title_lower === $query_lower) {
            $relevance += 100;
        }
        // Starts with query
        elseif (strpos($title_lower, $query_lower) === 0) {
            $relevance += 80;
        }
        // Contains query
        elseif (strpos($title_lower, $query_lower) !== false) {
            $relevance += 50;
        }
        // Word match
        else {
            $title_words = explode(' ', $title_lower);
            $query_words = explode(' ', $query_lower);
            
            foreach ($query_words as $query_word) {
                foreach ($title_words as $title_word) {
                    if (strpos($title_word, $query_word) !== false) {
                        $relevance += 20;
                    }
                }
            }
        }
        
        return $relevance;
    }
}
<?php
/**
 * Search API Endpoints
 * Handles search-related REST API endpoints
 */

if (!defined('ABSPATH')) exit;

class BD_Railway_Search_Endpoints {
    private $parent;
    
    public function __construct($parent) {
        $this->parent = $parent;
    }
    
    public function register_routes() {
        // General search endpoint
        register_rest_route($this->parent::API_NAMESPACE, '/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search_all'],
            'permission_callback' => '__return_true',
            'args' => [
                'query' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'type' => ['default' => 'all', 'sanitize_callback' => 'sanitize_text_field'],
                'limit' => ['default' => 10, 'sanitize_callback' => 'absint'],
            ],
        ]);
    }
    
    /**
     * Search across trains, coaches, and stations
     */
    public function search_all($request) {
        $query = trim($request['query']);
        $type = trim($request['type']);
        $limit = intval($request['limit']);
        
        $results = [
            'query' => $query,
            'results' => [],
        ];
        
        if ($type === 'all' || $type === 'trains') {
            $results['results']['trains'] = $this->search_trains($query, $limit);
        }
        
        if ($type === 'all' || $type === 'coaches') {
            $results['results']['coaches'] = $this->search_coaches($query, $limit);
        }
        
        if ($type === 'all' || $type === 'stations') {
            $results['results']['stations'] = $this->search_stations($query, $limit);
        }
        
        return rest_ensure_response($results);
    }
    
    private function search_trains($query, $limit) {
        $trains = get_posts([
            'post_type' => $this->parent::TRAIN,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            's' => $query,
        ]);
        
        $results = [];
        foreach ($trains as $train) {
            $results[] = [
                'id' => $train->ID,
                'name' => get_the_title($train->ID),
                'train_number' => get_field('train_number', $train->ID),
                'type' => 'train',
            ];
        }
        
        return $results;
    }
    
    private function search_coaches($query, $limit) {
        $coaches = get_posts([
            'post_type' => $this->parent::COACH,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            's' => $query,
        ]);
        
        $results = [];
        foreach ($coaches as $coach) {
            $results[] = [
                'id' => $coach->ID,
                'code' => get_field('coach_code', $coach->ID),
                'type' => 'coach',
            ];
        }
        
        return $results;
    }
    
    private function search_stations($query, $limit) {
        $stations = get_posts([
            'post_type' => $this->parent::STATION,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            's' => $query,
        ]);
        
        $results = [];
        foreach ($stations as $station) {
            $results[] = [
                'id' => $station->ID,
                'name' => get_the_title($station->ID),
                'code' => get_field('station_code', $station->ID),
                'type' => 'station',
            ];
        }
        
        return $results;
    }
}
