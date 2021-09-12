<?php namespace ProcessWire;

/**
 * Module config settings for Snippets
 */
class SnippetsConfig extends ModuleConfig {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->add([
			[
				'name' => 'hanna_code',
				'type' => 'fieldset',
				'icon' => 'sun-o',
				'label' => 'Hanna Code',
				'children' => [
					[
						'name' => 'enable_hanna_code',
						'type' => 'checkbox',
						'label' => 'Enable Hanna Code',
						'description' => 'If this setting is enabled, Hanna Code tags can be used within snippets.',
						'value' => false,
						'disabled' => true,
					],
				],
			],
		]);
	}
}
