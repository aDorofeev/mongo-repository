<?php
/**
 * Author: Anton Dorofeev <anton@dorofeev.me>
 * Created: 28.12.18
 */

namespace Adorofeev\MongoRepository\Entity;


use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;

abstract class BaseEntity implements \JsonSerializable
{
    public function getId()
    {
        return spl_object_hash($this);
    }

    use ToSerializable;

    /**
     * @param  mixed $value
     * @return bool|null
     */
    protected function _toBoolean($value): ?bool
    {
        if (\in_array($value, [true, 'true', 1, '1'], true)) {
            return true;
        }
        if (\in_array($value, [false, 'false', 0, '0'], true)) {
            return false;
        }

        return null;
    }

    /**
     * @param BSONDocument|BSONArray|array $BSONDocument
     * @return mixed
     */
    protected static function bsonToJson($BSONDocument)
    {
        $jsonDocument = is_array($BSONDocument) ? $BSONDocument : $BSONDocument->jsonSerialize();
        foreach ($jsonDocument as $key => $value) {
            if ($value instanceof BSONDocument || $value instanceof BSONArray || is_array($value)) {
                $newValue = self::bsonToJson($value);
            } elseif ($value instanceof ObjectId) {
                $newValue = (string) $value;
                if ($key === '_id') {
                    $key = 'id';
                }
                unset($jsonDocument->_id);
            } elseif (is_scalar($value) || $value === null) {
                continue;
            } else {
                throw new \LogicException(sprintf(
                    'Value wasn\'t converted: %s = %s',
                    $key,
                    var_export($value, true)
                ));
            }

            if ($BSONDocument instanceof BSONDocument) {
                if (null !== $newValue || null === $value) {
                    $jsonDocument->$key = $newValue;
                }
            } elseif ($BSONDocument instanceof BSONArray) {
                if (null !== $newValue || null === $value) {
                    $jsonDocument[$key] = $newValue;
                }
            } else {
                throw new \LogicException('Value wasn\'t set');
            }
        }

        return $jsonDocument;
    }

    /**
     * @param $mongoData
     * @return self
     * @throws \JsonMapper_Exception
     */
    public static function fromBson(BSONDocument $mongoData): self
    {
        return self::fromJson(self::bsonToJson($mongoData));
    }

    /**
     * @param \stdClass $data
     * @return self
     * @throws \JsonMapper_Exception
     */
    public static function fromJson(\stdClass $data): self
    {
        $instance = new static();

        $mapper = new \JsonMapper();
        $mapper->map($data, $instance);

        return $instance;
    }

    /**
     * @param null $value
     * @param array $traversed
     * @throws \InvalidArgumentException
     * @return \stdClass|array
     */
    public function toJson($emptyObject = \stdClass::class, $emptyCollection = [], $value = null, array &$traversed = [], $serializerMethodName = null)
    {
        $serializableData = $this->toSerializable(\stdClass::class, [], $value ?: $this, $traversed, __FUNCTION__);
        foreach ($serializableData as $key => $arrayValue) {
            if ($arrayValue === null) {
                unset($serializableData[$key]);
            }
        }

        return $serializableData;
    }

    public function jsonSerialize()
    {
        return $this->toJson();
    }

}
