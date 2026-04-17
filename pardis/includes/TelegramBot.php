<?php
// public_html/pardis/includes/TelegramBot.php

class TelegramBot {
    private $botToken;
    private $apiUrl;
    private $config;
    private $useProxy;
    private $proxyUrl;
    
    public function __construct($config) {
        $this->config = $config;
        $this->botToken = $config['bot_token'];
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}/";
        
        // Check if proxy is enabled
        $this->useProxy = !empty($config['proxy_url']);
        $this->proxyUrl = $config['proxy_url'] ?? '';
    }
    
    /**
     * Send a message to Telegram
     */
    public function sendMessage($chatId, $text, $parseMode = 'HTML', $disableNotification = false) {
        $url = $this->apiUrl . 'sendMessage';
        
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_notification' => $disableNotification
        ];
        
        return $this->makeRequest($url, $data);
    }
    
    /**
     * Send a photo to Telegram
     */
    public function sendPhoto($chatId, $photoPath, $caption = '', $parseMode = 'HTML') {
        $url = $this->apiUrl . 'sendPhoto';
        
        $data = [
            'chat_id' => $chatId,
            'caption' => $caption,
            'parse_mode' => $parseMode
        ];
        
        // If photo is a URL
        if (filter_var($photoPath, FILTER_VALIDATE_URL)) {
            $data['photo'] = $photoPath;
            return $this->makeRequest($url, $data);
        }
        
        // If photo is a local file
        if (file_exists($photoPath)) {
            $data['photo'] = new CURLFile($photoPath);
            return $this->makeRequest($url, $data, true);
        }
        
        return false;
    }
    
    /**
     * Send multiple photos as album
     */
    public function sendMediaGroup($chatId, $mediaArray) {
        $url = $this->apiUrl . 'sendMediaGroup';
        
        $data = [
            'chat_id' => $chatId,
            'media' => json_encode($mediaArray)
        ];
        
        return $this->makeRequest($url, $data);
    }
    
    /**
     * Send document to Telegram
     */
    public function sendDocument($chatId, $documentPath, $caption = '') {
        $url = $this->apiUrl . 'sendDocument';
        
        $data = [
            'chat_id' => $chatId,
            'caption' => $caption
        ];
        
        if (file_exists($documentPath)) {
            $data['document'] = new CURLFile($documentPath);
            return $this->makeRequest($url, $data, true);
        }
        
        return false;
    }
    
    /**
     * Make HTTP request to Telegram API (with proxy support)
     */
    private function makeRequest($url, $data, $isMultipart = false) {
        if ($this->useProxy && !empty($this->proxyUrl)) {
            // Use proxy for all requests
            return $this->makeProxyRequest($url, $data, $isMultipart);
        } else {
            // Direct request to Telegram (no proxy)
            return $this->makeDirectRequest($url, $data, $isMultipart);
        }
    }
    
    /**
     * Make request through Cloudflare Worker proxy
     */
    private function makeProxyRequest($url, $data, $isMultipart = false) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->proxyUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        if ($isMultipart) {
            // For file uploads, add URL to form data
            $data['url'] = $url;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            // For JSON requests, wrap in proxy format
            $proxyData = json_encode([
                'url' => $url,
                'data' => $data
            ]);
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, $proxyData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
        }
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("Telegram Proxy Error: " . $error);
            error_log("Proxy URL: " . $this->proxyUrl);
            return false;
        }
        
        curl_close($ch);
        
        $response = json_decode($result, true);
        
        if ($httpCode !== 200) {
            error_log("Telegram Proxy HTTP Error: " . $httpCode);
            error_log("Response: " . $result);
            return false;
        }
        
        if (!$response || !isset($response['ok']) || !$response['ok']) {
            error_log("Telegram API Error via Proxy: " . json_encode($response));
            return false;
        }
        
        return $response;
    }
    
    /**
     * Make direct request to Telegram API
     */
    private function makeDirectRequest($url, $data, $isMultipart = false) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        if ($isMultipart) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("Telegram Direct Connection Error: " . $error);
            return false;
        }
        
        curl_close($ch);
        
        $response = json_decode($result, true);
        
        if ($httpCode !== 200 || !$response['ok']) {
            error_log("Telegram API Error: " . json_encode($response));
            return false;
        }
        
        return $response;
    }
    
    /**
     * Format text for Telegram HTML
     */
public static function escapeHtml($text) {
    // Handle null or empty values
    if ($text === null || $text === '') {
        return '';
    }
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
    
    /**
     * Split long message into chunks
     */
    public static function splitMessage($text, $maxLength = 4096) {
        if (strlen($text) <= $maxLength) {
            return [$text];
        }
        
        $chunks = [];
        $lines = explode("\n", $text);
        $currentChunk = '';
        
        foreach ($lines as $line) {
            if (strlen($currentChunk . $line . "\n") > $maxLength) {
                if (!empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                    $currentChunk = '';
                }
                
                // If single line is too long, split it
                if (strlen($line) > $maxLength) {
                    $chunks[] = substr($line, 0, $maxLength);
                    continue;
                }
            }
            
            $currentChunk .= $line . "\n";
        }
        
        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }
        
        return $chunks;
    }
}