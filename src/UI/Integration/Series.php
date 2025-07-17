<?php

declare(strict_types=1);

namespace srag\Plugins\Opencast\UI\Integration;

use srag\Plugins\Opencast\Container\Container;
use srag\Plugins\Opencast\Model\Event\EventAPIRepository;
use srag\Plugins\Opencast\Model\Series\SeriesAPIRepository;
use ILIAS\UI\Component\Listing\Entity\DataRetrieval;
use ILIAS\UI\Component\Listing\Entity\Mapping;
use ILIAS\Data\Range;
use ILIAS\Data\URI;

/**
 * @author Fabian Schmid <fabian@sr.solutions>
 * @internal
 */
class Series implements DataRetrieval
{
    use Commons;

    private EventAPIRepository $event_repository;
    private SeriesAPIRepository $series_repository;
    private ?\srag\Plugins\Opencast\Model\Series\Series $series = null;

    public function __construct(
        private \ILIAS\UI\Factory $ui_factory,
        private Container $container,
        private Events $events
    ) {
        $this->series_repository = $this->container->get(SeriesAPIRepository::class);
        $this->event_repository = $this->container->get(EventAPIRepository::class);
    }

    public function asEntityList(
        string $series_id,
    ): \ILIAS\UI\Component\Listing\Entity\Standard {
        $series = $this->series_repository->find($series_id);
        if ($series === null) {
            throw new \InvalidArgumentException("Series with ID $series_id not found.");
        }

        $this->series = $series;

        return $this->ui_factory->listing()->entity()->standard($this->events)->withData($this);
    }

    public function asDataTableWithFilters(
        URI $calling_url
    ): array {
        return [
            
        ];
    }

    public function getEntities(Mapping $mapping, ?Range $range, ?array $additional_parameters): \Generator
    {
        foreach ($this->event_repository->getFiltered(['series' => $this->series->getIdentifier()]) as $event) {
            yield $mapping->map($event);
        }
    }

}
