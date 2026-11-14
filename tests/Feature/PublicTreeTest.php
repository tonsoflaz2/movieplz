<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The repository is public and a merge to main deploys, so these hold:
 * the App Review contact and code stay in store/private.json (gitignored),
 * CI never hands secrets to pull request code, and what `composer install`
 * runs on the server during a deploy doesn't change without someone
 * changing this test too (both are code-owned).
 */
class PublicTreeTest extends TestCase
{
    public function test_the_store_kit_keeps_the_review_contact_out(): void
    {
        $app = json_decode(file_get_contents(base_path('store/app.json')), true);

        $this->assertIsArray($app);
        foreach (['first_name', 'last_name', 'phone', 'email', 'notes', 'demo_password'] as $key) {
            $this->assertArrayNotHasKey($key, $app['review'] ?? [], "store/app.json review.{$key} belongs in store/private.json");
        }
    }

    public function test_the_private_store_file_is_ignored(): void
    {
        $ignore = file_get_contents(base_path('.gitignore'));

        $this->assertStringContainsString('/store/private.json', $ignore);
    }

    public function test_no_text_file_has_the_review_phone_or_code(): void
    {
        $private = base_path('store/private.json');
        if (! is_file($private)) {
            $this->markTestSkipped('No store/private.json here (CI, a contributor): nothing to compare with.');
        }

        $review = json_decode(file_get_contents($private), true)['review'] ?? [];
        $needles = [];
        if (! empty($review['phone'])) {
            $needles[] = preg_replace('/\D/', '', $review['phone']);
        }
        if (preg_match('/\b(\d{5})\b/', $review['notes'] ?? '', $m)) {
            $needles[] = $m[1];
        }
        $this->assertNotEmpty($needles);

        $files = Finder::create()->files()->in(base_path())
            ->exclude(['vendor', 'node_modules', 'storage', '.git', '.hype', '.claude', 'build', 'bootstrap/cache', 'public/build'])
            ->name(['*.php', '*.md', '*.json', '*.yml', '*.yaml', '*.toml', '*.csv', '*.txt', '.env.example'])
            ->notName(['composer.lock', 'package-lock.json', 'private.json']);

        foreach ($files as $file) {
            $digits = preg_replace('/[\s\-().+]/', '', $file->getContents());
            $text = $file->getContents();
            foreach ($needles as $needle) {
                $hit = strlen($needle) > 5 ? str_contains($digits, $needle) : preg_match('/(?<!\d)'.$needle.'(?!\d)/', $text);
                $this->assertFalse((bool) $hit, "{$file->getRelativePathname()} has a value from store/private.json");
            }
        }
    }

    public function test_ci_never_gives_pull_requests_secrets_or_write_access(): void
    {
        $files = glob(base_path('.github/workflows/*.y*ml'));
        $this->assertNotEmpty($files);

        foreach ($files as $path) {
            $yaml = preg_replace('/^\s*#.*$/m', '', file_get_contents($path));
            $name = basename($path);
            $this->assertStringNotContainsString('pull_request_target', $yaml, "{$name}: pull_request_target runs fork code with secrets");
            $this->assertStringNotContainsString('workflow_run', $yaml, "{$name}: workflow_run runs with secrets after a fork's run");
            $this->assertDoesNotMatchRegularExpression('/\$\{\{\s*secrets\./', $yaml, "{$name} uses a secret");
            $this->assertMatchesRegularExpression('/^permissions:\s*\n\s+contents:\s*read\s*$/m', $yaml, "{$name} must be read-only");
        }
    }

    public function test_what_composer_install_runs_on_deploy_stays_the_same(): void
    {
        $scripts = json_decode(file_get_contents(base_path('composer.json')), true)['scripts'] ?? [];

        // `composer install` on the server runs these (post-autoload-dump),
        // and package:discover boots the app. Changing them changes the
        // deploy: do it in its own pull request, and update this list.
        $this->assertSame([
            'Illuminate\\Foundation\\ComposerScripts::postAutoloadDump',
            '@php artisan package:discover --ansi',
        ], $scripts['post-autoload-dump'] ?? null);

        foreach (['pre-install-cmd', 'post-install-cmd', 'pre-autoload-dump', 'pre-update-cmd'] as $hook) {
            $this->assertArrayNotHasKey($hook, $scripts, "composer.json {$hook} would run on deploy");
        }
    }
}
