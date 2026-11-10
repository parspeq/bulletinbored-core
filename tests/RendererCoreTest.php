<?php

/**
 * RendererCoreTest — tests for Renderer core functionality.
 * Covers render(), display(), composer(), composeAll(), addGlobal().
 */

require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Renderer.php';
require_once __DIR__ . '/../src/helpers.php';

function renderer_core_test_tmp_dir(string $prefix): string
{
    $base = sys_get_temp_dir() . '/bb_renderer_core_' . $prefix . '_' . uniqid('', true);
    @mkdir($base, 0755, true);
    return $base;
}

function renderer_core_cleanup(string $dir): void
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

function test_renderer_render_basic(): Test
{
    $t = new Test('Renderer Core - Basic Render');

    $tmpDir = renderer_core_test_tmp_dir('render_basic');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<h1><?php echo $this->e($title); ?></h1>');

    $output = $renderer->render('test', ['title' => 'Hello World']);

    $t->assertTrue('Output contains rendered title', str_contains($output, 'Hello World'));
    $t->assertTrue('Output contains h1 tags', str_contains($output, '<h1>'));

    renderer_core_cleanup($tmpDir);
    return $t;
}

function test_renderer_render_with_special_chars(): Test
{
    $t = new Test('Renderer Core - Render with Special Characters');

    $tmpDir = renderer_core_test_tmp_dir('render_special');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<div><?php echo $this->e($content); ?></div>');

    $output = $renderer->render('test', ['content' => '<script>alert(1)</script> & "quotes"']);

    $t->assertTrue('Script tags escaped', str_contains($output, '<script>'));
    $t->assertTrue('Ampersand escaped', str_contains($output, '&'));
    $t->assertTrue('Quotes escaped', str_contains($output, '"'));

    renderer_core_cleanup($tmpDir);
    return $t;
}

function test_renderer_display_outputs(): Test
{
    $t = new Test('Renderer Core - Display Outputs to STDOUT');

    $tmpDir = renderer_core_test_tmp_dir('display');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<p><?php echo $this->e($message); ?></p>');

    ob_start();
    $renderer->display('test', ['message' => 'Displayed message']);
    $output = ob_get_clean();

    $t->assertTrue('Display outputs to buffer', str_contains($output, 'Displayed message'));

    renderer_core_cleanup($tmpDir);
    return $t;
}

function test_renderer_addGlobal(): Test
{
    $t = new Test('Renderer Core - Add Global Variable');

    $tmpDir = renderer_core_test_tmp_dir('addGlobal');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<span><?php echo $this->e($siteName); ?></span>');

    $renderer = $renderer->addGlobal('siteName', 'Global Forum');
    $output = $renderer->render('test');

    $t->assertTrue('Global variable accessible in template', str_contains($output, 'Global Forum'));

    renderer_core_cleanup($tmpDir);
    return $t;
}

function test_renderer_addGlobal_returns_clone(): Test
{
    $t = new Test('Renderer Core - Add Global Returns Clone');

    $tmpDir = renderer_core_test_tmp_dir('addGlobal_clone');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($value); ?>');

    $renderer2 = $renderer->addGlobal('value', 'Global');
    $output1 = $renderer->render('test', ['value' => 'Local']);
    $output2 = $renderer2->render('test');

    $t->assertEquals('Original renderer uses local data', 'Local', $output1);
    $t->assertEquals('Cloned renderer uses global', 'Global', $output2);

    renderer_core_cleanup($tmpDir);
    return $t;
}

function test_renderer_data_overrides_global(): Test
{
    $t = new Test('Renderer Core - Data Overrides Global');

    $tmpDir = renderer_core_test_tmp_dir('data_overrides');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($title); ?>');

    $renderer = $renderer->addGlobal('title', 'Global Title');
    $output = $renderer->render('test', ['title' => 'Local Title']);

    $t->assertEquals('Local data overrides global', 'Local Title', $output);

    renderer_core_cleanup($tmpDir);
    return $t;
}

function test_renderer_composer_single(): Test
{
    $t = new Test('Renderer Core - Single Composer');

    $tmpDir = renderer_core_test_tmp_dir('composer_single');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($composed); ?>');

    $renderer = $renderer->composer('test', function() {
        return ['composed' => 'Composed Value'];
    });

    $output = $renderer->render('test');

    $t->assertEquals('Composer provides data', 'Composed Value', $output);

    renderer_core_cleanup($tmpDir);
    return $t;
}

function test_renderer_composer_multiple(): Test
{
    $t = new Test('Renderer Core - Multiple Composers for Same Template');

    $tmpDir = renderer_core_test_tmp_dir('composer_multiple');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($a) . "-" . $this->e($b); ?>');

    $renderer = $renderer->composer('test', function() {
        return ['a' => 'First'];
    });
    $renderer = $renderer->composer('test', function() {
        return ['b' => 'Second'];
    });

    $output = $renderer->render('test');

    $t->assertEquals('Multiple composers merged', 'First-Second', $output);

    renderer_core_cleanup($tmpDir);
    return $t;
}

function test_renderer_composeAll(): Test
{
    $t = new Test('Renderer Core - ComposeAll Runs for All Templates');

    $tmpDir = renderer_core_test_tmp_dir('composeAll');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test1.php', '<?php echo $this->e($globalVar); ?>');
    file_put_contents($tmpDir . '/test2.php', '<?php echo $this->e($globalVar); ?>');

    $renderer = $renderer->composeAll(function() {
        return ['globalVar' => 'Global Composed'];
    });

    $output1 = $renderer->render('test1');
    $output2 = $renderer->render('test2');

    $t->assertEquals('ComposeAll runs for test1', 'Global Composed', $output1);
    $t->assertEquals('ComposeAll runs for test2', 'Global Composed', $output2);

    renderer_core_cleanup($tmpDir);
    return $t;
}

function test_renderer_composeAll_and_composer_merge(): Test
{
    $t = new Test('Renderer Core - ComposeAll and Composer Data Merged');

    $tmpDir = renderer_core_test_tmp_dir('composeAll_merge');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($global) . "-" . $this->e($specific); ?>');

    $renderer = $renderer->composeAll(function() {
        return ['global' => 'Global'];
    });
    $renderer = $renderer->composer('test', function() {
        return ['specific' => 'Specific'];
    });

    $output = $renderer->render('test');

    $t->assertEquals('Both composeAll and composer data merged', 'Global-Specific', $output);

    renderer_core_cleanup($tmpDir);
    return $t;
}

function test_renderer_composer_priority_over_composeAll(): Test
{
    $t = new Test('Renderer Core - Composer Data Overrides ComposeAll');

    $tmpDir = renderer_core_test_tmp_dir('composer_priority');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($value); ?>');

    $renderer = $renderer->composeAll(function() {
        return ['value' => 'From ComposeAll'];
    });
    $renderer = $renderer->composer('test', function() {
        return ['value' => 'From Composer'];
    });

    $output = $renderer->render('test');

    $t->assertEquals('Composer overrides composeAll', 'From Composer', $output);

    renderer_core_cleanup($tmpDir);
    return $t;
}

function test_renderer_render_returns_string(): Test
{
    $t = new Test('Renderer Core - Render Returns String');

    $tmpDir = renderer_core_test_tmp_dir('render_returns_string');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<div>Content</div>');

    $output = $renderer->render('test');

    $t->assertTrue('Returns string', is_string($output));
    $t->assertEquals('Correct content', '<div>Content</div>', $output);

    renderer_core_cleanup($tmpDir);
    return $t;
}

function test_renderer_render_missing_template_throws(): Test
{
    $t = new Test('Renderer Core - Missing Template Throws RuntimeException');

    $tmpDir = renderer_core_test_tmp_dir('missing_template');
    $renderer = new \Bulletin\Renderer($tmpDir);

    try {
        $renderer->render('nonexistent');
        $t->assert('Should throw RuntimeException', false);
    } catch (\RuntimeException $e) {
        $t->assertTrue('Throws RuntimeException', true);
        $t->assertTrue('Message mentions template', str_contains($e->getMessage(), 'Template not found'));
    }

    renderer_core_cleanup($tmpDir);
    return $t;
}

function test_renderer_render_with_empty_data(): Test
{
    $t = new Test('Renderer Core - Render with Empty Data Array');

    $tmpDir = renderer_core_test_tmp_dir('empty_data');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<p><?php echo $this->e($missing ?? "default"); ?></p>');

    $output = $renderer->render('test', []);

    $t->assertTrue('Uses default when variable missing', str_contains($output, 'default'));

    renderer_core_cleanup($tmpDir);
    return $t;
}

function test_renderer_render_with_complex_data(): Test
{
    $t = new Test('Renderer Core - Render with Complex Data');

    $tmpDir = renderer_core_test_tmp_dir('complex_data');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($user["name"]) . " - " . $this->e($user["email"]); ?>');

    $output = $renderer->render('test', [
        'user' => [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]
    ]);

    $t->assertEquals('Complex data accessible', 'John Doe - john@example.com', $output);

    renderer_core_cleanup($tmpDir);
    return $t;
}

function test_renderer_multiple_renders_independent(): Test
{
    $t = new Test('Renderer Core - Multiple Renders Are Independent');

    $tmpDir = renderer_core_test_tmp_dir('multiple_renders');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($value); ?>');

    $output1 = $renderer->render('test', ['value' => 'First']);
    $output2 = $renderer->render('test', ['value' => 'Second']);

    $t->assertEquals('First render', 'First', $output1);
    $t->assertEquals('Second render', 'Second', $output2);

    renderer_core_cleanup($tmpDir);
    return $t;
}

function test_renderer_clone_preserves_state(): Test
{
    $t = new Test('Renderer Core - Clone Preserves State');

    $tmpDir = renderer_core_test_tmp_dir('clone_state');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php echo $this->e($value); ?>');

    $renderer = $renderer->addGlobal('global', 'GlobalValue');
    $renderer = $renderer->composer('test', function() {
        return ['composed' => 'ComposedValue'];
    });

    $output = $renderer->render('test', ['value' => 'LocalValue']);

    $t->assertTrue('Global accessible', str_contains($output, 'GlobalValue'));
    $t->assertTrue('Composed accessible', str_contains($output, 'ComposedValue'));
    $t->assertTrue('Local accessible', str_contains($output, 'LocalValue'));

    renderer_core_cleanup($tmpDir);
    return $t;
}

register_tests(
    'test_renderer_render_basic',
    'test_renderer_render_with_special_chars',
    'test_renderer_display_outputs',
    'test_renderer_addGlobal',
    'test_renderer_addGlobal_returns_clone',
    'test_renderer_data_overrides_global',
    'test_renderer_composer_single',
    'test_renderer_composer_multiple',
    'test_renderer_composeAll',
    'test_renderer_composeAll_and_composer_merge',
    'test_renderer_composer_priority_over_composeAll',
    'test_renderer_render_returns_string',
    'test_renderer_render_missing_template_throws',
    'test_renderer_render_with_empty_data',
    'test_renderer_render_with_complex_data',
    'test_renderer_multiple_renders_independent',
    'test_renderer_clone_preserves_state'
);