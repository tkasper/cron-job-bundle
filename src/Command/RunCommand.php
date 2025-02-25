<?php declare(strict_types=1);

namespace Becklyn\CronJobBundle\Command;

use Becklyn\CronJobBundle\Cron\CronJobRegistry;
use Becklyn\CronJobBundle\Data\CronStatus;
use Becklyn\CronJobBundle\Data\WrappedJob;
use Becklyn\CronJobBundle\Model\CronModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Lock;

/**
 *
 */
class RunCommand extends Command
{
    public static $defaultName = "cron:run";


    /**
     * @var CronJobRegistry
     */
    private $registry;


    /**
     * @var CronModel
     */
    private $logModel;


    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @var Lock
     */
    private $lock;


    /**
     * @param CronJobRegistry $registry
     * @param CronModel       $model
     * @param LoggerInterface $logger
     * @param Factory         $lockFactory
     */
    public function __construct (
        CronJobRegistry $registry,
        CronModel $model,
        LoggerInterface $logger,
        Factory $lockFactory
    )
    {
        parent::__construct();
        $this->registry = $registry;
        $this->logModel = $model;
        $this->logger = $logger;
        $this->lock = $lockFactory->createLock("cron-run");
    }


    /**
     * @inheritDoc
     */
    protected function execute (InputInterface $input, OutputInterface $output) : ?int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("Cron Jobs");

        if (!$this->lock->acquire())
        {
            $io->warning("A previous cron command is still running.");
            return 2;
        }

        $now = new \DateTimeImmutable();
        $jobFailed = false;

        foreach ($this->registry->getAllJobs() as $job)
        {
            $io->section("Job: {$job->getName()}");

            try
            {
                $wrappedJob = new WrappedJob($job, $now);

                if (!$this->logModel->isDue($wrappedJob))
                {
                    $io->writeln("<fg=yellow>Not due</>");
                    continue;
                }

                try
                {
                    $status = $job->execute($io);
                    $this->logModel->logRun($wrappedJob, $status);
                    $this->logModel->flush();

                    if (!$status->isSucceeded())
                    {
                        $jobFailed = true;
                    }

                    $io->writeln(
                        $status->isSucceeded()
                            ? "<fg=green>Command succeeded.</>"
                            : "<fg=red>Command failed.</>"
                    );
                }
                catch (\Exception $e)
                {
                    $this->logModel->logRun($wrappedJob, new CronStatus(false));
                    $this->logModel->flush();
                    $this->logger->error("Running the cron job failed with an exception: {message}", [
                        "message" => $e->getMessage(),
                        "exception" => $e,
                    ]);

                    $io->writeln("<fg=red>Command failed.</>");
                }
            }
            catch (\InvalidArgumentException $exception)
            {
                $jobFailed = true;
                $this->logger->error("Invalid cron tab given: {message}", [
                    "message" => $exception->getMessage(),
                    "exception" => $exception,
                ]);

                // Write error message
                $io->writeln("<fg=red>Command failed.</>");
            }
        }

        $this->lock->release();
        return $jobFailed ? 0 : 1;
    }
}
