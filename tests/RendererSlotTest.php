<?php

/**
 * RendererSlotTest — tests for Renderer slot functionality.
 * Covers slot(), endSlot(), hasSlot(), slotContent(), renderSlot(), yield().
 */

require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Renderer.php';
require_once __DIR__ . '/../src/helpers.php';

function renderer_slot_test_tmp_dir(string $prefix): string
{
    $base = sys_get_temp_dir() . '/bb_renderer_slot_' . $prefix . '_' . uniqid('', true);
    @mkdir($base, 0755, true);
    return $base;
}

function renderer_slot_cleanup(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
    }
    @rmdir($dir);
}

function test_renderer_slot_basic(): Test
{
    $t = new Test('Renderer Slot - Basic Slot Capture');

    $tmpDir = renderer_slot_test_tmp_dir('slot_basic');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php $this->slot("sidebar"); ?><aside>Sidebar Content</aside><?php $this->endSlot(); ?><main>Main Content</main>');

    $output = $renderer->render('test');

    $t->assertTrue('Main content rendered', str_contains($output, 'Main Content'));
    $t->assertTrue('Slot content captured', str_contains($output, 'Sidebar Content'));

    renderer_slot_cleanup($tmpDir);
    return $t;
}

function test_renderer_slot_hasSlot(): Test
{
    $t = new Test('Renderer Slot - hasSlot()');

    $tmpDir = renderer_slot_test_tmp_dir('slot_hasSlot');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php $this->slot("sidebar"); ?><aside>Sidebar</aside><?php $this->endSlot(); ?><?php echo $this->hasSlot("sidebar") ? "HAS" : "NO"; ?>');

    $output = $renderer->render('test');

    $t->assertEquals('hasSlot returns true for defined slot', 'HAS', $output);

    renderer_slot_cleanup($tmpDir);
    return $t;
}

function test_renderer_slot_hasSlot_false(): Test
{
    $t = new Test('Renderer Slot - hasSlot() Returns False for Undefined Slot');

    $tmpDir = renderer_slot_test_tmp_dir('slot_hasSlot_false');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->hasSlot("undefined") ? "HAS" : "NO"; ?>');

    $output = $renderer->render('test');

    $t->assertEquals('hasSlot returns false for undefined slot', 'NO', $output);

    renderer_slot_cleanup($tmpDir);
    return $t;
}

function test_renderer_slot_slotContent(): Test
{
    $t = new Test('Renderer Slot - slotContent() Returns Slot Content');

    $tmpDir = renderer_slot_test_tmp_dir('slot_slotContent');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php $this->slot("title"); ?>Page Title<?php $this->endSlot(); ?><?php echo $this->slotContent("title"); ?>');

    $output = $renderer->render('test');

    $t->assertEquals('slotContent returns slot content', 'Page Title', $output);

    renderer_slot_cleanup($tmpDir);
    return $t;
}

function test_renderer_slot_slotContent_empty(): Test
{
    $t = new Test('Renderer Slot - slotContent() Returns Empty for Undefined Slot');

    $tmpDir = renderer_slot_test_tmp_dir('slot_slotContent_empty');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->slotContent("undefined"); ?>');

    $output = $renderer->render('test');

    $t->assertEquals('slotContent returns empty for undefined slot', '', $output);

    renderer_slot_cleanup($tmpDir);
    return $t;
}

function test_renderer_slot_renderSlot(): Test
{
    $t = new Test('Renderer Slot - renderSlot() Outputs Slot Content');

    $tmpDir = renderer_slot_test_tmp_dir('slot_renderSlot');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php $this->slot("footer"); ?><footer>Footer Content</footer><?php $this->endSlot(); ?><?php $this->renderSlot("footer"); ?>');

    $output = $renderer->render('test');

    $t->assertTrue('renderSlot outputs slot content', str_contains($output, 'Footer Content'));

    renderer_slot_cleanup($tmpDir);
    return $t;
}

function test_renderer_slot_yield(): Test
{
    $t = new Test('Renderer Slot - yield() Outputs Slot Content');

    $tmpDir = renderer_slot_test_tmp_dir('slot_yield');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php $this->slot("header"); ?><header>Header</header><?php $this->endSlot(); ?><?php $this->yield("header"); ?>');

    $output = $renderer->render('test');

    $t->assertTrue('yield outputs slot content', str_contains($output, 'Header'));

    renderer_slot_cleanup($tmpDir);
    return $t;
}

function test_renderer_slot_yield_with_default(): Test
{
    $t = new Test('Renderer Slot - yield() with Default Content');

    $tmpDir = renderer_slot_test_tmp_dir('slot_yield_default');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php $this->yield("undefined", "Default Content"); ?>');

    $output = $renderer->render('test');

    $t->assertEquals('yield outputs default for undefined slot', 'Default Content', $output);

    renderer_slot_cleanup($tmpDir);
    return $t;
}

function test_renderer_slot_multiple_slots(): Test
{
    $t = new Test('Renderer Slot - Multiple Slots');

    $tmpDir = renderer_slot_test_tmp_dir('slot_multiple');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php $this->slot("header"); ?>Header<?php $this->endSlot(); ?><?php $this->slot("sidebar"); ?>Sidebar<?php $this->endSlot(); ?><?php $this->slot("footer"); ?>Footer<?php $this->endSlot(); ?><?php echo $this->slotContent("header") . "|" . $this->slotContent("sidebar") . "|" . $this->slotContent("footer"); ?>');

    $output = $renderer->render('test');

    $t->assertEquals('Multiple slots captured correctly', 'Header|Sidebar|Footer', $output);

    renderer_slot_cleanup($tmpDir);
    return $t;
}

function test_renderer_slot_nested(): Test
{
    $t = new Test('Renderer Slot - Nested Slot Capture');

    $tmpDir = renderer_slot_test_tmp_dir('slot_nested');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php $this->slot("outer"); ?>Outer<?php $this->slot("inner"); ?>Inner<?php $this->endSlot(); ?><?php $this->endSlot(); ?><?php echo $this->slotContent("outer") . "|" . $this->slotContent("inner"); ?>');

    $output = $renderer->render('test');

    $t->assertEquals('Nested slots work', 'OuterInner|Inner', $output);

    renderer_slot_cleanup($tmpDir);
    return $t;
}

function test_renderer_slot_with_globals(): Test
{
    $t = new Test('Renderer Slot - Slot with Global Variables');

    $tmpDir = renderer_slot_test_tmp_dir('slot_globals');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php $this->slot("title"); ?><?php echo $this->e($siteName); ?><?php $this->endSlot(); ?><?php echo $this->slotContent("title"); ?>');

    $renderer = $renderer->addGlobal('siteName', 'My Site');
    $output = $renderer->render('test');

    $t->assertEquals('Global accessible in slot', 'My Site', $output);

    renderer_slot_cleanup($tmpDir);
    return $t;
}

function test_renderer_slot_with_data(): Test
{
    $t = new Test('Renderer Slot - Slot with Data Variables');

    $tmpDir = renderer_slot_test_tmp_dir('slot_data');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php $this->slot("user"); ?><?php echo $this->e($user["name"]); ?><?php $this->endSlot(); ?><?php echo $this->slotContent("user"); ?>');

    $output = $renderer->render('test', ['user' => ['name' => 'Alice']]);

    $t->assertEquals('Data accessible in slot', 'Alice', $output);

    renderer_slot_cleanup($tmpDir);
    return $t;
}

function test_renderer_slot_endSlot_without_start(): Test
{
    $t = new Test('Renderer Slot - endSlot() Without slot() Does Nothing');

    $tmpDir = renderer_slot_test_tmp_dir('slot_end_without_start');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php $this->endSlot(); ?>Content');

    $output = $renderer->render('test');

    $t->assertEquals('endSlot without slot does not error', 'Content', $output);

    renderer_slot_cleanup($tmpDir);
    return $t;
}

function test_renderer_slot_overwrite(): Test
{
    $t = new Test('Renderer Slot - Overwriting Slot');

    $tmpDir = renderer_slot_test_tmp_dir('slot_overwrite');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php $this->slot("test"); ?>First<?php $this->endSlot(); ?><?php $this->slot("test"); ?>Second<?php $this->endSlot(); ?><?php echo $this->slotContent("test"); ?>');

    $output = $renderer->render('test');

    $t->assertEquals('Last slot value wins', 'Second', $output);

    renderer_slot_cleanup($tmpDir);
    return $t;
}

function test_renderer_slot_in_layout(): Test
{
    $t = new Test('Renderer Slot - Slot Used in Layout');

    $tmpDir = renderer_slot_test_tmp_dir('slot_in_layout');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/layout.php', '<html><head><?php $this->yield("head"); ?></head><body><?php echo $content; ?></body></html>');
    file_put_contents($tmpDir . '/test.php', '<?php $this->slot("head"); ?><title>Page Title</title><?php $this->endSlot(); ?><?php $this->layout("layout"); ?>Page Content');

    $output = $renderer->render('test');

    $t->assertTrue('Slot content appears in layout', str_contains($output, 'Page Title'));
    $t->assertTrue('Page content in layout', str_contains($output, 'Page Content'));

    renderer_slot_cleanup($tmpDir);
    return $t;
}

register_tests(
    'test_renderer_slot_basic',
    'test_renderer_slot_hasSlot',
    'test_renderer_slot_hasSlot_false',
    'test_renderer_slot_slotContent',
    'test_renderer_slot_slotContent_empty',
    'test_renderer_slot_renderSlot',
    'test_renderer_slot_yield',
    'test_renderer_slot_yield_with_default',
    'test_renderer_slot_multiple_slots',
    'test_renderer_slot_nested',
    'test_renderer_slot_with_globals',
    'test_renderer_slot_with_data',
    'test_renderer_slot_endSlot_without_start',
    'test_renderer_slot_overwrite',
    'test_renderer_slot_in_layout'
);