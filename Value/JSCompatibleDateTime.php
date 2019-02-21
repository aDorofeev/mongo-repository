<?php
/**
 * Author: Anton Dorofeev <anton@dorofeev.me>
 * Created: 22.01.18
 */

namespace Adorofeev\MongoRepository\Value;


use DateTimeZone;

class JSCompatibleDateTime extends \DateTime
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
     * @param \DateTime $dateTime
     * @return self
     */
    public static function fromDateTime(?\DateTime $dateTime): self
    {
        if (!$dateTime) {
            return null;
        }

        if ($dateTime instanceof self) {
            return $dateTime;
        }

        $instance = new static();
        $instance->setTimestamp($dateTime->getTimestamp());

        return $instance;
    }

    public function isEmpty(): bool
    {
        return !$this->getTimestamp();
    }

    public function __toString(): string
    {
        return (string) $this->getTimestamp();
    }

}
