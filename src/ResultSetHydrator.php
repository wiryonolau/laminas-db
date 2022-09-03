<?php

namespace Itseasy\Database;

use Laminas\Hydrator\Exception\BadMethodCallException;
use Laminas\Hydrator\HydratorInterface;

class ResultSetHydrator implements HydratorInterface
{
    /**
     * Call populate first to speed up hydration since Resultset is one way 
     */
    public function hydrate(array $data, object $object)
    {
        if (method_exists($object, 'populate') && is_callable([$object, 'populate'])) {
            $object->populate($data);
            return $object;
        }

        $replacement = [];
        if (method_exists($object, 'exchangeArray') && is_callable([$object, 'exchangeArray'])) {
            if (method_exists($object, 'getArrayCopy') && is_callable([$object, 'getArrayCopy'])) {
                $original    = $object->getArrayCopy();
                $replacement = array_merge($original, $data);
            }
            $object->exchangeArray($replacement);
            return $object;
        }

        throw new BadMethodCallException(
            sprintf('%s expects the provided object to implement populate()', __METHOD__)
        );
    }

    public function extract(object $object): array
    {
        if (!method_exists($object, 'getArrayCopy') || !is_callable([$object, 'getArrayCopy'])) {
            throw new BadMethodCallException(
                sprintf('%s expects the provided object to implement getArrayCopy()', __METHOD__)
            );
        }

        return $object->getArrayCopy();
    }
}
