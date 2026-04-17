<?php
// Backward compatibility shim — delegates to unified shared API.
// Canonical implementation lives at shared/api/get_zone_statuses.php
require_once __DIR__ . '/../../shared/api/' . basename(__FILE__);
