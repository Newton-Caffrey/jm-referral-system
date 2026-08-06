<?php

namespace JMReferral\Portal\Clinical;

/**
 * Narrow view-rendering contract exposed to portal clinical handlers.
 *
 * Lets handlers render pages/errors through the same shell (breadcrumbs,
 * navigation, branding) as PortalController without depending on its
 * private rendering internals.
 */
interface PortalViewHost
{
    /**
     * @param array<int, array{label: string, url: string}> $breadcrumbs
     * @param array<string, mixed>                          $view
     */
    public function render_portal_page(
        string $template,
        string $page_title,
        string $current_route,
        array $breadcrumbs,
        array $view
    ): void;

    public function render_portal_error(string $template, string $title, int $status): void;
}
