<?php

/**
 * RendererLayoutTest — tests for Renderer layout functionality.
 * Covers layout() and extend().
 */

require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Renderer.php';
require_once __DIR__ . '/../src/helpers.php';

function renderer_layout_test_tmp_dir(string $prefix): string
{
    $base = sys_get_temp_dir() . '/bb_renderer_layout_' . $prefix . '_' . uniqid('', true);
    @mkdir($base, 0755, true);
    return $base;
}

function renderer_layout_cleanup(string $dir): void
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

function test_renderer_layout_basic(): Test
{
    $t = new Test('Renderer Layout - Basic Layout');

    $tmpDir = renderer_layout_test_tmp_dir('layout_basic');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/layout.php', '<html><head><title><?php echo $this->e($title); ?></title></head><body><?php echo $content; ?></body></html>');
    file_put_contents($tmpDir . '/test.php', '<?php $this->layout("layout"); ?>Page Content');

    $output = $renderer->render('test', ['title' => 'Test Page']);

    $t->assertTrue('Layout wraps content', str_contains($output, 'Page Content'));
    $t->assertTrue('Layout includes title', str_contains($output, 'Test Page'));
    $t->assertTrue('Full HTML structure', str_contains($output, '<html>'));

    renderer_layout_cleanup($tmpDir);
    return $t;
}

function test_renderer_layout_with_slots(): Test
{
    $t = new Test('Renderer Layout - Layout with Slots');

    $tmpDir = renderer_layout_test_tmp_dir('layout_slots');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/layout.php', '<html><head><?php $this->yield("head"); ?></head><body><?php echo $content; ?></body></html>');
    file_put_contents($tmpDir . '/test.php', '<?php $this->slot("head"); ?><meta name="description" content="Test"><?php $this->endSlot(); ?><?php $this->layout("layout"); ?>Page Content');

    $output = $renderer->render('test');

    $t->assertTrue('Slot content in layout head', str_contains($output, 'description'));
    $t->assertTrue('Page content in layout body', str_contains($output, 'Page Content'));

    renderer_layout_cleanup($tmpDir);
    return $t;
}

function test_renderer_layout_with_data(): Test
{
    $t = new Test('Renderer Layout - Layout with Data Variables');

    $tmpDir = renderer_layout_test_tmp_dir('layout_data');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/layout.php', '<div class="wrapper"><h1><?php echo $this->e($pageTitle); ?></h1><?php echo $content; ?></div>');
    file_put_contents($tmpDir . '/test.php', '<?php $this->layout("layout"); ?>Content here');

    $output = $renderer->render('test', ['pageTitle' => 'My Page']);

    $t->assertTrue('Data passed to layout', str_contains($output, 'My Page'));
    $t->assertTrue('Content in layout', str_contains($output, 'Content here'));

    renderer_layout_cleanup($tmpDir);
    return $t;
}

function test_renderer_layout_with_globals(): Test
{
    $t = new Test('Renderer Layout - Layout with Globals');

    $tmpDir = renderer_layout_test_tmp_dir('layout_globals');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/layout.php', '<header><?php echo $this->e($siteName); ?></header><?php echo $content; ?>');
    file_put_contents($tmpDir . '/test.php', '<?php $this->layout("layout"); ?>Body');

    $renderer = $renderer->addGlobal('siteName', 'Global Site');
    $output = $renderer->render('test');

    $t->assertTrue('Global accessible in layout', str_contains($output, 'Global Site'));

    renderer_layout_cleanup($tmpDir);
    return $t;
}

function test_renderer_layout_missing_returns_content(): Test
{
    $t = new Test('Renderer Layout - Missing Layout Returns Content');

    $tmpDir = renderer_layout_test_tmp_dir('layout_missing');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php $this->layout("nonexistent"); ?>Just Content');

    $output = $renderer->render('test');

    $t->assertEquals('Missing layout returns content only', 'Just Content', $output);

    renderer_layout_cleanup($tmpDir);
    return $t;
}

function test_renderer_extend_basic(): Test
{
    $t = new Test('Renderer Layout - Extend Basic');

    $tmpDir = renderer_layout_test_tmp_dir('extend_basic');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/base.php', '<html><body><?php echo $content; ?></body></html>');
    file_put_contents($tmpDir . '/test.php', '<?php echo $this->extend("base"); ?>Extended Content');

    $output = $renderer->render('test');

    $t->assertTrue('Extend wraps content', str_contains($output, 'Extended Content'));
    $t->assertTrue('Extend uses base template', str_contains($output, '<html>'));

    renderer_layout_cleanup($tmpDir);
    return $t;
}

function test_renderer_extend_with_data(): Test
{
    $t = new Test('Renderer Layout - Extend with Data');

    $tmpDir = renderer_layout_test_tmp_dir('extend_data');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/base.php', '<div><?php echo $this->e($title); ?><?php echo $content; ?></div>');
    file_put_contents($tmpDir . '/test.php', '<?php echo $this->extend("base", ["title" => "Extended Title"]); ?>Body Content');

    $output = $renderer->render('test');

    $t->assertTrue('Data passed to extended template', str_contains($output, 'Extended Title'));
    $t->assertTrue('Content in extended template', str_contains($output, 'Body Content'));

    renderer_layout_cleanup($tmpDir);
    return $t;
}

function test_renderer_extend_with_slots(): Test
{
    $t = new Test('Renderer Layout - Extend with Slots');

    $tmpDir = renderer_layout_test_tmp_dir('extend_slots');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/base.php', '<html><head><?php $this->yield("head"); ?></head><body><?php echo $content; ?></body></html>');
    file_put_contents($tmpDir . '/test.php', '<?php $this->slot("head"); ?><meta charset="utf-8"><?php $this->endSlot(); ?><?php echo $this->extend("base"); ?>Content');

    $output = $renderer->render('test');

    $t->assertTrue('Slot content in extended template', str_contains($output, 'charset'));
    $t->assertTrue('Content in extended template', str_contains($output, 'Content'));

    renderer_layout_cleanup($tmpDir);
    return $t;
}

function test_renderer_extend_without_current_template_throws(): Test
{
    $t = new Test('Renderer Layout - Extend Without Current Template Throws');

    $tmpDir = renderer_layout_test_tmp_dir('extend_no_template');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/base.php', '<div><?php echo $content; ?></div>');

    try {
        $renderer->extend('base');
        $t->assert('Should throw RuntimeException', false);
    } catch (\RuntimeException $e) {
        $t->assertTrue('Throws RuntimeException', true);
        $t->assertTrue('Message mentions extend', str_contains($e->getMessage(), 'extend'));
    }

    renderer_layout_cleanup($tmpDir);
    return $t;
}

function test_renderer_layout_and_extend_combined(): Test
{
    $t = new Test('Renderer Layout - Layout and Extend Combined');

    $tmpDir = renderer_layout_test_tmp_dir('layout_extend_combined');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/outer.php', '<outer><?php echo $content; ?></outer>');
    file_put_contents($tmpDir . '/inner.php', '<inner><?php echo $content; ?></inner>');
    file_put_contents($tmpDir . '/test.php', '<?php $this->layout("outer"); ?><?php echo $this->extend("inner"); ?>Deep Content');

    $output = $renderer->render('test');

    $t->assertTrue('Outer layout wraps', str_contains($output, '<outer>'));
    $t->assertTrue('Inner extend wraps', str_contains($output, '<inner>'));
    $t->assertTrue('Deep content present', str_contains($output, 'Deep Content'));

    renderer_layout_cleanup($tmpDir);
    return $t;
}

function test_renderer_layout_with_composer(): Test
{
    $t = new Test('Renderer Layout - Layout with Composer');

    $tmpDir = renderer_layout_test_tmp_dir('layout_composer');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/layout.php', '<div><?php echo $this->e($composedVar); ?><?php echo $content; ?></div>');
    file_put_contents($tmpDir . '/test.php', '<?php $this->layout("layout"); ?>Content');

    $renderer = $renderer->composer('test', function() {
        return ['composedVar' => 'Composed'];
    });

    $output = $renderer->render('test');

    $t->assertTrue('Composer data in layout', str_contains($output, 'Composed'));

    renderer_layout_cleanup($tmpDir);
    return $t;
}

function test_renderer_layout_nested_extend(): Test
{
    $t = new Test('Renderer Layout - Nested Extend');

    $tmpDir = renderer_layout_test_tmp_dir('layout_nested_extend');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/grandparent.php', '<gp><?php echo $content; ?></gp>');
    file_put_contents($tmpDir . '/parent.php', '<?php echo $this->extend("grandparent"); ?><parent><?php echo $content; ?></parent>');
    file_put_contents($tmpDir . '/test.php', '<?php echo $this->extend("parent"); ?>Child Content');

    $output = $renderer->render('test');

    $t->assertTrue('Grandparent wraps', str_contains($output, '<gp>'));
    $t->assertTrue('Parent wraps', str_contains($output, '<parent>'));
    $t->assertTrue('Child content present', str_contains($output, 'Child Content'));

    renderer_layout_cleanup($tmpDir);
    return $t;
}

register_tests(
    'test_renderer_layout_basic',
    'test_renderer_layout_with_slots',
    'test_renderer_layout_with_data',
    'test_renderer_layout_with_globals',
    'test_renderer_layout_missing_returns_content',
    'test_renderer_extend_basic',
    'test_renderer_extend_with_data',
    'test_renderer_extend_with_slots',
    'test_renderer_extend_without_current_template_throws',
    'test_renderer_layout_and_extend_combined',
    'test_renderer_layout_with_composer',
    'test_renderer_layout_nested_extend'
);