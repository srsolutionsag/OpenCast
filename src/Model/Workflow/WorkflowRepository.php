<?php

declare(strict_types=1);

namespace srag\Plugins\Opencast\Model\Workflow;

/**
 * Class WorkflowRepository
 *
 * @package srag\Plugins\Opencast\Model\Config\Workflow
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
interface WorkflowRepository
{
    public function anyWorkflowExists(): bool;

    /**
     * @return WorkflowAR[]
     */
    public function getAllWorkflows(bool $as_array): array;

    public function getAllWorkflowsAsArray($key = null, $values = null): array;

    public function store(
        string $workflow_id,
        string $title,
        string $description,
        string $tags,
        string $config_panel,
        string $config_panel_json,
        int $id = 0
    );

    public function exists(string $workflow_id): bool;

    /**
     * @param $id
     */
    public function delete($id);

    /**
     * @return WorkflowAR|null
     */
    public function getByWorkflowId(string $workflow_id);

    /**
     * @return WorkflowAR|null
     */
    public function getById(int $id);

    /**
     * @throws xoctException
     */
    public function getWorkflowsFromOpencastApi(
        array $filter = [],
        bool $with_configuration_panel = false,
        bool $with_configuration_panel_json = false,
        bool $with_tags = false
    ): array;

    public function createOrUpdate(
        string $workflow_id,
        string $title,
        string $description,
        string $tags = '',
        string $config_panel = '',
        string $config_panel_json = ''
    ): WorkflowAR;

    /**
     * @throws xoctException
     */
    public function parseConfigPanels(): array;

    /**
     * Generates the list of workflow selections in array form of key:value pairr
     */
    public function getWorkflowSelectionArray(): array;

    /**
     * @throws xoctException
     */
    public function buildWorkflowSelectOptions(): string;

    public function getFilteredWorkflowsArray(array $workflows = [], ?string $tags_str = null): array;

    public function resetList(): bool;

    public function updateList(?string $tags_str = null): bool;

    public function getConfigPanelAsArrayById(int $id): array;

    public function getConfigPanelJsonAsArrayById(int $id): array;
}
