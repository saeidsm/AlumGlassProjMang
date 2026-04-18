<?php
/**
 * Renders a reusable empty-state block.
 *
 * Usage:
 *   echo renderEmptyState([
 *       'title' => 'هنوز گزارشی ثبت نشده',
 *       'desc'  => 'برای ثبت اولین گزارش، دکمه زیر را بزنید.',
 *       'action' => ['label' => 'ثبت گزارش', 'url' => '/pardis/daily_report_form_ps.php'],
 *       'icon'  => 'file', // or 'chat', 'users', 'search', or null for default
 *   ]);
 */

function renderEmptyState(array $opts): string
{
    $title  = (string)($opts['title']  ?? '');
    $desc   = (string)($opts['desc']   ?? '');
    $icon   = (string)($opts['icon']   ?? 'file');
    $action = $opts['action'] ?? null;

    $iconSvg = _agEmptyStateIcon($icon);

    $html = '<div class="ag-empty-state" role="status">';
    $html .= $iconSvg;
    if ($title !== '') {
        $html .= '<p class="ag-empty-state__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    if ($desc !== '') {
        $html .= '<p class="ag-empty-state__desc">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    if (is_array($action) && !empty($action['label'])) {
        $label = htmlspecialchars((string)$action['label'], ENT_QUOTES, 'UTF-8');
        if (!empty($action['url'])) {
            $url = htmlspecialchars((string)$action['url'], ENT_QUOTES, 'UTF-8');
            $html .= '<a class="ag-empty-state__action" href="' . $url . '">' . $label . '</a>';
        } elseif (!empty($action['onclick'])) {
            $oc = htmlspecialchars((string)$action['onclick'], ENT_QUOTES, 'UTF-8');
            $html .= '<button type="button" class="ag-empty-state__action" onclick="' . $oc . '">' . $label . '</button>';
        }
    }
    $html .= '</div>';
    return $html;
}

function _agEmptyStateIcon(string $name): string
{
    $svg = function (string $inner): string {
        return '<svg class="ag-empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
             . ' stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
             . $inner . '</svg>';
    };

    switch ($name) {
        case 'chat':
            return $svg('<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/>');
        case 'users':
            return $svg('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>');
        case 'search':
            return $svg('<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>');
        case 'inbox':
            return $svg('<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>');
        case 'alert':
            return $svg('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>');
        case 'file':
        default:
            return $svg('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/>');
    }
}
