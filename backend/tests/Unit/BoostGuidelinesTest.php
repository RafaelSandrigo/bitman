<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Impede que um `boost:install`/`boost:update` reintroduza, sem revisão, recomendações
 * comerciais ou ferramentas que o projeto desabilitou (ver .ai/guidelines/bitman.md).
 */
final class BoostGuidelinesTest extends TestCase
{
    private const GENERATED_FILES = ['CLAUDE.md', 'AGENTS.md'];

    /** @return iterable<string, array{string}> */
    public static function forbiddenRecommendations(): iterable
    {
        yield 'Laravel Cloud link' => ['cloud.laravel.com'];
        yield 'Laravel Cloud deploy recommendation' => ['fastest way to deploy'];
        yield 'Cloud deploy skill' => ['deploying-to-cloud'];
        yield 'Nightwatch MCP' => ['nightwatch.laravel.com'];
    }

    #[DataProvider('forbiddenRecommendations')]
    public function test_generated_guidelines_do_not_contain_commercial_recommendations(string $needle): void
    {
        foreach (self::GENERATED_FILES as $file) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $this->read($file), "{$file} contém '{$needle}'.");
        }
    }

    public function test_project_rules_are_present_in_generated_guidelines(): void
    {
        $this->assertStringContainsString('Neutralidade de fornecedor', $this->read('CLAUDE.md'));
    }

    public function test_cloud_skill_is_not_installed(): void
    {
        $this->assertDirectoryDoesNotExist($this->path('.claude/skills/deploying-to-cloud'));
    }

    public function test_mcp_config_only_registers_boost_server(): void
    {
        /** @var array{mcpServers: array<string, mixed>} $mcp */
        $mcp = json_decode($this->read('.mcp.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['laravel-boost'], array_keys($mcp['mcpServers']));
    }

    public function test_boost_config_keeps_project_restrictions(): void
    {
        /** @var array<string, mixed> $boost */
        $boost = json_decode($this->read('boost.json'), true, flags: JSON_THROW_ON_ERROR);
        $config = $this->read('config/boost.php');

        $this->assertFalse($boost['cloud']);
        $this->assertFalse($boost['nightwatch']);
        $this->assertStringContainsString("'deployments'", $config);
        $this->assertStringContainsString('Tools\\Tinker::class', $config);
    }

    private function read(string $relative): string
    {
        $contents = file_get_contents($this->path($relative));
        $this->assertIsString($contents, "Não foi possível ler {$relative}.");

        return $contents;
    }

    private function path(string $relative): string
    {
        return dirname(__DIR__, 2).'/'.$relative;
    }
}
