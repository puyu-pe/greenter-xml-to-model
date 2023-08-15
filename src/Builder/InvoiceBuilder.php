<?php
/**
 * Created by PhpStorm.
 * User: Velnae.28
 * Date: 14/08/2023
 * Time: 18:47.
 */

declare(strict_types=1);

namespace Puyu\GreenterXmlToModel\Builder;

use Greenter\Model\DocumentInterface;
use Greenter\Model\Sale\Invoice;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class InvoiceBuilder implements BuilderInterface
{

    public function build(string $xml): ?DocumentInterface
    {
        $encoder = new XmlEncoder();
        $xmlContent = $encoder->decode($xml, 'xml');
        $invoice = [];
        $invoice['ublVersion'] = $xmlContent['cbc:UBLVersionID'];
        $serie_correlativo = explode('-', $xmlContent['cbc:ID']);
        $invoice['serie'] = $serie_correlativo[0];
        $invoice['correlativo'] = $serie_correlativo[1];
        $invoice['fechaEmision'] = $xmlContent['cbc:IssueDate'] . " " . $xmlContent['cbc:IssueTime'];
        $invoice['fecVencimiento'] = $xmlContent['cbc:DueDate'] ?? null;
        $invoice['tipoOperacion'] = $xmlContent["cbc:InvoiceTypeCode"]["@listID"] ?? null;
        $invoice['tipoDoc'] = $xmlContent["cbc:InvoiceTypeCode"]["#"] ?? null;

        $legends = $xmlContent['cbc:Note'] ?? null;
        $legends = isset($legends[0]) ? $legends : [$legends];
        $invoice['legends'] = $invoice['observacion'] = null;

        foreach ($legends as $item) {
            if (isset($item['@languageLocaleID']))
                $invoice['legends'][] = [
                    'code' => $item['@languageLocaleID'],
                    'value' => $item["#"],
                ];

            else
                $invoice['observacion'] = $item ?? null;

            unset($item);
        }

        $invoice['tipoMoneda'] = $xmlContent['cbc:DocumentCurrencyCode'];
        $invoice['compra'] = $xmlContent['cac:OrderReference']['cbc:ID'] ?? null;

        $guias = $xmlContent['cac:DespatchDocumentReference'] ?? null;
        $invoice['guias'] = null;

        if ($guias) {
            $guias = isset($guias[0]) ? $guias : [$guias];
            $invoice['guias'] = array_map(function ($item) {
                return [
                    'nroDoc' => $item['cbc:ID'],
                    'tipoDoc' => $item['cbc:DocumentTypeCode'],
                ];
            }, $guias);
        }

        $relDocs = $xmlContent['cac:AdditionalDocumentReference'] ?? null;
        $invoice['relDocs'] = $invoice['anticipos'] = null;

        if ($relDocs) {
            $relDocs = isset($relDocs[0]) ? $relDocs : [$relDocs];

            foreach ($relDocs as $item) {
                if (isset($item['DocumentStatusCode']))
                    $invoice['anticipos'][] = [
                        'nroDocRel' => $item['cbc:ID'],
                        'tipoDocRel' => $item['cbc:DocumentTypeCode'],
                    ];
                else
                    $invoice['relDocs'][] = [
                        'nroDoc' => $item['cbc:ID'],
                        'tipoDoc' => $item['cbc:DocumentTypeCode'],
                    ];
            }
        }

        $company = $xmlContent["cac:AccountingSupplierParty"]["cac:Party"];
        $invoice['company']['ruc'] = $company["cac:PartyIdentification"]["cbc:ID"]['#'];
        $invoice['company']['razonSocial'] = $company["cac:PartyLegalEntity"]["cbc:RegistrationName"];
        $invoice['company']['nombreComercial'] = $company["cac:PartyName"]["cbc:Name"] ?? null;

        $address = $company["cac:PartyLegalEntity"]["cac:RegistrationAddress"];
        $invoice['company']['address']['ubigueo'] = $address["cbc:ID"];
        $invoice['company']['address']['codLocal'] = $address["cbc:AddressTypeCode"];
        $invoice['company']['address']['urbanizacion'] = $address["cbc:CitySubdivisionName"] ?? null;
        $invoice['company']['address']['provincia'] = $address["cbc:CityName"];
        $invoice['company']['address']['departamento'] = $address["cbc:CountrySubentity"];
        $invoice['company']['address']['distrito'] = $address["cbc:District"];
        $invoice['company']['address']['direccion'] = $address["cac:AddressLine"]["cbc:Line"];
        $invoice['company']['address']['codigoPais'] = $address["cac:Country"]["cbc:IdentificationCode"];

        $contact = $company["cac:Contact"] ?? null;
        if ($contact) {
            $invoice['company']['telephone'] = $company["cac:Contact"]['cbc:Telephone'] ?? null;
            $invoice['company']['email'] = $company["cac:Contact"]['cbc:ElectronicMail'] ?? null;
        }

        $client = $xmlContent["cac:AccountingCustomerParty"]["cac:Party"];
        $invoice['client']['ruc'] = $client["cac:PartyIdentification"]["cbc:ID"]['#'];
        $invoice['client']['razonSocial'] = $client["cac:PartyLegalEntity"]["cbc:RegistrationName"];

        $address = $client["cac:PartyLegalEntity"]["cac:RegistrationAddress"] ?? null;
        if ($address) {
            $invoice['client']['address']['ubigueo'] = $address["cbc:ID"] ?? null;
            $invoice['client']['address']['direccion'] = $address["cac:AddressLine"]["cbc:Line"];
            $invoice['client']['address']['codigoPais'] = $address["cac:Country"]["cbc:IdentificationCode"];
        }

        $contact = $client["cac:Contact"] ?? null;
        if ($contact) {
            $invoice['client']['telephone'] = $client["cac:Contact"]['cbc:Telephone'] ?? null;
            $invoice['client']['email'] = $client["cac:Contact"]['cbc:ElectronicMail'] ?? null;
        }

        $seller = $company["cac:SellerSupplierParty"]["cac:Party"] ?? null;

        if ($seller) {
            $invoice['seller']['tipoDoc'] = $seller["cac:PartyIdentification"]['cbc:ID']['@schemeID'];
            $invoice['seller']['numDoc'] = $seller["cac:PartyIdentification"]['cbc:ID']['#'];
            $invoice['seller']['rznSocial'] = $seller["cac:PartyLegalEntity"]['cbc:RegistrationName'];
        }

        $address = $seller["cac:PartyLegalEntity"]['cac:RegistrationAddress'] ?? null;
        if ($address) {
            $invoice['seller']['address']['ubigueo'] = $address["cbc:ID"] ?? null;
            $invoice['seller']['address']['direccion'] = $address["cac:AddressLine"]["cbc:Line"];
            $invoice['seller']['address']['codigoPais'] = $address["cac:Country"]["cbc:IdentificationCode"];
        }

        $contact = $seller["cac:Contact"] ?? null;
        if ($contact) {
            $invoice['seller']['telephone'] = $seller["cac:Contact"]['cbc:Telephone'] ?? null;
            $invoice['seller']['email'] = $seller["cac:Contact"]['cbc:ElectronicMail'] ?? null;
        }

        $direccionEntrega = $xmlContent['cac:Delivery']['cac:DeliveryLocation']["cac:Address"] ?? null;
        if ($direccionEntrega) {
            $invoice['direccionEntrega']['ubigueo'] = $direccionEntrega['cbc:ID'];
            $invoice['direccionEntrega']['urbanizacion'] = $direccionEntrega['cbc:CitySubdivisionName'] ?? null;
            $invoice['direccionEntrega']['provincia'] = $direccionEntrega['cbc:CityName'];
            $invoice['direccionEntrega']['departamento'] = $direccionEntrega['cbc:CountrySubentity'];
            $invoice['direccionEntrega']['distrito'] = $direccionEntrega['cbc:District'];
            $invoice['direccionEntrega']['direccion'] = $direccionEntrega['cac:AddressLine']['cbc:Line'];
            $invoice['direccionEntrega']['codigoPais'] = $direccionEntrega['cac:Country']['cbc:IdentificationCode'];
        }


        $invoice['detraccion']['codMedioPago'] = $xmlContent["cac:PaymentMeans"]['cbc:PaymentMeansCode'] ?? null;
        $invoice['detraccion']['ctaBanco'] = $xmlContent["cac:PaymentMeans"]['cac:PayeeFinancialAccount']['cbc:ID'] ?? null;

        /**
         *  PaymentTerms
         *  - Detraccion
         *  - Percepcion
         *  - formaPago
         *  - cuotas
         */

        $paymentTerms = $xmlContent["cac:PaymentTerms"] ?? null;

        if ($paymentTerms) {
            $paymentTerms = isset($paymentTerms[0]) ? $paymentTerms : [$paymentTerms];
            foreach ($paymentTerms as $paymentTerm) {
                switch ($paymentTerm["cbc:ID"]) {
                    case "Detraccion":
                        $invoice['detraccion']['codBienDetraccion'] = $paymentTerm['cbc:PaymentMeansID'];
                        $invoice['detraccion']['percent'] = $paymentTerm['cbc:PaymentPercent'];
                        $invoice['detraccion']['mount'] = $paymentTerm['cbc:Amount'];
                        break;

                    case "Percepcion":
                        $invoice['perception']['mtoTotal'] = $paymentTerm['cbc:Amount'];
                        break;

                    case "FormaPago":
                        if (!isset($paymentTerm['cac:PaymentDueDate'])) {
                            $invoice['formaPago']["tipo"] = $paymentTerm['cbc:PaymentMeansID'];
                            $invoice['formaPago']['moneda'] = $paymentTerm['cbc:Amount']['@currencyID'] ?? null;
                            $invoice['formaPago']['monto'] = $paymentTerm['cbc:Amount']['#'] ?? null;
                        } else {
                            $invoice['cuotas'][]["moneda"] = $paymentTerm['cbc:Amount']['@currencyID'];
                            $invoice['cuotas'][]["monto"] = $paymentTerm['cbc:Amount']['#'];
                            $invoice['cuotas'][]["fechaPago"] = $paymentTerm['cac:PaymentDueDate'];
                        }
                        break;
                }
            }
        }

        $anticipos = $xmlContent["cac:PrepaidPayment"] ?? null;
        $invoice['anticipos'] = null;

        if ($anticipos) {
            $anticipos = isset($anticipos[0]) ? $anticipos : [$anticipos];
            $invoice['anticipos'] = array_map(function ($item) {
                return [
                    'tipoMoneda' => $item['PaidAmount']['@currencyID'],
                    'total' => $item["PaidAmount"]['#']
                ];
            }, $anticipos);
        }

        /**
         * AllowanceCharge
         * - cargos
         * - descruentos
         * - perception
         */

        $allowanceCharge = $xmlContent["cac:AllowanceCharge"] ?? null;
        if ($allowanceCharge) {
            $allowanceCharge = isset($allowanceCharge[0]) ? $allowanceCharge : [$allowanceCharge];

            //perception esta al final
            if (isset($invoice['perception'])) {
                $last_index = count($allowanceCharge);
                $perception = $allowanceCharge[$last_index];
                $invoice['perception']["codReg"] = $perception["cbc:AllowanceChargeReasonCode"];
                $invoice['perception']["porcentaje"] = $perception["cbc:MultiplierFactorNumeric"];
                $invoice['perception']["mto"] = $perception["cbc:Amount"]["#"];
                $invoice['perception']["mtoBase"] = $perception["cbc:BaseAmount"]["#"];
                unset($allowanceCharge[$last_index]);
            }

            foreach ($allowanceCharge as $item) {
                //cargos
                if ($item["cbc:ChargeIndicator"] == "true") {
                    $invoice['cargos'][]["codTipo"] = $item["cbc:AllowanceChargeReasonCode"];
                    $invoice['cargos'][]["factor"] = $item["cbc:MultiplierFactorNumeric"];
                    $invoice['cargos'][]["monto"] = $item["cbc:Amount"]["#"];
                    $invoice['cargos'][]["montoBase"] = $item["cbc:BaseAmount"]["#"];
                } //descuentos
                else {
                    $invoice['descuentos'][]["codTipo"] = $item["cbc:AllowanceChargeReasonCode"];
                    $invoice['descuentos'][]["factor"] = $item["cbc:MultiplierFactorNumeric"] ?? null;
                    $invoice['descuentos'][]["monto"] = $item["cbc:Amount"]["#"];
                    $invoice['descuentos'][]["montoBase"] = $item["cbc:BaseAmount"]["#"];
                }
            }
        }

        $invoice["totalImpuestos"] = $xmlContent["cac:TaxTotal"]["cbc:TaxAmount"]["#"];

        /**
         * TaxSubtotal
         * - mtoISC
         * - mtoOperGravadas
         * - mtoOperInafectas
         * - mtoOperExoneradas
         * - mtoOperGratuitas
         * - mtoOperExportacion
         * - mtoIva
         * - mtoOtrosTributos
         * - icbper
         */

        $taxSubtotal = $xmlContent["cac:TaxTotal"]["cac:TaxSubtotal"] ?? null;

        if ($taxSubtotal) {
            $taxSubtotal = isset($taxSubtotal[0]) ? $taxSubtotal : [$taxSubtotal];
            foreach ($taxSubtotal as $item) {
                //ISC
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "ISC" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "EXC"
                ) {
                    $invoice["mtoBaseIsc"] = $item["cbc:TaxableAmount"]["#"];
                    $invoice["mtoISC"] = $item["cbc:TaxAmount"]["#"];
                }

                //mtoOperGravadas
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "IGV" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "VAT"
                ) {
                    $invoice["mtoOperGravadas"] = $item["cbc:TaxableAmount"]["#"];
                    $invoice["mtoIGV"] = $item["cbc:TaxAmount"]["#"];
                }

                //mtoOperInafectas
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "INA" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "FRE"
                ) {
                    $invoice["mtoOperInafectas"] = $item["cbc:TaxableAmount"]["#"];
                }

                //mtoOperExoneradas
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "EXO" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "VAT"
                ) {
                    $invoice["mtoOperExoneradas"] = $item["cbc:TaxableAmount"]["#"];
                }

                //mtoOperGratuitas
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "GRA" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "FRE"
                ) {
                    $invoice["mtoOperGratuitas"] = $item["cbc:TaxableAmount"]["#"];
                    $invoice["mtoIGVGratuitas"] = $item["cbc:TaxAmount"]["#"];
                }

                //mtoOperExportacion
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "EXP" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "FRE"
                ) {
                    $invoice["mtoOperExportacion"] = $item["cbc:TaxableAmount"]["#"];
                }

                //mtoIvap
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "IVAP" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "VAT"
                ) {
                    $invoice["mtoBaseIvap"] = $item["cbc:TaxableAmount"]["#"];
                    $invoice["mtoIvap"] = $item["cbc:TaxAmount"]["#"];
                }

                //mtoOtrosTributos
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "OTROS" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "OTH"
                ) {
                    $invoice["mtoBaseOth"] = $item["cbc:TaxableAmount"]["#"];
                    $invoice["mtoOtrosTributos"] = $item["cbc:TaxAmount"]["#"];
                }

                //icbper
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "ICBPER" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "OTH"
                ) {
                    $invoice["icbper"] = $item["cbc:TaxAmount"]["#"];
                }
            }
        }

        $LegalMonetaryTotal = $xmlContent["cac:LegalMonetaryTotal"];
        $invoice["valorVenta"] = $LegalMonetaryTotal["cbc:LineExtensionAmount"]["#"];
        $invoice["subTotal"] = $LegalMonetaryTotal["cbc:TaxInclusiveAmount"]["#"] ?? null;
        $invoice["sumOtrosDescuentos"] = $LegalMonetaryTotal["cbc:TaxInclusiveAmount"]["#"] ?? null;
        $invoice["sumOtrosCargos"] = $LegalMonetaryTotal["cbc:ChargeTotalAmount"]["#"] ?? null;
        $invoice["totalAnticipos"] = $LegalMonetaryTotal["cbc:PrepaidAmount"]["#"] ?? null;
        $invoice["redondeo"] = $LegalMonetaryTotal["cbc:PayableRoundingAmount"]["#"] ?? null;
        $invoice["mtoImpVenta"] = $LegalMonetaryTotal["cbc:PayableAmount"]["#"] ?? null;

        //Details
        $details = $xmlContent["cac:InvoiceLine"];
        $details = isset($details[0]) ? $details : [$details];
        $item_details = [];

        foreach ($details as $detail) {
            $item_detail['unidad'] = $detail['cbc:InvoicedQuantity']['@unitCode'];
            $item_detail['cantidad'] = $detail['cbc:InvoicedQuantity']['#'];
            $item_detail['mtoValorVenta'] = $detail['cbc:LineExtensionAmount']['#'];

            $aternativeConditionPrice = $detail['cac:PricingReference']['cac:AlternativeConditionPrice'] ?? null;
            if ($aternativeConditionPrice) {
                $aternativeConditionPrice = isset($aternativeConditionPrice[0]) ? $aternativeConditionPrice : [$aternativeConditionPrice];

                foreach ($aternativeConditionPrice as $data) {
                    //mtoValorGratuito
                    if ($data["cbc:PriceTypeCode"] == "02") {
                        $item_detail['mtoValorGratuito'] = $data["cbc:PriceAmount"]["#"];
                    } //mtoPrecioUnitario
                    else {
                        $item_detail['mtoPrecioUnitario'] = $data["cbc:PriceAmount"]["#"];
                    }
                }
            }

            $allowanceCharge = $detail['cac:AllowanceCharge'] ?? null;
            if ($allowanceCharge) {
                $allowanceCharge = isset($allowanceCharge[0]) ? $allowanceCharge : [$allowanceCharge];
                foreach ($allowanceCharge as $item) {
                    //cargos
                    if ($item["cbc:ChargeIndicator"] == "true") {
                        $item_detail['cargos'][]["codTipo"] = $item["cbc:AllowanceChargeReasonCode"];
                        $item_detail['cargos'][]["factor"] = $item["cbc:MultiplierFactorNumeric"];
                        $item_detail['cargos'][]["monto"] = $item["cbc:Amount"]["#"];
                        $item_detail['cargos'][]["montoBase"] = $item["cbc:BaseAmount"]["#"];
                    } //descuentos
                    else {
                        $item_detail['descuentos'][]["codTipo"] = $item["cbc:AllowanceChargeReasonCode"];
                        $item_detail['descuentos'][]["factor"] = $item["cbc:MultiplierFactorNumeric"] ?? null;
                        $item_detail['descuentos'][]["monto"] = $item["cbc:Amount"]["#"];
                        $item_detail['descuentos'][]["montoBase"] = $item["cbc:BaseAmount"]["#"];
                    }
                }
            }

            $item_detail["totalImpuestos"] = $detail["cac:TaxTotal"]["cbc:TaxAmount"]["#"];

            /**
             * TaxSubtotal
             * - mtoISC
             * - mtoOperGravadas
             * - mtoOperInafectas
             * - mtoOperExoneradas
             * - mtoOperGratuitas
             * - mtoOperExportacion
             * - mtoIva
             * - mtoOtrosTributos
             * - icbper
             */

            $taxSubtotal = $detail["cac:TaxTotal"]["cac:TaxSubtotal"] ?? null;

            if ($taxSubtotal) {
                $taxSubtotal = isset($taxSubtotal[0]) ? $taxSubtotal : [$taxSubtotal];
                foreach ($taxSubtotal as $item) {
                    //ISC
                    if (
                        $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "ISC" &&
                        $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "EXC"
                    ) {
                        $item_detail["mtoBaseIsc"] = $item["cbc:TaxableAmount"]["#"];
                        $item_detail["isc"] = $item["cbc:TaxAmount"]["#"];
                        continue;
                    }

                    //mtoOtrosTributos
                    if (
                        $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "OTROS" &&
                        $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "OTH"
                    ) {
                        $item_detail["mtoBaseOth"] = $item["cbc:TaxableAmount"]["#"];
                        $item_detail["otroTributo"] = $item["cbc:TaxAmount"]["#"];
                        continue;
                    }

                    //icbper
                    if (
                        $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "ICBPER" &&
                        $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "OTH"
                    ) {
                        $item_detail["icbper"] = $item["cbc:TaxAmount"]["#"];
                        $item_detail["factorIcbper"] = $item["cac:TaxCategory"]["cbc:PerUnitAmount"]["#"];
                        continue;
                    }

                    //mtoBaseIgv
                    if (
                        $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "IGV" &&
                        $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "VAT"
                    ) {
                        $item_detail["mtoBaseIgv"] = $item["cbc:TaxableAmount"]["#"];
                        $item_detail["igv"] = $item["cbc:TaxAmount"]["#"];
                        $item_detail["porcentajeIgv"] = $item["cac:TaxCategory"]["cbc:Percent"];
                        $item_detail["tipAfeIgv"] = $item["cac:TaxCategory"]["cbc:TaxExemptionReasonCode"];
                    }

                }
            }

            $item =  $detail["cac:Item"];
            $item_detail["descripcion"] = $item["cbc:Description"];
            $item_detail["codProducto"] = $item["cac:SellersItemIdentification"]["cbc:ID"] ?? null;
            $item_detail["codProdGS1"] = $item["cac:StandardItemIdentification"]["cbc:ID"] ?? null;
            $item_detail["codProdSunat"] = $item["cac:CommodityClassification"]["cbc:ItemClassificationCode"] ?? null;

            $atributos = $item["cac:AdditionalItemProperty"] ?? null;

            if ($atributos) {
                $atributos = isset($atributos[0]) ? $atributos : [$atributos];
                foreach ($atributos as $atributo) {
                    $item_detail["atributos"][]["name"] = $atributo["cbc:Name"];
                    $item_detail["atributos"][]["code"] = $atributo["cbc:NameCode"];
                    $item_detail["atributos"][]["value"] = $atributo["cbc:Value"] ?? null;
                    $item_detail["atributos"][]["fecInicio"] = $atributo["cac:UsabilityPeriod"]["StartDate"] ?? null;
                    $item_detail["atributos"][]["fecFin"] = $atributo["cac:UsabilityPeriod"]["EndDate"] ?? null;
                    $item_detail["atributos"][]["duracion"] = $atributo["cac:UsabilityPeriod"]["DurationMeasure"]["#"] ?? null;
                }
            }

            $item_detail["mtoValorUnitario"] = $detail["cac:Price"]["cbc:PriceAmount"]["#"];

            $item_details[] = $item_detail;
        }

        $invoice['details'] = $item_details;

        $invoice = (new Invoice());

        return $invoice;
    }
}