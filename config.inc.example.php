<?php return [

    /**
     * Your ISPConfig Configuration path
     * 
     * @var String
     */
    'ispconfig_configuration' => '/usr/local/ispconfig/server/lib/config.inc.php',

    /**
     * Add password on archived file (ZIP)
     * 
     * @var String
     */
    'archive_password'  => null,

    /**
     * Remote Directory Without Slash at the End
     * 
     * @var String
     */
    'remote_path'        => 'RcloneDrive:Backups',

    /**
     * Group file by day on the remote directory
     * 
     * @var Boolean
     */
    'group_by_date'      => true,

    /**
     * Slack Webhook URL
     * 
     * @var String
     */
    'slack_webhook_url' => null,

    /**
     * Slack Bot Name
     * 
     * @var String
     */
    'slack_bot_name'    => 'Backup Bot',
];