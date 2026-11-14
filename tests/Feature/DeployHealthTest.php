<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Tests\TestCase;

/**
 * The deploy's health check (docs/deploy.md) asks, signed out, for
 * /app.ham and /up after every deploy, and rolls the code back unless
 * /up answers 200 and /app.ham's body, trimmed, starts with "<app" and ends
 * with "</app>". These tests hold the app to that contract, so a change
 * that would fail the check fails here first, and they show the check can
 * tell a broken start screen (the HAM error block, still HTTP 200) apart.
 */
class DeployHealthTest extends TestCase
{
    use RefreshDatabase;

    /** The same test the deploy script runs on the body. */
    private function passesTheCheck(string $body): bool
    {
        $b = trim($body);

        return str_starts_with($b, '<app') && str_ends_with($b, '</app>');
    }

    public function test_the_signed_out_start_screen_passes_the_check(): void
    {
        $response = $this->get('/app.ham');

        $response->assertOk();
        $this->assertTrue($this->passesTheCheck($response->getContent()), 'GET /app.ham signed out must be one <app>…</app>');
    }

    public function test_up_answers_200(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_a_start_screen_that_throws_fails_the_check_even_though_it_is_200(): void
    {
        View::composer('ham.app', fn () => throw new RuntimeException('broken on purpose'));

        $response = $this->get('/app.ham');

        $response->assertOk(); // HAM routes always answer 200 (bootstrap/app.php)
        $this->assertFalse($this->passesTheCheck($response->getContent()));
    }

    /** The bash block under "## The script" in docs/deploy.md. */
    private function deployScript(): string
    {
        $doc = file_get_contents(base_path('docs/deploy.md'));
        $this->assertSame(1, preg_match('/## The script\s*```bash\n(.*?)\n```/s', $doc, $m), 'docs/deploy.md has one bash block under "## The script"');

        return $m[1];
    }

    public function test_the_deploy_script_matches_origin_instead_of_merging(): void
    {
        $script = $this->deployScript();

        // GitHub's main has history unrelated to the old Bitbucket one; a pull would refuse to merge.
        $this->assertStringContainsString('git fetch origin "$FORGE_SITE_BRANCH"', $script);
        $this->assertStringContainsString('git reset --hard FETCH_HEAD', $script);
        $this->assertStringNotContainsString('git pull', $script);
        $this->assertStringContainsString('git reset --hard "$PREV"', $script);
        $this->assertStringContainsString('migrate:status --pending=3', $script);
        $this->assertStringNotContainsString('movieplz:backup', $script);
        $this->assertStringContainsString('healthy || rollback', $script);
    }

    public function test_the_deploy_script_is_valid_bash(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'deploy');
        file_put_contents($file, $this->deployScript());
        exec('bash -n '.escapeshellarg($file).' 2>&1', $out, $code);
        unlink($file);

        $this->assertSame(0, $code, implode("\n", $out));
    }
}
