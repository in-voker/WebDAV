<?php
namespace Grale\WebDav\Header\TimeoutValue;

use PHPUnit\Framework\TestCase;

/**
 *
 * @author samizdam
 *        
 */
class GMPValueTest extends TestCase
{

    public function testToString()
    {
        if (! extension_loaded('gmp')) {
            $this->markTestSkipped("For test GMPValue class enable gmp extension. ");
        }
        $value = new GMPValue(4100000000);
        $this->assertEquals("4100000000", $value);
    }
}