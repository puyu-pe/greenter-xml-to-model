<?php
/**
 * Created by Intellij IDEA
 * User: Velnae.28
 * Date: 14/08/2023
 * Time: 18:47.
 */

namespace Puyu\GreenterXmlToModel\Util;

use Greenter\Model\Sale\Invoice;

class InvoiceBuild extends BaseSaleBuild
{
    public function __construct(object $document)
    {
        $this->document = $document;
    }

    /**
     * @throws \Exception
     */
    public function build(): Invoice
    {
        $invoice = $this->document;

        $fecVencimiento = $invoice->fecVencimiento ?? $invoice->fechaVencimiento ?? null;
        $fecVencimiento = $fecVencimiento ? new \DateTimeImmutable($fecVencimiento) : null;

        return (new Invoice())
            ->setUblVersion($invoice->ublVersion ?? '2.1')
            ->setFechaEmision(new \DateTimeImmutable($invoice->fechaEmision))
            ->setFecVencimiento($fecVencimiento)
            ->setTipoOperacion($invoice->tipoOperacion ?? '0101')
            ->setTipoDoc($invoice->tipoDoc)
            ->setSerie($invoice->serie)
            ->setCorrelativo((int)$invoice->correlativo)
            ->setMtoOperGravadas($invoice->mtoOperGravadas ?? null)
            ->setMtoOperExoneradas($invoice->mtoOperExoneradas ?? null)
            ->setMtoOperInafectas($invoice->mtoOperInafectas ?? null)
            ->setMtoIGV($invoice->mtoIGV ?? null)
            ->setIcbper($invoice->icbper ?? NULL)
            ->setTotalImpuestos($invoice->totalImpuestos ?? $invoice->totalImpuesto)
            ->setValorVenta($invoice->valorVenta)
            ->setSubTotal($invoice->subTotal ?? $invoice->mtoImpVenta)
            ->setMtoImpVenta($invoice->mtoImpVenta)
            ->setSumDsctoGlobal($invoice->sumDsctoGlobal ?? null)
            ->setMtoOperGratuitas($invoice->mtoOperGratuitas ?? null)
            ->setMtoIGVGratuitas($invoice->mtoIGVGratuitas ?? null)
            ->setSumOtrosDescuentos($invoice->sumOtrosDescuentos ?? null)
            ->setSumOtrosCargos($invoice->sumOtrosCargos ?? null)
            ->setMtoDescuentos($invoice->mtoDescuentos ?? null)
            ->setTotalAnticipos($invoice->totalAnticipos ?? null)
            ->setMtoBaseIsc($invoice->mtoBaseIsc ?? null)
            ->setMtoOperExportacion($invoice->mtoOperExportacion ?? null)
            ->setMtoISC($invoice->mtoISC ?? null)
            ->setMtoBaseOth($invoice->mtoBaseOth ?? null)
            ->setMtoOtrosTributos($invoice->mtoOtrosTributos ?? null)
            ->setRedondeo($invoice->redondeo ?? null)
            ->setObservacion($invoice->observacion ?? null)
            ->setCompra($invoice->compra ?? null)
            ->setMtoBaseIvap($invoice->mtoBaseIvap ?? null)
            ->setMtoIvap($invoice->mtoIvap ?? null)
            ->setPerception($this->getPerception($invoice->perception ?? null))
            ->setGuias($this->getGuias($invoice->guias ?? null))
            ->setRelDocs($this->getRelDocs($invoice->relDocs ?? null))
            ->setAnticipos($this->getAnticipos($invoice->anticipos ?? null))
            ->setDetraccion($this->getDetraccion($invoice->detraccion ?? null))
            ->setFormaPago($this->getFormaPago($invoice->formaPago ?? null))
            ->setTipoMoneda($this->getTipoMoneda($invoice->tipoMoneda ?? null))
            ->setCuotas($this->getCuotas($invoice->cuotas ?? null))
            ->setClient($this->getClient($invoice->client))
            ->setSeller($this->getSeller($invoice->seller ?? null))
            ->setDescuentos($this->getDescuentos($invoice->descuentos ?? null))
            ->setCargos($this->getCargos($invoice->cargos ?? null))
            ->setLegends($this->getLegends($invoice->legends ?? null))
            ->setGuiaEmbebida($this->getGuiaEmbebida($invoice->guiaEmbebida ?? null))
            ->setDetails($this->getDetails($invoice->details))
            ->setCompany($this->getCompany($invoice->company));
    }
}
