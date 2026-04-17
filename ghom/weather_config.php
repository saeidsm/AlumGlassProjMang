<?php
// public_html/pardis/weather_config.php

return [
    // Weather API Provider
    // Options: 'openmeteo' (no key needed), 'openweathermap', 'weatherapi'
    'provider' => 'openmeteo',
    
    // API Keys (only needed for openweathermap and weatherapi)
    // Get free key from:
    // - OpenWeatherMap: https://openweathermap.org/api
    // - WeatherAPI: https://www.weatherapi.com/signup.aspx
    'api_key' => '',
    
    // Default location (Tehran)
    'default_location' => [
        'latitude' => 35.6892,
        'longitude' => 51.3890,
        'city' => 'تهران'
    ],
    
    // Project locations - add your project sites
    'project_locations' => [
        'pardis' => [
            'name' => 'پروژه بیمارستان هزار تخت خوابی قم',
            'latitude' => 34.634896,
            'longitude' => 410.861982,
            'city' => 'قم'
        ],
        'tehran_central' => [
            'name' => 'تهران مرکزی',
            'latitude' => 35.6892,
            'longitude' => 51.3890,
            'city' => 'تهران'
        ]
    ],
    
    // Language
    'language' => 'fa',
    
    // Units: 'metric' (Celsius) or 'imperial' (Fahrenheit)
    'units' => 'metric',
    
    // Cache settings
    'cache_duration' => 1800, // 30 minutes
    
    // Auto-fetch weather when creating reports
    'auto_fetch' => true
];