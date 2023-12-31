# BackfillGoals plugin for Matomo [![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate/?hosted_button_id=YLPY95NSDFH9G)
A plugin for [Matomo](https://matomo.org/) to backfill goals.

## What is this?
This Matomo plugins allows users to convert goals from stats of the past.
For example: if you added new goals and want historical tracking data get converted into those goals to show up in the statistics.

* only Regex Goals are supported right now
* tested on Matomo version 4.15.1 with PHP 8.0.2

## Installation
Download the complete code from this repository into a folder "BackfillGoals" (or download a release) and put this folder inside the "plugins" folder on your Matomo installation.

After installation it can be found here: Dashboard > Goals > Backfill Goals

## Info
The plugin would not exist if there wasn't this [issue on the Matomo repository](https://github.com/matomo-org/matomo/issues/6183) which was a good starting point for the rest of the code.

***Please make a backup of your data as of the time writing this the plugin is only tested on one single Matomo installation with a manageable number of websites!***

## Donation
If this project helps you to update your Matomo statistics, you can sponsor us a cup of :coffee: - or two! :)

**Of course it would also be great if someone with more knowledge in the area of Matomo plugins likes to optimize the plugin any further!**

[![paypal](https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif)](https://www.paypal.com/donate/?hosted_button_id=YLPY95NSDFH9G)
