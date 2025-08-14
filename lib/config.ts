export const API_CONFIG = {
  // Rail API base URL - hardcoded as requested
  RAIL_BASE_URL: "https://noman.ebazarhut.com/wp-json/rail/v1",

  // API endpoints
  ENDPOINTS: {
    TRAINS: "/trains",
    STATIONS: "/stations",
    SEATS: "/seats",
    COACHES: "/coaches",
    DIRECTIONS: "/directions",
  },

  // Request configuration
  DEFAULT_HEADERS: {
    "Content-Type": "application/json",
  },

  // Cache settings
  CACHE_TIME: 300, // 5 minutes
}

// Helper function to build full API URLs
export function buildApiUrl(endpoint: string): string {
  return `${API_CONFIG.RAIL_BASE_URL}${endpoint}`
}
