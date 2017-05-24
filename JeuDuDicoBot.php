<?php

include('dbinfo.php');
include('twitterCredentials.php');
require_once('TwitterAPIExchange.php');
header('Content-Type: text/html; charset=utf-8');

// Set Twitter official app's access tokens, see README.md
$APIsettingsAndroid = array(
    'oauth_access_token' => $oauthTokenAndroid,
    'oauth_access_token_secret' => $oauthTokenSecretAndroid,
    'consumer_key' => $consumerKeyAndroid,
    'consumer_secret' => $consumerSecretAndroid
);

/** Set access tokens here - see: https://apps.twitter.com/ **/
$APIsettings = array(
    'oauth_access_token' => $oauthToken,
    'oauth_access_token_secret' => $oauthTokenSecret,
    'consumer_key' => $consumerKey,
    'consumer_secret' => $consumerSecret
);

$twitterAndroid = new TwitterAPIExchange($APIsettingsAndroid);
$twitter = new TwitterAPIExchange($APIsettings);

// Connect to applications DB
try {
    $bddApp = new PDO('mysql:host=' . $dbhost . ';dbname=' . $dbAppName, $dbAppLogin, $dbAppPassword, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));
    $bddApp->exec("SET CHARACTER SET utf8");
} catch (PDOException $e) {
    echo 'Echec de la connexion : ' . $e->getMessage();
    exit;
}

// Get previous poll's tweet id and right answer
$appResponse = $bddApp->query("SELECT value FROM keyvalue WHERE app='jeuDuDicoBotTweetId'");
$appResult = $appResponse->fetchAll();
$tweetid = $appResult[0][0];
$appResponse = $bddApp->query("SELECT value FROM keyvalue WHERE app='jeuDuDicoBotWord'");
$appResult = $appResponse->fetchAll();
$word = $appResult[0][0];
$appResponse->closeCursor();

// If poll's tweet exists
if(strlen($tweetid) > 0 && strlen($word) > 0){
    $tweet = "Le terme correspondant à cette définition était... " . $word . " ! Bien joué à tous ! Et tout de suite, une nouvelle énigme...";
    // Post the tweet with the right word in reply to the previous poll's tweet
    $postfields = array(
        'status' =>  $tweet,
        'in_reply_to_status_id' => $tweetid
        );
    $url = "https://api.twitter.com/1.1/statuses/update.json";
    $requestMethod = "POST";

    echo $twitter->buildOauth($url, $requestMethod)
                  ->setPostfields($postfields)
                  ->performRequest();
}

// Connect to rare words DB
try {
    $bdd = new PDO('mysql:host=' . $dbhost . ';dbname=' . $dbname, $dblogin, $dbpassword, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));
    $bdd->exec("SET CHARACTER SET utf8");
} catch (PDOException $e) {
    echo 'Echec de la connexion : ' . $e->getMessage();
    exit;
}

// Get a rare word and at least another one with close lexical properties
$result = array();
do{
    $queryWord = 'SELECT * FROM rares WHERE lexinfo IS NOT NULL ORDER BY RAND() LIMIT 1';
    $response = $bdd->query($queryWord);
    $result = $response->fetchAll();
    $wordObject = $result[0];
    $response->closeCursor();

    // remove word root from definition if database is not clean
    $wordRoot = substr($wordObject['word'], 0, 4);
    $defSplit = explode(".", $wordObject['def']);
    foreach ($defSplit as $key => $defPart) {
        if(stripos($defPart, $wordRoot) !== FALSE)
            array_splice($defSplit, $key, 1);
    }
    $def = trim(implode(".", $defSplit));

    $lexinfo = $wordObject['lexinfo'];

    $lexQuery = "";
    $lexparts = explode(".", $lexinfo);
    foreach ($lexparts as $key => $part) {
        if(strpos($part, "et ") !== FALSE)
            $part = str_replace("et ", "", $part);
        $part = trim($part);
        if(strlen($part) == 0)
            continue;
        if($key != 0)
            $lexQuery .= "AND ";
        $lexQuery .= 'lexinfo LIKE "%' .$part . '.%" ';
    }

    $queryWord = 'SELECT word FROM rares WHERE '.$lexQuery.' AND word!="'.$wordObject['word'].'" ORDER BY RAND() LIMIT 3';
    $response = $bdd->query($queryWord);
    $result = $response->fetchAll();
    $response->closeCursor();
}while(count($result) == 0 || strlen($def) == 0);

// Add all words in one array and shuffle
$words = array($wordObject['word']);
foreach ($result as $word) {
    $words[] = $word[0];
}
shuffle($words);

var_dump($words);

// Create poll card with words
$cards = array(
    'twitter:long:duration_minutes' => 1440,
    'twitter:api:api:endpoint' => '1',
    'twitter:card' => 'poll'. count($words) .'choice_text_only',
    );
foreach ($words as $key => $word) {
    $cards['twitter:string:choice' . ($key+1) . '_label'] = $word;
}

var_dump($cards);

// Post the poll card
$postfields = array(
    'card_data' =>  json_encode($cards),
    'send_error_codes' => 1
);
$url = "https://caps.twitter.com/v2/cards/create.json";
$requestMethod = "POST";
// Use the user-agent of the official app
$curlOptions = array(CURLOPT_USERAGENT => 'TwitterAndroid/6.45.0 Nexus 4/17 (LGE;mako;google;occam;0)');
$card_data = $twitterAndroid->buildOauth($url, $requestMethod)
              ->setPostfields($postfields)
              ->performRequest(true, $curlOptions);

$card_data = json_decode($card_data);

var_dump($card_data);

// If card has been created
if(isset($card_data->card_uri)){
    $tweet = $def;
    if(strlen($tweet) > 140)
      $tweet = substr($tweet, 0, 140 - 3) . "...";

    // Post the tweet with the poll card and the definition
    $postfields = array(
        'status' =>  $tweet,
        'card_uri' => $card_data->card_uri,
        'cards_platform' => 'Android-10',
        'include_cards' => 1
        );
    $url = "https://api.twitter.com/1.1/statuses/update.json";
    $requestMethod = "POST";
    $tweet_data = $twitter->resetFields()->buildOauth($url, $requestMethod)
                  ->setPostfields($postfields)
                  ->performRequest();

    $tweet_data = json_decode($tweet_data);

    var_dump($tweet_data);
	
	// If successful, store the tweet's id and the right word answer in the application DB, else don't reply to inexisting poll next time
    if(isset($tweet_data->id_str)){
        $appResponse = $bddApp->query("UPDATE keyvalue SET value = '". $tweet_data->id_str ."' WHERE app = 'jeuDuDicoBotTweetId'");
        $appResponse = $bddApp->query("UPDATE keyvalue SET value = '". $wordObject['word'] ."' WHERE app = 'jeuDuDicoBotWord'");
    }else{
        $appResponse = $bddApp->query("UPDATE keyvalue SET value = '' WHERE app = 'jeuDuDicoBotTweetId'");
        $appResponse = $bddApp->query("UPDATE keyvalue SET value = '' WHERE app = 'jeuDuDicoBotWord'");
    }
}else{
    $appResponse = $bddApp->query("UPDATE keyvalue SET value = '' WHERE app = 'jeuDuDicoBotTweetId'");
    $appResponse = $bddApp->query("UPDATE keyvalue SET value = '' WHERE app = 'jeuDuDicoBotWord'");
}
$appResponse->closeCursor();

?>