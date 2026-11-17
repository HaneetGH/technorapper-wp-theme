<?php
if (!defined('ABSPATH')) {
    exit;
}

$disclosure = (isset($args) && is_array($args) && isset($args['disclosure']))
    ? $args['disclosure']
    : technorapper_get_ai_disclosure();

echo technorapper_render_ai_disclosure($disclosure);
