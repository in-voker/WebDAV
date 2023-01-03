<?php
/**
 * This file is part of the WebDav package.
 *
 * (c) Geoffroy Letournel <geoffroy.letournel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Grale\WebDav;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \Grale\WebDav\Client
 */
class WebDavClientTest extends TestCase
{

    public function testBaseUrl()
    {
        $client = new WebDavClient('http://www.foo.bar');
        $this->assertEquals('http://www.foo.bar', $client->getBaseUrl());
    }

    public function testConfig()
    {
        $client = new WebDavClient('', array(
            'base_url' => 'http://www.foo.bar',
            'auth' => array(
                'user',
                'pass',
                'Basic'
            ),
            'user_agent' => 'my/custom/agent'
        ));
        
        $mock = $this->getHttpClientMock(new Response(200));
        $client->setHttpClient($mock);
        
        $this->assertEquals('http://www.foo.bar', $client->getBaseUrl());

		try
		{
			$client->get('http://www.foo.bar/resource');
		} catch (ConnectException $e)
		{// foo.bar is not valid
		}
        $request = $client->getLastRequest();

        $this->assertContains('my/custom/agent', $request->getHeader('User-Agent'));
        $this->assertContains('Basic dXNlcjpwYXNz', $request->getHeader('Authorization'));
    }
    
    // /////////////////////////////////////////
    // ///////////// OPTIONS Method ////////////
    // /////////////////////////////////////////
    public function testGetComplianceClasses()
    {
        $response = new Response(200, array(
            'Dav' => '1, 2, <http://apache.org/dav/propset/fs/1>'
        ));
        
        $client = new WebDavClient('http://www.example.com');
        $client->setHttpClient($this->getHttpClientMock($response));
        
        $result = $client->getComplianceClasses();
        
        $this->assertEquals(array(
            '1',
            '2',
            '<http://apache.org/dav/propset/fs/1>'
        ), $result);
    }

    public function testGetSupportedMethods()
    {
        $response = new Response(200, array(
            'Allow' => 'GET, POST, MKCOL, PROPFIND'
        ));
        
        $client = new WebDavClient('http://www.example.com');
        $client->setHttpClient($this->getHttpClientMock($response));
        
        $result = $client->getSupportedMethods();
        
        $this->assertEquals(array(
            'GET',
            'POST',
            'MKCOL',
            'PROPFIND'
        ), $result);
    }
    
    // /////////////////////////////////////////
    // ///////////// HEAD Method ///////////////
    // /////////////////////////////////////////
    public function testExists()
    {
        $client = new WebDavClient('http://www.example.com');
        $client->setHttpClient($this->getHttpClientMock(new Response(200)));
        
        $this->assertTrue($client->exists('/resource'));
    }

    public function testNotExist()
    {
        $client = new WebDavClient('http://www.example.com');
        $client->setHttpClient($this->getHttpClientMock(new Response(404)));
        
        $this->assertFalse($client->exists('/resource'));
    }
    
    // /////////////////////////////////////////
    // ///////////// PUT Method ////////////////
    // /////////////////////////////////////////
    public function testPutWithLockToken()
    {
        $client = new WebDavClient('http://www.example.com');
        $client->setHttpClient($this->getHttpClientMock(new Response(201)));
        
        $client->put('resource', 'Hello World', array(
            'locktoken' => 'opaquelocktoken:e71d4fae-5dec-22d6-fea5-00a0c91e6be4'
        ));
        
        $request = $client->getLastRequest();
        $this->assertEquals('(<opaquelocktoken:e71d4fae-5dec-22d6-fea5-00a0c91e6be4>)', $request->getHeaderLine("If"));
    }

    public function testPutSuccessfully()
    {
        $client = new WebDavClient('http://www.example.com');
        $client->setHttpClient($this->getHttpClientMock(new Response(201)));
        $this->assertTrue($client->put('resource', 'data'));
    }

    public function testPutFailed()
    {
        $client = new WebDavClient('http://www.example.com');
        $client->setHttpClient($this->getHttpClientMock(new Response(423)));
        $this->assertFalse($client->put('resource', 'data'));
    }
    
    // /////////////////////////////////////////
    // ///////////// DELETE Method /////////////
    // /////////////////////////////////////////
    public function testDeleteSuccessfully()
    {
        $client = new WebDavClient('http://www.example.com');
        $client->setHttpClient($this->getHttpClientMock(new Response(204)));
        
        $result = $client->delete('/container/');
        $status = $client->getLastResponseStatus();
        
        $this->assertTrue($result);
        $this->assertEquals(204, $status, 'Failed asserting that the status-code equals to 204 (No Content)');
    }

    /**
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.6.2.1
     */
    public function testDeleteLockedResourceWithFailure()
    {
        $client = new WebDavClient('http://www.foo.bar');
        $client->setHttpClient($this->getHttpClientMock($this->getFixture('response.delete-failed')));
        
        $result = $client->delete('/container/');
        $status = $client->getLastResponseStatus();
        
        $this->assertFalse($result);
        $this->assertEquals(207, $status, 'Failed asserting that the status-code equals to 207 (Multi-Status)');
    }
    
    // /////////////////////////////////////////
    // ///////////// MKCOL Method //////////////
    // /////////////////////////////////////////
    public function testMkcol()
    {
        $client = new WebDavClient('http://www.server.org');
        $client->setHttpClient($this->getHttpClientMock(new Response(201)));
        
        $result = $client->mkcol('/webdisc/xfiles');
        $status = $client->getLastResponseStatus();
        
        $this->assertTrue($result, 'Failed asserting that the collection was created');
        $this->assertEquals(201, $status, 'Failed asserting that the status-code equals to 201 (Created)');
    }

    /**
     * @dataProvider getMkcolBadResponses
     *
     * @param int $status
     *            The expected HTTP status code
     * @param string $class
     *            The expected exception class
     * @param string $message
     *            The expected exception message
     */
    public function testMkcolBadResponses($status, $class, $message)
    {
        $this->expectException('GuzzleHttp' . '\\' . $class);
		$this->expectExceptionMessage($message);
        
        $client = new WebDavClient('http://www.foo.bar');
        $client->setHttpClient($this->getHttpClientMock(new Response($status)));
        $client->setThrowExceptions();
        $client->mkcol('/resource');
    }

    public function getMkcolBadResponses()
    {
        return array(
            array(
                403,
                'Exception\ClientException',
                'Forbidden'
            ),
            array(
                405,
                'Exception\ClientException',
                'Method Not Allowed'
            ),
            array(
                409,
                'Exception\ClientException',
                'Conflict'
            ),
            array(
                415,
                'Exception\ClientException',
                'Unsupported Media Type'
            ),
            array(
                507,
                'Exception\ServerException',
                'Insufficient Storage'
            )
        );
    }
    
    // /////////////////////////////////////////
    // ///////////// MOVE Method ///////////////
    // /////////////////////////////////////////
    
    /**
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.9.5
     */
    public function testMoveRegularFile()
    {
        $response = new Response(201, array(
            'Location' => 'http://www.ics.uci.edu/users/f/fielding/index.html'
        ));
        
        $client = new WebDavClient('http://www.ics.uci.edu');
        $client->setHttpClient($this->getHttpClientMock($response));
        
        $result = $client->move('/~fielding/index.html', '/users/f/fielding/index.html');
        $status = $client->getLastResponseStatus();
        $headers = $client->getLastResponseHeaders();
        $request = $client->getLastRequest();
        
        $this->assertTrue($result);
        $this->assertEquals(201, $status, 'Failed asserting that the status-code equals to 201 (Created)');
        $this->assertTrue(isset($headers['Location']), 'Failed asserting that response contains the "location" header');
        $this->assertContains('http://www.ics.uci.edu/users/f/fielding/index.html', $headers['Location']);
        
        //$this->assertContains('MOVE /~fielding/index.html HTTP/1.1', $request);
        $this->assertContains('http://www.ics.uci.edu/users/f/fielding/index.html', $request->getHeader('Destination'));
    }

    /**
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.9.6
     */
    public function testMoveLockedCollection()
    {
        $client = new WebDavClient('http://www.foo.bar');
        $client->setHttpClient($this->getHttpClientMock($this->getFixture('response.move-locked-collection')));
        
        $result = $client->move('/container/', '/othercontainer/', array(
            'recursive' => true,
            'overwrite' => false,
            'locktoken' => array(
                'opaquelocktoken:fe184f2e-6eec-41d0-c765-01adc56e6bb4',
                'opaquelocktoken:e454f3f3-acdc-452a-56c7-00a5c91e4b77'
            )
        ));
        $status = $client->getLastResponseStatus();
        $request = $client->getLastRequest();
        
        $this->assertFalse($result);
        $this->assertEquals(207, $status, 'Failed asserting that the status-code equals to 207 (Multi-Status)');
        
        $this->assertStringContainsString('MOVE /container/ HTTP/1.1', Message::toString($request));
		$this->assertContains('F', $request->getHeader('Overwrite'));
		$this->assertContains('http://www.foo.bar/othercontainer/', $request->getHeader('Destination'));
        $this->assertContains('(<opaquelocktoken:fe184f2e-6eec-41d0-c765-01adc56e6bb4>)' . ' (<opaquelocktoken:e454f3f3-acdc-452a-56c7-00a5c91e4b77>)', $request->getHeader('If'));
    }

    /**
     * @dataProvider getMoveBadResponses
     *
     * @param int $status
     *            The expected HTTP status code
     * @param string $class
     *            The expected exception class
     * @param string $message
     *            The expected exception message
     */
    public function testMoveBadResponses($status, $class, $message)
    {
        $this->expectException('GuzzleHttp' . '\\' . $class);
		$this->expectExceptionMessage($message);
        
        $client = new WebDavClient('http://www.foo.bar');
        $client->setHttpClient($this->getHttpClientMock(new Response($status)));
        $client->setThrowExceptions();
        $client->move('/source', '/destination');
    }

    public function getMoveBadResponses()
    {
        return array(
            array(
                403,
                'Exception\ClientException',
                'Forbidden'
            ),
            array(
                409,
                'Exception\ClientException',
                'Conflict'
            ),
            array(
                412,
                'Exception\ClientException',
                'Precondition Failed'
            ),
            array(
                423,
                'Exception\ClientException',
                'Locked'
            ),
            array(
                502,
                'Exception\ServerException',
                'Bad Gateway'
            )
        );
    }
    
    // /////////////////////////////////////////
    // ///////////// COPY Method ///////////////
    // /////////////////////////////////////////
    
    /**
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.8.6
     */
    public function testCopyWithOverwrite()
    {
        $client = new WebDavClient('http://www.ics.uci.edu');
        $client->setHttpClient($this->getHttpClientMock(new Response(204)));
        
        $result = $client->copy('/~fielding/index.html', '/users/f/fielding/index.html');
        $status = $client->getLastResponseStatus();
        $request = $client->getLastRequest();
        
        $this->assertTrue($result);
        $this->assertEquals(204, $status, 'Failed asserting that the status-code equals to 204 (No Content)');
        $this->assertStringContainsString('COPY /~fielding/index.html HTTP/1.1', Message::toString($request));
		$this->assertContains('http://www.ics.uci.edu/users/f/fielding/index.html', $request->getHeader('Destination'));
        $this->assertContains('T', $request->getHeader('Overwrite'));
		$this->assertContains('0', $request->getHeader('Depth'));
    }

    /**
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.8.7
     */
    public function testCopyWithNoOverwrite()
    {
        $client = new WebDavClient('http://www.ics.uci.edu');
        $client->setHttpClient($this->getHttpClientMock(new Response(412)));
        
        $result = $client->copy('/~fielding/index.html', '/users/f/fielding/index.html', array(
            'overwrite' => false
        ));
        $status = $client->getLastResponseStatus();
        $request = $client->getLastRequest();
        
        $this->assertFalse($result);
        $this->assertEquals(412, $status, 'Failed asserting that the status-code equals to 412 (Precondition Failed)');
        $this->assertStringContainsString('COPY /~fielding/index.html HTTP/1.1', Message::toString($request));
		$this->assertContains('http://www.ics.uci.edu/users/f/fielding/index.html', $request->getHeader('Destination'));
        $this->assertContains('F', $request->getHeader('Overwrite'));
		$this->assertContains('0', $request->getHeader('Depth'));
    }

    /**
     *
     * @link http://tools.ietf.org/html/rfc4918#section-9.8.8
     */
    public function testCopyCollection()
    {
        $client = new WebDavClient('http://www.example.com');
        $client->setHttpClient($this->getHttpClientMock($this->getFixture('response.copy-collection')));
        
        $result = $client->copy('/container/', '/othercontainer/', array(
            'recursive' => true
        ));
        $status = $client->getLastResponseStatus();
        $request = $client->getLastRequest();
        
        $this->assertFalse($result);
        $this->assertEquals(207, $status, 'Failed asserting that the status-code equals to 207 (Multi-Status)');
        $this->assertStringContainsString('COPY /container/ HTTP/1.1', Message::toString($request));
		$this->assertContains('http://www.example.com/othercontainer/', $request->getHeader('Destination'));
        $this->assertContains('T', $request->getHeader('Overwrite'));
		$this->assertContains('Infinity', $request->getHeader('Depth'));
    }

    /**
     * @dataProvider getCopyBadResponses
     *
     * @param int $status
     *            The expected HTTP status code
     * @param string $class
     *            The expected exception class
     * @param string $message
     *            The expected exception message
     */
    public function testCopyBadResponses($status, $class, $message)
    {
        $this->expectException('GuzzleHttp'  . '\\' . $class);
		$this->expectExceptionMessage($message);
        
        $client = new WebDavClient('http://www.foo.bar');
        $client->setHttpClient($this->getHttpClientMock(new Response($status)));
        $client->setThrowExceptions();
        $client->copy('/container', '/othercontainer');
    }

    public function getCopyBadResponses()
    {
        return array(
            array(
                403,
                'Exception\ClientException',
                'Forbidden'
            ),
            array(
                409,
                'Exception\ClientException',
                'Conflict'
            ),
            array(
                412,
                'Exception\ClientException',
                'Precondition Failed'
            ),
            array(
                423,
                'Exception\ClientException',
                'Locked'
            ),
            array(
                502,
                'Exception\ServerException',
                'Bad Gateway'
            ),
            array(
                507,
                'Exception\ServerException',
                'Insufficient Storage'
            )
        );
    }
    
    // /////////////////////////////////////////
    // ///////////// PROPFIND Method ///////////
    // /////////////////////////////////////////
    
    /**
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.1.1
     */
    public function testPropfindRetrievingNamedProperties()
    {
        $client = new WebDavClient('http://www.foo.bar');
        $client->setHttpClient($this->getHttpClientMock($this->getFixture('response.propfind-named-props')));
        
        $client->xmlNamespaces['http://www.foo.bar/boxschema/'] = 'R';
        
        $properties = array(
            'R:bigbox',
            'R:author',
            'R:DingALing',
            'R:Random'
        );
        
        $result = $client->propfind('/file', array(
            'properties' => $properties
        ));
        $status = $client->getLastResponseStatus();
        $request = $client->getLastRequest();
        
        $xml = '<D:propfind xmlns:D="DAV:">' . '<D:prop xmlns:R="http://www.foo.bar/boxschema/">' . '<R:bigbox/>' . '<R:author/>' . '<R:DingALing/>' . '<R:Random/>' . '</D:prop>' . '</D:propfind>';
        
        $this->assertStringContainsString('PROPFIND /file HTTP/1.1', Message::toString($request));
		$this->assertContains('text/xml; charset="utf-8"', $request->getHeader('Content-Type'));
        $this->assertContains('0', $request->getHeader('Depth'));
        $this->assertStringContainsString($xml, Message::toString($request));
        
        $this->assertInstanceOf('Grale\\WebDav\\MultiStatus', $result);
        $this->assertEquals(207, $status, 'Failed asserting that the status-code equals to 207 (Multi-Status)');
    }

    /**
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.1.2
     */
    public function testPropfindUsingAllprop()
    {
        $client = new WebDavClient('http://www.foo.bar');
        $client->setHttpClient($this->getHttpClientMock($this->getFixture('response.propfind-allprop')));
        
        $result = $client->propfind('/container/', array(
            'depth' => 1
        ));
        $status = $client->getLastResponseStatus();
        $request = $client->getLastRequest();
        
        $xml = '<D:propfind xmlns:D="DAV:">' . '<D:allprop/>' . '</D:propfind>';
        
        $this->assertStringContainsString('PROPFIND /container/ HTTP/1.1', Message::toString($request));
		$this->assertContains('text/xml; charset="utf-8"', $request->getHeader('Content-Type'));
        $this->assertContains('1', $request->getHeader('Depth'));
        $this->assertStringContainsString($xml, Message::toString($request));
        
        $this->assertInstanceOf('Grale\\WebDav\\MultiStatus', $result);
        $this->assertEquals(207, $status, 'Failed asserting that the status-code equals to 207 (Multi-Status)');
    }
    
    // /////////////////////////////////////////
    // ///////////// LOCK Method ///////////////
    // /////////////////////////////////////////
    
    /**
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.10.8
     */
    public function testSimpleLockRequest()
    {
        $client = new WebDavClient('http://webdav.sb.aol.com');
        $client->setHttpClient($this->getHttpClientMock($this->getFixture('response.simple-lock')));
        
        $lock = $client->createLock('/workspace/webdav/proposal.doc', array(
            'scope' => 'exclusive',
            'owner' => 'http://www.ics.uci.edu/~ejw/contact.html',
            'timeout' => 4100000000
        ));
        
        $request = $client->getLastRequest();
        
        $this->assertInstanceOf('Grale\\WebDav\\Lock', $lock);
        $this->assertTrue($lock->isExclusive(), 'Failed asserting that created lock is an exclusive lock');
        
        $xml = '<D:lockinfo xmlns:D="DAV:">' . '<D:lockscope><D:exclusive/></D:lockscope>' . '<D:locktype><D:write/></D:locktype>' . '<D:owner>' . '<D:href>http://www.ics.uci.edu/~ejw/contact.html</D:href>' . '</D:owner>' . '</D:lockinfo>';
        
        $this->assertStringContainsString($xml, $request->getBody()->getContents());
        $this->assertContains('0', $request->getHeader('Depth'));

		$this->assertContains('Second-4100000000', $request->getHeader('Timeout'));
        $this->assertStringContainsString('LOCK /workspace/webdav/proposal.doc HTTP/1.1', Message::toString($request));
        
        $this->assertEquals('http://www.ics.uci.edu/~ejw/contact.html', $lock->getOwner());
        $this->assertEquals('opaquelocktoken:e71d4fae-5dec-22d6-fea5-00a0c91e6be4', $lock->getToken());
        $this->assertEquals(604800, $lock->getTimeout());
        $this->assertTrue($lock->isDeep());
    }

    /**
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.10.9
     */
    public function testRefreshingWriteLock()
    {
        $client = new WebDavClient('http://webdav.sb.aol.com');
        $client->setHttpClient($this->getHttpClientMock($this->getFixture('response.refreshing-write-lock')));
        
        $result = $client->refreshLock('/workspace/webdav/proposal.doc', 'opaquelocktoken:e71d4fae-5dec-22d6-fea5-00a0c91e6be4', 4100000000);
        
        $request = $client->getLastRequest();
        
        $this->assertInstanceOf('Grale\\WebDav\\Lock', $result);
        $this->assertEquals('opaquelocktoken:e71d4fae-5dec-22d6-fea5-00a0c91e6be4', $result->getToken());
        $this->assertEquals(604800, $result->getTimeout());
        $this->assertTrue($result->isDeep());

        $this->assertContains('Second-4100000000', $request->getHeader('Timeout'));
        $this->assertStringContainsString('LOCK /workspace/webdav/proposal.doc HTTP/1.1', Message::toString($request));
		$this->assertContains('(<opaquelocktoken:e71d4fae-5dec-22d6-fea5-00a0c91e6be4>)', $request->getHeader('If'));
    }

    /**
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.10.10
     *
	 *
	 */
    public function testMultiResourceLockRequest()
    {
		$this->expectExceptionMessage("Unexpected server response");
		$this->expectException(\RuntimeException::class);
		$client = new WebDavClient('http://webdav.sb.aol.com');
        $client->setHttpClient($this->getHttpClientMock($this->getFixture('response.multi-resource-lock')));
        
        $client->createLock('/webdav/', array(
            'scope' => 'exclusive',
            'owner' => 'http://www.ics.uci.edu/~ejw/contact.html',
            'timeout' => 4100000000
        ));
    }

    /**
     * @dataProvider getLockBadResponses
     *
     * @param int $status
     *            The expected HTTP status code
     * @param string $class
     *            The expected exception class
     * @param string $message
     *            The expected exception message
     */
    public function testLockBadResponses($status, $class, $message)
    {
        $this->expectException('GuzzleHttp'  . '\\' . $class);
		$this->expectExceptionMessage($message);
        
        $client = new WebDavClient('http://www.foo.bar');
        $client->setHttpClient($this->getHttpClientMock(new Response($status)));
        $client->setThrowExceptions();
        $client->createLock('/resource');
    }

    public function getLockBadResponses()
    {
        return array(
            array(
                412,
                'Exception\ClientException',
                'Precondition Failed'
            ),
            array(
                423,
                'Exception\ClientException',
                'Locked'
            )
        );
    }
    
    // /////////////////////////////////////////
    // ///////////// UNLOCK Method /////////////
    // /////////////////////////////////////////
    
    /**
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.11.1
     */
    public function testUnlock()
    {
        $client = new WebDavClient('http://webdav.sb.aol.com');
        $client->setHttpClient($this->getHttpClientMock(new Response(204)));
        
        $result = $client->releaseLock('/workspace/webdav/info.doc', 'opaquelocktoken:a515cfa4-5da4-22e1-f5b5-00a0451e6bf7');
        $status = $client->getLastResponseStatus();
        $request = $client->getLastRequest();
        
        $this->assertTrue($result);
        $this->assertEquals(204, $status, 'Failed asserting that the status-code equals to 204 (No Content)');
        $this->assertStringContainsString('UNLOCK /workspace/webdav/info.doc HTTP/1.1', Message::toString($request));
		$this->assertContains('<opaquelocktoken:a515cfa4-5da4-22e1-f5b5-00a0451e6bf7>', $request->getHeader('Lock-Token'));
    }

    /**
     * Mock objects and test fixtures *
     */
    
    /**
     *
     * @param string $name            
     * @param bool $asString            
     *
     * @throws \RuntimeException
     * @return Request|Response
     */
    protected function getFixture($name, $asString = false)
    {
        $fixtures = realpath(__DIR__ . '/../../fixtures');
        $filename = "{$fixtures}/{$name}.txt";
        
        if (! file_exists($filename)) {
            throw new \RuntimeException('Could not load test fixture');
        }
        
        $contents = file_get_contents($filename);
        
        if (! $asString) {
            if (substr($name, 0, 7) == 'request') {
                $contents = Message::parseRequest($contents);
            } elseif (substr($name, 0, 8) == 'response') {
                $contents = Message::parseResponse($contents);
            }
        }
        
        return $contents;
    }

    /**
     *
     * @param Response $response
     * @return \GuzzleHttp\Client
     */
    protected function getHttpClientMock(Response $response)
    {
        $client = $this->getMockBuilder('\GuzzleHttp\Client')
            ->setMethods(array('send','transfer'))
            ->getMock();
		$level = (int) \floor($response->getStatusCode() / 100);
        if ($level == 4 || $level == 5) {
            $request = $this->getMockBuilder(Request::class)
                ->disableOriginalConstructor()
                ->getMock();
           $e = RequestException::create( $request, $response);
            $client->expects($this->any())
                ->method('send')
                ->will($this->throwException($e));
        } else {
            $client->expects($this->any())
                ->method('send')
                ->will($this->returnValue($response));
        }
        
        return $client;
    }
}
