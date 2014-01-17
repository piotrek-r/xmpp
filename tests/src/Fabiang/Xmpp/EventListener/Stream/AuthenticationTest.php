<?php

namespace Fabiang\Xmpp\EventListener\Stream;

use Fabiang\Xmpp\Event\XMLEvent;
use Fabiang\Xmpp\Connection\Test;
use Fabiang\Xmpp\Event\EventManager;
use Fabiang\Xmpp\Options;

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.1 on 2014-01-11 at 18:29:57.
 */
class AuthenticationTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Authentication
     */
    protected $object;
    
    /**
     * 
     * @var Test
     */
    protected $connection;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     *
     * @return void
     */
    protected function setUp()
    {
        $this->object = new Authentication;
        $this->connection = new Test;
        
        $options = new Options;
        $options->setConnection($this->connection);
        $this->object->setOptions($options);
        $this->connection->setReady(true);
    }

    /**
     * Test what events are attached.
     *
     * @covers Fabiang\Xmpp\EventListener\Stream\Authentication::attachEvents
     * @return void
     */
    public function testAttachEvents()
    {
        $connection = new Test();
        $options = new Options;
        $options->setConnection($connection);
        $this->object->setOptions($options);
        $this->object->attachEvents();
        
        $input = $connection->getInputStream()->getEventManager();
        $this->assertArrayHasKey('{urn:ietf:params:xml:ns:xmpp-sasl}mechanisms', $input->getEventList());
        $this->assertArrayHasKey('{urn:ietf:params:xml:ns:xmpp-sasl}mechanism', $input->getEventList());
    }

    /**
     * Test collecting machanisms from event.
     *
     * @covers Fabiang\Xmpp\EventListener\Stream\Authentication::collectMechanisms
     * @covers Fabiang\Xmpp\EventListener\Stream\Authentication::getMechanisms
     * @covers Fabiang\Xmpp\EventListener\Stream\Authentication::isBlocking
     * @covers Fabiang\Xmpp\EventListener\Stream\Authentication::isAuthenticated
     * @return void
     */
    public function testCollectMechanisms()
    {        
        $element = new \DOMElement('machanism', 'PLAIN');
        $event   = new XMLEvent;
        $event->setParameters(array($element));
        $this->object->collectMechanisms($event);
        $this->assertSame(array('plain'), $this->object->getMechanisms());
        
        $element = new \DOMElement('machanism', 'DIGEST-MD5');
        $event->setParameters(array($element));
        $this->object->collectMechanisms($event);
        $this->assertSame(array('plain', 'digest-md5'), $this->object->getMechanisms());

        $this->assertTrue($this->object->isBlocking());
    }

    /**
     * Test authentication.
     * 
     * @covers Fabiang\Xmpp\EventListener\Stream\Authentication::authenticate
     * @covers Fabiang\Xmpp\EventListener\Stream\Authentication::determineMechanismClass
     * @return void
     */
    public function testAuthenticate()
    {        
        $this->object->setEventManager(new EventManager);
        $this->object->getOptions()->setUsername('aaa')
            ->setPassword('bbb');
        
        $element = new \DOMElement('machanism', 'PLAIN');
        $event   = new XMLEvent;
        $event->setParameters(array($element));
        $this->object->collectMechanisms($event);
        
        $element = new \DOMElement('mechanisms');
        $event   = new XMLEvent;
        $event->setParameters(array($element));

        $this->object->authenticate($event);
        $this->assertContains(
            '<auth xmlns="urn:ietf:params:xml:ns:xmpp-sasl" mechanism="PLAIN">AGFhYQBiYmI=</auth>',
            $this->connection->getBuffer()
        );
    }
    
    /**
     * Test authentication when no mechanisms where collected.
     * 
     * @covers Fabiang\Xmpp\EventListener\Stream\Authentication::authenticate
     * @covers Fabiang\Xmpp\EventListener\Stream\Authentication::determineMechanismClass
     * @expectedException \Fabiang\Xmpp\Exception\RuntimeException
     * @expectedExceptionMessage No supportet authentication machanism found.
     * @return void
     */
    public function testAuthenticateWithoutMachanism()
    {
        $element = new \DOMElement('mechanisms');
        $event   = new XMLEvent;
        $event->setParameters(array($element));

        $this->object->authenticate($event);
    }
    
    /**
     * Test authentication when mechanism class is invalid instance.
     * 
     * @covers Fabiang\Xmpp\EventListener\Stream\Authentication::authenticate
     * @covers Fabiang\Xmpp\EventListener\Stream\Authentication::determineMechanismClass
     * @expectedException \Fabiang\Xmpp\Exception\RuntimeException
     * @return void
     */
    public function testAuthenticateInvalidMechanismHandler()
    {
        $this->object->getOptions()->setAuthenticationClasses(array('plain' => '\stdClass'));
        
        $element = new \DOMElement('machanism', 'PLAIN');
        $event   = new XMLEvent;
        $event->setParameters(array($element));
        $this->object->collectMechanisms($event);
        
        $element = new \DOMElement('mechanisms');
        $event   = new XMLEvent;
        $event->setParameters(array($element));

        $this->object->authenticate($event);
    }
    
    /**
     * Test authentication failure.
     * 
     * @covers Fabiang\Xmpp\EventListener\Stream\Authentication::failure
     * @expectedException Fabiang\Xmpp\Exception\Stream\StreamErrorException
     * @return void
     */
    public function testFailure()
    {
        $document = new \DOMDocument;
        $element = new \DOMElement('failure');
        $document->appendChild($element);
        $event   = new XMLEvent;
        $event->setParameters(array($element));
        
        try {
            $this->object->failure($event);
        } catch (\Fabiang\Xmpp\Exception\StreamErrorException $e) {
            $this->assertFalse($this->object->isBlocking());
            $this->assertSame('<failure/>', $e->getContent());
            throw $e;
        }
    }
    
    /**
     * Test successful authentication.
     * 
     * @covers Fabiang\Xmpp\EventListener\Stream\Authentication::success
     * @return void
     */
    public function testSuccess()
    {
        $element = new \DOMElement('success');
        $event   = new XMLEvent;
        $event->setParameters(array($element));
        
        $connection = $this->getMock('\Fabiang\Xmpp\Connection\Test', array());
        $this->object->getOptions()->setConnection($connection);
        
        $connection->expects($this->once())
            ->method('resetStreams');
        $connection->expects($this->once())
            ->method('connect');
        
        $this->object->success($event);
        $this->assertFalse($this->object->isBlocking());
        $this->assertTrue($this->object->getOptions()->isAuthenticated());
    }

}
