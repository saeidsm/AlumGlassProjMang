<?php
// Backward compatibility shim — delegates to unified shared API.
// Canonical implementation lives at shared/api/get_statuses_for_stage.php
require_once __DIR__ . '/../../shared/api/' . basename(__FILE__);
