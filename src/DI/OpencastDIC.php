<?php

declare(strict_types=1);

namespace srag\Plugins\Opencast\DI;

use ILIAS\DI\Container as DIC;
use ILIAS\UI\Component\Input\Field\UploadHandler;
use ilOpenCastPlugin;
use Pimple\Container;
use srag\Plugins\Opencast\Model\ACL\ACLParser;
use srag\Plugins\Opencast\Model\ACL\ACLUtils;
use srag\Plugins\Opencast\Model\Agent\AgentApiRepository;
use srag\Plugins\Opencast\Model\Agent\AgentParser;
use srag\Plugins\Opencast\Model\Event\EventAPIRepository;
use srag\Plugins\Opencast\Model\Event\EventParser;
use srag\Plugins\Opencast\Model\Metadata\Config\Event\MDFieldConfigEventRepository;
use srag\Plugins\Opencast\Model\Metadata\Config\Series\MDFieldConfigSeriesRepository;
use srag\Plugins\Opencast\Model\Metadata\Definition\MDCatalogueFactory;
use srag\Plugins\Opencast\Model\Metadata\Helper\MDParser;
use srag\Plugins\Opencast\Model\Metadata\Helper\MDPrefiller;
use srag\Plugins\Opencast\Model\Metadata\MetadataFactory;
use srag\Plugins\Opencast\Model\Metadata\MetadataService;
use srag\Plugins\Opencast\Model\Object\ObjectSettingsParser;
use srag\Plugins\Opencast\Model\Publication\Config\PublicationUsageRepository;
use srag\Plugins\Opencast\Model\Publication\PublicationRepository;
use srag\Plugins\Opencast\Model\Scheduling\SchedulingParser;
use srag\Plugins\Opencast\Model\Series\SeriesAPIRepository;
use srag\Plugins\Opencast\Model\Series\SeriesParser;
use srag\Plugins\Opencast\Model\Workflow\WorkflowDBRepository;
use srag\Plugins\Opencast\Model\Workflow\WorkflowRepository;
use srag\Plugins\Opencast\Model\WorkflowParameter\Config\WorkflowParameterRepository;
use srag\Plugins\Opencast\Model\WorkflowParameter\Series\SeriesWorkflowParameterRepository;
use srag\Plugins\Opencast\Model\WorkflowParameter\WorkflowParameterParser;
use srag\Plugins\Opencast\UI\EventFormBuilder;
use srag\Plugins\Opencast\UI\EventTableBuilder;
use srag\Plugins\Opencast\UI\Metadata\MDFormItemBuilder;
use srag\Plugins\Opencast\UI\ObjectSettings\ObjectSettingsFormItemBuilder;
use srag\Plugins\Opencast\UI\PaellaConfig\PaellaConfigFormBuilder;
use srag\Plugins\Opencast\UI\Scheduling\SchedulingFormItemBuilder;
use srag\Plugins\Opencast\UI\SeriesFormBuilder;
use srag\Plugins\Opencast\UI\SubtitleConfig\SubtitleConfigFormBuilder;
use srag\Plugins\Opencast\Util\FileTransfer\OpencastIngestService;
use srag\Plugins\Opencast\Util\FileTransfer\PaellaConfigStorageService;
use srag\Plugins\Opencast\Util\FileTransfer\UploadStorageService;
use srag\Plugins\Opencast\Util\Player\PaellaConfigServiceFactory;
use xoctFileUploadHandlerGUI;
use srag\Plugins\Opencast\UI\ThumbnailConfig\ThumbnailConfigFormBuilder;
use srag\Plugins\Opencast\Container\Init;

/**
 * @deperecated use srag\Plugins\Opencast\Container\Container instead
 */
class OpencastDIC
{
    protected static ?self $instance = null;

    /**
     * @deperecated use srag\Plugins\Opencast\Container\Container instead
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private \Pimple\Container $container;
    /**
     * @var DIC
     */
    private $dic;

    private function __construct()
    {
        global $DIC;
        $this->container = new Container();
        $this->dic = $DIC;
        $this->init();
    }

    private function init(): void
    {
        $this->container['event_parser'] = $this->container->factory(
            fn ($c): EventParser => new EventParser(
                $c['md_parser'],
                $c['acl_parser'],
                $c['scheduling_parser']
            )
        );
        $this->container['acl_utils'] = $this->container->factory(
            fn ($c): ACLUtils => new ACLUtils()
        );

        $this->container['ingest_service'] = $this->container->factory(
            fn ($c): OpencastIngestService => new OpencastIngestService($c['upload_storage_service'])
        );
        $this->container['publication_usage_repository'] = $this->container->factory(
            fn ($c): PublicationUsageRepository => new PublicationUsageRepository()
        );
        $this->container['upload_storage_service'] = $this->container->factory(
            fn ($c): UploadStorageService => new UploadStorageService($this->dic->filesystem()->temp(), $this->dic->upload())
        );
        $this->container['upload_handler'] = $this->container->factory(fn ($c): \xoctFileUploadHandlerGUI => new xoctFileUploadHandlerGUI($c['upload_storage_service']));
        $this->container['paella_config_storage_service'] = $this->container->factory(
            fn ($c): PaellaConfigStorageService => new PaellaConfigStorageService($this->dic->filesystem()->web(), $this->dic->upload())
        );
        $this->container['paella_config_upload_handler'] = $this->container->factory(
            fn ($c): \xoctFileUploadHandlerGUI => new xoctFileUploadHandlerGUI($c['paella_config_storage_service'])
        );
        $this->container['agent_repository'] = $this->container->factory(
            fn ($c): AgentApiRepository => new AgentApiRepository($c['agent_parser'])
        );
        $this->container['agent_parser'] = $this->container->factory(
            fn ($c): AgentParser => new AgentParser()
        );
        $this->container['md_parser'] = $this->container->factory(
            fn ($c): MDParser => new MDParser(
                $c['md_catalogue_factory'],
                $c['md_factory']
            )
        );
        $this->container['md_catalogue_factory'] = $this->container->factory(
            fn ($c): MDCatalogueFactory => new MDCatalogueFactory()
        );
        $this->container['md_factory'] = $this->container->factory(
            fn ($c): MetadataFactory => new MetadataFactory($c['md_catalogue_factory'])
        );
        $this->container['md_prefiller'] = $this->container->factory(
            fn ($c): MDPrefiller => new MDPrefiller($this->dic)
        );
        $this->container['md_conf_repository_event'] = $this->container->factory(
            fn ($c): MDFieldConfigEventRepository => new MDFieldConfigEventRepository($c['md_catalogue_factory'])
        );
        $this->container['md_conf_repository_series'] = $this->container->factory(
            fn ($c): MDFieldConfigSeriesRepository => new MDFieldConfigSeriesRepository($c['md_catalogue_factory'])
        );
        $this->container['md_form_item_builder_event'] = $this->container->factory(
            fn ($c): MDFormItemBuilder => new MDFormItemBuilder(
                $c['md_catalogue_factory']->event(),
                $c['md_conf_repository_event'],
                $c['md_prefiller'],
                $this->dic->ui()->factory(),
                $this->dic->refinery(),
                $c['md_parser'],
                $c['plugin'],
                $this->dic
            )
        );
        $this->container['md_form_item_builder_series'] = $this->container->factory(
            fn ($c): MDFormItemBuilder => new MDFormItemBuilder(
                $c['md_catalogue_factory']->series(),
                $c['md_conf_repository_series'],
                $c['md_prefiller'],
                $this->dic->ui()->factory(),
                $this->dic->refinery(),
                $c['md_parser'],
                $c['plugin'],
                $this->dic
            )
        );
        $this->container['workflow_repository'] = $this->container->factory(
            fn ($c): WorkflowDBRepository => new WorkflowDBRepository()
        );
        $this->container['workflow_parameter_conf_repository'] = $this->container->factory(
            fn ($c): WorkflowParameterRepository => new WorkflowParameterRepository($c['workflow_parameter_series_repository'])
        );
        $this->container['workflow_parameter_series_repository'] = $this->container->factory(
            fn ($c): SeriesWorkflowParameterRepository => new SeriesWorkflowParameterRepository(
                $c['workflow_parameter_parser'],
                $this->dic->ui()->factory(),
                $this->dic->refinery()
            )
        );
        $this->container['workflow_parameter_parser'] = $this->container->factory(
            fn ($c): WorkflowParameterParser => new WorkflowParameterParser()
        );
        $this->container['scheduling_parser'] = $this->container->factory(
            fn ($c): SchedulingParser => new SchedulingParser()
        );
        $this->container['scheduling_form_item_builder'] = $this->container->factory(
            fn ($c): SchedulingFormItemBuilder => new SchedulingFormItemBuilder(
                $this->dic->ui()->factory(),
                $this->dic->refinery(),
                $c['scheduling_parser'],
                $c['plugin'],
                $c['agent_repository']
            )
        );
        $this->container['event_form_builder'] = $this->container->factory(
            function (Container $c): EventFormBuilder {
                $opencastContainer = Init::init();
                return new EventFormBuilder(
                    $this->dic->ui()->factory(),
                    $this->dic->refinery(),
                    $c['md_form_item_builder_event'],
                    $c['workflow_parameter_series_repository'],
                    $c['upload_storage_service'],
                    $c['upload_handler'],
                    $c['plugin'],
                    $c['scheduling_form_item_builder'],
                    $opencastContainer->get(SeriesAPIRepository::class),
                    $this->dic
                );
            }
        );
        $this->container['event_table_builder'] = $this->container->factory(
            function (Container $c): EventTableBuilder {
                $opencastContainer = Init::init();
                return new EventTableBuilder(
                    $c['md_conf_repository_event'],
                    $c['md_catalogue_factory'],
                    $opencastContainer->get(EventAPIRepository::class),
                    $this->dic
                );
            }
        );
        $this->container['series_form_builder'] = $this->container->factory(
            function (Container $c): SeriesFormBuilder {
                $opencastContainer = Init::init();
                return new SeriesFormBuilder(
                    $this->dic->ui()->factory(),
                    $this->dic->refinery(),
                    $c['md_form_item_builder_series'],
                    $c['object_settings_form_item_builder'],
                    $opencastContainer->get(SeriesAPIRepository::class),
                    $c['plugin'],
                    $this->dic
                );
            }
        );
        $this->container['object_settings_parser'] = $this->container->factory(
            fn ($c): ObjectSettingsParser => new ObjectSettingsParser()
        );
        $this->container['object_settings_form_item_builder'] = $this->container->factory(
            fn ($c): ObjectSettingsFormItemBuilder => new ObjectSettingsFormItemBuilder(
                $this->dic->ui()->factory(),
                $this->dic->refinery(),
                $c['publication_usage_repository'],
                $c['object_settings_parser'],
                $c['paella_config_upload_handler'],
                $c['plugin']
            )
        );
        $this->container['plugin'] = $this->container->factory(fn ($c): \ilOpenCastPlugin => ilOpenCastPlugin::getInstance());

        $this->container['series_parser'] = $this->container->factory(
            fn ($c): SeriesParser => new SeriesParser($c['acl_parser'])
        );
        $this->container['acl_parser'] = $this->container->factory(
            fn ($c): ACLParser => new ACLParser()
        );
        $this->container['paella_config_service_factory'] = $this->container->factory(
            fn ($c): PaellaConfigServiceFactory => new PaellaConfigServiceFactory($c['paella_config_storage_service'])
        );
        $this->container['paella_config_form_builder'] = $this->container->factory(
            fn ($c): PaellaConfigFormBuilder => new PaellaConfigFormBuilder(
                $c['plugin'],
                $c['paella_config_upload_handler'],
                $c['paella_config_storage_service'],
                $this->dic->ui()->factory(),
                $this->dic->ui()->renderer()
            )
        );
        $this->container['subtitle_config_form_builder'] = $this->container->factory(
            fn ($c): SubtitleConfigFormBuilder => new SubtitleConfigFormBuilder(
                $c['plugin'],
                $this->dic->ui()->factory(),
                $this->dic->ui()->renderer()
            )
        );
        $this->container['thumbnail_config_form_builder'] = $this->container->factory(
            fn ($c): ThumbnailConfigFormBuilder => new ThumbnailConfigFormBuilder(
                $this->dic->ui()->factory(),
                $this->dic->ui()->renderer()
            )
        );
    }

    public function ingest_service(): OpencastIngestService
    {
        return $this->container['ingest_service'];
    }

    public function publication_repository(): PublicationRepository
    {
        return $this->container['publication_repository'];
    }

    public function upload_storage_service(): UploadStorageService
    {
        return $this->container['upload_storage_service'];
    }

    public function upload_handler(): UploadHandler
    {
        return $this->container['upload_handler'];
    }

    public function paella_config_storage_service(): PaellaConfigStorageService
    {
        return $this->container['paella_config_storage_service'];
    }

    public function paella_config_upload_handler(): UploadHandler
    {
        return $this->container['paella_config_upload_handler'];
    }

    public function event_form_builder(): EventFormBuilder
    {
        return $this->container['event_form_builder'];
    }

    public function event_table_builder(): EventTableBuilder
    {
        return $this->container['event_table_builder'];
    }

    public function series_form_builder(): SeriesFormBuilder
    {
        return $this->container['series_form_builder'];
    }

    public function workflow_parameter_conf_repository(): WorkflowParameterRepository
    {
        return $this->container['workflow_parameter_conf_repository'];
    }

    public function workflow_parameter_series_repository(): SeriesWorkflowParameterRepository
    {
        return $this->container['workflow_parameter_series_repository'];
    }

    public function workflow_parameter_parser(): WorkflowParameterParser
    {
        return $this->container['workflow_parameter_parser'];
    }

    public function metadata(): MetadataService
    {
        return new MetadataService($this->container);
    }

    public function acl_utils(): ACLUtils
    {
        return $this->container['acl_utils'];
    }

    public function workflow_repository(): WorkflowRepository
    {
        return $this->container['workflow_repository'];
    }

    public function paella_config_service_factory(): PaellaConfigServiceFactory
    {
        return $this->container['paella_config_service_factory'];
    }

    public function paella_config_form_builder(): PaellaConfigFormBuilder
    {
        return $this->container['paella_config_form_builder'];
    }

    public function subtitle_config_form_builder(): SubtitleConfigFormBuilder
    {
        return $this->container['subtitle_config_form_builder'];
    }
    public function thumbnail_config_form_builder(): ThumbnailConfigFormBuilder
    {
        return $this->container['thumbnail_config_form_builder'];
    }

    public function overwriteService(string $service_identifier, $value): void
    {
        $this->container[$service_identifier] = $value;
    }

    public function plugin(): ilOpenCastPlugin
    {
        return $this->container['plugin'];
    }

    public function get(string $service_identifier)
    {
        return $this->container[$service_identifier];
    }
}
