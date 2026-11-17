<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Genuine 404s must stay HTTP 404 and not be offered for indexing.
 * WordPress core does not noindex them; AIOSEO is not currently rendering.
 */
function technorapper_404_robots($robots)
{
    if (!is_array($robots)) {
        $robots = array();
    }
    if (is_404()) {
        $robots['noindex'] = true;
        $robots['follow'] = true;
    }
    return $robots;
}

function technorapper_should_emit_og_fallback()
{
    return !is_404();
}
