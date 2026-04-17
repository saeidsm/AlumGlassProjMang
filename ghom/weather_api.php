<?php
// public_html/pardis/weather_api.php

require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/WeatherService.php';

header('Content-Type: application/json; charset=utf-8');

secureSession();

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Load weather configuration
$weatherConfig = require __DIR__ . '/weather_config.php';

// Get location from request
$location = $_GET['location'] ?? 'default';
$lat = $_GET['lat'] ?? null;
$lon = $_GET['lon'] ?? null;

// Use project location if specified
if ($location !== 'default' && isset($weatherConfig['project_locations'][$location])) {
    $projectLocation = $weatherConfig['project_locations'][$location];
    $lat = $projectLocation['latitude'];
    $lon = $projectLocation['longitude'];
    $cityName = $projectLocation['city'];
} elseif ($lat === null || $lon === null) {
    // Use default location
    $lat = $weatherConfig['default_location']['latitude'];
    $lon = $weatherConfig['default_location']['longitude'];
    $cityName = $weatherConfig['default_location']['city'];
}

// Initialize weather service
$weatherService = new WeatherService([
    'provider' => $weatherConfig['provider'],
    'api_key' => $weatherConfig['api_key'] ?? '',
    'language' => $weatherConfig['language'],
    'units' => $weatherConfig['units'],
    'cache_duration' => $weatherConfig['cache_duration']
]);

// Get current weather
$weather = $weatherService->getCurrentWeather($lat, $lon, $cityName ?? null);

if (!$weather) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch weather data'
    ]);
    exit;
}

// Add formatted text
$weather['formatted_text'] = $weatherService->formatWeatherText($weather);
$weather['simple_category'] = $weatherService->getSimpleCategory($weather['condition']);

echo json_encode([
    'success' => true,
    'data' => $weather
]);