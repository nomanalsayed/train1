=== BD Railway Seat Guide Pro ===
Contributors: bd-railway-team
Tags: railway, trains, seats, bangladesh, transportation
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete Bangladesh Railway seat orientation system with mobile-optimized APIs and Advanced Custom Fields PRO integration.

== Description ==

BD Railway Seat Guide Pro is a comprehensive WordPress plugin designed specifically for Bangladesh Railway seat orientation lookup. It provides a complete system for managing trains, stations, coaches, and seat configurations with intelligent direction detection.

**Key Features:**

* **Train Management**: Complete train database with route information
* **Station Database**: Comprehensive railway station management
* **Coach Configuration**: Detailed seat layout and direction management
* **Smart Search**: Intelligent route and train search functionality
* **Mobile APIs**: RESTful APIs optimized for mobile applications
* **Direction Detection**: Automatic seat direction calculation based on train numbers and routes
* **Caching System**: Built-in caching for optimal performance
* **ACF Pro Integration**: Seamless integration with Advanced Custom Fields PRO

**API Endpoints:**

* `/wp-json/rail/v1/trains` - List and search trains
* `/wp-json/rail/v1/stations` - Station management and search
* `/wp-json/rail/v1/coaches` - Coach configuration and seat layouts
* `/wp-json/rail/v1/search` - Universal search functionality
* `/wp-json/rail/v1/trains/by-number/{number}` - Get train with seat directions
* `/wp-json/rail/v1/trains/search` - Route-based train search

**Perfect for:**

* Railway mobile applications
* Seat booking systems
* Travel planning websites
* Railway information portals
* Transportation management systems

== Installation ==

1. **Install Advanced Custom Fields PRO** (required dependency)
2. Upload the plugin files to `/wp-content/plugins/bd-railway-seat-guide/`
3. Activate the plugin through the 'Plugins' screen in WordPress
4. Configure your trains, stations, and coaches through the WordPress admin
5. Use the REST API endpoints in your applications

== Frequently Asked Questions ==

= Does this plugin require Advanced Custom Fields PRO? =

Yes, this plugin requires Advanced Custom Fields PRO to function properly. The plugin will display an admin notice if ACF PRO is not installed.

= Can I use this with mobile applications? =

The plugin is designed with mobile applications in mind and provides comprehensive REST API endpoints for all functionality.

= How does seat direction detection work? =

The plugin uses intelligent algorithms to determine seat directions based on:
- Train numbers (odd = forward, even = reverse)
- Route direction (from/to station analysis)
- Manual direction overrides
- Coach-specific configurations

= Is the plugin cached for performance? =

Yes, the plugin includes built-in caching mechanisms using WordPress transients and object cache to ensure optimal performance.

== Screenshots ==

1. Train management interface with ACF fields
2. Station database with search functionality
3. Coach configuration with seat layouts
4. API endpoint documentation
5. Mobile-optimized search results

== Changelog ==

= 3.0.0 =
* Complete rewrite with enhanced architecture
* Improved API endpoints with better error handling
* Advanced caching system implementation
* Enhanced search functionality with relevance scoring
* Better mobile optimization
* Comprehensive coach seat layout system
* Popular routes and stations tracking
* Universal search across all content types

= 2.0.0 =
* Added coach management system
* Implemented seat direction algorithms
* Enhanced API endpoints
* Added caching mechanisms

= 1.0.0 =
* Initial release
* Basic train and station management
* Simple API endpoints

== Upgrade Notice ==

= 3.0.0 =
Major update with significant improvements. Please backup your database before upgrading. This version includes breaking changes to API endpoints.

== API Documentation ==

**Base URL**: `/wp-json/rail/v1/`

**Authentication**: No authentication required for read operations.

**Rate Limiting**: Recommended to implement rate limiting on your server.

**Caching**: All endpoints support caching headers for optimal performance.

For detailed API documentation, visit: [API Documentation](https://your-site.com/api-docs)

== Support ==

For support and feature requests, please visit our [GitHub repository](https://github.com/your-repo/bd-railway-seat-guide) or contact us through the WordPress support forums.

== Privacy Policy ==

This plugin does not collect or store any personal user data. All data stored relates to railway infrastructure (trains, stations, coaches) and is considered public information.
