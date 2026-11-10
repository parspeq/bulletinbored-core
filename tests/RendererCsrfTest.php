<?php

/**
 * RendererCsrfTest — tests for Renderer CSRF functionality.
 * Covers csrfField().
 */

require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Renderer.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Security.php';

function renderer_csrf_test_tmp_dir(string $prefix): string
{
    $base = sys_get_temp_dir() . '/bb_renderer_csrf_' . $prefix . '_' . uniqid('', true);
    @mkdir($base, 0755, true);
    return $base;
}

function renderer_csrf_cleanup(string $dir): void
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

function test_renderer_csrfField_basic(): Test
{
    $t = new Test('Renderer CSRF - csrfField() Basic');

    $tmpDir = renderer_csrf_test_tmp_dir('csrfField_basic');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<form><?php echo $this->csrfField(); ?></form>');

    $output = $renderer->render('test');

    $t->assertTrue('Generates hidden input', str_contains($output, '<input type="hidden"'));
    $t->assertTrue('Name is csrf_token', str_contains($output, 'name="csrf_token"'));
    $t->assertTrue('Has value attribute', str_contains($output, 'value='));

    renderer_csrf_cleanup($tmpDir);
    return $t;
}

function test_renderer_csrfField_value_escaped(): Test
{
    $t = new Test('Renderer CSRF - csrfField() Value Escaped');

    $tmpDir = renderer_csrf_test_tmp_dir('csrfField_escaped');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<form><?php echo $this->csrfField(); ?></form>');

    $output = $renderer->render('test');

    // The value should be escaped (though CSRF tokens are typically alphanumeric)
    $t->assertTrue('Value attribute present', preg_match('/value="[^"]+"/', $output));

    renderer_csrf_cleanup($tmpDir);
    return $t;
}

function test_renderer_csrfField_in_form(): Test
{
    $t = new Test('Renderer CSRF - csrfField() in Form Context');

    $tmpDir = renderer_csrf_test_tmp_dir('csrfField_form');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<form method="POST"><input name="data" value="test"><?php echo $this->csrfField(); ?></form>');

    $output = $renderer->render('test');

    $t->assertTrue('CSRF field in form', str_contains($output, 'csrf_token'));
    $t->assertTrue('Other inputs preserved', str_contains($output, 'name="data"'));

    renderer_csrf_cleanup($tmpDir);
    return $t;
}

function test_renderer_csrfField_multiple_calls(): Test
{
    $t = new Test('Renderer CSRF - Multiple csrfField() Calls');

    $tmpDir = renderer_csrf_test_tmp_dir('csrfField_multiple');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<form><?php echo $this->csrfField(); ?><?php echo $this->csrfField(); ?></form>');

    $output = $renderer->render('test');

    // Each call should generate a field (though tokens may differ)
    $count = substr_count($output, 'name="csrf_token"');
    $t->assertEquals('Two CSRF fields generated', 2, $count);

    renderer_csrf_cleanup($tmpDir);
    return $t;
}

function test_renderer_csrfField_with_globals(): Test
{
    $t = new Test('Renderer CSRF - csrfField() with Globals');

    $tmpDir = renderer_csrf_test_tmp_dir('csrfField_globals');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<form><?php echo $this->csrfField(); ?><input name="site" value="<?php echo $this->e($siteName); ?>"></form>');

    $renderer = $renderer->addGlobal('siteName', 'Test Site');
    $output = $renderer->render('test');

    $t->assertTrue('CSRF field generated', str_contains($output, 'csrf_token'));
    $t->assertTrue('Global accessible', str_contains($output, 'Test Site'));

    renderer_csrf_cleanup($tmpDir);
    return $t;
}

function test_renderer_csrfField_returns_string(): Test
{
    $t = new Test('Renderer CSRF - csrfField() Returns String');

    $tmpDir = renderer_csrf_test_tmp_dir('csrfField_returns_string');
    $renderer = new \Bulletin\Renderer($tmpDir);

    $result = $renderer->csrfField();

    $t->assertTrue('Returns string', is_string($result));
    $t->assertTrue('Contains input tag', str_contains($result, '<input'));
    $t->assertTrue('Contains csrf_token', str_contains($result, 'csrf_token'));

    renderer_csrf_cleanup($tmpDir);
    return $t;
}

function test_renderer_csrfField_uses_generate_csrf_token(): Test
{
    $t = new Test('Renderer CSRF - csrfField() Uses generate_csrf_token()');

    $tmpDir = renderer_csrf_test_tmp_dir('csrfField_token');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<form><?php echo $this->csrfField(); ?></form>');

    $output = $renderer->render('test');

    // Token should be a hex string (32 bytes = 64 hex chars)
    preg_match('/value="([^"]+)"/', $output, $matches);
    $t->assertTrue('Token captured', isset($matches[1]));
    $token = $matches[1];
    $t->assertTrue('Token is hex', ctype_xdigit($token));
    $t->assertTrue('Token length is 64 (32 bytes)', strlen($token) === 64);

    renderer_csrf_cleanup($tmpDir);
    return $t;
}

function test_renderer_csrfField_in_partial(): Test
{
    $t = new Test('Renderer CSRF - csrfField() in Partial');

    $tmpDir = renderer_csrf_test_tmp_dir('csrfField_partial');
    @mkdir($tmpDir . '/partials', 0755, true);
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/partials/form.php', '<form><?php echo $this->csrfField(); ?></form>');
    file_put_contents($tmpDir . '/test.php', '<body><?php echo $this->partial("form"); ?></body>');

    $output = $renderer->render('test');

    $t->assertTrue('CSRF field in partial', str_contains($output, 'csrf_token'));

    renderer_csrf_cleanup($tmpDir);
    return $t;
}

function test_renderer_csrfField_in_slot(): Test
{
    $t = new Test('Renderer CSRF - csrfField() in Slot');

    $tmpDir = renderer_csrf_test_tmp_dir('csrfField_slot');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/test.php', '<?php $this->slot("form"); ?><form><?php echo $this->csrfField(); ?></form><?php $this->endSlot(); ?><?php echo $this->slotContent("form"); ?>');

    $output = $renderer->render('test');

    $t->assertTrue('CSRF field in slot', str_contains($output, 'csrf_token'));

    renderer_csrf_cleanup($tmpDir);
    return $t;
}

function test_renderer_csrfField_in_layout(): Test
{
    $t = new Test('Renderer CSRF - csrfField() in Layout');

    $tmpDir = renderer_csrf_test_tmp_dir('csrfField_layout');
    $renderer = new \Bulletin\Renderer($tmpDir);

    file_put_contents($tmpDir . '/layout.php', '<html><body><?php echo $content; ?></body></html>');
    file_put_contents($tmpDir . '/test.php', '<?php $this->layout("layout"); ?><form><?php echo $this->csrfField(); ?></form>');

    $output = $renderer->render('test');

    $t->assertTrue('CSRF field in layout', str_contains($output, 'csrf_token'));

    renderer_csrf_cleanup($tmpDir);
    return $t;
}

register_tests(
    'test_renderer_csrfField_basic',
    'test_renderer_csrfField_value_escaped',
    'test_renderer_csrfField_in_form',
    'test_renderer_csrfField_multiple_calls',
    'test_renderer_csrfField_with_globals',
    'test_renderer_csrfField_returns_string',
    'test_renderer_csrfField_uses_generate_csrf_token',
    'test_renderer_csrfField_in_partial',
    'test_renderer_csrfField_in_slot',
    'test_renderer_csrfField_in_layout'
);