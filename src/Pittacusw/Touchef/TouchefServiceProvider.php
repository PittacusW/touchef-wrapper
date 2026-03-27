<?php

namespace Pittacusw\Touchef;

use Illuminate\Support\ServiceProvider;
use Pittacusw\Touchef\Console\InstallAgentSkillCommand;

class TouchefServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $packageRoot = dirname(__DIR__, 3);

        $this->publishes([
            $packageRoot . '/src/config/config.php' => config_path('touchef.php'),
        ], 'touchef-config');

        $this->publishes([
            $packageRoot . '/.agents/skills/touchef' => base_path('.agents/skills/touchef'),
        ], 'touchef-agent-skill');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/config.php', 'touchef');

        $this->app->singleton(Touchef::class, static function (): Touchef {
            return new Touchef();
        });

        $this->app->alias(Touchef::class, 'Touchef');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallAgentSkillCommand::class,
            ]);
        }
    }
}
