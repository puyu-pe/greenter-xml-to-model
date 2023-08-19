<?php
/**
 * Created by Intellij IDEA
 * User: Velnae.28
 * Date: 14/08/2023
 * Time: 18:47.
 */

namespace Puyu\GreenterXmlToModel\Util;

use DateTime;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\DetailAttribute;

abstract class DocumentBuild
{
    const CURRENCY_DEFAULT = 'PEN';
    const CODE_COUNTRY_DEFAULT = 'PE';
    const CODE_ESTABLISHMENT_DEFAULT = '0000';
    public ?object $document;

    public function getCompany($company): ?Company
    {
        if (!$company)
         return null;

        return (new Company())
            ->setRuc($company->ruc)
            ->setNombreComercial($company->nombreComercial)
            ->setRazonSocial($company->razonSocial)
            ->setAddress((new Address())
                ->setUbigueo($company->address->ubigueo)
                ->setCodigoPais($company->address->codigoPais)
                ->setDistrito($company->address->distrito)
                ->setProvincia($company->address->provincia)
                ->setDepartamento($company->address->departamento)
                ->setUrbanizacion($company->address->urbanizacion)
                ->setDireccion($company->address->direccion)
                ->setCodLocal($company->address->codLocal))
            ->setEmail($company->email ?? null)
            ->setTelephone($company->telephone ?? null);
    }

    public function getClient($client): ?Client
    {
        if (!$client)
            return null;

        return (new Client())
            ->setTipoDoc($client->tipoDoc)
            ->setNumDoc($client->numDoc)
            ->setRznSocial($client->rznSocial)
            ->setEmail($client->email ?? null)
            ->setTelephone($client->telephone ?? null)
            ->setAddress($this->getAddress($client->address ?? null));
    }

    public function getAddress($address): ?Address
    {
        if (!$address)
            return null;

        return (new Address())
            ->setUbigueo($address->ubigueo)
            ->setCodigoPais($address->codigoPais ?? DocumentBuild::CODE_COUNTRY_DEFAULT)
            ->setDistrito($address->distrito ?? null)
            ->setProvincia($address->provincia ?? null)
            ->setDepartamento($address->departamento ?? null)
            ->setUrbanizacion($address->urbanizacion ?? null)
            ->setDireccion($address->direccion ?? null)
            ->setCodLocal($address->codLocal ?? DocumentBuild::CODE_ESTABLISHMENT_DEFAULT);
    }


    /**
     * @param $atributos
     * @return DetailAttribute[]|null
     */
    public function getAtributos($atributos = null): ?array
    {
        if (!$atributos)
            return null;

        $new_atributos = [];

        for ($i = 0; $i < count($atributos); $i++) {
            $new_atributos[] = (new DetailAttribute())
                ->setCode($atributos[$i]->code)
                ->setName($atributos[$i]->name)
                ->setValue($atributos[$i]->value ?? null)
                ->setFecInicio(isset($atributos[$i]->fecInicio) ? new DateTime($atributos[$i]->fecInicio) : null)
                ->setFecFin(isset($atributos[$i]->fecFin) ? new DateTime($atributos[$i]->fecFin) : null)
                ->setDuracion($atributos[$i]->duracion ?? null);
        }

        return $new_atributos;
    }
}
