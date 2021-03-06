<?php

namespace Minds\Controllers\Cli;

use Minds\Core\Minds;
use Minds\Cli;
use Minds\Interfaces;
use Minds\Core\Queue\Runners;

class QueueRunner extends Cli\Controller implements Interfaces\CliControllerInterface
{
    public function __construct()
    {
    }

    public function help($command = null)
    {
        $this->out('TBD');
    }

    public function exec()
    {
        $this->out('Missing subcommand');
    }

    public function run()
    {
        $runner = $this->getOpt('runner');
        try {
            $this->out("Running $runner");
            $this->out('Press Ctrl + C to cancel');
            $runner = Runners\Factory::build($runner)->run();
        } catch (Exception $e) {
            $this->out('Failed: '.$e->getMessage());
        }
    }
}
