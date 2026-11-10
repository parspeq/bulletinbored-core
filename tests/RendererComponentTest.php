<?php

/**
 * RendererComponentTest — tests for Renderer component functionality.
 * Covers renderComponent() and displayComponent().
 */

require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Renderer.php';
require_once __DIR__ . '/../src/helpers.php';

function renderer_component_test_tmp_dir(string $prefix): string
{
    $base = sys_get_temp_dir() . '/bb_renderer_component_' . $prefix . '_' . uniqid('', true);
    @mkdir($base, 0755, true);
    return $base;
}

function renderer_component_cleanup(string $dir): void
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

function test_renderer_renderComponent_basic(): Test
{
    $t = new Test('Renderer Component - renderComponent() Basic');

    $tmpDir = renderer_component_test_tmp_dir('component_basic');
    @mkdir($tmpDir . '/components', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/components/button.php', '<button><?php echo $this->e($label); ?></button>');
    file_put_contents($tmpDir . '/test.php', '<div><?php echo $this->renderComponent("button", ["label" => "Click Me"]); ?></div>');

    $output = $renderer->render('test');

    $t->assertTrue('Component rendered', str_contains($output, '<button>Click Me</button>'));

    renderer_component_cleanup($tmpDir);
    return $t;
}

function test_renderer_renderComponent_with_globals(): Test
{
    $t = new Test('Renderer Component - renderComponent() with Globals');

    $tmpDir = renderer_component_test_tmp_dir('component_globals');
    @mkdir($tmpDir . '/components', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/components/header.php', '<header><?php echo $this->e($siteName); ?></header>');
    file_put_contents($tmpDir . '/test.php', '<body><?php echo $this->renderComponent("header"); ?></body>');

    $renderer = $renderer->addGlobal('siteName', 'My Site');
    $output = $renderer->render('test');

    $t->assertTrue('Global accessible in component', str_contains($output, 'My Site'));

    renderer_component_cleanup($tmpDir);
    return $t;
}

function test_renderer_renderComponent_data_overrides_global(): Test
{
    $t = new Test('Renderer Component - renderComponent() Data Overrides Global');

    $tmpDir = renderer_component_test_tmp_dir('component_override');
    @mkdir($tmpDir . '/components', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/components/title.php', '<h1><?php echo $this->e($title); ?></h1>');
    file_put_contents($tmpDir . '/test.php', '<main><?php echo $this->renderComponent("title", ["title" => "Local Title"]); ?></main>');

    $renderer = $renderer->addGlobal('title', 'Global Title');
    $output = $renderer->render('test');

    $t->assertEquals('Local data overrides global', 'Local Title', $output);

    renderer_component_cleanup($tmpDir);
    return $t;
}

function test_renderer_renderComponent_missing_returns_empty(): Test
{
    $t = new Test('Renderer Component - Missing Component Returns Empty String');

    $tmpDir = renderer_component_test_tmp_dir('component_missing');
    @mkdir($tmpDir . '/components', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<div><?php echo $this->renderComponent("nonexistent"); ?></div>');

    $output = $renderer->render('test');

    $t->assertEquals('Missing component returns empty', '<div></div>', $output);

    renderer_component_cleanup($tmpDir);
    return $t;
}

function test_renderer_renderComponent_with_complex_data(): Test
{
    $t = new Test('Renderer Component - renderComponent() with Complex Data');

    $tmpDir = renderer_component_test_tmp_dir('component_complex');
    @mkdir($tmpDir . '/components', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/components/user_card.php', '<div class="user"><?php echo $this->e($user["name"]) . " <" . $this->e($user["email"]) . ">"; ?></div>');
    file_put_contents($tmpDir . '/test.php', '<section><?php echo $this->renderComponent("user_card", ["user" => ["name" => "Bob", "email" => "bob@example.com"]]); ?></section>');

    $output = $renderer->render('test');

    $t->assertTrue('Complex data in component', str_contains($output, 'Bob'));
    $t->assertTrue('Email in component', str_contains($output, 'bob@example.com'));

    renderer_component_cleanup($tmpDir);
    return $t;
}

function test_renderer_displayComponent_outputs(): Test
{
    $t = new Test('Renderer Component - displayComponent() Outputs to STDOUT');

    $tmpDir = renderer_component_test_tmp_dir('displayComponent');
    @mkdir($tmpDir . '/components', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/components/alert.php', '<div class="alert"><?php echo $this->e($message); ?></div>');

    ob_start();
    $renderer->displayComponent('alert', ['message' => 'Warning!']);
    $output = ob_get_clean();

    $t->assertTrue('displayComponent outputs to buffer', str_contains($output, 'Warning!'));

    renderer_component_cleanup($tmpDir);
    return $t;
}

function test_renderer_renderComponent_escaping(): Test
{
    $t = new Test('Renderer Component - renderComponent() Escaping');

    $tmpDir = renderer_component_test_tmp_dir('component_escaping');
    @mkdir($tmpDir . '/components', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/components/safe.php', '<p><?php echo $this->e($content); ?></p>');
    file_put_contents($tmpDir . '/test.php', '<div><?php echo $this->renderComponent("safe", ["content" => "<script>alert(1)</script>"]); ?></div>');

    $output = $renderer->render('test');

    $t->assertTrue('Content escaped in component', str_contains($output, '<script>'));
    $t->assertTrue('No raw script tags', !str_contains($output, '<script>alert'));

    renderer_component_cleanup($tmpDir);
    return $t;
}

function test_renderer_renderComponent_nested(): Test
{
    $t = new Test('Renderer Component - Nested Components');

    $tmpDir = renderer_component_test_tmp_dir('component_nested');
    @mkdir($tmpDir . '/components', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/components/inner.php', '<span><?php echo $this->e($text); ?></span>');
    file_put_contents($tmpDir . '/components/outer.php', '<div><?php echo $this->renderComponent("inner", ["text" => "Inner"]); ?></div>');
    file_put_contents($tmpDir . '/test.php', '<main><?php echo $this->renderComponent("outer"); ?></main>');

    $output = $renderer->render('test');

    $t->assertTrue('Nested components work', str_contains($output, '<span>Inner</span>'));

    renderer_component_cleanup($tmpDir);
    return $t;
}

function test_renderer_renderComponent_with_composer(): Test
{
    $t = new Test('Renderer Component - renderComponent() with Composer Data');

    $tmpDir = renderer_component_test_tmp_dir('component_composer');
    @mkdir($tmpDir . '/components', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/components/widget.php', '<div><?php echo $this->e($widgetTitle); ?></div>');
    file_put_contents($tmpDir . '/test.php', '<aside><?php echo $this->renderComponent("widget"); ?></aside>');

    $renderer = $renderer->composer('test', function() {
        return ['widgetTitle' => 'Composed Widget'];
    });

    $output = $renderer->render('test');

    $t->assertTrue('Composer data in component', str_contains($output, 'Composed Widget'));

    renderer_component_cleanup($tmpDir);
    return $t;
}

function test_renderer_renderComponent_multiple(): Test
{
    $t = new Test('Renderer Component - Multiple Components in One Template');

    $tmpDir = renderer_component_test_tmp_dir('component_multiple');
    @mkdir($tmpDir . '/components', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/components/header.php', '<header>Header</header>');
    file_put_contents($tmpDir . '/components/footer.php', '<footer>Footer</footer>');
    file_put_contents($tmpDir . '/test.php', '<body><?php echo $this->renderComponent("header"); ?><?php echo $this->renderComponent("footer"); ?></body>');

    $output = $renderer->render('test');

    $t->assertTrue('Header component rendered', str_contains($output, 'Header'));
    $t->assertTrue('Footer component rendered', str_contains($output, 'Footer'));

    renderer_component_cleanup($tmpDir);
    return $t;
}

function test_renderer_renderComponent_returns_string(): Test
{
    $t = new Test('Renderer Component - renderComponent() Returns String');

    $tmpDir = renderer_component_test_tmp_dir('component_returns_string');
    @mkdir($tmpDir . '/components', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/components/badge.php', '<span class="badge">Badge</span>');

    $result = $renderer->renderComponent('badge');

    $t->assertTrue('Returns string', is_string($result));
    $t->assertEquals('Correct content', '<span class="badge">Badge</span>', $result);

    renderer_component_cleanup($tmpDir);
    return $t;
}

function test_renderer_renderComponent_in_slot(): Test
{
    $t = new Test('Renderer Component - renderComponent() in Slot');

    $tmpDir = renderer_component_test_tmp_dir('component_slot');
    @mkdir($tmpDir . '/components', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/components/sidebar.php', '<aside>Sidebar</aside>');
    file_put_contents($tmpDir . '/test.php', '<?php $this->slot("sidebar"); ?><?php echo $this->renderComponent("sidebar"); ?><?php $this->endSlot(); ?><?php echo $this->slotContent("sidebar"); ?>');

    $output = $renderer->render('test');

    $t->assertTrue('Component in slot', str_contains($output, 'Sidebar'));

    renderer_component_cleanup($tmpDir);
    return $t;
}

function test_renderer_renderComponent_in_layout(): Test
{
    $t = new Test('Renderer Component - renderComponent() in Layout');

    $tmpDir = renderer_component_test_tmp_dir('component_layout');
    @mkdir($tmpDir . '/components', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/components/nav.php', '<nav>Navigation</nav>');
    file_put_contents($tmpDir . '/layout.php', '<html><body><?php echo $this->renderComponent("nav"); ?><?php echo $content; ?></body></html>');
    file_put_contents($tmpDir . '/test.php', '<?php $this->layout("layout"); ?>Content');

    $output = $renderer->render('test');

    $t->assertTrue('Component in layout', str_contains($output, 'Navigation'));
    $t->assertTrue('Content in layout', str_contains($output, 'Content'));

    renderer_component_cleanup($tmpDir);
    return $t;
}

register_tests(
    'test_renderer_renderComponent_basic',
    'test_renderer_renderComponent_with_globals',
    'test_renderer_renderComponent_data_overrides_global',
    'test_renderer_renderComponent_missing_returns_empty',
    'test_renderer_renderComponent_with_complex_data',
    'test_renderer_displayComponent_outputs',
    'test_renderer_renderComponent_escaping',
    'test_renderer_renderComponent_nested',
    'test_renderer_renderComponent_with_composer',
    'test_renderer_renderComponent_multiple',
    'test_renderer_renderComponent_returns_string',
    'test_renderer_renderComponent_in_slot',
    'test_renderer_renderComponent_in_layout'
);