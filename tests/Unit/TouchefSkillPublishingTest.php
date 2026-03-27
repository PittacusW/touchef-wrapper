<?php

namespace Pittacusw\Touchef\Tests\Unit;

use ReflectionClass;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Pittacusw\Touchef\TouchefServiceProvider;
use Tests\TestCase;

class TouchefSkillPublishingTest extends TestCase
{
    protected string $originalBasePath;

    /**
     * @var  array<int, string>
     */
    protected array $temporaryBasePaths = [];

    protected Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalBasePath = base_path();
        $this->files            = new Filesystem;
    }

    protected function tearDown(): void
    {
        $this->app->setBasePath($this->originalBasePath);

        foreach ($this->temporaryBasePaths as $temporaryBasePath) {
            if ($this->files->isDirectory($temporaryBasePath)) {
                $this->files->deleteDirectory($temporaryBasePath);
            }
        }

        parent::tearDown();
    }

    public function test_the_service_provider_registers_the_agent_skill_publish_group() : void {
        $basePath    = $this->makeTemporaryBasePath();
        $packageRoot = dirname((new ReflectionClass(TouchefServiceProvider::class))->getFileName(), 4);

        $this->reinitializePublishPaths($basePath);

        $paths           = ServiceProvider::pathsToPublish(TouchefServiceProvider::class, 'touchef-agent-skill');
        $normalizedPaths = array_combine(
            array_map($this->normalizePath(...), array_keys($paths)),
            array_map($this->normalizePath(...), array_values($paths)),
        );

        $this->assertArrayHasKey(
            $this->normalizePath($packageRoot . DIRECTORY_SEPARATOR . '.agents' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'touchef'),
            $normalizedPaths,
        );
        $this->assertSame(
            $this->normalizePath($basePath . DIRECTORY_SEPARATOR . '.agents' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'touchef'),
            array_values($normalizedPaths)[0],
        );
    }

    public function test_the_service_provider_registers_the_config_publish_group(): void
    {
        $basePath    = $this->makeTemporaryBasePath();
        $packageRoot = dirname((new ReflectionClass(TouchefServiceProvider::class))->getFileName(), 4);

        $this->reinitializePublishPaths($basePath);

        $paths           = ServiceProvider::pathsToPublish(TouchefServiceProvider::class, 'touchef-config');
        $normalizedPaths = array_combine(
            array_map($this->normalizePath(...), array_keys($paths)),
            array_map($this->normalizePath(...), array_values($paths)),
        );

        $this->assertArrayHasKey(
            $this->normalizePath($packageRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php'),
            $normalizedPaths,
        );
        $this->assertSame(
            $this->normalizePath($basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'touchef.php'),
            array_values($normalizedPaths)[0],
        );
    }

    public function test_the_install_command_publishes_the_skill_files() : void {
        $basePath = $this->makeTemporaryBasePath();

        $this->reinitializePublishPaths($basePath);

        $this->artisan('touchef:install-agent-skill', ['--force' => TRUE])
            ->assertSuccessful();

        $publishedSkill = $basePath . DIRECTORY_SEPARATOR . '.agents' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'touchef';

        $this->assertFileExists($publishedSkill . DIRECTORY_SEPARATOR . 'SKILL.md');
        $this->assertFileExists($publishedSkill . DIRECTORY_SEPARATOR . 'references' . DIRECTORY_SEPARATOR . 'api-reference.md');
        $this->assertStringContainsString('name: touchef', file_get_contents($publishedSkill . DIRECTORY_SEPARATOR . 'SKILL.md'));
    }

    protected function makeTemporaryBasePath(): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'touchef-skill-' . uniqid();

        $this->files->makeDirectory($directory);
        $this->temporaryBasePaths[] = $directory;

        return $directory;
    }

    protected function reinitializePublishPaths(string $basePath): void
    {
        $this->app->setBasePath($basePath);

        $reflection = new ReflectionClass(ServiceProvider::class);

        foreach (['publishes', 'publishGroups'] as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(TRUE);
            $property->setValue(NULL, []);
        }

        (new TouchefServiceProvider($this->app))->boot();
    }

    protected function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
