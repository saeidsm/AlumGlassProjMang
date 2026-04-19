<?php
// public_html/pardis/packing_list_api.php

require_once __DIR__ . '/../sercon/bootstrap.php';
secureSession();
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

$pdo = getProjectDBConnection('pardis');

function getPackingListData($pdo) {
    try {
        // Get detailed lists with receipt_date ordering
        $profiles = $pdo->query("
            SELECT p.*, COALESCE(SUM(it.quantity_taken), 0) as total_taken
            FROM profiles p
            LEFT JOIN inventory_transactions it ON p.id = it.item_id AND it.item_type = 'profile'
            GROUP BY p.id
            ORDER BY p.receipt_date DESC, p.item_code ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $accessories = $pdo->query("
            SELECT a.*, COALESCE(SUM(it.quantity_taken), 0) as total_taken
            FROM accessories a
            LEFT JOIN inventory_transactions it ON a.id = it.item_id AND it.item_type = 'accessory'
            GROUP BY a.id
            ORDER BY a.receipt_date DESC, a.item_code ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $remaining_profiles = $pdo->query("
            SELECT * FROM remaining_profiles ORDER BY item_code ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $remaining_accessories = $pdo->query("
            SELECT * FROM remaining_accessories ORDER BY item_code ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Add stock calculation to profiles
        foreach ($profiles as &$profile) {
            $profile['stock'] = floatval($profile['quantity']) - floatval($profile['total_taken']);
        }
        
        // Add stock calculation to accessories
        foreach ($accessories as &$accessory) {
            $accessory['stock'] = floatval($accessory['quantity']) - floatval($accessory['total_taken']);
        }

        // Calculate profile summary (grouped by item_code)
        $profile_summary_raw = [];
        foreach ($profiles as $profile) {
            $code = $profile['item_code'];
            if (!isset($profile_summary_raw[$code])) {
                $profile_summary_raw[$code] = [
                    'item_code' => $code,
                    'total_received' => 0,
                    'total_taken' => 0,
                    'stock' => 0,
                    'total_length' => 0,
                    'sheets' => [],
                    'image_file' => $profile['image_file'] ?? null
                ];
            }
            $profile_summary_raw[$code]['total_received'] += floatval($profile['quantity'] ?? 0);
            $profile_summary_raw[$code]['total_taken'] += floatval($profile['total_taken'] ?? 0);
            $profile_summary_raw[$code]['stock'] += floatval($profile['stock'] ?? 0);
            $profile_summary_raw[$code]['total_length'] += floatval($profile['length'] ?? 0) * floatval($profile['quantity'] ?? 0);
            if (!empty($profile['sheet_name'])) {
                $profile_summary_raw[$code]['sheets'][] = $profile['sheet_name'];
            }
        }
        
        // Finalize profile summary
        $profile_summary = [];
        foreach ($profile_summary_raw as $code => $data) {
            $data['sheets'] = array_unique($data['sheets']);
            $data['sheet_count'] = count($data['sheets']);
            $profile_summary[] = $data;
        }

        // Calculate accessory summary (grouped by item_code)
        $accessory_summary_raw = [];
        foreach ($accessories as $accessory) {
            $code = $accessory['item_code'];
            if (!isset($accessory_summary_raw[$code])) {
                $accessory_summary_raw[$code] = [
                    'item_code' => $code,
                    'total_received' => 0,
                    'total_taken' => 0,
                    'stock' => 0,
                    'sheets' => [],
                    'image_file' => $accessory['image_file'] ?? null
                ];
            }
            $accessory_summary_raw[$code]['total_received'] += floatval($accessory['quantity'] ?? 0);
            $accessory_summary_raw[$code]['total_taken'] += floatval($accessory['total_taken'] ?? 0);
            $accessory_summary_raw[$code]['stock'] += floatval($accessory['stock'] ?? 0);
            if (!empty($accessory['sheet_name'])) {
                $accessory_summary_raw[$code]['sheets'][] = $accessory['sheet_name'];
            }
        }
        
        // Finalize accessory summary
        $accessory_summary = [];
        foreach ($accessory_summary_raw as $code => $data) {
            $data['sheets'] = array_unique($data['sheets']);
            $data['sheet_count'] = count($data['sheets']);
            $accessory_summary[] = $data;
        }

        return [
            'success' => true,
            'profiles' => $profiles,
            'accessories' => $accessories,
            'remaining_profiles' => $remaining_profiles,
            'remaining_accessories' => $remaining_accessories,
            'profile_summary' => $profile_summary,
            'accessory_summary' => $accessory_summary
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

echo json_encode(getPackingListData($pdo));
?>