<?php
/**
 * This file is part of the WebDav package.
 *
 * (c) Geoffroy Letournel <geoffroy.letournel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Grale\WebDav\Exception;


use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * @covers Grale\WebDav\Exception\HttpException
 */
class HttpExceptionTest extends TestCase
{
    protected $request;

    public function setUp() : void
    {
        $this->request = new Request('GET','/container/',[
            "Host"=>"www.foo.bar"
        ]);
    }

    public function testClientFailureException()
    {
        $response = new Response(400);

        $httpException = RequestException::create(
            $this->request,
            $response
        );

        $this->assertInstanceOf(ClientException::class, $httpException);
        $this->assertEquals($this->request, $httpException->getRequest());
        $this->assertEquals($response, $httpException->getResponse());
        $this->assertEquals(400, $httpException->getResponse()->getStatusCode());
    }

    public function testServerFailureException()
    {
        $response = new Response(500);

        $httpException = RequestException::create(
            $this->request,
            $response
        );

        $this->assertInstanceOf(ServerException::class, $httpException);
        $this->assertEquals($this->request, $httpException->getRequest());
        $this->assertEquals($response, $httpException->getResponse());
        $this->assertEquals(500, $httpException->getResponse()->getStatusCode());
    }

    /**
     * @dataProvider getErrorMapping
     * @param string $httpMethod
     * @param int    $statusCode
     * @param string $description
     */
    public function testErrorDescriptions($httpMethod, $statusCode, $description)
    {
        $request = new Request($httpMethod,'/container/',[
			'Host'=>'www.foo.bar'
		]);

        $response = new Response($statusCode);

        $httpException = BadResponseException::create($request, $response);

        $this->assertEquals($description, $httpException->getMessage());
    }

    public function getErrorMapping()
    {
        return array(
            array('MOVE', 403, 'Source and destination URIs are the same'),
            array('MOVE', 409, 'One or more parent collections are not found'),
            array('MOVE', 412, 'The server was unable to maintain the availability of the properties'),
            array('MOVE', 423, 'The source or the destination resource was locked'),
            array('MOVE', 502, 'The destination server refuses to accept the resource'),
            array('COPY', 403, 'Source and destination URIs are the same'),
            array('COPY', 409, 'One or more parent collections are not found'),
            array('COPY', 412, 'The server was unable to maintain the availability of the properties'),
            array('COPY', 423, 'The destination resource was locked'),
            array('COPY', 502, 'The destination server refuses to accept the resource'),
            array('COPY', 507, 'Insufficient storage'),
            array('LOCK', 412, 'The lock token could not be enforced'),
            array('LOCK', 423, 'The resource is already locked'),
            array('MKCOL', 403, 'Permissions denied'),
            array('MKCOL', 405, 'The resource already exists'),
            array('MKCOL', 409, 'Cannot create a resource if all ancestors do not already exist'),
            array('MKCOL', 415, 'The server does not support the request type of the body'),
            array('MKCOL', 507, 'Insufficient storage'),
            array('PROPPATCH', 403, 'Properties cannot be set or removed'),
            array('PROPPATCH', 409, 'Cannot set property to value provided'),
            array('PROPPATCH', 423, 'The resource is locked'),
            array('PROPPATCH', 507, 'Insufficient storage')
        );
    }
}
