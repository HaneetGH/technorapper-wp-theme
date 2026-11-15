<?php
if (!defined('ABSPATH')) { exit; }
?>
<section id="ai-newsroom" class="ai-newsroom" aria-labelledby="ai-newsroom-title">
  <div class="ai-newsroom-head">
    <div>
      <span class="section-kicker">The TechnoRapper AI Newsroom</span>
      <h2 id="ai-newsroom-title">14 years of tech journalism. Now powered by a newsroom of AI agents.</h2>
    </div>
    <p class="ai-newsroom-intro">
      Every story you read on TechnoRapper passes through our AI-powered editorial pipeline.
      Specialized agents work together to discover, research, evaluate, write and quality-check
      technology news before it reaches you.
    </p>
  </div>

  <div class="ai-flow" aria-label="How every TechnoRapper story is processed">
    <?php
    $agents = array(
        array('icon' => '01', 'name' => 'Discover', 'text' => 'Finds emerging stories across technology sources.'),
        array('icon' => '02', 'name' => 'Research', 'text' => 'Collects context and information from multiple sources.'),
        array('icon' => '03', 'name' => 'Evaluate', 'text' => 'Determines relevance, impact and reader value.'),
        array('icon' => '04', 'name' => 'Verify', 'text' => 'Checks claims, sources and important details.'),
        array('icon' => '05', 'name' => 'Write', 'text' => 'Turns the selected research into a clear TechnoRapper story.'),
        array('icon' => '06', 'name' => 'Quality Gate', 'text' => 'Checks the story against publishing quality rules.'),
    );
    foreach ($agents as $index => $agent) :
    ?>
      <div class="ai-agent">
        <span class="ai-agent-number"><?php echo esc_html($agent['icon']); ?></span>
        <div class="ai-agent-copy">
          <span class="ai-agent-label">AI Agent</span>
          <h3><?php echo esc_html($agent['name']); ?></h3>
          <p><?php echo esc_html($agent['text']); ?></p>
        </div>
      </div>
      <?php if ($index < count($agents) - 1) : ?>
        <span class="ai-flow-arrow" aria-hidden="true">→</span>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <div class="ai-newsroom-foot">
    <strong>Sources in. Signal out.</strong>
    <span>Our goal isn't to publish everything. It's to surface what is worth your time.</span>
  </div>
</section>
