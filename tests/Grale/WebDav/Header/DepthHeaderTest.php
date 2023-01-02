<?php
/**
 * This file is part of the WebDav package.
 *
 * (c) Geoffroy Letournel <geoffroy.letournel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Grale\WebDav\Header;

use PHPUnit\Framework\TestCase;

/**
 * @covers Grale\WebDav\Header\DepthHeader
 */
class DepthHeaderTest extends TestCase
{
    /**
     * @dataProvider getHeaders
     */
    public function testParseHeader($header, $value)
    {
        $this->assertEquals($value, DepthHeader::parse($header));
    }

    public function getHeaders()
    {
        return array(
            array('Infinity', -1),
            array('infinity', -1),
            array('0', 0)
        );
    }
}
