<?php
// OpenWeatherMap API Configuration
define('OWM_API_KEY', '2b99d61b56b07d9fe809c2d89bf4acd9');
define('OWM_GEO_URL', 'https://api.openweathermap.org/geo/1.0/direct');
define('OWM_WEATHER_URL', 'https://api.openweathermap.org/data/2.5/weather');
define('OWM_FORECAST_URL', 'https://api.openweathermap.org/data/2.5/forecast');

// Geoapify Places API Configuration
define('GEO_API_KEY', '9cc5cecf64764064856c7a69dc8660b5');
define('GEO_PLACES_URL', 'https://api.geoapify.com/v2/places');

// ─── Amadeus Hotel API ────────────────────────────────────────────
// Get your FREE key at: https://developers.amadeus.com
// 1. Register → Create App → copy "API Key" and "API Secret" below
define('AMADEUS_API_KEY',    'PASTE_YOUR_AMADEUS_API_KEY_HERE');
define('AMADEUS_API_SECRET', 'PASTE_YOUR_AMADEUS_API_SECRET_HERE');
define('AMADEUS_AUTH_URL',   'https://test.api.amadeus.com/v1/security/oauth2/token');
define('AMADEUS_HOTELS_URL', 'https://test.api.amadeus.com/v1/reference-data/locations/hotels/by-geocode');
define('AMADEUS_OFFERS_URL', 'https://test.api.amadeus.com/v3/shopping/hotel-offers');

// ─── Teleport Cost-of-Living API (FREE, no key needed) ───────────
define('TELEPORT_BASE_URL',  'https://api.teleport.org/api');

// ─── Database Configuration ──────────────────────────────────────
define('DB_HOST', 'sql306.infinityfree.com');
define('DB_USER', 'if0_41473986');
define('DB_PASS', 'SRoyand2005');
define('DB_NAME', 'if0_41473986_travel');

// ─── Admin Dashboard Authentication ──────────────────────────────
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'travel123');

