<?php

namespace Oro\Bundle\StripeBundle\Model;

/**
 * Implements basic methods to store response data which contains collection of items.
 */
abstract class AbstractCollectionResponse implements CollectionResponseInterface
{
    protected array $data;
    protected ?array $items = null;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->getItems());
    }

    protected function getItems(): array
    {
        if (null === $this->items) {
            $this->items = [];

            if (isset($this->data['data'])) {
                foreach ($this->data['data'] as $responseData) {
                    $responseItem = $this->createItem($responseData);
                    $this->items[] = $responseItem;
                }
            }
        }

        return $this->items;
    }

    abstract protected function createItem(array $item);
}
