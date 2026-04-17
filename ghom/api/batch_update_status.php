<?php
// Backward compatibility shim — delegates to unified shared API.
// Canonical implementation lives at shared/api/batch_update_status.php
require_once __DIR__ . '/../../shared/api/' . basename(__FILE__);
