<?php

/**
 * RendererUtilityTest — tests for Renderer utility methods.
 * Covers when() and each().
 */

require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Renderer.php';
require_once __DIR__ . '/../src/helpers.php';

function renderer_utility_test_tmp_dir(string $prefix): string
{
    $base = sys_get_temp_dir() . '/bb_renderer_utility_' . $prefix . '_' . uniqid('', true);
    @mkdir($base, 0755, true);
    return $base;
}

function renderer_utility_cleanup(string $dir): void
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

function test_renderer_when_true(): Test
{
    $t = new Test('Renderer Utility - when() True Condition');

    $tmpDir = renderer_utility_test_tmp_dir('when_true');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->when(true, fn($r) => "Condition Met"); ?>');

    $output = $renderer->render('test');

    $t->assertEquals('Returns callback result when true', 'Condition Met', $output);

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_when_false(): Test
{
    $t = new Test('Renderer Utility - when() False Condition');

    $tmpDir = renderer_utility_test_tmp_dir('when_false');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->when(false, fn($r) => "Should Not Appear"); ?>');

    $output = $renderer->render('test');

    $t->assertEquals('Returns empty string when false', '', $output);

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_when_with_renderer(): Test
{
    $t = new Test('Renderer Utility - when() Receives Renderer Instance');

    $tmpDir = renderer_utility_test_tmp_dir('when_renderer');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->when(true, fn($r) => $r->e("Escaped <Value>")); ?>');

    $output = $renderer->render('test');

    $t->assertEquals('Renderer passed to callback', 'Escaped <Value>', $output);

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_when_with_data(): Test
{
    $t = new Test('Renderer Utility - when() with Data Variables');

    $tmpDir = renderer_utility_test_tmp_dir('when_data');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->when($show, fn($r) => $r->e($message)); ?>');

    $output = $renderer->render('test', ['show' => true, 'message' => 'Hello World']);

    $t->assertEquals('Data accessible in callback', 'Hello World', $output);

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_when_false_with_data(): Test
{
    $t = new Test('Renderer Utility - when() False with Data');

    $tmpDir = renderer_utility_test_tmp_dir('when_false_data');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->when($show, fn($r) => $r->e($message)); ?>');

    $output = $renderer->render('test', ['show' => false, 'message' => 'Should Not Appear']);

    $t->assertEquals('Returns empty when false', '', $output);

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_when_nested(): Test
{
    $t = new Test('Renderer Utility - Nested when()');

    $tmpDir = renderer_utility_test_tmp_dir('when_nested');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->when(true, fn($r) => $r->when(true, fn($r2) => "Nested")); ?>');

    $output = $renderer->render('test');

    $t->assertEquals('Nested when works', 'Nested', $output);

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_when_in_template(): Test
{
    $t = new Test('Renderer Utility - when() in Template');

    $tmpDir = renderer_utility_test_tmp_dir('when_template');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<div><?php echo $this->when($showSidebar, fn($r) => "<aside>Sidebar</aside>"); ?></div>');

    $output = $renderer->render('test', ['showSidebar' => true]);

    $t->assertTrue('when() works in template', str_contains($output, '<aside>Sidebar</aside>'));

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_each_basic(): Test
{
    $t = new Test('Renderer Utility - each() Basic Iteration');

    $tmpDir = renderer_utility_test_tmp_dir('each_basic');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->each(["a", "b", "c"], fn($item, $key, $r) => $r->e($item) . "|"); ?>');

    $output = $renderer->render('test');

    $t->assertEquals('Iterates over array', 'a|b|c|', $output);

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_each_with_keys(): Test
{
    $t = new Test('Renderer Utility - each() with Keys');

    $tmpDir = renderer_utility_test_tmp_dir('each_keys');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->each(["x" => 1, "y" => 2], fn($item, $key, $r) => $r->e($key) . "=" . $r->e($item) . ";"); ?>');

    $output = $renderer->render('test');

    $t->assertTrue('Keys accessible', str_contains($output, 'x=1'));
    $t->assertTrue('Values accessible', str_contains($output, 'y=2'));

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_each_empty_array(): Test
{
    $t = new Test('Renderer Utility - each() Empty Array');

    $tmpDir = renderer_utility_test_tmp_dir('each_empty');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->each([], fn($item, $key, $r) => "item"); ?>');

    $output = $renderer->render('test');

    $t->assertEquals('Empty array returns empty string', '', $output);

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_each_with_renderer(): Test
{
    $t = new Test('Renderer Utility - each() Receives Renderer Instance');

    $tmpDir = renderer_utility_test_tmp_dir('each_renderer');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->each(["<test>"], fn($item, $key, $r) => $r->e($item)); ?>');

    $output = $renderer->render('test');

    $t->assertEquals('Renderer passed to callback', '<test>', $output);

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_each_with_data(): Test
{
    $t = new Test('Renderer Utility - each() with Data Variables');

    $tmpDir = renderer_utility_test_tmp_dir('each_data');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->each($items, fn($item, $key, $r) => $r->e($prefix) . $r->e($item) . "|"); ?>');

    $output = $renderer->render('test', ['prefix' => 'Item: ', 'items' => ['a', 'b']]);

    $t->assertEquals('Data accessible in callback', 'Item: a|Item: b|', $output);

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_each_in_template(): Test
{
    $t = new Test('Renderer Utility - each() in Template');

    $tmpDir = renderer_utility_test_tmp_dir('each_template');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<ul><?php echo $this->each($items, fn($item, $key, $r) => "<li>" . $r->e($item) . "</li>"); ?></ul>');

    $output = $renderer->render('test', ['items' => ['First', 'Second', 'Third']]);

    $t->assertTrue('each() works in template', str_contains($output, '<li>First</li>'));
    $t->assertTrue('All items rendered', str_contains($output, '<li>Second</li>'));
    $t->assertTrue('All items rendered', str_contains($output, '<li>Third</li>'));

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_each_assoc_array(): Test
{
    $t = new Test('Renderer Utility - each() with Associative Array');

    $tmpDir = renderer_utility_test_tmp_dir('each_assoc');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->each(["name" => "Alice", "role" => "Admin"], fn($item, $key, $r) => $r->e($key) . ":" . $r->e($item) . ";"); ?>');

    $output = $renderer->render('test');

    $t->assertTrue('Associative keys work', str_contains($output, 'name:Alice'));
    $t->assertTrue('Associative values work', str_contains($output, 'role:Admin'));

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_when_and_each_combined(): Test
{
    $t = new Test('Renderer Utility - when() and each() Combined');

    $tmpDir = renderer_utility_test_tmp_dir('when_each_combined');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->when($showList, fn($r) => $r->each($items, fn($item, $key, $r2) => $r2->e($item) . "|")); ?>');

    $output = $renderer->render('test', ['showList' => true, 'items' => ['a', 'b']]);

    $t->assertEquals('when and each combined', 'a|b|', $output);

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_when_false_skips_each(): Test
{
    $t = new Test('Renderer Utility - when() False Skips each()');

    $tmpDir = renderer_utility_test_tmp_dir('when_false_skips_each');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->when($showList, fn($r) => $r->each($items, fn($item, $key, $r2) => $r2->e($item))); ?>');

    $output = $renderer->render('test', ['showList' => false, 'items' => ['a', 'b']]);

    $t->assertEquals('when false skips each', '', $output);

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_each_returns_string(): Test
{
    $t = new Test('Renderer Utility - each() Returns String');

    $tmpDir = renderer_utility_test_tmp_dir('each_returns_string');
    $renderer = new \Bulletin\Renderer($tmpDir);

    $result = $renderer->each(['x', 'y'], fn($item, $key, $r) => $r->e($item));

    $t->assertTrue('Returns string', is_string($result));
    $t->assertEquals('Correct concatenated result', 'xy', $result);

    renderer_utility_cleanup($tmpDir);
    return $t;
}

function test_renderer_when_returns_string(): Test
{
    $t = new Test('Renderer Utility - when() Returns String');

    $tmpDir = renderer_utility_test_tmp_dir('when_returns_string');
    $renderer = new \Bulletin\Renderer($tmpDir);

    $result = $renderer->when(true, fn($r) => 'Result');

    $t->assertTrue('Returns string', is_string($result));
    $t->assertEquals('Correct result', 'Result', $result);

    renderer_utility_cleanup($tmpDir);
    return $t;
}

register_tests(
    'test_renderer_when_true',
    'test_renderer_when_false',
    'test_renderer_when_with_renderer',
    'test_renderer_when_with_data',
    'test_renderer_when_false_with_data',
    'test_renderer_when_nested',
    'test_renderer_when_in_template',
    'test_renderer_each_basic',
    'test_renderer_each_with_keys',
    'test_renderer_each_empty_array',
    'test_renderer_each_with_renderer',
    'test_renderer_each_with_data',
    'test_renderer_each_in_template',
    'test_renderer_each_assoc_array',
    'test_renderer_when_and_each_combined',
    'test_renderer_when_false_skips_each',
    'test_renderer_each_returns_string',
    'test_renderer_when_returns_string'
);