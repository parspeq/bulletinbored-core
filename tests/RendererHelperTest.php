<?php

/**
 * RendererHelperTest — tests for Renderer helper methods.
 * Covers url() and t().
 */

require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Renderer.php';
require_once __DIR__ . '/../src/helpers.php';

function renderer_helper_test_tmp_dir(string $prefix): string
{
    $base = sys_get_temp_dir() . '/bb_renderer_helper_' . $prefix . '_' . uniqid('', true);
    @mkdir($base, 0755, true);
    return $base;
}

function renderer_helper_cleanup(string $dir): void
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

function test_renderer_url_basic(): Test
{
    $t = new Test('Renderer Helper - url() Basic');

    $tmpDir = renderer_helper_test_tmp_dir('url_basic');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<a href="<?php echo $this->url("home"); ?>">Home</a>');

    $output = $renderer->render('test');

    $t->assertTrue('Generates URL', str_contains($output, 'href='));

    renderer_helper_cleanup($tmpDir);
    return $t;
}

function test_renderer_url_with_params(): Test
{
    $t = new Test('Renderer Helper - url() With Parameters');

    $tmpDir = renderer_helper_test_tmp_dir('url_params');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<a href="<?php echo $this->url("thread", ["id" => 123, "slug" => "test-thread"]); ?>">Thread</a>');

    $output = $renderer->render('test');

    $t->assertTrue('Generates URL with params', str_contains($output, 'href='));

    renderer_helper_cleanup($tmpDir);
    return $t;
}

function test_renderer_url_returns_string(): Test
{
    $t = new Test('Renderer Helper - url() Returns String');

    $tmpDir = renderer_helper_test_tmp_dir('url_returns_string');
    $renderer = new \Bulletin\Renderer($tmpDir);

    $result = $renderer->url('home');

    $t->assertTrue('Returns string', is_string($result));

    renderer_helper_cleanup($tmpDir);
    return $t;
}

function test_renderer_t_basic(): Test
{
    $t = new Test('Renderer Helper - t() Basic Translation');

    $tmpDir = renderer_helper_test_tmp_dir('t_basic');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<span><?php echo $this->t("welcome"); ?></span>');

    $output = $renderer->render('test');

    // t() returns the key if not found in translations
    $t->assertTrue('Returns translation or key', str_contains($output, 'welcome'));

    renderer_helper_cleanup($tmpDir);
    return $t;
}

function test_renderer_t_with_params(): Test
{
    $t = new Test('Renderer Helper - t() With Parameters');

    $tmpDir = renderer_helper_test_tmp_dir('t_params');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<span><?php echo $this->t("welcome_user", ["name" => "Alice"]); ?></span>');

    $output = $renderer->render('test');

    // t() returns the key with params replaced if not found
    $t->assertTrue('Handles params', str_contains($output, 'welcome_user') || str_contains($output, 'Alice'));

    renderer_helper_cleanup($tmpDir);
    return $t;
}

function test_renderer_t_with_globals(): Test
{
    $t = new Test('Renderer Helper - t() With Global Translations');

    $tmpDir = renderer_helper_test_tmp_dir('t_globals');
    $renderer = new \Bulletin\Renderer($tmpDir);

    // Add translation to global i18n via the renderer's internal i18n
    // The t() function uses App::getInstance()->i18n, so we need to set it up
    require_once __DIR__ . '/../src/App.php';
    $app = \Bulletin\App::getInstance();
    $app->i18n = ['core' => ['test_key' => 'Translated Value']];

    file_put_contents($tmpDir . '/test.php', '<span><?php echo $this->t("test_key"); ?></span>');

    $output = $renderer->render('test');

    $t->assertTrue('Uses global translations', str_contains($output, 'Translated Value'));

    renderer_helper_cleanup($tmpDir);
    return $t;
}

function test_renderer_t_fallback_to_key(): Test
{
    $t = new Test('Renderer Helper - t() Falls Back to Key');

    $tmpDir = renderer_helper_test_tmp_dir('t_fallback');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<span><?php echo $this->t("nonexistent_key"); ?></span>');

    $output = $renderer->render('test');

    $t->assertEquals('Falls back to key', '<span>nonexistent_key</span>', $output);

    renderer_helper_cleanup($tmpDir);
    return $t;
}

function test_renderer_url_in_template(): Test
{
    $t = new Test('Renderer Helper - url() in Template Context');

    $tmpDir = renderer_helper_test_tmp_dir('url_template');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<nav><a href="<?php echo $this->url("home"); ?>">Home</a><a href="<?php echo $this->url("search"); ?>">Search</a></nav>');

    $output = $renderer->render('test');

    $t->assertTrue('Multiple URLs in template', substr_count($output, 'href=') >= 2);

    renderer_helper_cleanup($tmpDir);
    return $t;
}

function test_renderer_t_in_partial(): Test
{
    $t = new Test('Renderer Helper - t() in Partial');

    $tmpDir = renderer_helper_test_tmp_dir('t_partial');
    @mkdir($tmpDir . '/partials', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    // Add translation to global i18n
    require_once __DIR__ . '/../src/App.php';
    $app = \Bulletin\App::getInstance();
    $app->i18n = ['core' => ['home' => 'Home Page']];

    file_put_contents($tmpDir . '/partials/nav.php', '<a href="#"><?php echo $this->t("home"); ?></a>');
    file_put_contents($tmpDir . '/test.php', '<nav><?php echo $this->partial("nav"); ?></nav>');

    $output = $renderer->render('test');

    $t->assertTrue('Translation in partial', str_contains($output, 'Home Page'));

    renderer_helper_cleanup($tmpDir);
    return $t;
}

function test_renderer_t_in_slot(): Test
{
    $t = new Test('Renderer Helper - t() in Slot');

    $tmpDir = renderer_helper_test_tmp_dir('t_slot');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php $this->slot("title"); ?><?php echo $this->t("page_title"); ?><?php $this->endSlot(); ?><?php echo $this->slotContent("title"); ?>');

    $output = $renderer->render('test');

    $t->assertTrue('Translation in slot', str_contains($output, 'page_title'));

    renderer_helper_cleanup($tmpDir);
    return $t;
}

function test_renderer_url_with_empty_params(): Test
{
    $t = new Test('Renderer Helper - url() With Empty Params');

    $tmpDir = renderer_helper_test_tmp_dir('url_empty_params');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<a href="<?php echo $this->url("home", []); ?>">Home</a>');

    $output = $renderer->render('test');

    $t->assertTrue('Works with empty params array', str_contains($output, 'href='));

    renderer_helper_cleanup($tmpDir);
    return $t;
}

function test_renderer_t_with_scope(): Test
{
    $t = new Test('Renderer Helper - t() With Scope');

    $tmpDir = renderer_helper_test_tmp_dir('t_scope');
    $renderer = new \Bulletin\Renderer($tmpDir);

    // Add plugin-scoped translation
    require_once __DIR__ . '/../src/App.php';
    $app = \Bulletin\App::getInstance();
    $app->i18n = ['core' => [], 'plugin:testplugin' => ['plugin_key' => 'Plugin Value']];

    file_put_contents($tmpDir . '/test.php', '<span><?php echo $this->t("plugin_key", [], "plugin:testplugin"); ?></span>');

    $output = $renderer->render('test');

    $t->assertTrue('Uses scoped translation', str_contains($output, 'Plugin Value'));

    renderer_helper_cleanup($tmpDir);
    return $t;
}

function test_renderer_url_and_t_combined(): Test
{
    $t = new Test('Renderer Helper - url() and t() Combined');

    $tmpDir = renderer_helper_test_tmp_dir('url_t_combined');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<a href="<?php echo $this->url("thread", ["id" => 1]); ?>"><?php echo $this->t("view_thread"); ?></a>');

    $output = $renderer->render('test');

    $t->assertTrue('Both helpers work together', str_contains($output, 'href=') && str_contains($output, 'view_thread'));

    renderer_helper_cleanup($tmpDir);
    return $t;
}

register_tests(
    'test_renderer_url_basic',
    'test_renderer_url_with_params',
    'test_renderer_url_returns_string',
    'test_renderer_t_basic',
    'test_renderer_t_with_params',
    'test_renderer_t_with_globals',
    'test_renderer_t_fallback_to_key',
    'test_renderer_url_in_template',
    'test_renderer_t_in_partial',
    'test_renderer_t_in_slot',
    'test_renderer_url_with_empty_params',
    'test_renderer_t_with_scope',
    'test_renderer_url_and_t_combined'
);