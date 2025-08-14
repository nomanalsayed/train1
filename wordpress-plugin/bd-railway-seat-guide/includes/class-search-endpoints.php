<?php
/**
 * Search API Endpoints
 * Handles search functionality across trains and stations
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
            'callback' => [$this, 'general_search'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'type' => ['default' => 'all', 'sanitize_callback' => 'sanitize_text_field'],
                'limit' => ['default' => 10, 'sanitize_callback' => 'absint'],
            ],
        ]);

        // Station search endpoint
        register_rest_route($this->parent::API_NAMESPACE, '/search/stations', [
            'methods' => 'GET',
            'callback' => [$this, 'search_stations'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'limit' => ['default' => 10, 'sanitize_callback' => 'absint'],
            ],
        ]);

        // Train search endpoint
        register_rest_route($this->parent::API_NAMESPACE, '/search/trains', [
            'methods' => 'GET',
            'callback' => [$this, 'search_trains'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'limit' => ['default' => 10, 'sanitize_callback' => 'absint'],
            ],
        ]);
    }

    /**
     * General search across all content types
     */
    public function general_search($request) {
        $query = trim($request['q']);
        $type = $request['type'];
        $limit = intval($request['limit']);

        if (empty($query)) {
            return rest_ensure_response(['results' => []]);
        }

        $results = [];

        if ($type === 'all' || $type === 'stations') {
            $station_results = $this->search_stations_internal($query, $limit);
            $results = array_merge($results, $station_results);
        }

        if ($type === 'all' || $type === 'trains') {
            $train_results = $this->search_trains_internal($query, $limit);
            $results = array_merge($results, $train_results);
        }

        // Limit total results
        if (count($results) > $limit) {
            $results = array_slice($results, 0, $limit);
        }

        return rest_ensure_response(['results' => $results]);
    }

    /**
     * Search stations
     */
    public function search_stations($request) {
        $query = trim($request['q']);
        $limit = intval($request['limit']);

        if (empty($query)) {
            return rest_ensure_response(['stations' => []]);
        }

        $results = $this->search_stations_internal($query, $limit);

        return rest_ensure_response(['stations' => $results]);
    }

    /**
     * Search trains
     */
    public function search_trains($request) {
        $query = trim($request['q']);
        $limit = intval($request['limit']);

        if (empty($query)) {
            return rest_ensure_response(['trains' => []]);
        }

        $results = $this->search_trains_internal($query, $limit);

        return rest_ensure_response(['trains' => $results]);
    }

    /**
     * Internal station search function
     */
    private function search_stations_internal($query, $limit = 10) {
        $args = [
            'post_type' => $this->parent::STATION,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            's' => $query,
        ];

        // Also search by station code
        $meta_query_args = [
            'post_type' => $this->parent::STATION,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'station_code',
                    'value' => $query,
                    'compare' => 'LIKE',
                ],
            ],
        ];

        // Get results from both queries
        $title_results = get_posts($args);
        $code_results = get_posts($meta_query_args);

        // Merge and deduplicate results
        $all_results = array_merge($title_results, $code_results);
        $unique_results = [];
        $seen_ids = [];

        foreach ($all_results as $post) {
            if (!in_array($post->ID, $seen_ids)) {
                $unique_results[] = $post;
                $seen_ids[] = $post->ID;
            }
        }

        // Limit results
        if (count($unique_results) > $limit) {
            $unique_results = array_slice($unique_results, 0, $limit);
        }

        $results = [];
        foreach ($unique_results as $station) {
            $results[] = [
                'type' => 'station',
                'id' => $station->ID,
                'name' => get_the_title($station->ID),
                'code' => get_field('station_code', $station->ID) ?: '',
                'slug' => $station->post_name,
            ];
        }

        return $results;
    }

    /**
     * Internal train search function
     */
    private function search_trains_internal($query, $limit = 10) {
        $args = [
            'post_type' => $this->parent::TRAIN,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            's' => $query,
        ];

        $trains = get_posts($args);
        $results = [];

        foreach ($trains as $train) {
            $origin_id = intval(get_field('origin_station', $train->ID));
            $dest_id = intval(get_field('destination_station', $train->ID));

            $results[] = [
                'type' => 'train',
                'id' => $train->ID,
                'name' => get_the_title($train->ID),
                'from_station' => $origin_id ? get_the_title($origin_id) : '',
                'to_station' => $dest_id ? get_the_title($dest_id) : '',
                'slug' => $train->post_name,
            ];
        }

        return $results;
    }
}