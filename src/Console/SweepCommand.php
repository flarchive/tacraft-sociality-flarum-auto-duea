<?php

namespace TacraftSociality\AutoDuea\Console;

use Flarum\Console\AbstractCommand;
use TacraftSociality\AutoDuea\AutoDuea;

class SweepCommand extends AbstractCommand
{
    public function __construct(protected AutoDuea $autoDuea)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('auto-duea:sweep')
            ->setDescription('Delete unconfirmed accounts when automatic deletion is enabled and due.');
    }

    protected function fire(): int
    {
        if (! $this->autoDuea->enabled()) {
            $this->comment('自动删除已关闭，跳过。');

            return 0;
        }

        $deleted = $this->autoDuea->runIfDue();

        $this->info($deleted > 0 ? '已删除 '.$deleted.' 个未验证邮箱的账号。' : '还没到运行时间，跳过。');

        return 0;
    }
}
