<?php

namespace Pap\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Pap\Helpers\FLock;
use Pap\Helpers\ApiHelper;

class HistoryCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('update-pap')
            ->setDescription('Установка статусов в Pap в соответствии с данными о статусах заказов из IntaroCRM')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $flock = new FLock(__CLASS__);
        if(!$flock->isLocked()){
            $output->writeln("<error> Command ". $this->getName(). " already running in this system. Kill it or try again later </error>", OutputInterface::VERBOSITY_QUIET);
            return;
        }

        $api = new ApiHelper();

        if($api->orderHistory())
            $output->writeln("<info>Successfull history load!</info>");
        else
            $output->writeln("<error>Oops we 've got some problems!</error>");

        return;
    }
}
