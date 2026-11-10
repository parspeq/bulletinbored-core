<?php

/**
 * RendererEscapeTest — tests for Renderer escaping functionality.
 * Covers e(), raw(), escapeAttr().
 */

require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Renderer.php';
require_once __DIR__ . '/../src/helpers.php';

function renderer_escape_test_tmp_dir(string $prefix): string
{
    $base = sys_get_temp_dir() . '/bb_renderer_escape_' . $prefix . '_' . uniqid('', true);
    @mkdir($base, 0755, true);
    return $base;
}

function renderer_escape_cleanup(string $dir): void
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

function test_renderer_e_basic(): Test
{
    $t = new Test('Renderer Escape - e() Basic Escaping');

    $tmpDir = renderer_escape_test_tmp_dir('e_basic');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($value); ?>');

    $output = $renderer->render('test', ['value' => '<script>alert(1)</script>']);

    $t->assertTrue('Escapes <', str_contains($output, '<'));
    $t->assertTrue('Escapes >', str_contains($output, '>'));
    $t->assertFalse('No raw <', str_contains($output, '<script>'));

    renderer_escape_cleanup($tmpDir);
    return $t;
}

function test_renderer_e_quotes(): Test
{
    $t = new Test('Renderer Escape - e() Escapes Quotes');

    $tmpDir = renderer_escape_test_tmp_dir('e_quotes');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($value); ?>');

    $output = $renderer->render('test', ['value' => 'He said "Hello" and \'Hi\'']);

    $t->assertTrue('Escapes double quotes', str_contains($output, '"'));
    $t->assertTrue('Escapes single quotes', str_contains($output, '&#039;'));

    renderer_escape_cleanup($tmpDir);
    return $t;
}

function test_renderer_e_ampersand(): Test
{
    $t = new Test('Renderer Escape - e() Escapes Ampersand');

    $tmpDir = renderer_escape_test_tmp_dir('e_ampersand');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($value); ?>');

    $output = $renderer->render('test', ['value' => 'Tom & Jerry']);

    $t->assertTrue('Escapes ampersand', str_contains($output, '&'));

    renderer_escape_cleanup($tmpDir);
    return $t;
}

function test_renderer_e_null(): Test
{
    $t = new Test('Renderer Escape - e() Handles Null');

    $tmpDir = renderer_escape_test_tmp_dir('e_null');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($value); ?>');

    $output = $renderer->render('test', ['value' => null]);

    $t->assertEquals('Null becomes empty string', '', $output);

    renderer_escape_cleanup($tmpDir);
    return $t;
}

function test_renderer_e_integer(): Test
{
    $t = new Test('Renderer Escape - e() Handles Integer');

    $tmpDir = renderer_escape_test_tmp_dir('e_integer');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($value); ?>');

    $output = $renderer->render('test', ['value' => 42]);

    $t->assertEquals('Integer converted to string', '42', $output);

    renderer_escape_cleanup($tmpDir);
    return $t;
}

function test_renderer_e_array(): Test
{
    $t = new Test('Renderer Escape - e() Handles Array');

    $tmpDir = renderer_escape_test_tmp_dir('e_array');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($value); ?>');

    $output = $renderer->render('test', ['value' => ['a', 'b']]);

    $t->assertEquals('Array becomes "Array"', 'Array', $output);

    renderer_escape_cleanup($tmpDir);
    return $t;
}

function test_renderer_raw_basic(): Test
{
    $t = new Test('Renderer Escape - raw() Returns Unescaped');

    $tmpDir = renderer_escape_test_tmp_dir('raw_basic');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->raw($value); ?>');

    $output = $renderer->render('test', ['value' => '<strong>Bold</strong>']);

    $t->assertEquals('raw() returns unescaped HTML', '<strong>Bold</strong>', $output);

    renderer_escape_cleanup($tmpDir);
    return $t;
}

function test_renderer_raw_vs_e(): Test
{
    $t = new Test('Renderer Escape - raw() vs e() Difference');

    $tmpDir = renderer_escape_test_tmp_dir('raw_vs_e');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo "e: " . $this->e($value) . " | raw: " . $this->raw($value); ?>');

    $output = $renderer->render('test', ['value' => '<em>Emphasis</em>']);

    $t->assertTrue('e() escapes', str_contains($output, '<em>'));
    $t->assertTrue('raw() does not escape', str_contains($output, '<em>Emphasis</em>'));

    renderer_escape_cleanup($tmpDir);
    return $t;
}

function test_renderer_escapeAttr_basic(): Test
{
    $t = new Test('Renderer Escape - escapeAttr() Basic');

    $tmpDir = renderer_escape_test_tmp_dir('escapeAttr_basic');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<input value="<?php echo $this->escapeAttr($value); ?>">');

    $output = $renderer->render('test', ['value' => 'Value with "quotes" and <tags>']);

    $t->assertTrue('Escapes double quotes for attributes', str_contains($output, '"'));
    $t->assertTrue('Escapes < for attributes', str_contains($output, '<'));
    $t->assertTrue('Escapes > for attributes', str_contains($output, '>'));

    renderer_escape_cleanup($tmpDir);
    return $t;
}

function test_renderer_escapeAttr_single_quotes(): Test
{
    $t = new Test('Renderer Escape - escapeAttr() Escapes Single Quotes');

    $tmpDir = renderer_escape_test_tmp_dir('escapeAttr_single');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<input value=\'<?php echo $this->escapeAttr($value); ?>\'>');

    $output = $renderer->render('test', ['value' => "It's working"]);

    $t->assertTrue('Escapes single quotes', str_contains($output, '&#039;'));

    renderer_escape_cleanup($tmpDir);
    return $t;
}

function test_renderer_escapeAttr_in_template(): Test
{
    $t = new Test('Renderer Escape - escapeAttr() in Template Context');

    $tmpDir = renderer_escape_test_tmp_dir('escapeAttr_template');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<a href="<?php echo $this->escapeAttr($url); ?>" title="<?php echo $this->escapeAttr($title); ?>">Link</a>');

    $output = $renderer->render('test', [
        'url' => 'https://example.com?param="value"',
        'title' => 'Title with "quotes"'
    ]);

    $t->assertTrue('URL escaped for attribute', str_contains($output, '"'));
    $t->assertTrue('Title escaped for attribute', str_contains($output, '"'));

    renderer_escape_cleanup($tmpDir);
    return $t;
}

function test_renderer_e_vs_escapeAttr(): Test
{
    $t = new Test('Renderer Escape - e() vs escapeAttr()');

    $tmpDir = renderer_escape_test_tmp_dir('e_vs_escapeAttr');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo "e: " . $this->e($value) . " | attr: " . $this->escapeAttr($value); ?>');

    $output = $renderer->render('test', ['value' => '"quoted"']);

    $t->assertTrue('Both escape quotes', str_contains($output, '"'));

    renderer_escape_cleanup($tmpDir);
    return $t;
}

function test_renderer_escape_unicode(): Test
{
    $t = new Test('Renderer Escape - Unicode Handling');

    $tmpDir = renderer_escape_test_tmp_dir('escape_unicode');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($value); ?>');

    $output = $renderer->render('test', ['value' => '日本語 🎉']);

    $t->assertEquals('Unicode preserved', '日本語 🎉', $output);

    renderer_escape_cleanup($tmpDir);
    return $t;
}

function test_renderer_escape_newlines(): Test
{
    $t = new Test('Renderer Escape - Newlines Preserved');

    $tmpDir = renderer_escape_test_tmp_dir('escape_newlines');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($value); ?>');

    $output = $renderer->render('test', ['value' => "Line 1\nLine 2\r\nLine 3"]);

    $t->assertTrue('Newlines preserved', str_contains($output, "\n"));

    renderer_escape_cleanup($tmpDir);
    return $t;
}

register_tests(
    'test_renderer_e_basic',
    'test_renderer_e_quotes',
    'test_renderer_e_ampersand',
    'test_renderer_e_null',
    'test_renderer_e_integer',
    'test_renderer_e_array',
    'test_renderer_raw_basic',
    'test_renderer_raw_vs_e',
    'test_renderer_escapeAttr_basic',
    'test_renderer_escapeAttr_single_quotes',
    'test_renderer_escapeAttr_in_template',
    'test_renderer_e_vs_escapeAttr',
    'test_renderer_escape_unicode',
    'test_renderer_escape_newlines'
);