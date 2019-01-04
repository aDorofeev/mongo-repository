<?php
/**
 * Author: Anton Dorofeev <anton@dorofeev.me>
 * Created: 14.12.18
 */

namespace Adorofeev\MongoRepository\Repository;


use Adorofeev\MongoRepository\Entity\BaseEntity;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use MongoDB\InsertOneResult;
use MongoDB\UpdateResult;

abstract class BaseMongoRepository
{
    /** @var Database */
    protected $mongo;

    /**
     * BaseMongoRepository constructor.
     * @param Database $mongo
     */
    public function __construct(Database $mongo)
    {
        $this->mongo = $mongo;
    }

    /**
     * @return Collection
     * @throws \JsonMapper_Exception
     */
    abstract protected function getCollection(): Collection;

    /**
     * @param BSONDocument $document
     * @return BaseEntity
     */
    abstract protected function wakeUp(BSONDocument $document): ?BaseEntity;

    /**
     * @param BSONDocument[] $documentList
     * @throws \JsonMapper_Exception
     * @return array
     */
    protected function wakeUpAll(iterable $documentList): array
    {
        $result = [];
        foreach ($documentList as $document) {
            $result[] = $this->wakeUp($document);
        }

        return $result;
    }

    protected function deepMergeBson($left, $right)
    {
        foreach ($left as $key => $leftValue) {
            $rightValue = $right->$key;
            if (is_scalar($leftValue) && is_scalar($rightValue)) {
                $left->$key = $rightValue;
            } elseif (($leftValue instanceof BSONDocument) && ($rightValue instanceof BSONDocument)) {
                $left->$key = $this->deepMergeBson($leftValue, $rightValue);
            } elseif ($leftValue instanceof BSONArray && $rightValue instanceof BSONArray) {
                $left->$key = $rightValue;
            } elseif (null === $leftValue && null === $rightValue) {
                $left->$key = null;
            } elseif (null === $leftValue) {
                $left->$key = $rightValue;
            } elseif (null === $rightValue) {
                $left->$key = $leftValue;
            } else {
                dump($leftValue, $rightValue); die;
            }
        }

        return $left;
    }

    /**
     * @param string $id
     * @throws \JsonMapper_Exception
     * @throws ApiException
     * @throws InvalidArgumentException
     * @return BaseEntity|null
     */
    public function getById(string $id): ?BaseEntity
    {
        /** @var BSONDocument $result */
        $result = $this->getCollection()->findOne(['_id' => new ObjectId($id)]);

        return $this->wakeUp($result);
    }

    /**
     * @param array $filter
     * @param array $options
     * @return BaseEntity[]
     * @throws \JsonMapper_Exception
     */
    public function getListByFilter(array $filter, array $options = []): array
    {
        /** @var BSONDocument[] $result */
        $result = $this->getCollection()->find($filter, $options);

        return $this->wakeUpAll($result);
    }

    public function save(BaseEntity $entity): BaseEntity
    {
        if ($entity->getId()) {
            $oldDocument = $this->getById($entity->getId());
            $oldDocumentBson = $oldDocument
                ? $oldDocument->toSerializable(BSONDocument::class, BSONArray::class)
                : null;
        } else {
            $oldDocumentBson = null;
            $entity->setId((string) new ObjectId());
        }
        $newDocumentBson = $entity->toSerializable(BSONDocument::class, BSONArray::class);

        if ($oldDocumentBson) {
            /** @var BSONDocument $mergedBson */
            $mergedBson = $this->deepMergeBson($oldDocumentBson, $newDocumentBson);

            /** @var UpdateResult $response */
            $response = $this->getCollection()->replaceOne(['_id' => new ObjectId($entity->getId())], $mergedBson);

            return $this->wakeUp($mergedBson);
        } else {
            if (!$entity->getId()) {
                $entity->setId((string)new ObjectId());
            }
            /** @var InsertOneResult $response */
            $response = $this->getCollection()->insertOne($entity->toSerializable(BSONDocument::class, BSONArray::class));
            $entity->setId((string) $response->getInsertedId());

            return $entity;
        }
    }
}
