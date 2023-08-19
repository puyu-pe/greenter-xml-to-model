<?php
/**
 * Created by Intellij IDEA
 * User: Velnae.28
 * Date: 14/08/2023
 * Time: 18:47.
 */

namespace Puyu\GreenterXmlToModel\Util;

use Greenter\Model\Client\Client;
use Greenter\Model\Despatch\Direction;
use Greenter\Model\Sale\Charge;
use Greenter\Model\Sale\Cuota;
use Greenter\Model\Sale\Detraction;
use Greenter\Model\Sale\Document;
use Greenter\Model\Sale\EmbededDespatch;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\PaymentTerms;
use Greenter\Model\Sale\Prepayment;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Model\Sale\SalePerception;

abstract class BaseSaleBuild extends DocumentBuild
{
    public function getFormaPago($formaPago): ?PaymentTerms
    {
        if (!$formaPago)
            return new FormaPagoContado();

        return (new PaymentTerms())
            ->setMoneda($formaPago->moneda)
            ->setTipo($formaPago->tipo)
            ->setMonto($formaPago->monto);
    }

    public function getCuotas($cuotas): ?array
    {
        if (!$cuotas)
            return null;

        $new_cuotas = [];

        for ($i = 0; $i < count($cuotas); $i++) {
            $new_cuotas[] = (new Cuota())
                ->setMoneda($cuotas[$i]->moneda)
                ->setMonto($cuotas[$i]->monto)
                ->setFechaPago(new \Datetime($cuotas[$i]->fechaPago));
        }

        return $new_cuotas;
    }

    public function getTipoMoneda($tipoMoneda): string
    {
        if (!$tipoMoneda)
            return DocumentBuild::CURRENCY_DEFAULT;

        return $tipoMoneda;
    }

    public function getSeller($seller): ?Client
    {
        if (!$seller)
            return null;

        return $this->getClient($seller);
    }

    /**
     * @param $details
     * @param bool $strip_tags
     * @return SaleDetail[]
     */
    public function getDetails($details, bool $strip_tags = true): array
    {
        $new_details = [];
        for ($i = 0; $i < count($details); $i++) {
            $detail = $details[$i];
            $description = $detail->descripcion;

            if ($strip_tags) {
                $description = strip_tags($description);
                $description = str_replace('&nbsp;', ' ', $description);
                $description = str_replace('\t', ' ', $description);
            }

            $new_details[] = (new SaleDetail())
                ->setCodProducto($detail->codProducto)
                ->setUnidad($detail->unidad)
                ->setDescripcion($description)
                ->setCantidad($detail->cantidad)
                ->setMtoValorUnitario($detail->mtoValorUnitario) //valor del item sin igv
                ->setMtoValorGratuito($detail->mtoValorGratuito ?? null)
                ->setMtoValorVenta($detail->mtoValorVenta) //valor unitario sin igv * cantidad
                ->setMtoBaseIgv($detail->mtoBaseIgv ?? null) //monto al que sele aplica el igv
                ->setPorcentajeIgv($detail->porcentajeIgv ?? null) // porcentage igv 18 u otro en caso de ser inafecta o gratuita
                ->setIgv($detail->igv ?? null) // total del igv calculado
                ->setIcbper($detail->icbper ?? null) // (cantidad)*(factor ICBPER)
                ->setFactorIcbper($detail->factorIcbper ?? null)
                ->setTipAfeIgv($detail->tipAfeIgv ?? null) // catalogo Nº 07
                ->setTotalImpuestos($detail->totalImpuestos ?? $detail->totalImpuesto) // igv + isc e IVAP de corresponder
                ->setMtoPrecioUnitario($detail->mtoPrecioUnitario ?? null)
                ->setAtributos($detail->atributos ?? [])
                ->setMtoBaseIsc($detail->mtoBaseIsc ?? null)
                ->setPorcentajeIsc($detail->porcentajeIsc ?? null)
                ->setIsc($detail->isc ?? null)
                ->setTipSisIsc($detail->tipSisIsc ?? null)
                ->setAtributos($this->getAtributos($detail->atributos ?? null))
                ->setCodProdSunat($detail->codProdSunat ?? null)
                ->setCodProdGS1($detail->codProdGS1 ?? null)
                ->setCargos($this->getCargos($detail->cargos ?? null))
                ->setDescuentos($this->getDescuentos($detail->descuentos ?? null))
                ->setDescuento($detail->descuento ?? null)
                ->setMtoBaseOth($detail->mtoBaseOth ?? null)
                ->setPorcentajeOth($detail->porcentajeOth ?? null)
                ->setOtroTributo($detail->otroTributo ?? null);
        }

        return $new_details;
    }

    /**
     * @param $descuentos
     * @return Charge[]|null
     */
    public function getDescuentos($descuentos): ?array
    {
        if (!$descuentos)
            return null;

        return $this->arrayCharge($descuentos);
    }


    /**
     * @param $cargos
     * @return Charge[]|null
     */
    public function getCargos($cargos): ?array
    {
        if (!$cargos)
            return null;

        return $this->arrayCharge($cargos);
    }

    /**
     * @param $legends
     * @return Legend[]]|null
     */
    public function getLegends($legends): ?array
    {
        if (!$legends)
            return null;

        $new_legends = [];

        for ($i = 0; $i < count($legends); $i++) {
            $legend = $legends[$i];
            $item = (new Legend())
                ->setCode($legend->code)
                ->setValue($legend->value);
            $new_legends[] = $item;
        }

        return $new_legends;
    }

    /**
     * @param $charges
     * @return array
     *
     * funcion auxilar para convertir un array de charge en un array de Charge
     */
    public function arrayCharge($charges): array
    {
        $new_charges = [];

        for ($i = 0; $i < count($charges); $i++) {
            $charge = $charges[$i];

            $item = (new Charge())
                ->setCodTipo($charge->codTipo)
                ->setMontoBase($charge->montoBase)
                ->setFactor($charge->factor)
                ->setMonto($charge->monto);

            $new_charges[] = $item;
        }

        return $new_charges;
    }

    public function getPerception($perception): ?SalePerception
    {
        if (!$perception)
            return null;

        return (new SalePerception())
            ->setCodReg($perception->codReg)
            ->setPorcentaje($perception->porcentaje)
            ->setMto($perception->mto)
            ->setMtoBase($perception->mtoBase)
            ->setMtoTotal($perception->mtoTotal);
    }

    public function getGuiaEmbebida($guiaEmbebida): ?EmbededDespatch
    {
        if (!$guiaEmbebida)
            return null;

        return (new EmbededDespatch())
            ->setLlegada(new Direction($guiaEmbebida->llegada->ubigueo, $guiaEmbebida->llegada->direccion))
            ->setPartida(new Direction($guiaEmbebida->partida->ubigueo, $guiaEmbebida->partida->direccion))
            ->setTransportista(
                (new Client())
                    ->setTipoDoc($guiaEmbebida->transportista->tipoDoc)
                    ->setNumDoc($guiaEmbebida->transportista->numDoc)
                    ->setRznSocial($guiaEmbebida->transportista->rznSocial)
            )->setNroLicencia($guiaEmbebida->nroLicencia)
            ->setTranspPlaca($guiaEmbebida->transpPlaca)
            ->setTranspCodeAuth($guiaEmbebida->transpCodeAuth)
            ->setTranspMarca($guiaEmbebida->transpMarca)
            ->setModTraslado($guiaEmbebida->modTraslado)
            ->setUndPesoBruto($guiaEmbebida->undPesoBruto)
            ->setPesoBruto($guiaEmbebida->pesoBruto);
    }

    /**
     * @param $guias
     * @return Document[]|null
     */
    public function getGuias($guias): ?array
    {
        return $this->getDocuments($guias);
    }

    /**
     * @param $relDocs
     * @return Document[]|null
     */
    public function getRelDocs($relDocs): ?array
    {
        return $this->getDocuments($relDocs);
    }

    /**
     * @param $documents
     * @return Document[]|null
     */
    public function getDocuments($documents): ?array
    {
        if (!$documents)
            return null;

        $new_documents = [];

        for ($i = 0; $i < count($documents); $i++) {
            $new_documents[] = (new Document())
                ->setTipoDoc($documents[$i]->tipoDoc)
                ->setNroDoc($documents[$i]->nroDoc);
        }

        return $new_documents;
    }

    /**
     * @param $anticipos
     * @return Legend[]]|null
     */
    public function getAnticipos($anticipos): ?array
    {
        if (!$anticipos)
            return null;

        $new_anticipos = [];

        for ($i = 0; $i < count($anticipos); $i++) {
            $new_anticipos[] = (new Prepayment())
                ->setTotal($anticipos[$i]->total)
                ->setTipoDocRel($anticipos[$i]->tipoDocRel)
                ->setNroDocRel($anticipos[$i]->nroDocRel);
        }

        return $new_anticipos;
    }


    public function getDetraccion($detraccion): ?Detraction
    {
        if (!$detraccion)
            return null;

        return (new Detraction())
            ->setMount($detraccion->mount)
            ->setPercent($detraccion->percent)
            ->setValueRef($detraccion->valueRef);
    }
}
