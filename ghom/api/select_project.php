<?php
// ghom/api/select_project.php

// This file now simply includes the real select_project file from the parent directory.
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Authentication required']));
}
require __DIR__ . '/../../select_project.php';