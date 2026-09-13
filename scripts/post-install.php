<?php

require_once dirname(__FILE__) . '/db/install.php';

pm_Scheduler::getInstance()->removeAllTasks();

function sesManagerPutSchedulerTask($scriptPath, array $schedule)
{
    $task = new pm_Scheduler_Task();
    $task->setCmd($scriptPath);
    $task->setArguments(array());
    $task->setSchedule($schedule);

    try {
        pm_Scheduler::getInstance()->putTask($task);
    } catch (pm_Exception $e) {
        if (strpos($e->getMessage(), 'already exists') === false) {
            throw $e;
        }
    }
}

sesManagerPutSchedulerTask('health-check.php', array(
    'minute' => '0,15,30,45',
    'hour'   => '*',
    'dom'    => '*',
    'month'  => '*',
    'dow'    => '*',
));

sesManagerPutSchedulerTask('job-runner.php', array(
    'minute' => '*/5',
    'hour'   => '*',
    'dom'    => '*',
    'month'  => '*',
    'dow'    => '*',
));

sesManagerPutSchedulerTask('ip-reputation-check.php', array(
    'minute' => '17',
    'hour'   => '3',
    'dom'    => '*',
    'month'  => '*',
    'dow'    => '*',
));
