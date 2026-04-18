<?php
/**
 * Renders a Bootstrap-compatible breadcrumb trail with
 * BreadcrumbList JSON-LD for SEO/a11y.
 *
 * Usage:
 *   echo renderBreadcrumbs([
 *       ['label' => 'خانه', 'url' => '/'],
 *       ['label' => 'گزارش‌ها', 'url' => '/pardis/daily_reports_dashboard_ps.php'],
 *       ['label' => 'گزارش ۱۴۰۵/۰۱/۲۹'], // last item has no url
 *   ]);
 *
 * Escapes labels via htmlspecialchars(). URLs are not escaped as HTML
 * content because they live inside href="…" — they go through
 * htmlspecialchars() for the attribute context.
 */

function renderBreadcrumbs(array $items): string
{
    if (empty($items)) return '';

    $count = count($items);
    $html = '<nav aria-label="breadcrumb" class="ag-breadcrumb-nav">';
    $html .= '<ol class="ag-breadcrumb" itemscope itemtype="https://schema.org/BreadcrumbList">';

    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => [],
    ];

    foreach ($items as $i => $item) {
        $label = (string)($item['label'] ?? '');
        $url = isset($item['url']) ? (string)$item['url'] : null;
        $isLast = ($i === $count - 1);

        $html .= '<li class="ag-breadcrumb__item' . ($isLast ? ' is-current' : '') . '"'
               . ' itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';

        if ($isLast || !$url) {
            $html .= '<span aria-current="' . ($isLast ? 'page' : 'false') . '" itemprop="name">'
                   . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        } else {
            $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" itemprop="item">'
                   . '<span itemprop="name">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
                   . '</a>';
        }

        $html .= '<meta itemprop="position" content="' . ($i + 1) . '">';
        $html .= '</li>';

        $jsonLd['itemListElement'][] = [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'name'     => $label,
        ] + ($url ? ['item' => $url] : []);
    }

    $html .= '</ol></nav>';
    $html .= '<script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

    return $html;
}

/**
 * Convenience: auto-generate breadcrumbs from the current path.
 * Does NOT know human labels, so use sparingly.
 */
function autoBreadcrumbs(array $labelMap = []): string
{
    $parts = array_filter(explode('/', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
    $items = [['label' => 'خانه', 'url' => '/']];
    $acc = '';
    foreach ($parts as $seg) {
        $acc .= '/' . $seg;
        $items[] = [
            'label' => $labelMap[$seg] ?? ucfirst(str_replace(['.php', '_', '-'], ['', ' ', ' '], $seg)),
            'url'   => $acc,
        ];
    }
    return renderBreadcrumbs($items);
}
