<?php
require_once __DIR__ . '/../sercon/bootstrap.php';
echo "<pre>";

$csv_file = __DIR__ . '/Book1.csv';
if (!file_exists($csv_file)) {
    die("Error: Book1.csv not found in the ghom directory.");
}

try {
    $pdo = getProjectDBConnection('ghom');
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO workshop_qc (element_id, initial_status, tolerance_length, tolerance_width, tolerance_thickness, tolerance_bow_lat, tolerance_diag_25, tolerance_diag_36, tolerance_bow_long, damage_description, final_status) 
         VALUES (:element_id, :initial_status, :tol_len, :tol_wid, :tol_thk, :tol_bow_l, :tol_d25, :tol_d36, :tol_bow_long, :damage, :final_status)
         ON DUPLICATE KEY UPDATE 
         initial_status=VALUES(initial_status), damage_description=VALUES(damage_description), final_status=VALUES(final_status)"
    );

    $file = fopen($csv_file, 'r');
    fgetcsv($file); // Skip header row

    $count = 0;
    while (($row = fgetcsv($file)) !== FALSE) {
        // Clean up panel code
        $panel_code = str_replace(' ', '', $row[1]);

        // Determine status
        $initial_status = null;
        if (strpos(strtolower($panel_code), 'rej') !== false || $row[2] === 'ReJ') {
            $initial_status = 'Reject';
        } elseif ($row[2] === 'O') {
            $initial_status = 'OK';
        } elseif (!empty($row[10])) {
            $initial_status = 'Repair';
        }

        $final_status = 'In_Repair';
        if ($initial_status === 'OK') $final_status = 'Usable';
        if ($initial_status === 'Reject') $final_status = 'Rejected';

        $params = [
            ':element_id' => $panel_code,
            ':initial_status' => $initial_status,
            ':tol_len' => empty($row[3]) ? null : (float)$row[3],
            ':tol_wid' => empty($row[4]) ? null : (float)$row[4],
            ':tol_thk' => empty($row[5]) ? null : (float)$row[5],
            ':tol_bow_l' => empty($row[6]) ? null : (float)$row[6],
            ':tol_d25' => empty($row[7]) ? null : (float)$row[7],
            ':tol_d36' => empty($row[8]) ? null : (float)$row[8],
            ':tol_bow_long' => empty($row[9]) ? null : (float)$row[9],
            ':damage' => $row[10] ?: null,
            ':final_status' => $final_status
        ];
        
        $stmt->execute($params);
        $count++;
    }

    $pdo->commit();
    echo "<h2>Success!</h2><p>{$count} records have been imported/updated in the `workshop_qc` table.</p>";

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    die("<h2>Error:</h2><pre>" . $e->getMessage() . "</pre>");
}
echo "</pre>";