<?php

namespace TacraftSociality\AutoDuea\Console;

use Flarum\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputOption;
use TacraftSociality\AutoDuea\AutoDuea;

class RunCommand extends AbstractCommand
{
    public function __construct(protected AutoDuea $autoDuea)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('auto-duea:run')
            ->setDescription('Delete accounts that never confirmed their email address.')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Delete every eligible account instead of a single batch.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of accounts to delete in this run.');
    }

    protected function fire(): int
    {
        $limit = $this->input->getOption('limit');
        $all = (bool) $this->input->getOption('all');

        $deleted = $all
            ? $this->autoDuea->runAll()
            : $this->autoDuea->run($limit !== null ? (int) $limit : null);

        $this->info('已删除 '.$deleted.' 个未验证邮箱的账号。');

        return 0;
    }
}
