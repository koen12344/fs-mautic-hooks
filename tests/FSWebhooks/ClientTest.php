<?php
declare(strict_types=1);

namespace FSWebhooks;

use PHPUnit\Framework\TestCase;

use FSWebhooks\Acelle\Client;
use InvalidArgumentException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Middleware;

class ClientTest extends TestCase
{
    protected $base_endpoint = "https://example.com/api/v1/";
    protected $token = "M2hKN7jWhVHHXkkwWZC1gooCg6492CARvJZCxMfGdsfXdK9w4iLtvbSBI9wy";
    protected $client;

    protected function setUp():void{
        $this->client = Client::initialize($this->base_endpoint, $this->token);
    }

    public function testCanBeCreatedFromValidCredentials():void
    {
        $this->assertInstanceOf(
            Client::class,
            Client::initialize($this->base_endpoint, $this->token)
        );
    }
    public function testCannotBeCreatedFromInvalidCredentials():void
    {
        $this->expectException(InvalidArgumentException::class);

        Client::initialize('invalid', '34234');
    }

    public function testGetLists():void
    {
        $listsresponse = '[{"uid":"5c3c8243112b1","name":"Test list","default_subject":"","from_email":"koen@tycoonwp.com","from_name":"Koen | Post to Google My Business","status":null,"created_at":"2019-01-14 12:36:19","updated_at":"2019-04-04 12:19:02"}]';
        $mock = new MockHandler([
            new Response(200, [], $listsresponse)
        ]);
        $handler = HandlerStack::create($mock);
        $client = new GuzzleClient(['handler' => $handler]);
        $acelleClient = Client::initialize($this->base_endpoint, $this->token, $client);

        $lists = $acelleClient->getLists();
        $this->assertObjectHasAttribute('uid', $lists[0]);
    }

    /*
     * {"message":"Something went wrong adding subscriber. The email address is blacklisted"}
     */

    public function testAddValidSubscriber():void{
        $successresponse = '{"status":1,"message":"Subscriber was successfully created","subscriber_uid":"5c3f3736b9fb8"}';

        $container = [];
        $history = Middleware::history($container);

        $mock = new MockHandler([
            new Response(200, [], $successresponse)
        ]);
        $handler = HandlerStack::create($mock);

        $handler->push($history);

        $client = new GuzzleClient(['handler' => $handler]);
        $acelleClient = Client::initialize($this->base_endpoint, $this->token, $client);
        $extra_fields = [
            'FIRST_NAME'    => 'example',
            'LAST_NAME'     => 'last'
        ];
        $success = $acelleClient->addSubscriber('5c3c8243112b1', 'test@example.com', $extra_fields);
        $this->assertObjectHasAttribute('message', $success);


        foreach ($container as $transaction) {
            //echo $transaction['request']->getBody();
            //echo $transaction['request']->getURI();
            //> GET, HEAD
            if ($transaction['response']) {
                echo $transaction['response']->getStatusCode();
                //> 200, 200
            } elseif ($transaction['error']) {
                echo $transaction['error'];
                //> exception
            }
            //var_dump($transaction['options']);
            //> dumps the request options of the sent request.
        }

    }

    public function testGetSubscribersByEmail():void{
        $response = '{"subscribers":[{"uid":"3887bff6a3cb7","list_uid":"5c3c89b5bd4c9","email":"ik@koenreus.com","status":"subscribed","source":null,"ip_address":null,"FIRST_NAME":"Koen","LAST_NAME":"Reus","LICENSE":"","GOOGLE":"true","INSTALL_STATE":"Activated","UNINSTALL_REASON":"","BETA_TESTER":"true"},{"uid":"5c3f3736b9fb8","list_uid":"5c3c8243112b1","email":"ik@koenreus.com","status":"subscribed","source":null,"ip_address":null,"FIRST_NAME":"KoenTest","LAST_NAME":"KoenTestLaste3","BETA_TESTER":"no"},{"uid":"440b7d76d3179","list_uid":"5ca368dbb750b","email":"ik@koenreus.com","status":"unsubscribed","source":null,"ip_address":null,"FIRST_NAME":"Koen","LAST_NAME":"Reus"}]}';
        $mock = new MockHandler([
            new Response(200, [], $response)
        ]);
        $handler = HandlerStack::create($mock);
        $client = new GuzzleClient(['handler' => $handler]);
        $acelleClient = Client::initialize($this->base_endpoint, $this->token, $client);

        $subscribers = $acelleClient->getSubscribersByEmail('ik@koenreus.com');
        $this->assertObjectHasAttribute('uid', $subscribers->subscribers[0]);

    }
}
