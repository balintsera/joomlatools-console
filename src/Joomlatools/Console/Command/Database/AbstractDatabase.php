<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Command\Database;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Joomlatools\Console\Command\Site\AbstractSite;

abstract class AbstractDatabase extends AbstractSite
{
    protected $target_db;
    protected $target_db_prefix = 'sites_';

    protected $mysql;
    protected $pdoDB;

    protected function configure()
    {
        parent::configure();

        $this->addOption(
            'mysql-login',
            'L',
            InputOption::VALUE_REQUIRED,
            "MySQL credentials in the form of user:password",
            'root:root'
        )
        ->addOption(
            'mysql-host',
            'H',
            InputOption::VALUE_REQUIRED,
            "MySQL host",
            'localhost'
        )
        ->addOption(
            'mysql-port',
            'P',
            InputOption::VALUE_REQUIRED,
            "MySQL port",
            3306
        )
        ->addOption(
            'mysql_db_prefix',
            null,
            InputOption::VALUE_REQUIRED,
            "MySQL database prefix",
            $this->target_db_prefix
        )
        ->addOption(
            'mysql-database',
            'db',
            InputOption::VALUE_REQUIRED,
            "MySQL database name. If set, the --mysql_db_prefix option will be ignored."
        )
        ->addOption(
            'mysql-driver',
            null,
            InputOption::VALUE_REQUIRED,
            "MySQL driver",
            'mysqli'
        )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $db_name = $input->getOption('mysql-database');
        if (empty($db_name))
        {
            $this->target_db_prefix = $input->getOption('mysql_db_prefix');
            $this->target_db        = $this->target_db_prefix.$this->site;
        }
        else
        {
            $this->target_db_prefix = '';
            $this->target_db        = $db_name;
        }

        $credentials = explode(':', $input->getOption('mysql-login'), 2);

        $this->mysql = (object) array(
            'user'     => $credentials[0],
            'password' => $credentials[1],
            'host'     => $input->getOption('mysql-host'),
            'port'     => (int) $input->getOption('mysql-port'),
            'driver'   => strtolower($input->getOption('mysql-driver'))
        );

        if (!in_array($this->mysql->driver, array('mysql', 'mysqli'))) {
            throw new \RuntimeException(sprintf('Invalid MySQL driver %s', $this->mysql->driver));
        }
    }

    protected function _executeSQLCommandLine($query)
    {
        $password = empty($this->mysql->password) ? '' : sprintf("--password='%s'", $this->mysql->password);
        $cmd      = sprintf("echo '$query' | mysql --host=%s --port=%u --user='%s' %s", $this->mysql->host, $this->mysql->port, $this->mysql->user, $password);

        return exec($cmd);
    }

    public function executeSQL($query) 
    {
        $this->_executeSQL($query);
    }

    protected function _executeSQL($query)
    {
        $db = $this->dbConnection();
        try {
            $db->exec($query);
        } catch (\PDOException $ex) {
            throw new \RuntimeException(sprintf('Database query failed: error: "%s" "%s"', $ex->getMessage(), $query));
        }
    }

    private function dbConnection()
    {
        if (! $this->pdoDB) {
            $password = empty($this->mysql->password) || $this->mysql->password == 'root' ? '' :  $this->mysql->password;
            $connectionString = "mysql:host={$this->mysql->host}:{$this->mysql->port};dbname={$this->target_db};charset=utf8mb4";
            $pdoDB = new \PDO($connectionString, $this->mysql->user, $password);
            $pdoDB->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdoDB->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        }

        return $pdoDB;
    }

    protected function _promptDatabaseDetails(InputInterface $input, OutputInterface $output)
    {
        $this->mysql->user     = $this->_ask($input, $output, 'MySQL user', $this->mysql->user, true);
        $this->mysql->password = $this->_ask($input, $output, 'MySQL password', $this->mysql->password, true, true);
        $this->mysql->host     = $this->_ask($input, $output, 'MySQL host', $this->mysql->host, true);
        $this->mysql->port     = (int) $this->_ask($input, $output, 'MySQL port', $this->mysql->port, true);
        $this->mysql->driver   = $this->_ask($input, $output, 'MySQL driver', array('mysqli', 'mysql'), true);

        $output->writeln('Choose the database name. We will attempt to create it if it does not exist.');
        $this->target_db       = $this->_ask($input, $output, 'MySQL database', $this->target_db, true);

        $input->setOption('mysql-login', $this->mysql->user . ':' . $this->mysql->password);
        $input->setOption('mysql-host', $this->mysql->host);
        $input->setOption('mysql-port', $this->mysql->port);
        $input->setOption('mysql-database', $this->target_db);
        $input->setOption('mysql-driver', $this->mysql->driver);
    }
}
