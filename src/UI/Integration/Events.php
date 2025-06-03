<?php

declare(strict_types=1);

namespace srag\Plugins\Opencast\UI\Integration;

use srag\Plugins\Opencast\Container\Container;
use srag\Plugins\Opencast\Model\Event\EventAPIRepository;
use srag\Plugins\Opencast\Model\Event\Event;
use ILIAS\UI\Component\Item\Item;
use srag\Plugins\Opencast\Model\Series\SeriesAPIRepository;
use ILIAS\UI\Component\Panel\Panel;
use ILIAS\UI\Component\Button\Standard;
use ILIAS\UI\Component\Listing\Entity\RecordToEntity;
use ILIAS\UI\Component\Entity\Entity;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Component\Button\Tag;

/**
 * @author Fabian Schmid <fabian@sr.solutions>
 * @internal
 */
class Events implements RecordToEntity
{
    use Commons;

    private EventAPIRepository $event_repository;
    private SeriesAPIRepository $series_repository;

    public function __construct(
        private \ILIAS\UI\Factory $ui_factory,
        private Container $container
    ) {
        $this->event_repository = $this->container->get(EventAPIRepository::class);
        $this->series_repository = $this->container->get(SeriesAPIRepository::class);
    }

    public function map(UIFactory $ui_factory, mixed $record): Entity
    {
        $record = $this->event_repository->find($record['identifier'] ?? '');
        if (!$record instanceof Event) {
            throw new \InvalidArgumentException(
                "Record must be an instance of " . Event::class . ", " . get_class($record) . " given."
            );
        }

        return $this->ui_factory->entity()->standard(
            $record->getTitle(),
            $this->ui_factory->image()->responsive(
                $record->publications()->getThumbnailUrl(),
                'src'
            )
        )->withMainDetails(
            $this->ui_factory->listing()->property()->withProperty('Description', $record->getDescription()),
            $this->ui_factory->listing()->property()->withProperty('Speaker', implode(", ", $record->getPresenter())),
        )->withReactions(
            $this->ui_factory->button()->tag(
                'Play',
                "#"
            )->withRelevance(Tag::REL_VERYHIGH),
            $this->ui_factory->button()->tag(
                'Download',
                "#"
            )->withRelevance(Tag::REL_VERYLOW),
        )->withActions(
            $this->ui_factory->button()->shy(
                'Play',
                "#"
            ),
            $this->ui_factory->button()->shy(
                'Download',
                "#"
            ),
        )->withFeaturedProperties(
            $this->ui_factory->listing()->property()->withProperty('Date', $record->getStart()->format('d.m.Y H:i')),
        );
    }

    public function asItemFromEventId(
        string $event_id,
        ?Standard $main_action = null,
        ?array $additional_actions = null,
        ?string $surround_with_panel = null
    ): Item|Panel {
        $event = $this->event_repository->find($event_id);
        $item = $this->asItem($event, $main_action, $additional_actions);

        if ($surround_with_panel === null) {
            return $item;
        }

        return $this->ui_factory->panel()->standard($surround_with_panel, $item);
    }


    public function asItem(
        Event $event,
        ?Standard $main_action = null,
        ?array $additional_actions = null
    ): Item {
        $item = $this->ui_factory
            ->item()
            ->standard(
                $event->getTitle(),
            );

        $lead_image = $this->ui_factory->image()->responsive(
            $event->publications()->getThumbnailUrl(),
            'src'
        );

        if ($main_action !== null) {
            $item = $item->withMainAction($main_action);
            $lead_image = $lead_image->withAction($main_action->getAction());
        }

        if (!empty($additional_actions)) {
            $item = $item->withActions(
                $this->ui_factory->dropdown()->standard($additional_actions)
            );
        }

        $item = $item->withProperties([
            $this->translate("event_date") => $event->getStart()->format('d.m.Y H:i'),
            $this->translate("event_series") => $this->getSeriesName($event),
            $this->translate("event_presenter") => implode(", ", $event->getPresenter()),
        ])->withLeadImage(
            $lead_image
        );

        return $item;
    }
}
