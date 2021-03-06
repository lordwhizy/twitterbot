<?php

namespace App\Console\Commands;

use App\DM;
use App\DMConfig;
use App\Library\TwitterBot;
use App\Setting;
use Exception;
use Illuminate\Console\Command;

class DMFollower extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twitterbot:dmfollower';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send DM to new follower';

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
     * @throws Exception
     */
    public function handle()
    {
        $settings = Setting::findOrNew(1);
        if (!$settings->bot_power || !$settings->onfollow_power || !$settings->consumer_key || !$settings->consumer_secret || !$settings->access_token || !$settings->access_secret) {
            return;
        }

        $twitter = new TwitterBot();

        $dm_conf = DMConfig::findOrNew(1);
        if (!$dm_conf->disable && !empty($dm_conf->text)) {

            $response = json_decode($twitter->buildOauth('https://api.twitter.com/1.1/followers/list.json', 'GET')->performRequest());
            foreach ($response->users as $user) {
                $exist = DM::where('follower_id', $user->id_str)->exists();
                if (!$exist) {
                    $this->sendDM($user->id_str, $dm_conf->text);

                    $dm = new DM();
                    $dm->follower_id = $user->id_str;
                    $dm->screen_name = $user->screen_name;
                    $dm->name = $user->name;
                    $dm->msg = $dm_conf->text;
                    $dm->sent = true;
                    $dm->save();
                }
            }
        }
    }

    public function sendDM($user_id, $text)
    {
        $twitter = new TwitterBot();
        try {
            $postfields = array(
                'event' =>
                    array(
                        'type' => 'message_create',
                        'message_create' =>
                            array(
                                'target' => array('recipient_id' => $user_id),
                                'message_data' => array('text' => $text)
                            )
                    )
            );
            $url = 'https://api.twitter.com/1.1/direct_messages/events/new.json';
            $requestMethod = 'POST';

            $twitter->appjson = true;
            $twitter->buildOauth($url, $requestMethod)->performRequest(true,
                [
                    CURLOPT_POSTFIELDS => json_encode($postfields)
                ]
            );
        } catch (Exception $e) {}
    }
}