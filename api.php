<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

// Get action and place from query params
$action = isset($_GET['action']) ? $_GET['action'] : '';
$place = isset($_GET['place']) ? trim($_GET['place']) : '';

if ($action !== 'search' || empty($place)) {
    echo json_encode(['error' => 'Invalid request. Use ?action=search&place=CityName']);
    exit;
}

// ── 1. Geocode the place ──────────────────────────────────────────
$geoUrl = OWM_GEO_URL . '?' . http_build_query([
    'q'     => $place,
    'limit' => 1,
    'appid' => OWM_API_KEY
]);

$geoData = json_decode(fetchUrl($geoUrl), true);

if (empty($geoData)) {
    echo json_encode(['error' => 'Place not found. Please check the spelling and try again.']);
    exit;
}

$lat = $geoData[0]['lat'];
$lon = $geoData[0]['lon'];
$cityName = $geoData[0]['name'];
$country  = isset($geoData[0]['country']) ? $geoData[0]['country'] : '';
$state    = isset($geoData[0]['state']) ? $geoData[0]['state'] : '';

// ── 2. Fetch current weather ──────────────────────────────────────
$weatherUrl = OWM_WEATHER_URL . '?' . http_build_query([
    'lat'   => $lat,
    'lon'   => $lon,
    'appid' => OWM_API_KEY,
    'units' => 'metric'
]);

$weatherData = json_decode(fetchUrl($weatherUrl), true);

// ── 3. Fetch 5-day forecast ──────────────────────────────────────
$forecastUrl = OWM_FORECAST_URL . '?' . http_build_query([
    'lat'   => $lat,
    'lon'   => $lon,
    'appid' => OWM_API_KEY,
    'units' => 'metric'
]);

$forecastData = json_decode(fetchUrl($forecastUrl), true);

// Extract daily forecast (one entry per day at noon)
$dailyForecast = [];
$processedDates = [];
if (isset($forecastData['list'])) {
    foreach ($forecastData['list'] as $item) {
        $date = date('Y-m-d', $item['dt']);
        // Take the midday reading or first available for each day
        if (!in_array($date, $processedDates)) {
            $hour = (int)date('H', $item['dt']);
            if ($hour >= 11 && $hour <= 14 || count($processedDates) === 0 && !in_array($date, $processedDates)) {
                $dailyForecast[] = [
                    'date'        => $date,
                    'day_name'    => date('l', $item['dt']),
                    'temp'        => round($item['main']['temp']),
                    'temp_min'    => round($item['main']['temp_min']),
                    'temp_max'    => round($item['main']['temp_max']),
                    'humidity'    => $item['main']['humidity'],
                    'description' => $item['weather'][0]['description'],
                    'icon'        => $item['weather'][0]['icon'],
                    'wind_speed'  => round($item['wind']['speed'] * 3.6, 1) // m/s to km/h
                ];
                $processedDates[] = $date;
            }
        }
    }
}

// ── 3.5 Log search to Database ───────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $stmt = $pdo->prepare(
        "INSERT INTO search_history (user_id, search_query, resolved_city) VALUES (:uid, :query, :city)"
    );
    $stmt->execute([
        ':uid'   => $userId,
        ':query' => $place,
        ':city'  => $cityName
    ]);
} catch (PDOException $e) {
    // Ignore DB errors so search still works
}

// ── 4. Get LIVE cost data (Amadeus + Teleport) ───────────────────
$costData = getLiveCostData($place, $cityName, $lat, $lon, $country);

// ── 4.5. Fetch live exchange rate for destination currency → INR ──
$destCurrency = isset($costData['currency']) ? strtoupper($costData['currency']) : 'USD';
$exchangeRate = getLiveExchangeRate($destCurrency, 'INR');
$costData['exchange_rate_to_inr'] = $exchangeRate;
$costData['rate_source'] = $exchangeRate ? 'live' : 'estimated';

// ── 5. Get tourist places from OpenTripMap ───────────────────────
$touristPlaces = getTouristPlaces($lat, $lon);

// ── 6. Build response ────────────────────────────────────────────
$response = [
    'success' => true,
    'place' => [
        'name'    => $cityName,
        'country' => $country,
        'state'   => $state,
        'lat'     => $lat,
        'lon'     => $lon
    ],
    'current_weather' => [
        'temp'        => round($weatherData['main']['temp']),
        'feels_like'  => round($weatherData['main']['feels_like']),
        'temp_min'    => round($weatherData['main']['temp_min']),
        'temp_max'    => round($weatherData['main']['temp_max']),
        'humidity'    => $weatherData['main']['humidity'],
        'description' => $weatherData['weather'][0]['description'],
        'icon'        => $weatherData['weather'][0]['icon'],
        'wind_speed'  => round($weatherData['wind']['speed'] * 3.6, 1),
        'visibility'  => isset($weatherData['visibility']) ? $weatherData['visibility'] : null,
        'sunrise'     => date('H:i', $weatherData['sys']['sunrise']),
        'sunset'      => date('H:i', $weatherData['sys']['sunset'])
    ],
    'forecast' => $dailyForecast,
    'costs'    => $costData,
    'tourist_places' => $touristPlaces
];

echo json_encode($response, JSON_PRETTY_PRINT);

// ══════════════════════════════════════════════════════════════════
// Helper functions
// ══════════════════════════════════════════════════════════════════

function fetchUrl($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => 'TravelScope/1.0'
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($result === false || $httpCode >= 400) {
        $msg = $error ? $error : "HTTP $httpCode error";
        // Don't exit for non-critical fetches
        return json_encode(['error' => $msg]);
    }
    return $result;
}

/**
 * Fetch nearby tourist places using Geoapify Places API
 */
function getTouristPlaces($lat, $lon) {
    $url = GEO_PLACES_URL . '?' . http_build_query([
        'categories' => 'tourism.sights,tourism.attraction,entertainment.museum,building.historic',
        'filter'     => "circle:$lon,$lat,5000",
        'bias'       => "proximity:$lon,$lat",
        'limit'      => 10,
        'apiKey'     => GEO_API_KEY
    ]);

    $result = json_decode(fetchUrl($url), true);

    if (!is_array($result) || isset($result['error']) || empty($result['features'])) {
        return [];
    }

    $places = [];

    foreach ($result['features'] as $feature) {
        $props = isset($feature['properties']) ? $feature['properties'] : [];
        
        if (empty($props['name'])) continue; // Skip unnamed places

        // Extract readable categories
        $categories = isset($props['categories']) ? $props['categories'] : [];
        $kinds = [];
        foreach ($categories as $cat) {
            $parts = explode('.', $cat);
            $lastPart = end($parts);
            $lastPart = str_replace('_', ' ', $lastPart);
            if (!in_array($lastPart, ['sights', 'attraction', 'yes', 'other'])) {
                $kinds[] = $lastPart;
            }
        }
        $kinds = array_unique($kinds);
        $kinds = array_slice($kinds, 0, 3);

        // Build description from address
        $desc = '';
        if (isset($props['address_line2'])) {
            $desc = $props['address_line2'];
        }

        $place = [
            'name'        => $props['name'],
            'kinds'       => implode(',', $kinds),
            'rate'        => 0,
            'image'       => '',
            'wikipedia'   => isset($props['wiki_and_media']['wikidata']) 
                             ? 'https://www.wikidata.org/wiki/' . $props['wiki_and_media']['wikidata'] 
                             : '',
            'url'         => isset($props['website']) ? $props['website'] : '',
            'description' => $desc,
            'wiki_title'  => '',
            'wiki_lang'   => 'en',
        ];

        // Try to get a Wikidata/Wikipedia link
        if (empty($place['wikipedia']) && isset($props['datasource']['raw']['wikidata'])) {
            $place['wikipedia'] = 'https://www.wikidata.org/wiki/' . $props['datasource']['raw']['wikidata'];
        }
        if (isset($props['datasource']['raw']['wikipedia'])) {
            $wikiParts = explode(':', $props['datasource']['raw']['wikipedia'], 2);
            if (count($wikiParts) === 2) {
                $place['wikipedia'] = 'https://' . $wikiParts[0] . '.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $wikiParts[1]));
                $place['wiki_title'] = $wikiParts[1];
                $place['wiki_lang']  = $wikiParts[0];
            }
        }

        $places[] = $place;
    }

    return $places;
}


// ══════════════════════════════════════════════════════════════════
// LIVE COST DATA — Amadeus (hotels) + Teleport (food/transport/COL)
// ══════════════════════════════════════════════════════════════════

/**
 * Master function: tries Amadeus + Teleport, falls back to costs.json
 */
function getLiveCostData($searchPlace, $cityName, $lat, $lon, $countryCode) {
    $dataSources = [];

    // ── A. Teleport cost-of-living indices (free, no key) ─────────
    $teleport = getTeleportCosts($cityName, $searchPlace);
    if ($teleport) {
        $dataSources[] = 'Teleport';
    }

    // ── B. Amadeus real hotel prices ──────────────────────────────
    $amadeus = getAmadeusHotelPrice($lat, $lon);
    if ($amadeus !== null) {
        $dataSources[] = 'Amadeus';
    }

    // ── C. If we got nothing, fall back to static JSON ────────────
    if (!$teleport && $amadeus === null) {
        return getStaticCostData($searchPlace, $cityName);
    }

    // ── D. Determine currency ─────────────────────────────────────
    $currency = 'USD';
    if ($teleport && isset($teleport['currency'])) {
        $currency = $teleport['currency'];
    }

    // ── E. Build the three budget tiers ───────────────────────────
    // Hotel: from Amadeus (real) or Teleport index baseline
    $hotelBudget   = $amadeus ? $amadeus['budget']   : ($teleport ? $teleport['hotel_budget']    : 40);
    $hotelMid      = $amadeus ? $amadeus['mid_range'] : ($teleport ? $teleport['hotel_mid']      : 120);
    $hotelLuxury   = $amadeus ? $amadeus['luxury']   : ($teleport ? $teleport['hotel_luxury']    : 350);

    // Food/transport/attractions: from Teleport index or static fallback
    $foodBudget    = $teleport ? $teleport['food_budget']    : 25;
    $foodMid       = $teleport ? $teleport['food_mid']       : 55;
    $foodLuxury    = $teleport ? $teleport['food_luxury']    : 120;

    $transBudget   = $teleport ? $teleport['transport_budget']  : 10;
    $transMid      = $teleport ? $teleport['transport_mid']     : 22;
    $transLuxury   = $teleport ? $teleport['transport_luxury']  : 50;

    $attrBudget    = $teleport ? $teleport['attractions_budget'] : 12;
    $attrMid       = $teleport ? $teleport['attractions_mid']    : 28;
    $attrLuxury    = $teleport ? $teleport['attractions_luxury'] : 60;

    // ── F. Carry over highlights / travel info from static JSON ───
    $static = getStaticCostData($searchPlace, $cityName);

    return [
        'country'      => $static['country']     ?? '',
        'currency'     => $currency,
        'daily_budget' => [
            'budget' => [
                'hotel'       => $hotelBudget,
                'food'        => $foodBudget,
                'transport'   => $transBudget,
                'attractions' => $attrBudget,
                'total'       => $hotelBudget + $foodBudget + $transBudget + $attrBudget,
            ],
            'mid_range' => [
                'hotel'       => $hotelMid,
                'food'        => $foodMid,
                'transport'   => $transMid,
                'attractions' => $attrMid,
                'total'       => $hotelMid + $foodMid + $transMid + $attrMid,
            ],
            'luxury' => [
                'hotel'       => $hotelLuxury,
                'food'        => $foodLuxury,
                'transport'   => $transLuxury,
                'attractions' => $attrLuxury,
                'total'       => $hotelLuxury + $foodLuxury + $transLuxury + $attrLuxury,
            ],
        ],
        'highlights'   => $static['highlights']  ?? [],
        'best_season'  => $static['best_season'] ?? 'Varies by season',
        'visa_info'    => $static['visa_info']   ?? 'Check your country\'s visa requirements',
        'data_sources' => $dataSources,
        'amadeus_note' => $amadeus ? 'Hotel prices from live Amadeus API' : 'Hotel prices estimated',
        'teleport_note'=> $teleport ? 'Food/transport from Teleport city data' : 'Food/transport estimated',
    ];
}

// ──────────────────────────────────────────────────────────────────
// AMADEUS HOTEL PRICES
// ──────────────────────────────────────────────────────────────────

/**
 * Get Amadeus OAuth2 token. Cached in /tmp for 29 minutes.
 */
function getAmadeusToken() {
    // Skip if placeholder keys are still in place
    if (strpos(AMADEUS_API_KEY, 'PASTE_') !== false) return null;

    $cacheFile = sys_get_temp_dir() . '/travelscope_amadeus_token.json';
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (isset($cached['token']) && $cached['expires'] > time()) {
            return $cached['token'];
        }
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => AMADEUS_AUTH_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => AMADEUS_API_KEY,
            'client_secret' => AMADEUS_API_SECRET,
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'TravelScope/1.0',
    ]);
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($result && $httpCode === 200) {
        $data = json_decode($result, true);
        if (isset($data['access_token'])) {
            file_put_contents($cacheFile, json_encode([
                'token'   => $data['access_token'],
                'expires' => time() + 1740, // 29 minutes
            ]));
            return $data['access_token'];
        }
    }
    return null;
}

/**
 * Fetch real hotel nightly rates from Amadeus near given coordinates.
 * Returns ['budget'=>x, 'mid_range'=>y, 'luxury'=>z] in local currency, or null.
 */
function getAmadeusHotelPrice($lat, $lon) {
    $token = getAmadeusToken();
    if (!$token) return null;

    // Step 1: Get hotel IDs near coordinates
    $listUrl = AMADEUS_HOTELS_URL . '?' . http_build_query([
        'latitude'   => round($lat, 6),
        'longitude'  => round($lon, 6),
        'radius'     => 20,
        'radiusUnit' => 'KM',
        'ratings'    => '1,2,3,4,5',
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $listUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
        CURLOPT_USERAGENT      => 'TravelScope/1.0',
    ]);
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$result || $httpCode !== 200) return null;
    $hotelList = json_decode($result, true);
    if (empty($hotelList['data'])) return null;

    // Take up to 20 hotel IDs
    $hotelIds = array_column(array_slice($hotelList['data'], 0, 20), 'hotelId');
    if (empty($hotelIds)) return null;

    // Step 2: Get offers/prices for those hotels
    $checkIn  = date('Y-m-d', strtotime('+7 days'));
    $checkOut = date('Y-m-d', strtotime('+8 days'));

    $offersUrl = AMADEUS_OFFERS_URL . '?' . http_build_query([
        'hotelIds'   => implode(',', $hotelIds),
        'adults'     => 1,
        'checkInDate'  => $checkIn,
        'checkOutDate' => $checkOut,
        'roomQuantity' => 1,
        'currency'     => 'USD',
        'bestRateOnly' => 'true',
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $offersUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
        CURLOPT_USERAGENT      => 'TravelScope/1.0',
    ]);
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$result || $httpCode !== 200) return null;
    $offersData = json_decode($result, true);
    if (empty($offersData['data'])) return null;

    // Step 3: Extract prices and sort them
    $prices = [];
    foreach ($offersData['data'] as $hotel) {
        foreach ($hotel['offers'] ?? [] as $offer) {
            $price = floatval($offer['price']['total'] ?? 0);
            if ($price > 0) $prices[] = $price;
        }
    }
    if (empty($prices)) return null;

    sort($prices);
    $count = count($prices);

    // Budget = cheapest 25%, mid = median, luxury = top 25%
    $budget   = $prices[0];                          // cheapest available
    $mid      = $prices[intval($count * 0.5)];       // median
    $luxury   = $prices[intval($count * 0.75)];      // 75th percentile

    return [
        'budget'   => round($budget),
        'mid_range'=> round($mid),
        'luxury'   => round($luxury),
        'currency' => $offersData['data'][0]['offers'][0]['price']['currency'] ?? 'USD',
        'count'    => $count,
    ];
}

// ──────────────────────────────────────────────────────────────────
// TELEPORT COST-OF-LIVING
// ──────────────────────────────────────────────────────────────────

/**
 * Fetch Teleport cost-of-living indices for a city.
 * Returns structured budget estimates or null if city not found.
 */
function getTeleportCosts($cityName, $searchQuery) {
    // Cache file (daily)
    $slug      = null;
    $cacheKey  = preg_replace('/[^a-z0-9]/', '_', strtolower($cityName));
    $cacheFile = sys_get_temp_dir() . '/travelscope_teleport_' . $cacheKey . '_' . date('Ymd') . '.json';

    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached !== null) return $cached; // can return false (not found) too
        // null means parse failed, retry
    }

    // Step 1: Find the Teleport urban area slug
    $slug = findTeleportSlug($cityName, $searchQuery);
    if (!$slug) {
        file_put_contents($cacheFile, json_encode(false));
        return null;
    }

    // Step 2: Fetch details endpoint for cost & salary data
    $detailsUrl = TELEPORT_BASE_URL . '/urban_areas/slug:' . urlencode($slug) . '/details/';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $detailsUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_USERAGENT      => 'TravelScope/1.0',
    ]);
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$result || $httpCode !== 200) {
        file_put_contents($cacheFile, json_encode(false));
        return null;
    }

    $details = json_decode($result, true);
    if (empty($details['categories'])) {
        file_put_contents($cacheFile, json_encode(false));
        return null;
    }

    // Step 3: Extract relevant cost categories
    $costItems    = [];
    $currency     = 'USD';
    $hotelBudget  = null;
    $hotelMid     = null;
    $hotelLuxury  = null;
    $foodBudget   = null;
    $foodMid      = null;
    $transBudget  = null;

    foreach ($details['categories'] as $cat) {
        if (!isset($cat['id'], $cat['data'])) continue;
        foreach ($cat['data'] as $item) {
            $label = strtolower($item['label'] ?? '');
            $value = $item['currency_dollar_value'] ?? $item['float_value'] ?? null;
            if ($value === null) continue;

            // Hotels
            if (str_contains($label, 'hotel room') && str_contains($label, 'budget'))  $hotelBudget = $value;
            if (str_contains($label, 'hotel room') && str_contains($label, '3 stars')) $hotelMid    = $value;
            if (str_contains($label, 'hotel room') && str_contains($label, '5 stars')) $hotelLuxury = $value;

            // Food
            if (str_contains($label, 'inexpensive restaurant') || str_contains($label, 'meal, inexpensive')) $foodBudget = $value;
            if (str_contains($label, 'two people') || str_contains($label, '3-course'))                      $foodMid    = $value / 2; // per-person

            // Transport
            if (str_contains($label, 'monthly pass') || str_contains($label, 'public transport')) $transBudget = $value / 30; // daily
        }
    }

    // Step 4: Fetch scores endpoint as a supplementary signal
    $scoresUrl = TELEPORT_BASE_URL . '/urban_areas/slug:' . urlencode($slug) . '/scores/';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $scoresUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'TravelScope/1.0',
    ]);
    $scoreResult = curl_exec($ch);
    curl_close($ch);

    $costScore = 5.0; $cultureScore = 5.0;
    if ($scoreResult) {
        $scores = json_decode($scoreResult, true);
        foreach ($scores['categories'] ?? [] as $cat) {
            if (strtolower($cat['name']) === 'cost of living') $costScore    = $cat['score_out_of_10'];
            if (strtolower($cat['name']) === 'culture')        $cultureScore = $cat['score_out_of_10'];
        }
    }

    // Step 5: Fill in any missing values using score index
    // USD baseline — will be exchanged to local currency later
    $costMultiplier = (11 - $costScore) / 5; // higher score = lower cost city, invert
    if (!$hotelBudget)  $hotelBudget  = round(35  * $costMultiplier);
    if (!$hotelMid)     $hotelMid     = round(110 * $costMultiplier);
    if (!$hotelLuxury)  $hotelLuxury  = round(320 * $costMultiplier);
    if (!$foodBudget)   $foodBudget   = round(15  * $costMultiplier);
    if (!$foodMid)      $foodMid      = round(40  * $costMultiplier);
    if (!$transBudget)  $transBudget  = round(8   * $costMultiplier);

    $data = [
        'currency'           => $currency,
        'slug'               => $slug,
        'hotel_budget'       => $hotelBudget,
        'hotel_mid'          => $hotelMid,
        'hotel_luxury'       => $hotelLuxury * 1.5, // premium
        'food_budget'        => $foodBudget,
        'food_mid'           => $foodMid,
        'food_luxury'        => round($foodMid * 2.8),
        'transport_budget'   => $transBudget,
        'transport_mid'      => round($transBudget * 1.8),
        'transport_luxury'   => round($transBudget * 4),
        'attractions_budget' => round($cultureScore * 1.5),
        'attractions_mid'    => round($cultureScore * 3.5),
        'attractions_luxury' => round($cultureScore * 7),
        'cost_score'         => $costScore,
    ];

    file_put_contents($cacheFile, json_encode($data));
    return $data;
}

/**
 * Find Teleport urban area slug for a city name.
 */
function findTeleportSlug($cityName, $fallbackQuery) {
    $searchTerms = [
        strtolower(trim($cityName)),
        strtolower(trim($fallbackQuery)),
    ];

    foreach ($searchTerms as $term) {
        $searchUrl = TELEPORT_BASE_URL . '/cities/?search=' . urlencode($term) . '&embed=city%3Asearch-results%2Fcity%3Aitem%2Fcity%3Aurban_area';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $searchUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'TravelScope/1.0',
        ]);
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$result || $httpCode !== 200) continue;

        $data = json_decode($result, true);
        $items = $data['_embedded']['city:search-results'] ?? [];
        if (empty($items)) continue;

        foreach ($items as $item) {
            // Try to get urban_area slug from embedded data
            $ua = $item['_embedded']['city:item']['_links']['city:urban_area']['href'] ?? null;
            if ($ua) {
                if (preg_match('/slug:([^/]+)/', $ua, $matches)) {
                    return $matches[1];
                }
            }
        }
    }
    return null;
}

// ──────────────────────────────────────────────────────────────────
// STATIC FALLBACK (costs.json)
// ──────────────────────────────────────────────────────────────────

function getStaticCostData($searchPlace, $apiCityName) {
    $costsFile = __DIR__ . '/data/costs.json';
    $costsDb = json_decode(file_get_contents($costsFile), true);

    $searchLower = strtolower(trim($searchPlace));
    $apiLower    = strtolower(trim($apiCityName));

    if (isset($costsDb['cities'][$searchLower])) return $costsDb['cities'][$searchLower];
    if (isset($costsDb['cities'][$apiLower]))    return $costsDb['cities'][$apiLower];

    foreach ($costsDb['cities'] as $key => $data) {
        if (strpos($searchLower, $key) !== false || strpos($key, $searchLower) !== false) return $data;
        if (strpos($apiLower, $key)    !== false || strpos($key, $apiLower)    !== false) return $data;
    }

    return [
        'country'      => '',
        'currency'     => 'USD',
        'daily_budget' => [
            'budget'   => $costsDb['regional_defaults']['default'],
            'mid_range'=> multiplyBudget($costsDb['regional_defaults']['default'], 2.5),
            'luxury'   => multiplyBudget($costsDb['regional_defaults']['default'], 6),
        ],
        'highlights'   => [],
        'best_season'  => 'Varies by region',
        'visa_info'    => 'Check your country\'s visa requirements',
        'data_sources' => ['static'],
    ];
}

function multiplyBudget($budget, $multiplier) {
    $result = [];
    foreach ($budget as $key => $value) {
        $result[$key] = round($value * $multiplier);
    }
    return $result;
}

/**
 * Fetch live exchange rate using open.er-api.com (free, no key)
 */
function getLiveExchangeRate($from, $to) {
    if ($from === $to) return 1.0;

    $cacheFile = sys_get_temp_dir() . '/travelscope_fx_' . $from . '_' . $to . '_' . date('Ymd') . '.json';
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (isset($cached['rate'])) return $cached['rate'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => "https://open.er-api.com/v6/latest/{$from}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'TravelScope/1.0',
    ]);
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($result && $httpCode === 200) {
        $data = json_decode($result, true);
        if (isset($data['rates'][$to])) {
            $rate = $data['rates'][$to];
            file_put_contents($cacheFile, json_encode(['rate' => $rate]));
            return $rate;
        }
    }

    $fallbacks = [
        'USD' => 84.5, 'EUR' => 91.0, 'GBP' => 107.0, 'JPY' => 0.56,
        'AED' => 23.0, 'AUD' => 55.0, 'SGD' => 63.0,  'THB' => 2.4,
        'IDR' => 0.0053,'BRL' => 16.5,'ZAR' => 4.6,   'CZK' => 3.8,
        'TRY' => 2.6,  'EGP' => 1.75, 'MVR' => 5.5,   'KRW' => 0.063,
        'MYR' => 19.0, 'HKD' => 10.8, 'MAD' => 8.5,   'NOK' => 8.0,
        'VND' => 0.0033,'MXN'=> 4.2,  'ARS' => 0.095, 'KES' => 0.65,
        'JOD' => 119.0,'PEN' => 22.5, 'ISK' => 0.61,  'INR' => 1.0,
        'RUB' => 0.95,
    ];
    return $fallbacks[$from] ?? 84.5;
}
