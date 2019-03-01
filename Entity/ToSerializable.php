<?php
/**
 * Author: Anton Dorofeev <anton@dorofeev.me>
 * Created: 4/13/17
 */

namespace Adorofeev\MongoRepository\Entity;


use Adorofeev\MongoRepository\Value\JSCompatibleDateTime;
use Adorofeev\MongoRepository\Value\MongoCompatibleDatetime;

trait ToSerializable
{

    public function toSerializable($emptyObject = \stdClass::class, $emptyCollection = [], $value = null, array &$traversed = [], $serializerMethodName = null)
    {
        $serializerMethodName = $serializerMethodName ?: __FUNCTION__;
        if (!$value) {
            $traversed[\get_class($this) . '(#' . $this->getId() . ')'] = $this;
            $bsonCollection = is_string($emptyObject)
                ? new $emptyObject()
                : $emptyObject;
            $vars = get_object_vars($this);
            $arrayAccess = false;
        } else {
            $bsonCollection = is_string($emptyCollection)
                ? new $emptyCollection
                : $emptyCollection;
            $vars = $value;
            $arrayAccess = true;
        }

        foreach ($vars as $name => $varValue) {
            $newName = $name;
            if (!$value) {
                if (!$varValue) {
                    $getter = 'get' . ucfirst($name);
                    if (method_exists($this, $getter)) {
                        $varValue = $this->$getter();
                    }
                }
                $skipConstantName = 'toSerializableSkip';
                if (\defined('static::' . $skipConstantName) && !empty(static::$skipConstantName[$name])) {
                    continue;
                }
            }

            if (is_scalar($varValue) || (null === $varValue)) {
                $newValue = $varValue;
            } elseif ($varValue instanceof MongoCompatibleDatetime) {
                $newValue = $varValue->$serializerMethodName();
            } elseif ($varValue instanceof JSCompatibleDateTime) {
//                $newValue = (string) $varValue;
                $newValue = $varValue->format('c');
            } elseif ($varValue instanceof \DateTime) {
                $newValue = $varValue->getTimestamp();
            } elseif (\is_array($varValue) || $varValue instanceof \Traversable) {
                $newValue = \count($varValue) ? $this->$serializerMethodName($emptyObject, $emptyCollection, $varValue, $traversed) : [];
            } elseif ($varValue instanceof self) {
                $duplicate = false;
                foreach ($traversed as $parentObject) {
                    if ($parentObject === $varValue) {
                        $duplicate = true;
                        break;
                    }
                }

                if (!$duplicate) {
                    $traversed[\get_class($varValue) . '(#' . $varValue->getId() . ')'] = $varValue;
                    $newValue = $varValue->$serializerMethodName($emptyObject, $emptyCollection, null, $traversed);
                } else {
                    $newValue = $varValue ? 'dup_' . $varValue->getId() : null;
                }
            } elseif (\is_object($varValue) && method_exists($varValue, 'getId')) {
                $newName = $name . 'Id';
                $newValue = $varValue->getId();
            } else {
                throw new \InvalidArgumentException(sprintf('Cannot convert %s = %s to scalar value',
                    $name,
                    \is_object($varValue) ? \get_class($varValue) : \gettype($varValue)
                ));
            }

            if ($arrayAccess) {
                $bsonCollection[$newName] = $newValue;
            } else {
                $bsonCollection->$newName = $newValue;
            }
        }

        return $bsonCollection;
    }

}
