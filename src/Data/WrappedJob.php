<?php declare(strict_types=1);

namespace Becklyn\CronJobBundle\Data;

use Becklyn\CronJobBundle\Cron\CronJobInterface;
use Becklyn\CronJobBundle\Entity\CronJobRun;
use Cron\CronExpression;

class WrappedJob
{
    /**
     * @var CronJobInterface
     */
    private $job;


    /**
     * @var \DateTimeImmutable
     */
    private $supposedLastRun;


    /**
     * @var \DateTimeImmutable
     */
    private $nextRun;


    public function __construct (CronJobInterface $job, \DateTimeInterface $now)
    {
        $cron = CronExpression::factory($job->getCronTab());

        $this->job = $job;
        $this->supposedLastRun = \DateTimeImmutable::createFromMutable($cron->getPreviousRunDate($now, 0, true));
        $this->nextRun = \DateTimeImmutable::createFromMutable($cron->getNextRunDate($now, 0, true));
    }


    /**
     * @return CronJobInterface
     */
    public function getJob () : CronJobInterface
    {
        return $this->job;
    }


    /**
     * @return \DateTimeImmutable
     */
    public function getSupposedLastRun () : \DateTimeImmutable
    {
        return $this->supposedLastRun;
    }


    /**
     * @return string
     */
    public function getKey () : string
    {
        return \get_class($this->job);
    }


    /**
     * @return \DateTimeImmutable
     */
    public function getNextRun () : \DateTimeImmutable
    {
        return $this->nextRun;
    }


    /**
     * @param CronJobRun|null $lastRun
     *
     * @return bool
     */
    public function isDue (?CronJobRun $lastRun) : bool
    {
        return null === $lastRun || $lastRun->getTimeRun() < $this->supposedLastRun;
    }
}
