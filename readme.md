# ISPConfig Backup Script
PHP script to backup all active web & database from ISPConfig panel to Google Drive. 
This script using `rclone` command to upload file. For now it's only tested on Google Drive Storage, if you try it on another storage please let me now if it's work.

## Features
- backup all active web & databases
- password protected zip
- slack notification

## Requirements
- [rclone](https://rclone.org/)
- zip
- php-cli

## Installation
1. Install & setup `rclone` to your system, follow documentation on their site https://rclone.org/drive/ . 
2. Download or clone this repo to your ispconfig Server
3. Create configuration file `config.inc.php` from sample file. 
4. Edit `config.inc.php` put your rclone remote directory to `remote_path` configuration. Read the comments on that file for more information, i think it's pretty clear.
4. run the script `php run.php`

Setup a cronjob if you want to run this script periodically