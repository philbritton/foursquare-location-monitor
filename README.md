Foursquare Location Monitor
===========================

Mock SaaS project that allows routine monitoring of selected foursquare venues.

Synopsis
========

This CodeIgniter-based project can be used to monitor a group of foursquare venues (particular places that a foursquare user can "check-in"). A backend script queries the foursquare API at regular intervals and logs the results to a MySQL database. The front-end interface manages which venues are being monitored (add and remove) as well as aggregate that data in charts and tables.

Installation (Core)
===================

 1. Use `git clone git://github.com/yeargin/foursquare-location-monitor.git` (requires git) to obtain the latest version of the project.
 2. Run `git submodule init` and `git submodule update` to pull down the requisite libraries (see `.gitmodules` for where they are to be loaded)
 3. Rename and edit `application/config/database.php-dish` and `application/config/applicaiton.php-dist` to connect to your desired MySQL server and to provide your foursquare OAuth Consumer information. See https://developer.foursquare.com/overview/auth.html for more information about how to get this information.
 4. Navigating to `/install` in your web browser will run the necessary database queries to set up your installation. If a connection error occurs, be sure to check your `application/config/database.php` file to make sure your credentials and database name are correct. 
 5. Create a user by first running a query such as `INSERT INTO beta_keys (beta_key, status) VALUES ('ABCDEF123456', 1);` and then use the given beta key to create an account.
 6. Change the 'level' column for the created user from 'user' to 'admin' to be able to access the Site Administration section (requires logout).

Installation (Monitoring)
=========================

Within the `application/cron` folder, you will find a shell script and a faux daemon script for local testing. Set these up to run about every 5-10 minutes on your server. You may need to prepend a `cd` into the working directory to make this work on some hosts.

`cd /path/to/webroot ; ./application/cron/foursquare_checks.sh`

The script will self-regulate (meaning, it will not attempt a daily check more than once a day, and live checks are configured to be no more than one every 15 minutes). The larger your check database becomes, the more often you will need to process through records. If you receive an error in the Administrator Dashboard that says that monitoring has stopped, verify that `cron` or other task scheduling service is running properly.

If you are wanting to set up Location Monitor for local development, the easiest way is to use the script found in `application/cron/daemon.sh`. Here is a recommended usage:

`$ ./application/cron/daemon.sh ./application/cron/foursquare_checks.sh 300`

Powered By
==========

* CodeIgniter - http://codeigniter.com/
* Twitter Bootstrap - http://twitter.github.com/bootstrap/
* foursquare API - https://developer.foursquare.com/
