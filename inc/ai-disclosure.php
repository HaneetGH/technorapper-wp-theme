<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reader-facing AI content disclosure.
 *
 * AI Wire writes provenance. This file is the only theme layer that talks to
 * that plugin contract. Templates consume the stable array below, never plugin
 * meta keys or pipeline internals.
 *
 * Plugin contract, first match wins:
 * 1. TechnoRapper_AI_Wire::get_post_provenance( $post_id )
 * 2. apply_filters( 'tr_ai_wire_provenance', null, $post_id )
 * 3. Post meta `_tr_ai_wire_provenance`
 * 4. Conservative fallback from public AI Wire markers
 *
 * Theme override:
 * apply_filters( 'technorapper_ai_disclosure', $disclosure, $post_id )
 *
 * Stable shape (or null when nothing should render):
 * - processed (bool)
 * - roles (string[]): generated|assisted|rewritten|summarized|curated|human-reviewed
 * - source_url (string)
 * - source_label (string)
 * - byline (string)
 * - headline (string)
 * - copy (string)
 * - how_it_works_url (string)
 */

function technorapper_ai_disclosure_roles()
{
    return array(
        'generated'       => __('AI-generated', 'technorapper'),
        'assisted'        => __('AI-assisted', 'technorapper'),
        'rewritten'       => __('AI-rewritten', 'technorapper'),
        'summarized'      => __('AI-summarized', 'technorapper'),
        'curated'         => __('AI-curated', 'technorapper'),
        'human-reviewed'  => __('Human-reviewed', 'technorapper'),
    );
}

function technorapper_get_ai_disclosure($post_id = 0)
{
    $post_id = (int) $post_id;
    if ($post_id <= 0 && function_exists('get_the_ID')) {
        $post_id = (int) get_the_ID();
    }
    if ($post_id <= 0) {
        return null;
    }

    $raw = technorapper_ai_disclosure_read_provenance($post_id);
    $disclosure = technorapper_ai_disclosure_normalize($raw, $post_id);

    if (function_exists('apply_filters')) {
        $disclosure = apply_filters('technorapper_ai_disclosure', $disclosure, $post_id);
    }

    if (!is_array($disclosure) || empty($disclosure['processed']) || empty($disclosure['roles'])) {
        return null;
    }

    return $disclosure;
}

function technorapper_render_ai_disclosure($disclosure = null)
{
    if ($disclosure === null) {
        $disclosure = technorapper_get_ai_disclosure();
    }
    if (!is_array($disclosure) || empty($disclosure['processed'])) {
        return '';
    }

    $post_id = isset($disclosure['post_id']) ? (int) $disclosure['post_id'] : 0;
    $heading_id = 'ai-disclosure-heading' . ($post_id ? '-' . $post_id : '');
    $roles = technorapper_ai_disclosure_roles();
    $labels = array();
    foreach ((array) $disclosure['roles'] as $role) {
        if (isset($roles[$role])) {
            $labels[] = $roles[$role];
        }
    }
    if (!$labels) {
        return '';
    }

    $headline = isset($disclosure['headline']) ? (string) $disclosure['headline'] : __('AI Wire', 'technorapper');
    $copy = isset($disclosure['copy']) ? (string) $disclosure['copy'] : '';
    $source_url = isset($disclosure['source_url']) ? (string) $disclosure['source_url'] : '';
    $source_label = isset($disclosure['source_label']) ? (string) $disclosure['source_label'] : '';
    $how_url = isset($disclosure['how_it_works_url']) ? (string) $disclosure['how_it_works_url'] : '';

    ob_start();
    ?>
<aside class="ai-disclosure" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
  <div class="ai-disclosure-head">
    <p class="ai-disclosure-brand" id="<?php echo esc_attr($heading_id); ?>">
      <span class="ai-disclosure-kicker"><?php echo esc_html($headline); ?></span>
    </p>
    <ul class="ai-disclosure-roles">
      <?php foreach ($labels as $label) : ?>
        <li><span class="ai-disclosure-role"><?php echo esc_html($label); ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php if ($copy !== '') : ?>
    <p class="ai-disclosure-copy"><?php echo esc_html($copy); ?></p>
  <?php endif; ?>
  <?php if ($source_url !== '' || $how_url !== '') : ?>
    <p class="ai-disclosure-actions">
      <?php if ($source_url !== '') : ?>
        <a class="ai-disclosure-source" href="<?php echo esc_url($source_url); ?>" rel="nofollow noopener noreferrer" target="_blank">
          <?php echo esc_html__('View original source', 'technorapper'); ?>
          <?php if ($source_label !== '') : ?>
            <span class="ai-disclosure-source-name"><?php echo esc_html($source_label); ?></span>
          <?php endif; ?>
          <span class="screen-reader-text"><?php echo esc_html__('(opens in a new tab)', 'technorapper'); ?></span>
        </a>
      <?php endif; ?>
      <?php if ($how_url !== '') : ?>
        <a class="ai-disclosure-more" href="<?php echo esc_url($how_url); ?>"><?php echo esc_html__('How AI Wire works', 'technorapper'); ?></a>
      <?php endif; ?>
    </p>
  <?php endif; ?>
</aside>
    <?php
    return trim((string) ob_get_clean());
}

function technorapper_the_ai_disclosure()
{
    echo technorapper_render_ai_disclosure();
}

function technorapper_ai_disclosure_read_provenance($post_id)
{
    $post_id = (int) $post_id;

    if (class_exists('TechnoRapper_AI_Wire') && method_exists('TechnoRapper_AI_Wire', 'get_post_provenance')) {
        $from_plugin = TechnoRapper_AI_Wire::get_post_provenance($post_id);
        if (is_array($from_plugin) && $from_plugin) {
            return $from_plugin;
        }
    }

    if (function_exists('apply_filters')) {
        $from_filter = apply_filters('tr_ai_wire_provenance', null, $post_id);
        if (is_array($from_filter) && $from_filter) {
            return $from_filter;
        }
    }

    $meta = technorapper_ai_disclosure_meta($post_id, '_tr_ai_wire_provenance');
    if (is_array($meta) && $meta) {
        return $meta;
    }
    if (is_string($meta) && $meta !== '') {
        $decoded = json_decode($meta, true);
        if (is_array($decoded) && $decoded) {
            return $decoded;
        }
    }

    return technorapper_ai_disclosure_fallback($post_id);
}

function technorapper_ai_disclosure_fallback($post_id)
{
    $queue_id = technorapper_ai_disclosure_meta($post_id, '_tr_ai_wire_queue_id');
    $source_url = technorapper_ai_disclosure_meta($post_id, '_tr_ai_wire_source_url');
    $experiment_id = technorapper_ai_disclosure_meta($post_id, '_tr_ai_wire_experiment_id');
    $human_flag = technorapper_ai_disclosure_meta($post_id, '_tr_ai_wire_human_reviewed');

    $processed = ($queue_id !== '' && $queue_id !== null && $queue_id !== false && $queue_id !== '0' && $queue_id !== 0)
        || (is_string($source_url) && $source_url !== '')
        || (is_string($experiment_id) && $experiment_id !== '');

    if (!$processed) {
        return null;
    }

    $roles = array();
    $safe_source = technorapper_ai_disclosure_safe_url(is_string($source_url) ? $source_url : '');
    if ($safe_source !== '') {
        $roles[] = 'rewritten';
        $roles[] = 'curated';
    } else {
        $roles[] = 'generated';
    }

    if (technorapper_ai_disclosure_truthy($human_flag)) {
        $roles[] = 'human-reviewed';
    }

    return array(
        'processed'  => true,
        'roles'      => $roles,
        'source_url' => $safe_source,
    );
}

function technorapper_ai_disclosure_normalize($raw, $post_id)
{
    if (!is_array($raw) || !$raw) {
        return null;
    }

    $raw = technorapper_ai_disclosure_strip_sensitive($raw);

    $processed = true;
    if (array_key_exists('processed', $raw)) {
        $processed = technorapper_ai_disclosure_truthy($raw['processed']);
    } elseif (array_key_exists('has_ai', $raw)) {
        $processed = technorapper_ai_disclosure_truthy($raw['has_ai']);
    }
    if (!$processed) {
        return null;
    }

    $roles = technorapper_ai_disclosure_collect_roles($raw);
    if (!$roles) {
        return null;
    }

    $source_url = '';
    foreach (array('source_url', 'original_source_url', 'canonical_source_url', 'url') as $key) {
        if (!empty($raw[$key]) && is_string($raw[$key])) {
            $source_url = technorapper_ai_disclosure_safe_url($raw[$key]);
            if ($source_url !== '') {
                break;
            }
        }
    }

    $source_label = '';
    foreach (array('source_label', 'source_name', 'publisher', 'feed_name') as $key) {
        if (!empty($raw[$key]) && is_scalar($raw[$key])) {
            $source_label = technorapper_ai_disclosure_plain((string) $raw[$key], 80);
            if ($source_label !== '') {
                break;
            }
        }
    }
    if ($source_label === '' && $source_url !== '') {
        $host = function_exists('wp_parse_url') ? wp_parse_url($source_url, PHP_URL_HOST) : parse_url($source_url, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            $source_label = preg_replace('/^www\./i', '', $host);
        }
    }

    $copy = '';
    foreach (array('summary', 'copy', 'description', 'notice') as $key) {
        if (!empty($raw[$key]) && is_scalar($raw[$key])) {
            $copy = technorapper_ai_disclosure_plain((string) $raw[$key], 360);
            if ($copy !== '') {
                break;
            }
        }
    }
    if ($copy === '') {
        $copy = technorapper_ai_disclosure_default_copy($roles, $source_url !== '');
    }

    $how = technorapper_ai_disclosure_how_it_works_url();
    if (!empty($raw['how_it_works_url']) && is_string($raw['how_it_works_url'])) {
        $custom_how = technorapper_ai_disclosure_safe_url($raw['how_it_works_url']);
        if ($custom_how !== '') {
            $how = $custom_how;
        }
    }

    $labels = technorapper_ai_disclosure_roles();
    $primary = technorapper_ai_disclosure_primary_role($roles);
    $byline = isset($labels[$primary]) ? $labels[$primary] : __('AI Wire', 'technorapper');

    return array(
        'post_id'          => (int) $post_id,
        'processed'        => true,
        'roles'            => array_values($roles),
        'source_url'       => $source_url,
        'source_label'     => $source_label,
        'byline'           => $byline,
        'headline'         => __('AI Wire', 'technorapper'),
        'copy'             => $copy,
        'how_it_works_url' => $how,
    );
}

function technorapper_ai_disclosure_collect_roles($raw)
{
    $found = array();
    $known = technorapper_ai_disclosure_roles();

    $lists = array();
    foreach (array('roles', 'labels', 'flags', 'methods') as $key) {
        if (isset($raw[$key])) {
            $lists[] = $raw[$key];
        }
    }
    if (isset($raw['role']) && is_scalar($raw['role'])) {
        $lists[] = array($raw['role']);
    }
    if (isset($raw['content_origin']) && is_scalar($raw['content_origin'])) {
        $lists[] = array($raw['content_origin']);
    }
    if (isset($raw['digitalSourceType']) && is_scalar($raw['digitalSourceType'])) {
        $lists[] = array($raw['digitalSourceType']);
    }

    foreach ($lists as $list) {
        if (is_string($list)) {
            $decoded = json_decode($list, true);
            $list = is_array($decoded) ? $decoded : preg_split('/[,|]/', $list);
        }
        if (!is_array($list)) {
            continue;
        }
        $assoc = $list && array_keys($list) !== range(0, count($list) - 1);
        foreach ($list as $key => $value) {
            if ($assoc && is_string($key) && (is_bool($value) || $value === 1 || $value === '1' || $value === 0 || $value === '0' || $value === '')) {
                if (!technorapper_ai_disclosure_truthy($value)) {
                    continue;
                }
                $role = technorapper_ai_disclosure_canonical_role($key);
            } else {
                $role = technorapper_ai_disclosure_canonical_role(is_scalar($value) ? (string) $value : '');
            }
            if ($role !== '' && isset($known[$role])) {
                $found[$role] = true;
            }
        }
    }

    foreach (array('generated', 'assisted', 'rewritten', 'summarized', 'curated') as $role) {
        $keys = array($role, 'ai_' . str_replace('-', '_', $role), 'is_' . str_replace('-', '_', $role));
        foreach ($keys as $key) {
            if (isset($raw[$key]) && technorapper_ai_disclosure_truthy($raw[$key])) {
                $found[$role] = true;
            }
        }
    }
    foreach (array('human_reviewed', 'human-reviewed', 'humanReviewed', 'reviewed_by_human') as $key) {
        if (isset($raw[$key]) && technorapper_ai_disclosure_truthy($raw[$key])) {
            $found['human-reviewed'] = true;
        }
    }

    $order = array('generated', 'assisted', 'rewritten', 'summarized', 'curated', 'human-reviewed');
    $roles = array();
    foreach ($order as $role) {
        if (!empty($found[$role])) {
            $roles[] = $role;
        }
    }
    return $roles;
}

function technorapper_ai_disclosure_canonical_role($value)
{
    $value = strtolower(trim((string) $value));
    $value = str_replace(array('_', ' '), '-', $value);
    $value = preg_replace('/^ai-/', '', $value);

    $map = array(
        'generated' => 'generated',
        'generate' => 'generated',
        'written' => 'generated',
        'ai-generated' => 'generated',
        'trainedalgorithmicmedia' => 'generated',
        'algorithmicmedia' => 'generated',
        'assisted' => 'assisted',
        'assist' => 'assisted',
        'compositewithtrainedalgorithmicmedia' => 'assisted',
        'rewritten' => 'rewritten',
        'rewrite' => 'rewritten',
        'adapted' => 'rewritten',
        'summarized' => 'summarized',
        'summarised' => 'summarized',
        'summary' => 'summarized',
        'curated' => 'curated',
        'curate' => 'curated',
        'discovered' => 'curated',
        'selected' => 'curated',
        'human-reviewed' => 'human-reviewed',
        'humanreviewed' => 'human-reviewed',
        'reviewed' => 'human-reviewed',
        'human' => 'human-reviewed',
    );

    $compact = str_replace('-', '', $value);
    if (isset($map[$value])) {
        return $map[$value];
    }
    if (isset($map[$compact])) {
        return $map[$compact];
    }
    return isset(technorapper_ai_disclosure_roles()[$value]) ? $value : '';
}

function technorapper_ai_disclosure_primary_role($roles)
{
    $priority = array('summarized', 'rewritten', 'generated', 'assisted', 'curated');
    foreach ($priority as $role) {
        if (in_array($role, $roles, true)) {
            return $role;
        }
    }
    return isset($roles[0]) ? $roles[0] : 'generated';
}

function technorapper_ai_disclosure_default_copy($roles, $has_source)
{
    $roles = (array) $roles;
    $parts = array();
    $primary = technorapper_ai_disclosure_primary_role($roles);

    if ($primary === 'summarized') {
        $parts[] = __('This story is an AI-generated summary of a source.', 'technorapper');
    } elseif ($primary === 'rewritten' && in_array('curated', $roles, true)) {
        $parts[] = __('This story was discovered and rewritten using AI agents.', 'technorapper');
    } elseif ($primary === 'rewritten') {
        $parts[] = __('This story was rewritten from a source using AI agents.', 'technorapper');
    } elseif ($primary === 'assisted') {
        $parts[] = __('This story was written with AI assistance.', 'technorapper');
    } elseif ($primary === 'curated') {
        $parts[] = __('This story was discovered and selected using AI agents.', 'technorapper');
    } else {
        $parts[] = __('This story was written using AI agents.', 'technorapper');
    }

    if (in_array('human-reviewed', $roles, true)) {
        $parts[] = __('A human reviewed it before publication.', 'technorapper');
    }

    $parts[] = __('AI-generated content may contain errors.', 'technorapper');
    if ($has_source) {
        $parts[] = __('Verify important information with the original source.', 'technorapper');
    }

    return implode(' ', $parts);
}

function technorapper_ai_disclosure_how_it_works_url()
{
    $url = function_exists('home_url') ? home_url('/ai-wire/') : '/ai-wire/';
    if (function_exists('apply_filters')) {
        $url = apply_filters('technorapper_ai_disclosure_how_it_works_url', $url);
    }
    return technorapper_ai_disclosure_safe_url((string) $url);
}

function technorapper_ai_disclosure_meta($post_id, $key)
{
    if (!function_exists('get_post_meta')) {
        return '';
    }
    return get_post_meta((int) $post_id, $key, true);
}

function technorapper_ai_disclosure_safe_url($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (function_exists('esc_url_raw')) {
        $url = esc_url_raw($url, array('http', 'https'));
    }
    if ($url === '') {
        return '';
    }
    $parts = function_exists('wp_parse_url') ? wp_parse_url($url) : parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }
    $scheme = strtolower((string) $parts['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') {
        return '';
    }
    return $url;
}

function technorapper_ai_disclosure_plain($text, $max = 360)
{
    $text = (string) $text;
    $text = preg_replace('@<(script|style)[^>]*>.*?</\1>@si', '', $text);
    $text = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($text) : strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if ($text === '') {
        return '';
    }
    $max = (int) $max;
    if ($max > 0 && function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') > $max) {
            return rtrim(mb_substr($text, 0, $max - 1, 'UTF-8')) . '…';
        }
        return $text;
    }
    if ($max > 0 && strlen($text) > $max) {
        return rtrim(substr($text, 0, $max - 1)) . '…';
    }
    return $text;
}

function technorapper_ai_disclosure_truthy($value)
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return (int) $value !== 0;
    }
    if (is_string($value)) {
        $value = strtolower(trim($value));
        return $value !== '' && !in_array($value, array('0', 'false', 'no', 'off', 'null'), true);
    }
    return !empty($value);
}

function technorapper_ai_disclosure_strip_sensitive($value)
{
    $blocked = array(
        'api_key', 'apikey', 'openai_api_key', 'gemini_api_key', 'fal_api_key',
        'token', 'secret', 'password', 'credential', 'authorization',
        'prompt', 'system_prompt', 'developer_prompt', 'instructions',
        'agent_instructions', 'private_prompt', 'model_config', 'temperature',
        'rewrite_json', 'review_json', 'error_text', 'tenant_id',
        'view_token', 'hmac', 'salt', 'private_key',
    );

    if (!is_array($value)) {
        return $value;
    }

    $clean = array();
    foreach ($value as $key => $item) {
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string) $key));
        $blocked_key = false;
        foreach ($blocked as $needle) {
            if ($normalized === $needle || substr($normalized, -strlen($needle) - 1) === '_' . $needle) {
                $blocked_key = true;
                break;
            }
        }
        if ($blocked_key) {
            continue;
        }
        $clean[$key] = is_array($item) ? technorapper_ai_disclosure_strip_sensitive($item) : $item;
    }
    return $clean;
}
