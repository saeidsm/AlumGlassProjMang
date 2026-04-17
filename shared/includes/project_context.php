<?php
/**
 * Project Context Resolver
 *
 * Determines the current project ('ghom' | 'pardis') for shared API endpoints.
 * Used by unified endpoints under shared/api/ to avoid duplicating logic per module.
 *
 * Resolution order:
 *   1. Session value $_SESSION['current_project']
 *   2. URL path segment (/ghom/ or /pardis/)
 *   3. Explicit GET/POST parameter ?project=...
 *   4. Fallback: throws RuntimeException
 */

function getAvailableProjects(): array
{
    return ['ghom', 'pardis'];
}

function getCurrentProject(): string
{
    $available = getAvailableProjects();

    // 1. Session (set during project selection)
    if (!empty($_SESSION['current_project']) && in_array($_SESSION['current_project'], $available, true)) {
        return $_SESSION['current_project'];
    }

    // 2. URL path: /ghom/api/... or /pardis/api/...
    $path = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/(ghom|pardis)/#', $path, $m)) {
        return $m[1];
    }

    // 3. Explicit GET/POST override
    $project = $_GET['project'] ?? $_POST['project'] ?? '';
    if (in_array($project, $available, true)) {
        return $project;
    }

    throw new RuntimeException('Could not determine current project context');
}

/**
 * Returns a PDO connection for the current project's database.
 * Thin wrapper over getProjectDBConnection() defined in bootstrap.
 */
function getProjectDB(): PDO
{
    return getProjectDBConnection(getCurrentProject());
}
