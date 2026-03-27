<?php

namespace Pittacusw\Touchef\Console;

use Illuminate\Console\Command;
use Pittacusw\Touchef\TouchefServiceProvider;

class InstallAgentSkillCommand extends Command
{
    protected $signature = 'touchef:install-agent-skill {--force : Overwrite existing published skill files}';

    protected $description = 'Publish the Touchef agent skill into the application .agents directory';

    public function handle(): int
    {
        $result = $this->call('vendor:publish', [
            '--provider' => TouchefServiceProvider::class,
            '--tag' => 'touchef-agent-skill',
            '--force' => (bool) $this->option('force'),
        ]);

        if ($result !== self::SUCCESS) {
            return $result;
        }

        $this->components->info('The Touchef agent skill has been published to .agents/skills/touchef.');

        return self::SUCCESS;
    }
}
