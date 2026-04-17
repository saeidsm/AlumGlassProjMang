<?php
// public_html/pardis/includes/WeatherService.php

class WeatherService {
    private $config;
    
    // Tehran coordinates (default)
    private $defaultLat = 35.6892;
    private $defaultLon = 51.3890;
    
    public function __construct($config = []) {
        $this->config = array_merge([
            'provider' => 'openmeteo', // openmeteo, openweathermap, weatherapi
            'api_key' => '', // Only needed for openweathermap and weatherapi
            'language' => 'fa', // fa for Farsi
            'units' => 'metric', // metric or imperial
            'cache_duration' => 1800, // 30 minutes cache
            'cache_file' => __DIR__ . '/../cache/weather_cache.json'
        ], $config);
        
        // Create cache directory if not exists
        $cacheDir = dirname($this->config['cache_file']);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
    }
    
    /**
     * Get current weather for a location
     */
    public function getCurrentWeather($lat = null, $lon = null, $cityName = null) {
        $lat = $lat ?? $this->defaultLat;
        $lon = $lon ?? $this->defaultLon;
        
        // Check cache first
        $cacheKey = "weather_{$lat}_{$lon}";
        $cached = $this->getCache($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        // Fetch from API
        $weather = null;
        switch ($this->config['provider']) {
            case 'openmeteo':
                $weather = $this->fetchOpenMeteo($lat, $lon);
                break;
            case 'openweathermap':
                $weather = $this->fetchOpenWeatherMap($lat, $lon, $cityName);
                break;
            case 'weatherapi':
                $weather = $this->fetchWeatherAPI($lat, $lon, $cityName);
                break;
        }
        
        if ($weather) {
            $this->setCache($cacheKey, $weather);
        }
        
        return $weather;
    }
    
    /**
     * Fetch from Open-Meteo (No API key required!)
     */
    private function fetchOpenMeteo($lat, $lon) {
        $url = "https://api.open-meteo.com/v1/forecast?" . http_build_query([
            'latitude' => $lat,
            'longitude' => $lon,
            'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,rain,weather_code,wind_speed_10m',
            'timezone' => 'Asia/Tehran'
        ]);
        
        $response = $this->makeRequest($url);
        if (!$response) {
            return null;
        }
        
        $data = json_decode($response, true);
        if (!$data || !isset($data['current'])) {
            return null;
        }
        
        $current = $data['current'];
        
        return [
            'temperature' => round($current['temperature_2m']),
            'feels_like' => round($current['apparent_temperature']),
            'humidity' => $current['relative_humidity_2m'],
            'wind_speed' => round($current['wind_speed_10m']),
            'precipitation' => $current['precipitation'] ?? 0,
            'condition' => $this->mapOpenMeteoCode($current['weather_code']),
            'condition_fa' => $this->getConditionFarsi($this->mapOpenMeteoCode($current['weather_code'])),
            'icon' => $this->getWeatherIcon($this->mapOpenMeteoCode($current['weather_code'])),
            'timestamp' => time(),
            'provider' => 'Open-Meteo'
        ];
    }
    
    /**
     * Fetch from OpenWeatherMap (API key required)
     */
    private function fetchOpenWeatherMap($lat, $lon, $cityName = null) {
        if (empty($this->config['api_key'])) {
            error_log("OpenWeatherMap API key is required");
            return null;
        }
        
        $params = [
            'lat' => $lat,
            'lon' => $lon,
            'appid' => $this->config['api_key'],
            'units' => $this->config['units'],
            'lang' => 'fa'
        ];
        
        $url = "https://api.openweathermap.org/data/2.5/weather?" . http_build_query($params);
        
        $response = $this->makeRequest($url);
        if (!$response) {
            return null;
        }
        
        $data = json_decode($response, true);
        if (!$data || $data['cod'] != 200) {
            return null;
        }
        
        return [
            'temperature' => round($data['main']['temp']),
            'feels_like' => round($data['main']['feels_like']),
            'humidity' => $data['main']['humidity'],
            'wind_speed' => round($data['wind']['speed']),
            'precipitation' => $data['rain']['1h'] ?? 0,
            'condition' => strtolower($data['weather'][0]['main']),
            'condition_fa' => $data['weather'][0]['description'] ?? $this->getConditionFarsi(strtolower($data['weather'][0]['main'])),
            'icon' => $this->getWeatherIcon(strtolower($data['weather'][0]['main'])),
            'timestamp' => time(),
            'provider' => 'OpenWeatherMap',
            'city' => $data['name'] ?? 'تهران'
        ];
    }
    
    /**
     * Fetch from WeatherAPI.com (API key required)
     */
    private function fetchWeatherAPI($lat, $lon, $cityName = null) {
        if (empty($this->config['api_key'])) {
            error_log("WeatherAPI key is required");
            return null;
        }
        
        $query = $cityName ?? "{$lat},{$lon}";
        $params = [
            'key' => $this->config['api_key'],
            'q' => $query,
            'lang' => 'fa'
        ];
        
        $url = "https://api.weatherapi.com/v1/current.json?" . http_build_query($params);
        
        $response = $this->makeRequest($url);
        if (!$response) {
            return null;
        }
        
        $data = json_decode($response, true);
        if (!$data || isset($data['error'])) {
            return null;
        }
        
        $current = $data['current'];
        
        return [
            'temperature' => round($current['temp_c']),
            'feels_like' => round($current['feelslike_c']),
            'humidity' => $current['humidity'],
            'wind_speed' => round($current['wind_kph'] / 3.6), // Convert to m/s
            'precipitation' => $current['precip_mm'] ?? 0,
            'condition' => strtolower($current['condition']['text']),
            'condition_fa' => $current['condition']['text'],
            'icon' => $this->getWeatherIcon(strtolower($current['condition']['text'])),
            'timestamp' => time(),
            'provider' => 'WeatherAPI',
            'city' => $data['location']['name'] ?? 'تهران'
        ];
    }
    
    /**
     * Map Open-Meteo weather codes to conditions
     */
    private function mapOpenMeteoCode($code) {
        $codes = [
            0 => 'clear',
            1 => 'clear', 2 => 'cloudy', 3 => 'cloudy',
            45 => 'fog', 48 => 'fog',
            51 => 'drizzle', 53 => 'drizzle', 55 => 'drizzle',
            61 => 'rain', 63 => 'rain', 65 => 'rain',
            71 => 'snow', 73 => 'snow', 75 => 'snow',
            80 => 'rain', 81 => 'rain', 82 => 'rain',
            95 => 'thunderstorm', 96 => 'thunderstorm', 99 => 'thunderstorm'
        ];
        
        return $codes[$code] ?? 'clear';
    }
    
    /**
     * Get Farsi condition name
     */
    private function getConditionFarsi($condition) {
        $translations = [
            'clear' => 'آفتابی',
            'clouds' => 'ابری',
            'cloudy' => 'ابری',
            'rain' => 'بارانی',
            'drizzle' => 'نم‌نم باران',
            'thunderstorm' => 'طوفان و رعد و برق',
            'snow' => 'برفی',
            'mist' => 'مه',
            'fog' => 'مه غلیظ',
            'smoke' => 'دود',
            'haze' => 'غبار',
            'dust' => 'گرد و خاک',
            'sand' => 'ماسه',
            'ash' => 'خاکستر',
            'squall' => 'توفان',
            'tornado' => 'گردباد'
        ];
        
        $condition = strtolower($condition);
        return $translations[$condition] ?? 'نامشخص';
    }
    
    /**
     * Get weather icon emoji
     */
    private function getWeatherIcon($condition) {
        $icons = [
            'clear' => '☀️',
            'clouds' => '☁️',
            'cloudy' => '☁️',
            'rain' => '🌧️',
            'drizzle' => '🌦️',
            'thunderstorm' => '⛈️',
            'snow' => '❄️',
            'mist' => '🌫️',
            'fog' => '🌫️',
            'smoke' => '🌫️',
            'haze' => '🌫️',
            'dust' => '🌪️',
            'sand' => '🌪️'
        ];
        
        $condition = strtolower($condition);
        return $icons[$condition] ?? '🌤️';
    }
    
    /**
     * Get simple weather category for database
     */
    public function getSimpleCategory($condition) {
        $condition = strtolower($condition);
        
        if (strpos($condition, 'rain') !== false || strpos($condition, 'drizzle') !== false) {
            return 'rainy';
        }
        if (strpos($condition, 'cloud') !== false) {
            return 'cloudy';
        }
        if (strpos($condition, 'clear') !== false || strpos($condition, 'sun') !== false) {
            return 'clear';
        }
        if (strpos($condition, 'snow') !== false) {
            return 'cold';
        }
        
        return 'other';
    }
    
    /**
     * Make HTTP request
     */
    private function makeRequest($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Pardis Project Manager/1.0');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch) || $httpCode !== 200) {
            error_log("Weather API Error: " . curl_error($ch) . " (HTTP {$httpCode})");
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        return $response;
    }
    
    /**
     * Get cached data
     */
    private function getCache($key) {
        if (!file_exists($this->config['cache_file'])) {
            return null;
        }
        
        $cache = json_decode(file_get_contents($this->config['cache_file']), true);
        if (!$cache || !isset($cache[$key])) {
            return null;
        }
        
        $data = $cache[$key];
        if ($data['expires'] < time()) {
            return null;
        }
        
        return $data['data'];
    }
    
    /**
     * Set cache data
     */
    private function setCache($key, $data) {
        $cache = [];
        if (file_exists($this->config['cache_file'])) {
            $cache = json_decode(file_get_contents($this->config['cache_file']), true) ?? [];
        }
        
        $cache[$key] = [
            'data' => $data,
            'expires' => time() + $this->config['cache_duration']
        ];
        
        file_put_contents($this->config['cache_file'], json_encode($cache));
    }
    
    /**
     * Format weather for display
     */
    public function formatWeatherText($weather) {
        if (!$weather) {
            return "اطلاعات آب و هوا در دسترس نیست";
        }
        
        $text = "{$weather['icon']} {$weather['condition_fa']}\n";
        $text .= "🌡️ دما: {$weather['temperature']}°C";
        if ($weather['feels_like'] != $weather['temperature']) {
            $text .= " (احساس می‌شود: {$weather['feels_like']}°C)";
        }
        $text .= "\n💧 رطوبت: {$weather['humidity']}%\n";
        $text .= "💨 باد: {$weather['wind_speed']} m/s";
        
        if ($weather['precipitation'] > 0) {
            $text .= "\n🌧️ بارش: {$weather['precipitation']} mm";
        }
        
        return $text;
    }
}