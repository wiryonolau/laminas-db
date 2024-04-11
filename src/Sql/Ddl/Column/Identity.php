<?php

declare(strict_types=1);

namespace Itseasy\Database\Sql\Ddl\Column;

use Laminas\Db\Sql\Ddl\Column\Integer;

class Identity extends Integer implements PostgreColumnInterface
{
    /**
     * @return array
     */
    public function getExpressionData()
    {
        $data    = parent::getExpressionData();

        $options = $this->getOptions();

        $data[0][0] .= "%s %s";

        $data[0][1][1] = sprintf(
            '%s, ALTER COLUMN %s ADD GENERATED %s AS IDENTITY ( INCREMENT %d START %d MINVALUE %d MAXVALUE %d %sCYCLE)',
            $this->type,
            $this->getName(),
            empty($options["identity_generation"]) ? "ALWAYS" : $options["identity_generation"],
            empty($options["identity_increment"]) ? 1 : $options["identity_increment"],
            empty($options["identity_start"]) ? 1 : $options["identity_start"],
            empty($options["identity_minimum"]) ? 1 : $options["identity_minimum"],
            empty($options["identity_maximum"]) ? 2147483647 : $options["identity_maximum"],
            empty($options["identity_cycle"]) ? "NO " : ""
        );

        return $data;
    }
}
