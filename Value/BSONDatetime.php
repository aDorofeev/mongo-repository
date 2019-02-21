<?php

namespace Adorofeev\MongoRepository\Value;

use DateTimeZone;
use MongoDB\BSON\UTCDateTime;

class BSONDatetime extends \DateTime
{
    public function __construct(string $time = 'now', DateTimeZone $timezone = null)
    {
        if (is_numeric($time)) {
            $time = '@' . $time;
        }

        parent::__construct($time, $timezone);

        if ((\is_string($time) && $time === '') || (\is_int($time) && $time === 0)) {
            $this->setTimestamp(0);
        }
    }

    /**
     * @param null|\DateTime $dateTime
     *
     * @return self|null
     * @throws \Exception
     */
    public static function fromDateTime(?\DateTime $dateTime): ?self
    {
        if (!$dateTime) {
            return null;
        }

        if ($dateTime instanceof self) {
            return $dateTime;
        }

        $instance = new static();
        $instance
          ->setTimestamp($dateTime->getTimestamp())
          ->setTimezone($dateTime->getTimezone())
        ;

        return $instance;
    }

    public static function fromUTCDatetime(UTCDateTime $mongoValue)
    {
        return self::fromDateTime($mongoValue->toDateTime());
    }

    public function toSerializable()
    {
        return new UTCDateTime($this);
    }
}