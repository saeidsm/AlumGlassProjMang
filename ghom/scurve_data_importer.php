<?php
/**
 * S-Curve Data Importer
 * One-time script to populate scurve_data table from chart
 * Using Gregorian dates
 * 
 * Usage: Run this file once in browser: http://localhost/ghom/scurve_data_importer.php
 * After successful import, DELETE THIS FILE for security
 */

require_once __DIR__ . '/../sercon/bootstrap.php';
secureSession();

// Security check
if (!isLoggedIn() || !in_array($_SESSION['role'], ['superuser', 'admin'])) {
    die("Access Denied - Superuser Only");
}

$pdo = getProjectDBConnection('ghom');

// Plan Data (Complete Project Timeline) - Gregorian Dates
$planData = [
    ['2025-09-05', 0.00, 0.00],    // Week 1
    ['2025-09-12', 0.20, 0.20],    // Week 2
    ['2025-09-19', 1.20, 1.40],    // Week 3
    ['2025-09-26', 1.50, 2.90],    // Week 4
    ['2025-10-03', 0.90, 3.80],    // Week 5
    ['2025-10-10', 1.30, 5.10],    // Week 6
    ['2025-10-17', 1.50, 6.60],    // Week 7
    ['2025-10-24', 0.82, 7.42],    // Week 8
    ['2025-10-31', 1.88, 9.30],    // Week 9
    ['2025-11-07', 2.30, 11.60],   // Week 10
    ['2025-11-14', 4.86, 16.46],   // Week 11
    ['2025-11-21', 3.15, 19.61],   // Week 12
    ['2025-11-28', 4.82, 24.43],   // Week 13
    ['2025-12-05', 3.11, 27.54],   // Week 14
    ['2025-12-12', 3.10, 30.64],   // Week 15
    ['2025-12-19', 9.55, 40.19],   // Week 16
    ['2025-12-26', 10.22, 50.41],  // Week 17
    ['2026-01-02', 10.51, 60.92],  // Week 18
    ['2026-01-09', 8.56, 69.48],   // Week 19
    ['2026-01-16', 11.22, 80.70],  // Week 20
    ['2026-01-20', 19.30, 100.00], // Week 21 - Project End
];

// Actual Data (Progress as of 1404/9/14 = 2025-12-05)
$actualData = [
    ['2025-09-05', 0.00],
    ['2025-09-12', 0.00],
    ['2025-09-19', 0.00],
    ['2025-09-26', 0.00],
    ['2025-10-03', 0.08],
    ['2025-10-10', 0.29],
    ['2025-10-17', 0.88],
    ['2025-10-24', 1.04],
    ['2025-10-31', 2.47],
    ['2025-11-07', 11.60],
    ['2025-11-14', 16.46],
    ['2025-11-21', 19.61],
    ['2025-11-28', 24.43],
    ['2025-12-05', 27.54],
    ['2025-12-12', 29.93],
    ['2025-12-19', 32.02],
    ['2025-12-26', 34.68],
];

try {
    $pdo->beginTransaction();
    
    echo "<h2>S-Curve Data Import (Gregorian Dates)</h2>";
    echo "<pre>";
    
    // 1. Insert Plan Baseline
    echo "Step 1: Inserting Plan Baseline...\n";
    $stmtPlan = $pdo->prepare("
        INSERT INTO scurve_data 
        (report_date, date_point, block_type, plan_periodic, plan_cumulative, actual_cumulative)
        VALUES ('PLAN_BASELINE', ?, 'total', ?, ?, 0)
        ON DUPLICATE KEY UPDATE
            plan_periodic = VALUES(plan_periodic),
            plan_cumulative = VALUES(plan_cumulative)
    ");
    
    $planCount = 0;
    foreach ($planData as $row) {
        $stmtPlan->execute([$row[0], $row[1], $row[2]]);
        $planCount++;
        echo "  ✓ {$row[0]}: Periodic = {$row[1]}%, Cumulative = {$row[2]}%\n";
    }
    echo "Plan baseline: $planCount records inserted\n\n";
    
    // 2. Insert Actual Progress
    echo "Step 2: Inserting Actual Progress (as of 1404/9/14 / 2025-12-05)...\n";
    $stmtActual = $pdo->prepare("
        INSERT INTO scurve_data 
        (report_date, date_point, block_type, plan_periodic, plan_cumulative, actual_cumulative)
        VALUES ('1404/9/14', ?, 'total', 0, 0, ?)
        ON DUPLICATE KEY UPDATE
            actual_cumulative = VALUES(actual_cumulative)
    ");
    
    $actualCount = 0;
    foreach ($actualData as $row) {
        $stmtActual->execute([$row[0], $row[1]]);
        $actualCount++;
        echo "  ✓ {$row[0]}: Actual = {$row[1]}%\n";
    }
    echo "Actual progress: $actualCount records inserted\n\n";
    
    // 3. Verify Combined View
    echo "Step 3: Verification - Combined View:\n";
    echo str_repeat("-", 90) . "\n";
    printf("%-12s | %10s | %10s | %10s | %10s | %s\n", 
        "Date", "Plan Per.", "Plan Cum.", "Actual", "Deviation", "Status");
    echo str_repeat("-", 90) . "\n";
    
    $stmtVerify = $pdo->query("
        SELECT 
            p.date_point,
            p.plan_periodic,
            p.plan_cumulative,
            COALESCE(a.actual_cumulative, 0) as actual_cumulative,
            (COALESCE(a.actual_cumulative, 0) - p.plan_cumulative) as deviation
        FROM scurve_data p
        LEFT JOIN scurve_data a 
            ON p.date_point = a.date_point 
            AND p.block_type = a.block_type 
            AND a.report_date = '1404/9/14'
        WHERE p.report_date = 'PLAN_BASELINE'
          AND p.block_type = 'total'
        ORDER BY p.date_point
    ");
    
    while ($row = $stmtVerify->fetch(PDO::FETCH_ASSOC)) {
        $dev = $row['deviation'];
        $status = '';
        if ($row['actual_cumulative'] > 0) {
            if ($dev < -2) {
                $status = '🔴 Behind';
            } elseif ($dev > 2) {
                $status = '🟢 Ahead';
            } else {
                $status = '🟡 On Track';
            }
        } else {
            $status = '⚪ Not Started';
        }
        
        printf("%-12s | %9.2f%% | %9.2f%% | %9.2f%% | %9.2f%% | %s\n",
            $row['date_point'],
            $row['plan_periodic'],
            $row['plan_cumulative'],
            $row['actual_cumulative'],
            $row['deviation'],
            $status
        );
    }
    
    $pdo->commit();
    
    echo "\n" . str_repeat("=", 90) . "\n";
    echo "✅ SUCCESS! Data imported successfully.\n";
    echo "   Plan Records: $planCount (21 weeks)\n";
    echo "   Actual Records: $actualCount (17 weeks)\n";
    echo "   Report Date: 1404/9/14 (2025-12-05)\n";
    echo "\n📊 Current Status:\n";
    echo "   - Plan: 27.54% (Week 14)\n";
    echo "   - Actual: 27.54% (Week 14)\n";
    echo "   - Latest Actual: 34.68% (Week 17 - 2025-12-26)\n";
    echo "\n🔒 SECURITY: Please DELETE this file after successful import!\n";
    echo str_repeat("=", 90) . "\n";
    echo "</pre>";
    
    echo "<p><a href='weekly_reports.php?date=1404/9/14' style='padding:10px 20px; background:#48bb78; color:white; text-decoration:none; border-radius:5px;'>View Report</a></p>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<pre style='color:red;'>";
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    echo "</pre>";
}