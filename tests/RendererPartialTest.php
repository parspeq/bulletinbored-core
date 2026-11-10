<?php

/**
 * RendererPartialTest — tests for Renderer partial functionality.
 * Covers partial() and renderPartial().
 */

require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Renderer.php';
require_once __DIR__ . '/../src/helpers.php';

function renderer_partial_test_tmp_dir(string $prefix): string
{
    $base = sys_get_temp_dir() . '/bb_renderer_partial_' . $prefix . '_' . uniqid('', true);
    @mkdir($base, 0755, true);
    return $base;
}

function renderer_partial_cleanup(string $dir): void
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

function test_renderer_partial_basic(): Test
{
    $t = new Test('Renderer Partial - Basic Partial');

    $tmpDir = renderer_partial_test_tmp_dir('partial_basic');
    @mkdir($tmpDir . '/partials', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/partials/card.php', '<div class="card"><?php echo $this->e($title); ?></div>');
    file_put_contents($tmpDir . '/test.php', '<main><?php echo $this->partial("card", ["title" => "Card Title"]); ?></main>');

    $output = $renderer->render('test');

    $t->assertTrue('Partial rendered in template', str_contains($output, '<div class="card">Card Title</div>'));

    renderer_partial_cleanup($tmpDir);
    return $t;
}

function test_renderer_partial_with_globals(): Test
{
    $t = new Test('Renderer Partial - Partial with Globals');

    $tmpDir = renderer_partial_test_tmp_dir('partial_globals');
    @mkdir($tmpDir . '/partials', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/partials/header.php', '<header><?php echo $this->e($siteName); ?></header>');
    file_put_contents($tmpDir . '/test.php', '<body><?php echo $this->partial("header"); ?></body>');

    $renderer = $renderer->addGlobal('siteName', 'My Forum');
    $output = $renderer->render('test');

    $t->assertTrue('Global accessible in partial', str_contains($output, 'My Forum'));

    renderer_partial_cleanup($tmpDir);
    return $t;
}

function test_renderer_partial_data_overrides_global(): Test
{
    $t = new Test('Renderer Partial - Partial Data Overrides Global');

    $tmpDir = renderer_partial_test_tmp_dir('partial_override');
    @mkdir($tmpDir . '/partials', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/partials/item.php', '<li><?php echo $this->e($name); ?></li>');
    file_put_contents($tmpDir . '/test.php', '<ul><?php echo $this->partial("item", ["name" => "Local"]); ?></ul>');

    $renderer = $renderer->addGlobal('name', 'Global');
    $output = $renderer->render('test');

    $t->assertEquals('Local data overrides global in partial', 'Local', $output);

    renderer_partial_cleanup($tmpDir);
    return $t;
}

function test_renderer_partial_missing_returns_empty(): Test
{
    $t = new Test('Renderer Partial - Missing Partial Returns Empty String');

    $tmpDir = renderer_partial_test_tmp_dir('partial_missing');
    @mkdir($tmpDir . '/partials', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<div><?php echo $this->partial("nonexistent"); ?></div>');

    $output = $renderer->render('test');

    $t->assertEquals('Missing partial returns empty', '<div></div>', $output);

    renderer_partial_cleanup($tmpDir);
    return $t;
}

function test_renderer_partial_nested(): Test
{
    $t = new Test('Renderer Partial - Nested Partials');

    $tmpDir = renderer_partial_test_tmp_dir('partial_nested');
    @mkdir($tmpDir . '/partials', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/partials/inner.php', '<span><?php echo $this->e($inner); ?></span>');
    file_put_contents($tmpDir . '/partials/outer.php', '<div><?php echo $this->partial("inner", ["inner" => "Inner Content"]); ?></div>');
    file_put_contents($tmpDir . '/test.php', '<main><?php echo $this->partial("outer"); ?></main>');

    $output = $renderer->render('test');

    $t->assertTrue('Nested partials work', str_contains($output, '<span>Inner Content</span>'));

    renderer_partial_cleanup($tmpDir);
    return $t;
}

function test_renderer_partial_with_complex_data(): Test
{
    $t = new Test('Renderer Partial - Partial with Complex Data');

    $tmpDir = renderer_partial_test_tmp_dir('partial_complex');
    @mkdir($tmpDir . '/partials', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/partials/user.php', '<div><?php echo $this->e($user["name"]) . " <" . $this->e($user["email"]) . ">"; ?></div>');
    file_put_contents($tmpDir . '/test.php', '<section><?php echo $this->partial("user", ["user" => ["name" => "Alice", "email" => "alice@example.com"]]); ?></section>');

    $output = $renderer->render('test');

    $t->assertTrue('Complex data accessible in partial', str_contains($output, 'Alice'));
    $t->assertTrue('Email accessible in partial', str_contains($output, 'alice@example.com'));

    renderer_partial_cleanup($tmpDir);
    return $t;
}

function test_renderer_renderPartial_outputs(): Test
{
    $t = new Test('Renderer Partial - renderPartial Outputs to STDOUT');

    $tmpDir = renderer_partial_test_tmp_dir('renderPartial');
    @mkdir($tmpDir . '/partials', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/partials/notice.php', '<div class="notice"><?php echo $this->e($message); ?></div>');

    ob_start();
    $renderer->renderPartial('notice', ['message' => 'Important notice']);
    $output = ob_get_clean();

    $t->assertTrue('renderPartial outputs to buffer', str_contains($output, 'Important notice'));

    renderer_partial_cleanup($tmpDir);
    return $t;
}

function test_renderer_partial_escaping(): Test
{
    $t = new Test('Renderer Partial - Escaping in Partial');

    $tmpDir = renderer_partial_test_tmp_dir('partial_escaping');
    @mkdir($tmpDir . '/partials', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/partials/safe.php', '<p><?php echo $this->e($content); ?></p>');
    file_put_contents($tmpDir . '/test.php', '<div><?php echo $this->partial("safe", ["content" => "<script>alert(1)</script>"]); ?></div>');

    $output = $renderer->render('test');

    $t->assertTrue('Content escaped in partial', str_contains($output, '<script>'));
    $t->assertTrue('No raw script tags', !str_contains($output, '<script>alert'));

    renderer_partial_cleanup($tmpDir);
    return $t;
}

function test_renderer_partial_multiple_in_one_template(): Test
{
    $t = new Test('Renderer Partial - Multiple Partials in One Template');

    $tmpDir = renderer_partial_test_tmp_dir('partial_multiple');
    @mkdir($tmpDir . '/partials', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/partials/header.php', '<header>Header</header>');
    file_put_contents($tmpDir . '/partials/footer.php', '<footer>Footer</footer>');
    file_put_contents($tmpDir . '/test.php', '<body><?php echo $this->partial("header"); ?><?php echo $this->partial("footer"); ?></body>');

    $output = $renderer->render('test');

    $t->assertTrue('Header partial rendered', str_contains($output, 'Header'));
    $t->assertTrue('Footer partial rendered', str_contains($output, 'Footer'));

    renderer_partial_cleanup($tmpDir);
    return $t;
}

function test_renderer_partial_with_composer(): Test
{
    $t = new Test('Renderer Partial - Partial with Composer Data');

    $tmpDir = renderer_partial_test_tmp_dir('partial_composer');
    @mkdir($tmpDir . '/partials', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/partials/widget.php', '<div><?php echo $this->e($widgetTitle); ?></div>');
    file_put_contents($tmpDir . '/test.php', '<main><?php echo $this->partial("widget"); ?></main>');

    $renderer = $renderer->composer('test', function() {
        return ['widgetTitle' => 'Composed Widget'];
    });

    $output = $renderer->render('test');

    $t->assertTrue('Composer data accessible in partial', str_contains($output, 'Composed Widget'));

    renderer_partial_cleanup($tmpDir);
    return $t;
}

function test_renderer_partial_returns_string(): Test
{
    $t = new Test('Renderer Partial - partial() Returns String');

    $tmpDir = renderer_partial_test_tmp_dir('partial_returns_string');
    @mkdir($tmpDir . '/partials', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/partials/snippet.php', '<span>Snippet</span>');

    $result = $renderer->partial('snippet');

    $t->assertTrue('Returns string', is_string($result));
    $t->assertEquals('Correct content', '<span>Snippet</span>', $result);

    renderer_partial_cleanup($tmpDir);
    return $t;
}

register_tests(
    'test_renderer_partial_basic',
    'test_renderer_partial_with_globals',
    'test_renderer_partial_data_overrides_global',
    'test_renderer_partial_missing_returns_empty',
    'test_renderer_partial_nested',
    'test_renderer_partial_with_complex_data',
    'test_renderer_renderPartial_outputs',
    'test_renderer_partial_escaping',
    'test_renderer_partial_multiple_in_one_template',
    'test_renderer_partial_with_composer',
    'test_renderer_partial_returns_string'
);