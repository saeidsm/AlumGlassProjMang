<?php
// includes/validation.php — Input validation helpers

function validateInt($value): ?int {
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    return $filtered !== false ? $filtered : null;
}

function validatePositiveInt($value): ?int {
    $val = validateInt($value);
    return ($val !== null && $val > 0) ? $val : null;
}

function validateString($value, int $maxLen = 255): ?string {
    if (!is_string($value)) return null;
    $value = trim($value);
    if ($value === '' || mb_strlen($value) > $maxLen) return null;
    return $value;
}

function validateEmail($value): ?string {
    $filtered = filter_var($value, FILTER_VALIDATE_EMAIL);
    return $filtered !== false ? $filtered : null;
}

function validateDate($value): ?string {
    if (!is_string($value)) return null;
    $value = trim($value);
    if (preg_match('/^\d{4}[\/-]\d{2}[\/-]\d{2}$/', $value)) {
        return $value;
    }
    return null;
}

function validateInArray($value, array $allowed): mixed {
    return in_array($value, $allowed, true) ? $value : null;
}
