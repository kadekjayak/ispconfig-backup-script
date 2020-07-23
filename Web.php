<?php

class Web {

    /**
     * @var mysqli
     */
    public $DB = null;

    /**
     * @var String
     */
    public $started_at = null;

    /**
     * Contruct
     */
    public function __construct()
    {
       $this->connectDb();
    }

    protected function connectDb()
    {
        $this->DB = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE );
        if ( $this->DB->connect_errno ) {
            $this->log("Connect failed: %s\n", $this->DB->connect_error);
            exit();
        }
    }

    /**
     * Get All Active Web
     * 
     * @return Array
     */
    public function getWebs()
    {
        $server_id = config('server_id');
        $query = "SELECT `domain_id`, `server_id`, `domain`, `document_root` FROM web_domain WHERE `active` = 'y' AND `type` = 'vhost' AND `server_id` = {$server_id}";

        $result = $this->DB->query( $query );
        $this->log( 'FETCHING ALL ACTIVE WEB');
        return $result->fetch_all( MYSQLI_ASSOC );
    }

    /**
     * Get Web Databases
     * 
     * @return Array
     */
    public function getWebDatabases( $web )
    {
        // on small server Sometimes MySQL gone away after do long archive operation
        if ( ! $this->DB->ping() ) {
            $this->connectDb();
        }

        $query = "SELECT `database_name` FROM web_database WHERE `parent_domain_id` = {$web['domain_id']}";
        $result = $this->DB->query( $query );

        return $result->fetch_all( MYSQLI_ASSOC );
    }

    /**
     * Dump Databases
     */
    public function dumpDatabases( $databases )
    {
        foreach( $databases as $database ) {
            $this->dumpDatabase( $database );
        }
    }

    /**
     * Dump Database
     */
    public function dumpDatabase( $database )
    {
        $this->log( 'Dumping Database: ' . $database['database_name'] );
        $temp_dir = sys_get_temp_dir();

        if ( ! file_exists( $temp_dir . '/databases') ) {
            mkdir( $temp_dir . '/databases') ;
        }

        $command = "mysqldump {$database['database_name']} > {$temp_dir}/databases/{$database['database_name']}.sql";
        echo shell_exec( $command );
        $this->log( 'Dumping Database ' . $database['database_name'] . ' Complete' );
    }

    /**
     * Make Zip From Web
     * 
     * @return String
     */
    function makeZipFromWeb( $web )
    {
        $this->log( 'Compressing Web Files : ' . $web['domain'] );
        $path = sys_get_temp_dir() . "/web.zip";
        $this->compress( $web['document_root'], $path, config('backup_folders') );

        return $path;
    }

    /**
     * Make Zip From Databases
     * 
     * @return String
     */
    public function makeZipFromDatabases( $databases )
    {
        $this->log( 'Dumping database' );

        $this->dumpDatabases( $databases );
        $source = sys_get_temp_dir() . "/databases";
        $output = sys_get_temp_dir() . "/databases.zip";

        $this->compress( $source, $output);
        $this->removeDirectory( $source );

        return $output;
    }

    /**
     * Clean temporary files
     */
    private function cleanup()
    {
        $files = ['web.zip', 'databases', 'databases.zip'];
        try {
            foreach( $files as $file ) {
                $path = sys_get_temp_dir() . "/" . $file;
                if ( file_exists( $path ) ) {
                    if ( is_dir( $path ) ) {
                        $this->removeDirectory( $path );
                    } else {
                        unlink( $path );
                    }
                }
            } 
        } catch( \Exception $e ) {

        }
    }

    /**
     * Compress
     */
    public function compress( $source_path, $output, $files = '*' )
    {
        $command = 'zip -r -q ';
        $archive_password = config('archive_password');

        if ( $archive_password ) {
            $command .= "-P '{$archive_password}' ";
        }

        if ( is_dir( $source_path ) ) {
            $command = "cd {$source_path} && {$command} {$output} {$files} ";
        } else {
            $command = "cd " . dirname( $source_path ) . " && {$command} {$output} " . basename( $source_path );
        }

        $output = shell_exec( $command );

        if ( $output ) {
            $this->log( $output );
        }
    }    

    /**
     * Remove Directory
     */
    private function removeDirectory( $dir )
    {
        if ( $dir == ''){
            return ;
        }

        $it = new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS );
        $files = new RecursiveIteratorIterator( $it, RecursiveIteratorIterator::CHILD_FIRST );

        foreach( $files as $file ) {
            if ( $file->isDir() ){
                rmdir( $file->getRealPath() );
            } else {
                unlink( $file->getRealPath() );
            }
        }

        rmdir( $dir );
    }
    
    /**
     * Run Backup
     */
	public function runBackup()
	{
        // Cleanup Prevous file if any
        $this->cleanup();
        
        $this->started_at = date('Y-m-d H:i:s');
        $webs = $this->getWebs();
        $stats = [];

		foreach( $webs as $web ) {
            try {
                $stat = [
                    'domain' => $web['domain']
                ];
                
                $web_file = $this->makeZipFromWeb( $web );
                $remote_path = config('remote_path') . '/' . "{$web['domain']}/";

                if ( config('group_by_date') == true ) {
                    $remote_path = config('remote_path') . "/" . date('Y-m-d') . '/' . $web['domain']. '/';
                }

                // Upload Web
                $stat['web_size'] = filesize( $web_file );
                $this->log('Uploading Web File');
                $command = "rclone copy {$web_file} {$remote_path} -v";
                $this->log( shell_exec( $command ) );

                // Upload Database if Any
                $databases = $this->getWebDatabases( $web );
                if ( count( $databases ) > 0 ) {
                    $db_file = $this->makeZipFromDatabases( $databases );
                    $stat['database_size'] = filesize( $db_file );
                    $this->log('Uploading Database File');
                    $command = "rclone copy {$db_file} {$remote_path} -v";
                    $this->log( shell_exec( $command ) );
                }

                $stats[] = $stat;
                $this->cleanup();
            } catch( \Exception $e ) {
                $this->log( $e->getMessage() );
                $this->report( $e->getMessage() );
                $this->cleanup();
            }   
        }

        $this->reportBackup( $stats );
    }

    /**
     * Report Backup
     */
    private function reportBackup( $stats )
    {
        $message = 'Backup has Been Finished' . PHP_EOL
                    . 'Started At: ' . $this->started_at . PHP_EOL
                    . 'Finished At: ' . date( 'Y-m-d H:i:s' ) . PHP_EOL . PHP_EOL;

        foreach( $stats as $stat ) {
            $message .= "*{$stat['domain']}*" . PHP_EOL
                        . "Web: " . $this->formatBytes( $stat['web_size'] ) . PHP_EOL;

            if ( isset( $stat['database_size'] ) ) {
                $message .= "DB: " . $this->formatBytes( $stat['database_size'] ) . PHP_EOL;
            }
        }

        $this->report( $message );
    }

    /**
     * Format Bytes
     * 
     * @return String
     */
    private function formatBytes($size, $precision = 2) { 
        $base = log( $size, 1024 );
        $suffixes = array('', 'K', 'M', 'G', 'T');   

        return round( pow( 1024, $base - floor( $base ) ), $precision ) .' '. $suffixes[ floor( $base ) ];
    } 

    /**
     * Report to Notification Channel
     */
    private function report( $message )
    {
        $slack_webhook_url = config('slack_webhook_url');

        if ( ! $slack_webhook_url ) {
            return;
        }

        $payload = [
            'username'  => config('slack_bot_name'),
            'text'      => $message
        ];

        $ch = curl_init();
        $options = array(
            CURLOPT_URL             => $slack_webhook_url,
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => json_encode( $payload ),
            CURLOPT_RETURNTRANSFER  => 1
        );

        curl_setopt_array($ch, $options);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Log 
     */    
    private function log( $message )
    {
        echo $message . PHP_EOL;
    }

}