<?php

// Your existing connection and security setup
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$expected_project_key = 'pardis';
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    echo json_encode(['error' => 'Invalid project context']);
    exit();
}

// For this setup script, you might want to restrict it to admin users
if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Permission denied. Only admins can run this setup.']);
    exit();
}


/**
 * Converts a Jalali date string to a Gregorian date string.
 */
function toGregorian($jalaliDate)
{
    if (empty($jalaliDate) || !is_string($jalaliDate)) {
        return null;
    }

    $parts = array_map('intval', preg_split('/[-\/]/', trim($jalaliDate)));
    if (count($parts) !== 3 || $parts[0] < 1300) {
        return null;
    }

    if (function_exists('jalali_to_gregorian')) {
        return implode('-', jalali_to_gregorian($parts[0], $parts[1], $parts[2]));
    }

    return null;
}

try {
    // Establish connection to the database.
    // The connection should target the 'pardis' database or have permissions to create it.
    $pdo = getProjectDBConnection('pardis');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // -- Part 1: Create Tables and Views --
    // These statements are terminated by a semicolon and can be executed together.
    $sql_tables_and_views = "
        CREATE TABLE IF NOT EXISTS profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_code VARCHAR(100) NOT NULL,
            length DECIMAL(10,2) COMMENT 'Length in mm',
            quantity DECIMAL(10,2),
            uom VARCHAR(20) COMMENT 'Unit of Measurement',
            column1_content TEXT COMMENT 'Description from Column1 (e.g., RAL color, texture)',
            image_file VARCHAR(255),
            sheet_name VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_item_code (item_code),
            INDEX idx_sheet (sheet_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Received profiles data';

        CREATE TABLE IF NOT EXISTS accessories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_code VARCHAR(100) NOT NULL,
            length DECIMAL(10,2) COMMENT 'Length in mm',
            quantity DECIMAL(10,2),
            uom VARCHAR(20) COMMENT 'Unit of Measurement',
            origin VARCHAR(100) COMMENT 'Menşe / Origin',
            pallet_no VARCHAR(50) COMMENT 'Palet Numarası / Pallet No',
            image_file VARCHAR(255),
            sheet_name VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_item_code (item_code),
            INDEX idx_sheet (sheet_name),
            INDEX idx_pallet (pallet_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Received accessories data';

        CREATE TABLE IF NOT EXISTS remaining_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            part_no VARCHAR(100) COMMENT 'Part No',
            no VARCHAR(50) COMMENT 'No',
            package VARCHAR(100) COMMENT 'Package/Shape',
            item_code VARCHAR(100) NOT NULL,
            item_name VARCHAR(255) COMMENT 'Item description',
            type_of_service VARCHAR(100),
            lot VARCHAR(100),
            length DECIMAL(10,2) COMMENT 'Length in mm',
            uom2 VARCHAR(20),
            qty1 DECIMAL(10,2),
            uom1 VARCHAR(20),
            qty2 DECIMAL(10,2),
            origin VARCHAR(100),
            image_file VARCHAR(255),
            sheet_name VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_item_code (item_code),
            INDEX idx_package (package)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Remaining profiles to be received';

        CREATE TABLE IF NOT EXISTS remaining_accessories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            no VARCHAR(50) COMMENT 'No',
            package VARCHAR(100) COMMENT 'Package/Shape',
            item_code VARCHAR(100) NOT NULL,
            item_name VARCHAR(255) COMMENT 'Item description',
            uom3 VARCHAR(20),
            qty3 DECIMAL(10,2),
            description TEXT,
            image_file VARCHAR(255),
            sheet_name VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_item_code (item_code),
            INDEX idx_package (package)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Remaining accessories to be received';

        CREATE OR REPLACE VIEW v_profile_summary AS
        SELECT 
            item_code,
            COUNT(*) as entry_count,
            SUM(quantity) as total_quantity,
            SUM(length * quantity) as total_length_mm,
            ROUND(SUM(length * quantity) / 1000, 2) as total_length_m,
            GROUP_CONCAT(DISTINCT sheet_name) as sheets,
            MIN(created_at) as first_received,
            MAX(created_at) as last_received
        FROM profiles
        GROUP BY item_code
        ORDER BY item_code;

        CREATE OR REPLACE VIEW v_accessory_summary AS
        SELECT 
            item_code,
            COUNT(*) as entry_count,
            SUM(quantity) as total_quantity,
            uom,
            GROUP_CONCAT(DISTINCT origin) as origins,
            GROUP_CONCAT(DISTINCT pallet_no) as pallets,
            GROUP_CONCAT(DISTINCT sheet_name) as sheets,
            MIN(created_at) as first_received,
            MAX(created_at) as last_received
        FROM accessories
        GROUP BY item_code, uom
        ORDER BY item_code;

        CREATE OR REPLACE VIEW v_all_items AS
        SELECT 
            id, 'profile' as item_type, item_code, length, quantity, uom,
            NULL as origin, NULL as pallet_no, column1_content as description,
            image_file, sheet_name, created_at
        FROM profiles
        UNION ALL
        SELECT 
            id, 'accessory' as item_type, item_code, length, quantity, uom,
            origin, pallet_no, NULL as description,
            image_file, sheet_name, created_at
        FROM accessories
        ORDER BY created_at DESC;

        CREATE OR REPLACE VIEW v_remaining_items AS
        SELECT 
            'profile' as item_type, item_code, item_name, length,
            qty1 as quantity, uom1 as uom, origin, image_file, sheet_name
        FROM remaining_profiles
        UNION ALL
        SELECT 
            'accessory' as item_type, item_code, item_name, NULL as length,
            qty3 as quantity, uom3 as uom, NULL as origin, image_file, sheet_name
        FROM remaining_accessories
        ORDER BY item_code;
    ";
    
    $pdo->exec($sql_tables_and_views);

    // -- Part 2: Stored Procedures --
    // Each procedure is executed as a single, separate statement.
    
    $sp_get_profile_stats = "
        CREATE PROCEDURE sp_get_profile_stats()
        BEGIN
            SELECT 
                COUNT(DISTINCT item_code) as unique_profiles,
                COUNT(*) as total_entries,
                SUM(quantity) as total_pieces,
                ROUND(SUM(length * quantity) / 1000, 2) as total_length_m,
                COUNT(DISTINCT sheet_name) as total_sheets
            FROM profiles;
        END
    ";
    $pdo->exec($sp_get_profile_stats);

    $sp_get_accessory_stats = "
        CREATE PROCEDURE sp_get_accessory_stats()
        BEGIN
            SELECT 
                COUNT(DISTINCT item_code) as unique_accessories,
                COUNT(*) as total_entries,
                SUM(quantity) as total_pieces,
                COUNT(DISTINCT pallet_no) as total_pallets,
                COUNT(DISTINCT sheet_name) as total_sheets
            FROM accessories;
        END
    ";
    $pdo->exec($sp_get_accessory_stats);

    $sp_search_items = "
        CREATE PROCEDURE sp_search_items(IN search_term VARCHAR(100))
        BEGIN
            SELECT * FROM v_all_items 
            WHERE item_code LIKE CONCAT('%', search_term, '%')
               OR description LIKE CONCAT('%', search_term, '%')
            ORDER BY item_type, item_code;
        END
    ";
    $pdo->exec($sp_search_items);
    
    $sp_get_item_details = "
        CREATE PROCEDURE sp_get_item_details(IN code VARCHAR(100))
        BEGIN
            SELECT 'PROFILES' as section;
            SELECT * FROM profiles WHERE item_code = code;
            SELECT 'ACCESSORIES' as section;
            SELECT * FROM accessories WHERE item_code = code;
            SELECT 'REMAINING PROFILES' as section;
            SELECT * FROM remaining_profiles WHERE item_code = code;
            SELECT 'REMAINING ACCESSORIES' as section;
            SELECT * FROM remaining_accessories WHERE item_code = code;
        END
    ";
    $pdo->exec($sp_get_item_details);

    $sp_cleanup_old_data = "
        CREATE PROCEDURE sp_cleanup_old_data(IN days_old INT)
        BEGIN
            DELETE FROM profiles WHERE created_at < DATE_SUB(NOW(), INTERVAL days_old DAY);
            DELETE FROM accessories WHERE created_at < DATE_SUB(NOW(), INTERVAL days_old DAY);
            DELETE FROM remaining_profiles WHERE created_at < DATE_SUB(NOW(), INTERVAL days_old DAY);
            DELETE FROM remaining_accessories WHERE created_at < DATE_SUB(NOW(), INTERVAL days_old DAY);
            
            SELECT CONCAT('Cleaned up records older than ', days_old, ' days') as result;
        END
    ";
    $pdo->exec($sp_cleanup_old_data);

    // -- Part 3: Optimize Tables --
    $sql_optimize = "
        OPTIMIZE TABLE profiles;
        OPTIMIZE TABLE accessories;
        OPTIMIZE TABLE remaining_profiles;
        OPTIMIZE TABLE remaining_accessories;
    ";
    $pdo->exec($sql_optimize);

    // If all executions were successful
    echo json_encode(['success' => 'Database schema created and optimized successfully.']);

} catch (PDOException $e) {
    // If any error occurs, return it in the JSON response
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'error' => 'Database setup failed',
        'message' => $e->getMessage()
    ]);
}