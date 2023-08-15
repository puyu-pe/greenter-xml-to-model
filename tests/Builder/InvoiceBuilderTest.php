<?php
/**
 * Created by PhpStorm.
 * User: Velnae.28
 * Date: 14/08/2023
 * Time: 18:47.
 */

declare(strict_types=1);

namespace Tests\Builder;

use Puyu\GreenterXmlToModel\Builder\InvoiceBuilder;
use PHPUnit\Framework\TestCase;

class InvoiceBuilderTest extends TestCase
{
    public function testInvoiceBuilder()
    {
        $xmlData = file_get_contents(__DIR__ . '/../Resource/invoice2.1-03-bonif-zero.xml');

        // Create an instance of the InvoiceFactory
        $invoiceFactory = new InvoiceBuilder();

        // Call the createFromXml method
        $invoice = $invoiceFactory->build($xmlData);

        // Assertions
        $this->assertInstanceOf(\Greenter\Model\Sale\Invoice::class, $invoice);
//        $this->assertEquals('F001', $invoice->getSerie());
//        $this->assertEquals('123', $invoice->getNumero());
    }
}