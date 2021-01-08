<?php

require_once("config/config.php");
require_once("DiscordAuth.php");

if ($noDiscordLogin === false) {
    try {

        if (isset($_GET['code'])) {
            $auth = new DiscordAuth();
            $auth->handleAuthorizationResponse($_GET);
            $user = json_decode($auth->get("/api/users/@me"));

	    $client = new \GuzzleHttp\Client([
		    'base_uri' => 'https://discordapp.com',
		    'headers' => [
			    'Authorization' => 'Bot ' . $discordBotToken,
			    'User-Agent' => $discordBotUA 
		    ]
	    ]);
            //Figure out registration and role status
            $canViewMap = false;
            $nickname = null;
            $ismod = false;

            //foreach ([$localsDiscordServerId, $publicDiscordServerId] as $sid){
            foreach ([$localsDiscordServerId] as $sid){
                    $url = "/api/guilds/{$sid}/members/{$user->id}";
    try {
                    $guildMember = json_decode($client->request('GET', $url)->getBody()->getContents());
    } catch (Exception $e) {
    continue;
    }

                    if (!$guildMember){
                        //Not a member of this discord 
                        continue;
                    }

                    if (!is_null($guildMember->nick)){
                        $nickname = $guildMember->nick;
                    }

                    //If they have no roles at all, give them a 1 day trial period.
                    $date = new DateTime($guildMember->joined_at);
                    if ($date->getTimestamp() + 3600 *24 > time()){
                            $canViewMap = true;
                    }

                    //Do they have one of the mod or donor roles?
                    foreach ($guildMember->roles as $role){
                        if (in_array($role, $discordAdminRoles)){
                                $canViewMap = true;
                                $ismod = true;
                        }
                        if (in_array($role, $donatorRoles)){
                                $canViewMap = true;
                        }
                    }
            }

            if (!$canViewMap){
                //Redirect and die
                    header("Location: ".$discordUrl);
                    die();
            }
            if (is_null($nickname)){
                $nickname = $user->username;
            }

            $count = $monocledb->count("users", [
                "session_id" => session_id(),
            ]);

            #expire_timestamp used only to clean up unused sessions
            #Expire monthly
            $new_expire_timestamp = time() + 24 * 60 * 60 * 30;
            #Never expiring timestamps:
            #$new_expire_timestamp = time() + 24 * 60 * 60 * 9999;
            if ($count === 0) {
                $monocledb->insert("users", [
                    "session_id" => session_id(),
                    "id" => $user->{'id'},
                    "user" => $nickname,
		    "ismod" => $ismod,
                    "expire_timestamp" => $new_expire_timestamp,
                    "login_system" => 'discord'
                ]);

                $logMsg = "NEW SESSION ('{$user->id}', '{$user->username}" . "#" . "{$user->discriminator}', 'discord', '{$nickname}', '" . session_id() . "'); -- " . date('Y-m-d H:i:s') . "\r\n";
                file_put_contents($logfile, $logMsg, FILE_APPEND);
            }

            #cookie lifetime is separate from session cleanup
            #similarity is superficial
            #remember that we still revalidate on each access
            file_put_contents($logfile, "Setting logincookie\n", FILE_APPEND);
            setcookie("LoginCookie", session_id(), time() + 24 * 60 * 60 * 7);

            #Update only this session due to the expire_timestamp business
            #We already checked that they can view the map above.
            $monocledb->update("users", [
                    "id" => $user->{'id'},
                    "user" => $nickname,
		    "ismod" => $ismod,
                    "expire_timestamp" => $new_expire_timestamp,
                    "login_system" => 'discord'
            ], [
                "session_id" => session_id(),
            ]);
        }
        header("Location: .");
        die();
    } catch (Exception $e) {
    file_put_contents(
		'/tmp/test',
	$e,
	    FILE_APPEND
    );
        header("Location: ./discord-login");
    }
} else {
    header("Location: .");
}
