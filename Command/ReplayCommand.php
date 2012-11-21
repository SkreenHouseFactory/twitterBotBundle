<?php

/**
 * Description of ReplayCommand
 *
 * @author sebastienhupin
 */

namespace SkreenHouseFactory\twitterBotBundle\Command;

require('%kernel.root_dir%/../vendor/twitteroauth/twitteroauth/twitteroauth.php');

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
//use Goutte\Client;
use TwitterOAuth;

use SkreenHouseFactory\v3Bundle\Api\ApiManager;

class ReplayCommand extends Command {

  /**
   *
   * @var \Symfony\Component\Console\Output\OutputInterface; 
   */
  protected $output;

  /**
   *
   * @var array 
   */
  protected $hashtags = array();
  /**
   *
   * @var Object (twitter api)
   */
  protected $tweeter;
  
  protected function configure() {
    $this
            ->setName('myskreen:replay')
            ->setDescription('Replay to tweet from users')
    /*
      ->addArgument(
      // Add arguments here
      )
      ->addOption(
      // Add option here
      )
     * 
     */
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->output = $output;

    $twitter = $this->tweeter = $this->callApiTwitter();

    $tweets = $twitter->get('search/tweets', array(
        'q' => 'to:myskreen',
        'include_entities' => true
            ));

    foreach ($tweets->statuses as $tweet) {
      $hashtags = $tweet->entities->hashtags;
      foreach ($hashtags as $hashtag) {
        $output->writeln("Found a hashtag : " . $hashtag->text);
        $this->hashtags[$tweet->id] = array('username' => $tweet->user->screen_name, 'hashtag' => $hashtag->text);
      }
    }

    $users_to_reply = array();
    foreach ($this->hashtags as $id => $tweet) {
      $result = $this->callApiMyskreen($tweet['hashtag']);
      if ($result->success) {
        // Search for retweet
        //statuses/retweets/:id.json        
        $rettweets = $twitter->get('statuses/retweets/' . $id);
                
        $this->sendResponse($id, $tweet['username'], $result->tweet);
        
        // Reply to users.
        foreach ($rettweets as $rettweet) {
          $output->writeln("User : " . $rettweet->user->screen_name);
          $this->sendResponse($id, $rettweet->user->screen_name, $result->tweet);
        }
      } else {
        $output->writeln($result->error . ' : ' . $hashtag);
      }
    }
  }

  protected function sendResponse($id,$user,$text) {
    $message = mb_strcut(sprintf("@%s %s",$user,$text), 0, 139, 'UTF-8');
    $this->output->writeln("Send response : " . $message);

    $status = $this->tweeter->post('statuses/update', array(
        'in_reply_to_status_id' => $id,
        'status' => $message
    ));

    //print_r($status);

  }

  /**
   * 
   * @param string $hashtag
   * @return type
   */
  protected function callApiMyskreen($hashtag) {
    $api = 'http://api.myskreen.com/api/1';
    $url = $api . '/hashtag/program/' . $hashtag . '.json?since=1351750285';
    $this->output->writeln($url);
    //$client = new Client();
    //$client->request('GET', $url);
    //$response = $client->getResponse()->getContent();
    // Parse reponse json to stdclass.
    //$response = json_decode($response);
    $this->container = $this->getApplication()->getKernel()->getContainer();
    $api   = new ApiManager($this->container->getParameter('kernel.environment'), '.json');
    $response = $api->fetch('hashtag/program/' . $hashtag, array(
                  'since' => '1351750285'
              ));
    
    //print_r($response);
    return $response;
  }

  protected function callApiTwitter() {
    $token_credentials = array(
        'consumer_key' => 'JSuauKd6CCPrURhSXn3hWQ',
        'consumer_secret' => 'nfY0OE20cEbyx54e83e72dTh2zFqPd3sUaS0k00IP0',
        'oauth_token' => '944336262-Xvw2ooHGK6CqipizpH1tgW5xBX6TNqUDChRsRHby',
        'oauth_token_secret' => '3kPZjKJhcHUY3Dmb3qbTApXGgL9GTR6jqFnjdBvIRc'
    );

    try {
      $connection = new TwitterOAuth(
                      $token_credentials['consumer_key'],
                      $token_credentials['consumer_secret'],
                      $token_credentials['oauth_token'],
                      $token_credentials['oauth_token_secret']);

      $connection->host = 'https://api.twitter.com/1.1/';

      //print_r($content);
      return $connection;
    } catch (\Exception $ex) {
      throw new \Exception($ex);
    }
  }

}

?>