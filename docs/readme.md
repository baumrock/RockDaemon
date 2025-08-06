# RockDeamon

## Concept

- add a simple deamon script
- add a cronjob that runs every minute and makes sure the script is alive

## WHY

all you want is a long running task
there are several ways to do this, eg reactphp or a cronjob
quickly you realise the problems
- how to make sure that only one instance runs and that the cron does not fire up multiple instances?
- how to restart the task manually?
- how to restart the task after deployments?
- quickly lots of boilerplate code
- how to echo all output but only if -d debug flag?
- how to read arguments php mytask.php -f foo.txt -d
- setting up deamons can be tricky or not possible
- adding cronjobs is easy in most hosting environments
- you can quickly get caught in a task running and not being able to quit it

## Features

- easy monitoring from the console with -d debug flag
- overview of running tasks on the module config page
- possible to stop tasks from there
