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

/**
 * Interface BuilderInterface.
 */
interface BuilderInterface
{
    /**
     * Create file for document.
     *
     * @param string $xml
     *
     * @return DocumentInterface|null Content File
     */
    public function build(string $xml): ?DocumentInterface;
}
