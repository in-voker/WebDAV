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
            array('MOVE', 403, 'Client error: `MOVE /container/` resulted in a `403 Forbidden` response'),
            array('MOVE', 409, 'Client error: `MOVE /container/` resulted in a `409 Conflict` response'),
            array('MOVE', 412, 'Client error: `MOVE /container/` resulted in a `412 Precondition Failed` response'),
            array('MOVE', 423, 'Client error: `MOVE /container/` resulted in a `423 Locked` response'),
            array('MOVE', 502, 'Server error: `MOVE /container/` resulted in a `502 Bad Gateway` response'),
            array('COPY', 403, 'Client error: `COPY /container/` resulted in a `403 Forbidden` response'),
            array('COPY', 409, 'Client error: `COPY /container/` resulted in a `409 Conflict` response'),
            array('COPY', 412, 'Client error: `COPY /container/` resulted in a `412 Precondition Failed` response'),
            array('COPY', 423, 'Client error: `COPY /container/` resulted in a `423 Locked` response'),
            array('COPY', 502, 'Server error: `COPY /container/` resulted in a `502 Bad Gateway` response'),
            array('COPY', 507, 'Server error: `COPY /container/` resulted in a `507 Insufficient Storage` response'),
            array('LOCK', 412, 'Client error: `LOCK /container/` resulted in a `412 Precondition Failed` response'),
            array('LOCK', 423, 'Client error: `LOCK /container/` resulted in a `423 Locked` response'),
            array('MKCOL', 403, 'Client error: `MKCOL /container/` resulted in a `403 Forbidden` response'),
            array('MKCOL', 405, 'Client error: `MKCOL /container/` resulted in a `405 Method Not Allowed` response'),
            array('MKCOL', 409, 'Client error: `MKCOL /container/` resulted in a `409 Conflict` response'),
            array('MKCOL', 415, 'Client error: `MKCOL /container/` resulted in a `415 Unsupported Media Type` response'),
            array('MKCOL', 507, 'Server error: `MKCOL /container/` resulted in a `507 Insufficient Storage` response'),
            array('PROPPATCH', 403, 'Client error: `PROPPATCH /container/` resulted in a `403 Forbidden` response'),
            array('PROPPATCH', 409, 'Client error: `PROPPATCH /container/` resulted in a `409 Conflict` response'),
            array('PROPPATCH', 423, 'Client error: `PROPPATCH /container/` resulted in a `423 Locked` response'),
            array('PROPPATCH', 507, 'Server error: `PROPPATCH /container/` resulted in a `507 Insufficient Storage` response')
        );
    }
}
