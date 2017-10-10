<?php
/**
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the General Public License (GPL 3.0)
 * that is bundled with this package in the file LICENSE
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/GPL-3.0
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future.
 *
 * @author      Jeroen Bleijenberg
 *
 * @copyright   Copyright (c) 2017
 * @license     http://opensource.org/licenses/GPL-3.0 General Public License (GPL 3.0)
 */
namespace Transip\Api\Command\Dns;


use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Transip\Api\Command\CommandAbstract;
use Transip\Api\Model\DnsEntry;
use Transip\Api\Model\Domain;

class DeleteCommand extends CommandAbstract
{

    protected function configure()
    {
        $this
            ->setName('dns:delete')
            ->setAliases(['dns:remove'])
            ->setDescription('Remove a DNS record from the given domain')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'The domains the record should be deleted from')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'The type of the record. Must be of type A, AAAA, CNAME, MX, NS, SRV or TXT')
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the record');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getDomainHelper();
        $domain = $helper->getDomain($input->getOption('domain'));

        if ($domain instanceof Domain) {
            /**
             * If no record is presented, interactively ask the user to select one from the list
             */
            if ($input->getOption('type') === null && $input->getArgument('name') === null) {
                $this->presentOptionList($input, $output, $domain);
            } else {

            }
        }
    }

    /**
     * Present a list with all DNS record so the user can select a record to delete
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param Domain $domain
     */
    protected function presentOptionList(InputInterface $input, OutputInterface $output, Domain &$domain)
    {
        $options = [];
        $namePad = 0;
        $typePad = 0;
        $ttlPad  = 0;

        /**
         * @var int      $id
         * @var DnsEntry $dns
         */
        foreach ($domain->getDnsEntries() as $id => $dns) {
            if (strlen($dns->getName()) > $namePad) {
                $namePad = strlen($dns->getName());
            }

            if (strlen($dns->getType()) > $typePad) {
                $typePad = strlen($dns->getType());
            }

            if (strlen($dns->getTtl()) > $ttlPad) {
                $ttlPad = strlen($dns->getTtl());
            }
        }

        foreach ($domain->getDnsEntries() as $id => $dns) {
            $options[$id] = sprintf("%s\t%s\t%s\t%s", str_pad($dns->getName(), $namePad), str_pad($dns->getType(), $typePad), str_pad($dns->getTtl(), $ttlPad), $dns->getContent());
        }

        $questionHelper = $this->getHelper('question');
        $question       = new ChoiceQuestion('<info>INPUT: </info>Please select the DNS record you wish to delete. Multiple records can be selected, seperated by a comma (,):', $options);

        $question->setMultiselect(true);
        $question->setErrorMessage("Record ID %d is invalid");

        $output->writeln('');
        $records = $questionHelper->ask($input, $output, $question);
        $output->writeln('');

        if (!empty($records)) {
            foreach ($records as $dnsRecords) {
                $record = explode("\t", $dnsRecords);

                try {
                    if (($id = $domain->getDnsEntryIdByName($record[0], $record[1]))) {
                        $domain->deleteDnsEntry($id);
                    }

                } catch (\Exception $e) {
                    $output->writeln('');
                    $output->writeln("<warning>ERROR: </warning>{$e->getMessage()}");
                    $output->writeln('');
                }
            }
        }
    }
}