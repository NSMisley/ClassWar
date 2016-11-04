# ClassWar
The history of all hitherto existing society is the history of ClassWar.

ClassWar is a tool to assist the archival of Regional Message Boards on the online browser game NationStates.

## Using ClassWar

1. Upload ClassWar to your web server

2. Remove the comment (`//`) before `$useragent` on line 3 and change `CHANGETHISTOYOURNATION` to your nation name.
 
3. Either access it via a browser (for small regions only! large RMBs can't be archived this way) like so: `https://yourwebsite.url/classwar.php?region=the_internationale` OR via the command line, like so: `php -e classwar.php -r "the internationale"`

4. Congratulations! You're the new owner of a SQL file with the contents of your requested RMB.

## What if I don't have a web server?

Well, then you can't use ClassWar. It's designed to work with a MySQL database to display RMB posts (more on that coming later), which you can't do without a web server.
