<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Predis;

class LogoutAllUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:force-logout {--operation=} {--with-values} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Max attempts to remove session keys.
     */
    protected $maxAttempts = 5;

    /**
     * Pause duration, in seconds, between session key force expire attempts.
     */
    protected $pauseDuration = 60;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Get and validation requested operation
        $op = $this->option('operation');

        if(!$op) {
            $this->error('Missing required option: --operation');

            exit -1;
        }

        // Route to correct function based on requested operation
        switch(strtolower($op)) {
            case 'key-count':
                $this->displayKeyCount();
                break;

            case 'session-key-count':
                $this->displaySessionKeyCount();
                break;

            case 'list-session-keys':
                $this->listSessionKeys();
                break;

            case 'expire-session-keys':
                $this->expireSessionKeys();
                break;

            default:
                $this->error('Unrecognized operation requested: ' . $op);
                $this->error('Valid operations: key-count, session-key-count, list-session-keys [--with-values], expire-session-keys [--dry-run]');
                break;
        }
        
        
    }

    /**
     * Create the redis client.
     *
     * Returns an object indicating success or error.
     * The client is returned in the 'data' result property.
     */
    protected function createRedisClient()
    {
        $client = null;

        try {
            $client = new Predis\Client([
                'scheme' => 'tcp',
                // 'host' => 'localhost',
                'host' => 'tpv-cache-cluster.2skd8m.clustercfg.use1.cache.amazonaws.com',
                'port' => 6379,
                'database' => 0
            ]);

        } catch(\Exception $e) {
            return $this->newResult('error', 'Error creating Redis client: ' . $e->getMessage());
        }

        return $this->newResult('success', '', $client);
    }

    /**
     * List session keys.
     */
    protected function listSessionKeys()
    {
        // Create the redis client.
        $clientResult = $this->createRedisClient();

        if($clientResult->result !== 'success') {
            $this->error($clientResult->message);

            exit -1;
        }

        $client = $clientResult->data;

        // Iterate keys list and remove session keys.
        // Session keys are identify by having the login_web_59ba36addc2b2f9401580f014c7f58ea4e30989d property
        $keys = $client->keys('*');

        $numKeys = count($keys);
        $ctr = 0;

        $this->info('Total Keys to search: ' . $numKeys);

        foreach($keys as $key) {            

            $ctr += 1;

            // Get the value for current key
            try {
                $val = $client->get($key);
            } catch(\Exception $e) {
                continue;
            }

            // Ignore keys that are not session keys
            try {
                if(!strpos($val, 'login_web_59ba36addc2b2f9401580f014c7f58ea4e30989d')) {
                    continue;
                }
            } catch (\Exception $e) {
                continue; // Expecting catch to trigger on non-string values in Redis. Skip because those cannot be session keys anyway.
            }

            $this->info('--------------------------------------------------');
            $this->info('[ ' . $ctr . '/' . $numKeys . ' ] ' . strtoupper($key));

            if($this->option('with-values')) {
                $this->info('');
                print_r($val);
            }

            $this->info('');
            $this->info('');
        }
    }

    /**
     * Expire session keys. Makes several attempts to expire session keys in redis.
     */
    protected function expireSessionKeys()
    {
        // In prod server, the script does not always remove session keys on first try, so multiple attempts should be made.
        $keysExpired = 0;

        for($numTry = 0; $numTry < $this->maxAttempts; $numTry++) {

            // Create the redis client.
            // The clients is being created each iteration intentionally.
            // In the previous version of this program, the Redis client was created once.
            // In that version, if the first attempt failed to expire any keys, all other attempts failed as well.
            // Hopefully recreating the client remedies that behavior.
            $clientResult = $this->createRedisClient();

            if($clientResult->result !== 'success') {
                $this->error($clientResult->message);

                if(!$this->option('dry-run')) {
                    SendTeamMessage('monitoring', '[ForceLogoutUsers] ' . $clientResult->message);
                }
                exit -1;
            }

            $client = $clientResult->data;

            // Send MM blast
            if(!$this->option('dry-run')) {
                SendTeamMessage('monitoring', "[ForceLogoutUsers] Attempting to expire Focus session keys [" . ($numTry + 1) . " / $this->maxAttempts]");
            }

            // Expire the session keys
            $keysExpired = $this->doExpireSessionKeys($client);
            
            if($this->option('dry-run')) {
                $msg = "Dry Run. $keysExpired user login session(s) would have been expired.";
            } else {
                $msg = "$keysExpired user login session(s) have been expired.";
            }

            $this->info($msg);
            if(!$this->option('dry-run')) {
                SendTeamMessage('monitoring', '[ForceLogoutUsers] ' . $msg);
            }

            // Were keys expired? If yes, break out of loop.
            if($keysExpired > 0) {
                break;
            }

            // No keys expired. Display reattempt message if this wasn't the last attempt.
            if($numTry < ($this->maxAttempts - 1)) {
                $this->info("Reattempting after $this->pauseDuration seconds...");

                sleep($this->pauseDuration); // Wait before trying again.                
            }
        }

        // Display message and send MM blast if we were not able to expire any keys after all attempts were exhausted.
        if($keysExpired == 0 && $numTry == $this->maxAttempts) {
            $msg = "Failed to expire any session keys after $this->maxAttempts tries.";
            $this->info($msg);

            if(!$this->option('dry-run')) {
                SendTeamMessage('monitoring', '[ForceLogoutUsers] ' . $msg);
            }
        }
    }

    /**
     * The function that searches for and expires session keys.
     */
    protected function doExpireSessionKeys($client)
    {
        // Iterate keys list and remove session keys.
        // Session keys are identified by having the login_web_59ba36addc2b2f9401580f014c7f58ea4e30989d property
        $keys = $client->keys('*');

        $numKeys = count($keys);
        $ctr = 0;
        $keysExpired = 0;

        $this->info('Total Keys to search: ' . $numKeys);

        foreach($keys as $key) {            

            $ctr += 1;

            // Get the value for current key
            try {
                $val = $client->get($key);
            } catch(\Exception $e) {
                continue;
            }

            // Ignore keys that are not session keys
            try {
                if(!strpos($val, 'login_web_59ba36addc2b2f9401580f014c7f58ea4e30989d')) {
                    continue;
                }
            } catch (\Exception $e) {
                continue; // Expecting catch to trigger on non-string values in Redis. Skip because those cannot be session keys anyway.
            }

            $this->info('--------------------------------------------------');
            $this->info('[ ' . $ctr . '/' . $numKeys . ' ] ' . strtoupper($key));

            // Expire the session key
            try {
                if(strpos($val, 'login_web_59ba36addc2b2f9401580f014c7f58ea4e30989d')) {
                    $keysExpired++;

                    if(!$this->option('dry-run')) {
                        $client->expire($key, 0);
                        $this->info('');
                        $this->info('');
                        $this->info('EXPIRED THE KEY');
                    } else {
                        $this->info('');
                        $this->info('');
                        $this->info('KEY WOULD HAVE BEEN EXPIRED');
                    }
                }
            } catch (\Exception $e) {
                ; // Do nothing.
            }

            $this->info('');
            $this->info('');
        }

        return $keysExpired;
    }

    /**
     * Display count of all keys in Redis
     */
    protected function displayKeyCount()
    {
        // Create the redis client
        $clientResult = $this->createRedisClient();

        if($clientResult->result !== 'success') {
            $this->error($clientResult->message);

            exit -1;
        }

        $client = $clientResult->data;

        $keys = $client->keys('*');

        $this->info('Key Count: ' . count($keys));
    }

    /**
     * Display count of all session keys in Redis
     */
    protected function displaySessionKeyCount()
    {
        // Create the redis client
        $clientResult = $this->createRedisClient();

        if($clientResult->result !== 'success') {
            $this->error($clientResult->message);

            exit -1;
        }

        $client = $clientResult->data;

        $keys = $client->keys('*');

        $ctr = 0;
        foreach($keys as $key) {
            try {
                $val = $client->get($key);

                // If login_web... exists in value, we're considering this a session key.
                if(strpos($val, 'login_web_59ba36addc2b2f9401580f014c7f58ea4e30989d')) {
                    $ctr++;
                }

            } catch (\Exception $e) {
                ; // Do nothing
            }
        }

        $this->info("Session Key Count: " . $ctr);
    }

    /**
     * Result object helper method
     */
    protected function newResult($result = 'success', $msg = '', $data = null)
    {
        return (object)['result' => $result, 'message' => $msg, 'data' => $data];
    }
}
