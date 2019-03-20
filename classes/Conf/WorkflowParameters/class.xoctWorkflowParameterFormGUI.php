<?php

use \srag\CustomInputGUIs\OpenCast\PropertyFormGUI\PropertyFormGUI;

/**
 * Class xoctWorkflowParameterFormGUI
 *
 * @author Theodor Truffer <tt@studer-raimann.ch>
 */
class xoctWorkflowParameterFormGUI extends PropertyFormGUI {

	const PLUGIN_CLASS_NAME = ilOpenCastPlugin::class;

	const PROPERTY_TITLE = 'setTitle';
	const PROPERTY_INFO = 'setInfo';

	const F_OVERWRITE_SERIES_PARAMS = 'overwrite_series_params';

	/**
	 * @var bool
	 */
	protected $overwrite_series_params = false;

	/**
	 * @param string $key
	 *
	 * @return mixed|void
	 */
	protected function getValue($key) {

	}


	/**
	 *
	 */
	protected function initCommands() {
		$this->addCommandButton(xoctWorkflowParameterGUI::CMD_UPDATE, $this->lng->txt('save'));
	}


	/**
	 * @throws \srag\DIC\OpenCast\Exception\DICException
	 */
	protected function initFields() {
		$this->fields[] = [
			self::PROPERTY_CLASS => ilFormSectionHeaderGUI::class,
			self::PROPERTY_TITLE => self::plugin()->translate('workflow_parameters'),
		];
		/** @var xoctWorkflowParameter $xoctWorkflowParameter */
		foreach (xoctWorkflowParameter::get() as $xoctWorkflowParameter) {
			$this->fields[$xoctWorkflowParameter->getId()] = [
				self::PROPERTY_CLASS => ilSelectInputGUI::class,
				self::PROPERTY_TITLE => $xoctWorkflowParameter->getTitle() ?: $xoctWorkflowParameter->getId(),
				self::PROPERTY_OPTIONS => [
					xoctWorkflowParameter::VALUE_IGNORE => self::plugin()->translate('workflow_parameter_value_' . xoctWorkflowParameter::VALUE_IGNORE, 'config'),
					xoctWorkflowParameter::VALUE_SET_AUTOMATICALLY => self::plugin()->translate('workflow_parameter_value_' . xoctWorkflowParameter::VALUE_SET_AUTOMATICALLY, 'config'),
					xoctWorkflowParameter::VALUE_SHOW_IN_FORM => self::plugin()->translate('workflow_parameter_value_' . xoctWorkflowParameter::VALUE_SHOW_IN_FORM, 'config')
				],
				self::PROPERTY_VALUE => $xoctWorkflowParameter->getDefaultValue(),
			];
		}
		$this->fields[] = [
			self::PROPERTY_CLASS => ilFormSectionHeaderGUI::class,
			self::PROPERTY_TITLE => self::plugin()->translate('settings', 'tab'),
		];
		$this->fields[xoctConf::F_ALLOW_WORKFLOW_PARAMS_IN_SERIES] = [
			self::PROPERTY_TITLE => self::plugin()->translate(xoctConf::F_ALLOW_WORKFLOW_PARAMS_IN_SERIES, 'config'),
			self::PROPERTY_CLASS => ilCheckboxInputGUI::class,
			self::PROPERTY_VALUE => (bool) xoctConf::getConfig(xoctConf::F_ALLOW_WORKFLOW_PARAMS_IN_SERIES),
			self::PROPERTY_SUBITEMS => [
				self::F_OVERWRITE_SERIES_PARAMS => [
					self::PROPERTY_TITLE => self::plugin()->translate(self::F_OVERWRITE_SERIES_PARAMS, 'config'),
					self::PROPERTY_INFO => self::plugin()->translate(self::F_OVERWRITE_SERIES_PARAMS . '_info', 'config'),
					self::PROPERTY_CLASS => ilCheckboxInputGUI::class,
				]
			]
		];
	}


	/**
	 *
	 */
	protected function initId() {
		// TODO: Implement initId() method.
	}


	/**
	 *
	 */
	protected function initTitle() {
		// TODO: Implement initTitle() method.
	}


	/**
	 * @param string $key
	 * @param mixed  $value
	 */
	protected function storeValue($key, $value) {
		switch ($key) {
			case xoctConf::F_ALLOW_WORKFLOW_PARAMS_IN_SERIES:
				xoctConf::set(xoctConf::F_ALLOW_WORKFLOW_PARAMS_IN_SERIES, $value);
				break;
			case self::F_OVERWRITE_SERIES_PARAMS:
				$this->overwrite_series_params = true;
				break;
			default:
				/** @var xoctWorkflowParameter $xoctWorkflowParameter */
				$xoctWorkflowParameter = xoctWorkflowParameter::find($key);
				$xoctWorkflowParameter->setDefaultValue($value);
				$xoctWorkflowParameter->store();
		}
	}


	public function storeForm() {
		return parent::storeForm();
		// TODO: show confirmation
	}
}