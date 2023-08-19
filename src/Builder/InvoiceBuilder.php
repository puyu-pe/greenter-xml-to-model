<?php
/**
 * Created by Intellij IDEA
 * User: Velnae.28
 * Date: 14/08/2023
 * Time: 18:47.
 */

declare(strict_types=1);

namespace Puyu\GreenterXmlToModel\Builder;

use Greenter\Model\DocumentInterface;
use Puyu\GreenterXmlToModel\Util\InvoiceBuild;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class InvoiceBuilder implements BuilderInterface
{
    public function build(string $xml): ?DocumentInterface
    {
        $encoder = new XmlEncoder();
        $xmlContent = $encoder->decode($xml, "xml");
        $invoice = [];
        $invoice["ublVersion"] = $this->getValue($xmlContent["cbc:UBLVersionID"]);
        $serie_correlativo = explode("-", $this->getValue($xmlContent["cbc:ID"]));
        $invoice["serie"] = $serie_correlativo[0];
        $invoice["correlativo"] = $serie_correlativo[1];
        $invoice["fechaEmision"] = $this->getValue($xmlContent["cbc:IssueDate"]) . " " . $this->getValue($xmlContent["cbc:IssueTime"]);
        $invoice["fecVencimiento"] = $this->getValue($xmlContent["cbc:DueDate"] ?? null);
        $invoice["tipoOperacion"] = $xmlContent["cbc:InvoiceTypeCode"]["@listID"] ?? null;
        $invoice["tipoDoc"] = $this->getValue($xmlContent["cbc:InvoiceTypeCode"]);

        $legends = $xmlContent["cbc:Note"] ?? null;
        $legends = isset($legends[0]) ? $legends : [$legends];
        $invoice["legends"] = $invoice["observacion"] = null;

        foreach ($legends as $item) {
            if (isset($item["@languageLocaleID"]))
                $invoice["legends"][] = [
                    "code" => $item["@languageLocaleID"],
                    "value" => $this->getValue($item),
                ];

            else
                $invoice["observacion"] = $item ?? null;

            unset($item);
        }

        $invoice["tipoMoneda"] = $this->getValue($xmlContent["cbc:DocumentCurrencyCode"]);
        $invoice["compra"] = $xmlContent["cac:OrderReference"]["cbc:ID"] ?? null;

        $guias = $xmlContent["cac:DespatchDocumentReference"] ?? null;
        $invoice["guias"] = null;

        if ($guias) {
            $guias = isset($guias[0]) ? $guias : [$guias];
            $invoice["guias"] = array_map(function ($item) {
                return [
                    "nroDoc" => $item["cbc:ID"],
                    "tipoDoc" => $item["cbc:DocumentTypeCode"],
                ];
            }, $guias);
        }

        $relDocs = $xmlContent["cac:AdditionalDocumentReference"] ?? null;
        $invoice["relDocs"] = $invoice["anticipos"] = null;

        if ($relDocs) {
            $relDocs = isset($relDocs[0]) ? $relDocs : [$relDocs];

            foreach ($relDocs as $item) {
                if (isset($item["DocumentStatusCode"]))
                    $invoice["anticipos"][] = [
                        "nroDocRel" => $item["cbc:ID"],
                        "tipoDocRel" => $item["cbc:DocumentTypeCode"],
                    ];
                else
                    $invoice["relDocs"][] = [
                        "nroDoc" => $item["cbc:ID"],
                        "tipoDoc" => $item["cbc:DocumentTypeCode"],
                    ];
            }
        }

        $company = $xmlContent["cac:AccountingSupplierParty"]["cac:Party"];
        $invoice["company"]["ruc"] = $this->getValue($company["cac:PartyIdentification"]["cbc:ID"]);
        $invoice["company"]["razonSocial"] = $company["cac:PartyLegalEntity"]["cbc:RegistrationName"];
        $invoice["company"]["nombreComercial"] = $company["cac:PartyName"]["cbc:Name"] ?? null;

        $address = $company["cac:PartyLegalEntity"]["cac:RegistrationAddress"];
        $invoice["company"]["address"]["ubigueo"] = $this->getValue($address["cbc:ID"]);
        $invoice["company"]["address"]["codLocal"] = $this->getValue($address["cbc:AddressTypeCode"]);
        $invoice["company"]["address"]["urbanizacion"] = $address["cbc:CitySubdivisionName"] ?? null;
        $invoice["company"]["address"]["provincia"] = $address["cbc:CityName"];
        $invoice["company"]["address"]["departamento"] = $address["cbc:CountrySubentity"];
        $invoice["company"]["address"]["distrito"] = $address["cbc:District"];
        $invoice["company"]["address"]["direccion"] = $address["cac:AddressLine"]["cbc:Line"];
        $invoice["company"]["address"]["codigoPais"] = $this->getValue($address["cac:Country"]["cbc:IdentificationCode"]);

        $contact = $company["cac:Contact"] ?? null;
        if ($contact) {
            $invoice["company"]["telephone"] = $company["cac:Contact"]["cbc:Telephone"] ?? null;
            $invoice["company"]["email"] = $company["cac:Contact"]["cbc:ElectronicMail"] ?? null;
        }

        $client = $xmlContent["cac:AccountingCustomerParty"]["cac:Party"];
        $invoice["client"]["tipoDoc"] = $client["cac:PartyIdentification"]["cbc:ID"]["@schemeID"];
        $invoice["client"]["numDoc"] = $this->getValue($client["cac:PartyIdentification"]["cbc:ID"]);
        $invoice["client"]["rznSocial"] = $client["cac:PartyLegalEntity"]["cbc:RegistrationName"];

        $address = $client["cac:PartyLegalEntity"]["cac:RegistrationAddress"] ?? null;
        if ($address) {
            $invoice["client"]["address"]["ubigueo"] = $address["cbc:ID"] ?? null;
            $invoice["client"]["address"]["direccion"] = $address["cac:AddressLine"]["cbc:Line"];
            $invoice["client"]["address"]["codigoPais"] = $address["cac:Country"]["cbc:IdentificationCode"] ?? null;
        }

        $contact = $client["cac:Contact"] ?? null;
        if ($contact) {
            $invoice["client"]["telephone"] = $client["cac:Contact"]["cbc:Telephone"] ?? null;
            $invoice["client"]["email"] = $client["cac:Contact"]["cbc:ElectronicMail"] ?? null;
        }

        $seller = $company["cac:SellerSupplierParty"]["cac:Party"] ?? null;

        if ($seller) {
            $invoice["seller"]["tipoDoc"] = $seller["cac:PartyIdentification"]["cbc:ID"]["@schemeID"];
            $invoice["seller"]["numDoc"] = $this->getValue($seller["cac:PartyIdentification"]["cbc:ID"]);
            $invoice["seller"]["rznSocial"] = $seller["cac:PartyLegalEntity"]["cbc:RegistrationName"];
        }

        $address = $seller["cac:PartyLegalEntity"]["cac:RegistrationAddress"] ?? null;
        if ($address) {
            $invoice["seller"]["address"]["ubigueo"] = $address["cbc:ID"] ?? null;
            $invoice["seller"]["address"]["direccion"] = $address["cac:AddressLine"]["cbc:Line"];
            $invoice["seller"]["address"]["codigoPais"] = $address["cac:Country"]["cbc:IdentificationCode"];
        }

        $contact = $seller["cac:Contact"] ?? null;
        if ($contact) {
            $invoice["seller"]["telephone"] = $seller["cac:Contact"]["cbc:Telephone"] ?? null;
            $invoice["seller"]["email"] = $seller["cac:Contact"]["cbc:ElectronicMail"] ?? null;
        }

        $direccionEntrega = $xmlContent["cac:Delivery"]["cac:DeliveryLocation"]["cac:Address"] ?? null;
        if ($direccionEntrega) {
            $invoice["direccionEntrega"]["ubigueo"] = $direccionEntrega["cbc:ID"];
            $invoice["direccionEntrega"]["urbanizacion"] = $direccionEntrega["cbc:CitySubdivisionName"] ?? null;
            $invoice["direccionEntrega"]["provincia"] = $direccionEntrega["cbc:CityName"];
            $invoice["direccionEntrega"]["departamento"] = $direccionEntrega["cbc:CountrySubentity"];
            $invoice["direccionEntrega"]["distrito"] = $direccionEntrega["cbc:District"];
            $invoice["direccionEntrega"]["direccion"] = $direccionEntrega["cac:AddressLine"]["cbc:Line"];
            $invoice["direccionEntrega"]["codigoPais"] = $direccionEntrega["cac:Country"]["cbc:IdentificationCode"];
        }


        $detraccion = $xmlContent["cac:PaymentMeans"] ?? null;

        if ($detraccion) {
            $invoice["detraccion"]["codMedioPago"] = $detraccion["cbc:PaymentMeansCode"] ?? null;
            $invoice["detraccion"]["ctaBanco"] = $detraccion["cac:PayeeFinancialAccount"]["cbc:ID"] ?? null;
        }

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
                        $invoice["detraccion"]["codBienDetraccion"] = $paymentTerm["cbc:PaymentMeansID"];
                        $invoice["detraccion"]["percent"] = $paymentTerm["cbc:PaymentPercent"];
                        $invoice["detraccion"]["mount"] = $paymentTerm["cbc:Amount"];
                        break;

                    case "Percepcion":
                        $invoice["perception"]["mtoTotal"] = $paymentTerm["cbc:Amount"];
                        break;

                    case "FormaPago":
                        if (!isset($paymentTerm["cac:PaymentDueDate"])) {
                            $invoice["formaPago"]["tipo"] = $paymentTerm["cbc:PaymentMeansID"];
                            $invoice["formaPago"]["moneda"] = $paymentTerm["cbc:Amount"]["@currencyID"] ?? null;
                            $invoice["formaPago"]["monto"] = $this->getValue($paymentTerm["cbc:Amount"]) ?? null;
                        } else {
                            $invoice["cuotas"][]["moneda"] = $paymentTerm["cbc:Amount"]["@currencyID"];
                            $invoice["cuotas"][]["monto"] = $this->getValue($paymentTerm["cbc:Amount"]);
                            $invoice["cuotas"][]["fechaPago"] = $paymentTerm["cac:PaymentDueDate"];
                        }
                        break;
                }
            }
        }

        $anticipos = $xmlContent["cac:PrepaidPayment"] ?? null;
        $invoice["anticipos"] = null;

        if ($anticipos) {
            $anticipos = isset($anticipos[0]) ? $anticipos : [$anticipos];
            $invoice["anticipos"] = array_map(function ($item) {
                return [
                    "tipoMoneda" => $item["PaidAmount"]["@currencyID"],
                    "total" => $this->getValue($item["PaidAmount"])
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
            if (isset($invoice["perception"])) {
                $last_index = count($allowanceCharge);
                $perception = $allowanceCharge[$last_index];
                $invoice["perception"]["codReg"] = $perception["cbc:AllowanceChargeReasonCode"];
                $invoice["perception"]["porcentaje"] = $perception["cbc:MultiplierFactorNumeric"];
                $invoice["perception"]["mto"] = $this->getValue($perception["cbc:Amount"]);
                $invoice["perception"]["mtoBase"] = $this->getValue($perception["cbc:BaseAmount"]);
                unset($allowanceCharge[$last_index]);
            }

            foreach ($allowanceCharge as $item) {
                //cargos
                if ($item["cbc:ChargeIndicator"] == "true") {
                    $invoice["cargos"][]["codTipo"] = $item["cbc:AllowanceChargeReasonCode"];
                    $invoice["cargos"][]["factor"] = $item["cbc:MultiplierFactorNumeric"];
                    $invoice["cargos"][]["monto"] = $this->getValue($item["cbc:Amount"]);
                    $invoice["cargos"][]["montoBase"] = $this->getValue($item["cbc:BaseAmount"]);
                } //descuentos
                else {
                    $invoice["descuentos"][]["codTipo"] = $item["cbc:AllowanceChargeReasonCode"];
                    $invoice["descuentos"][]["factor"] = $item["cbc:MultiplierFactorNumeric"] ?? null;
                    $invoice["descuentos"][]["monto"] = $this->getValue($item["cbc:Amount"]);
                    $invoice["descuentos"][]["montoBase"] = $this->getValue($item["cbc:BaseAmount"]);
                }
            }
        }

        $invoice["totalImpuestos"] = $this->getValue($xmlContent["cac:TaxTotal"]["cbc:TaxAmount"]);

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
                    $invoice["mtoBaseIsc"] = $this->getValue($item["cbc:TaxableAmount"]);
                    $invoice["mtoISC"] = $this->getValue($item["cbc:TaxAmount"]);
                }

                //mtoOperGravadas
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "IGV" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "VAT"
                ) {
                    $invoice["mtoOperGravadas"] = $this->getValue($item["cbc:TaxableAmount"]);
                    $invoice["mtoIGV"] = $this->getValue($item["cbc:TaxAmount"]);
                }

                //mtoOperInafectas
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "INA" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "FRE"
                ) {
                    $invoice["mtoOperInafectas"] = $this->getValue($item["cbc:TaxableAmount"]);
                }

                //mtoOperExoneradas
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "EXO" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "VAT"
                ) {
                    $invoice["mtoOperExoneradas"] = $this->getValue($item["cbc:TaxableAmount"]);
                }

                //mtoOperGratuitas
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "GRA" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "FRE"
                ) {
                    $invoice["mtoOperGratuitas"] = $this->getValue($item["cbc:TaxableAmount"]);
                    $invoice["mtoIGVGratuitas"] = $this->getValue($item["cbc:TaxAmount"]);
                }

                //mtoOperExportacion
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "EXP" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "FRE"
                ) {
                    $invoice["mtoOperExportacion"] = $this->getValue($item["cbc:TaxableAmount"]);
                }

                //mtoIvap
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "IVAP" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "VAT"
                ) {
                    $invoice["mtoBaseIvap"] = $this->getValue($item["cbc:TaxableAmount"]);
                    $invoice["mtoIvap"] = $this->getValue($item["cbc:TaxAmount"]);
                }

                //mtoOtrosTributos
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "OTROS" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "OTH"
                ) {
                    $invoice["mtoBaseOth"] = $this->getValue($item["cbc:TaxableAmount"]);
                    $invoice["mtoOtrosTributos"] = $this->getValue($item["cbc:TaxAmount"]);
                }

                //icbper
                if (
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "ICBPER" &&
                    $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "OTH"
                ) {
                    $invoice["icbper"] = $this->getValue($item["cbc:TaxAmount"]);
                }
            }
        }

        $LegalMonetaryTotal = $xmlContent["cac:LegalMonetaryTotal"];
        $invoice["valorVenta"] = $this->getValue($LegalMonetaryTotal["cbc:LineExtensionAmount"]);
        $invoice["subTotal"] = $this->getValue($LegalMonetaryTotal["cbc:TaxInclusiveAmount"] ?? null);
        $invoice["sumOtrosDescuentos"] = $this->getValue($LegalMonetaryTotal["cbc:TaxInclusiveAmount"] ?? null);
        $invoice["sumOtrosCargos"] = $this->getValue($LegalMonetaryTotal["cbc:ChargeTotalAmount"] ?? null);
        $invoice["totalAnticipos"] = $this->getValue($LegalMonetaryTotal["cbc:PrepaidAmount"] ?? null);
        $invoice["redondeo"] = $this->getValue($LegalMonetaryTotal["cbc:PayableRoundingAmount"] ?? null);
        $invoice["mtoImpVenta"] = $this->getValue($LegalMonetaryTotal["cbc:PayableAmount"] ?? null);

        //Details
        $details = $xmlContent["cac:InvoiceLine"];
        $details = isset($details[0]) ? $details : [$details];
        $item_details = [];

        foreach ($details as $detail) {
            $item_detail["unidad"] = $detail["cbc:InvoicedQuantity"]["@unitCode"];
            $item_detail["cantidad"] = $this->getValue($detail["cbc:InvoicedQuantity"]);
            $item_detail["mtoValorVenta"] = $this->getValue($detail["cbc:LineExtensionAmount"]);

            $aternativeConditionPrice = $detail["cac:PricingReference"]["cac:AlternativeConditionPrice"] ?? null;
            if ($aternativeConditionPrice) {
                $aternativeConditionPrice = isset($aternativeConditionPrice[0]) ? $aternativeConditionPrice : [$aternativeConditionPrice];

                foreach ($aternativeConditionPrice as $data) {
                    //mtoValorGratuito
                    if ($this->getValue($data["cbc:PriceTypeCode"]) == "02") {
                        $item_detail["mtoValorGratuito"] = $this->getValue($data["cbc:PriceAmount"]);
                    } //mtoPrecioUnitario
                    else {
                        $item_detail["mtoPrecioUnitario"] = $this->getValue($data["cbc:PriceAmount"]);
                    }
                }
            }

            $allowanceCharge = $detail["cac:AllowanceCharge"] ?? null;
            if ($allowanceCharge) {
                $allowanceCharge = isset($allowanceCharge[0]) ? $allowanceCharge : [$allowanceCharge];
                foreach ($allowanceCharge as $item) {
                    //cargos
                    if ($item["cbc:ChargeIndicator"] == "true") {
                        $item_detail["cargos"][]["codTipo"] = $item["cbc:AllowanceChargeReasonCode"];
                        $item_detail["cargos"][]["factor"] = $item["cbc:MultiplierFactorNumeric"];
                        $item_detail["cargos"][]["monto"] = $this->getValue($item["cbc:Amount"]);
                        $item_detail["cargos"][]["montoBase"] = $this->getValue($item["cbc:BaseAmount"]);
                    } //descuentos
                    else {
                        $item_detail["descuentos"][]["codTipo"] = $item["cbc:AllowanceChargeReasonCode"];
                        $item_detail["descuentos"][]["factor"] = $item["cbc:MultiplierFactorNumeric"] ?? null;
                        $item_detail["descuentos"][]["monto"] = $this->getValue($item["cbc:Amount"]);
                        $item_detail["descuentos"][]["montoBase"] = $this->getValue($item["cbc:BaseAmount"]);
                    }
                }
            }

            $item_detail["totalImpuestos"] = $this->getValue($detail["cac:TaxTotal"]["cbc:TaxAmount"]);

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
                        $item_detail["mtoBaseIsc"] = $this->getValue($item["cbc:TaxableAmount"]);
                        $item_detail["isc"] = $this->getValue($item["cbc:TaxAmount"]);
                        continue;
                    }

                    //mtoOtrosTributos
                    if (
                        $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "OTROS" &&
                        $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "OTH"
                    ) {
                        $item_detail["mtoBaseOth"] = $this->getValue($item["cbc:TaxableAmount"]);
                        $item_detail["otroTributo"] = $this->getValue($item["cbc:TaxAmount"]);
                        continue;
                    }

                    //icbper
                    if (
                        $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "ICBPER" &&
                        $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "OTH"
                    ) {
                        $item_detail["icbper"] = $this->getValue($item["cbc:TaxAmount"]);
                        $item_detail["factorIcbper"] = $this->getValue($item["cac:TaxCategory"]["cbc:PerUnitAmount"]);
                        continue;
                    }

                    //mtoBaseIgv
                    if (
                        $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:Name"] == "IGV" &&
                        $item["cac:TaxCategory"]["cac:TaxScheme"]["cbc:TaxTypeCode"] == "VAT"
                    ) {
                        $item_detail["mtoBaseIgv"] = $this->getValue($item["cbc:TaxableAmount"]);
                        $item_detail["igv"] = $this->getValue($item["cbc:TaxAmount"]);
                        $item_detail["porcentajeIgv"] = $item["cac:TaxCategory"]["cbc:Percent"];
                        $item_detail["tipAfeIgv"] = $item["cac:TaxCategory"]["cbc:TaxExemptionReasonCode"];
                    }

                }
            }

            $item = $detail["cac:Item"];
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
                    $item_detail["atributos"][]["duracion"] = $this->getValue($atributo["cac:UsabilityPeriod"]["DurationMeasure"]) ?? null;
                }
            }

            $item_detail["mtoValorUnitario"] = $this->getValue($detail["cac:Price"]["cbc:PriceAmount"]);

            $item_details[] = $item_detail;
        }

        $invoice["details"] = $item_details;

//        $encoders = [new JsonEncoder()];
//        $normalizers = [new ObjectNormalizer()];
//        $normalizer = new ObjectNormalizer();
//        $temp = $this->normalizer->denormalize($invoice, Invoice::class);
//        $serializer = new Serializer($normalizers, $encoders);
//        $invoice = $serializer->deserialize($data, Invoice::class, "json");
//        $invoice = (new Invoice());
        $json = json_encode($invoice);
        $invoiceObject = json_decode($json);
        $builderInvoice = new InvoiceBuild($invoiceObject);
        $temp = $builderInvoice->build();


        return $temp;
    }

    /**
     * @param mixed|null $element
     * @return string|null
     *
     * Retorna el valor del elemento, si no existe retorna null
     */
    private function getValue($element):  ?string
    {
        if(!$element)
            return null;

        $value = $element["#"] ?? $element;
        return trim($value);
    }
}