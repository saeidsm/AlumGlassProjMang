<?php
// public_html/pardis/telegram_config.php

// Telegram Bot Configuration
// Get your bot token from @BotFather on Telegram
// Get your chat/group ID from @userinfobot or @RawDataBot

return [
    // Your Telegram Bot Token (from @BotFather)
    'bot_token' => '8488060252:AAEiXEMhCO4DPFiZo_uRUNS3anUdp96OmfU', 
     'proxy_url' => 'https://pt.sabaat.ir',
    // Your Telegram Group/Chat ID (negative number for groups)
    'chat_id' => '104884775', 
    
    // Alternative: Multiple chat IDs if you want to send to multiple groups
    'chat_ids' => [
        '104884775', // Main project group
       
    ],
    
    // Report Settings
    'send_time' => '10:00', // Time to send daily report (24-hour format)
    'timezone' => 'Asia/Tehran',
    
    // Message Settings
    'parse_mode' => 'HTML', // HTML or Markdown
    'disable_notification' => false, // Set to true for silent notifications
    
    // What to include in reports
    'include_images' => true, // Include activity images if available
    'include_stats' => true, // Include summary statistics
    'include_issues' => true, // Include reported issues
    'include_tomorrow_plan' => true, // Include next day plans
    
    // Minimum report threshold (skip users with less activities)
    'min_activities' => 0, // Set to 0 to include all users
];