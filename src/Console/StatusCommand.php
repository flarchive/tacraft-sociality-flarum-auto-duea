<?php

namespace TacraftSociality\AutoDuea\Console;

use Flarum\Console\AbstractCommand;
use TacraftSociality\AutoDuea\AutoDuea;

class StatusCommand extends AbstractCommand
{
    public function __construct(protected AutoDuea $autoDuea)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('auto-duea:status')
            ->setDescription('Show how many accounts have not confirmed their email address.');
    }

    protected function fire(): int
    {
        $counts = $this->autoDuea->counts();

        $this->info('未验证邮箱的账号（不含管理员）：'.$counts['pending']);
        $this->info('其中已过宽限期、可被删除的账号：'.$counts['eligible']);
        $this->comment('宽限期：'.$counts['graceDays'].' 天');
        $this->comment('自动删除：'.($counts['enabled'] ? '已开启' : '已关闭').'，间隔 '.$counts['intervalHours'].' 小时，每批 '.$counts['batch'].' 个');
        $this->comment('累计已删除：'.$counts['deletedTotal'].'，上次删除：'.$counts['lastDeleted'].'，上次运行：'.($counts['lastRun'] ?? '从未'));

        return 0;
    }
}
