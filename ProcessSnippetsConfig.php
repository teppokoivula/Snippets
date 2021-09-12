<?php namespace ProcessWire;

/**
 * Module config settings for Process Snippets
 */
class ProcessSnippetsConfig extends ModuleConfig {

	/**
	 * Constructor
	 */
	public function __construct() {
		$themes = array_map('basename', glob(__DIR__ . "/codemirror/theme/*.css"));
		$this->add([
			[
				'name' => 'snippet_editor',
				'type' => 'fieldset',
				'icon' => 'code',
				'label' => 'Snippet Editor',
				'children' => [
					[
						'name' => 'enable_codemirror',
						'type' => 'checkbox',
						'label' => 'Enable CodeMirror',
						'value' => true,
					],
					[
						'name' => 'codemirror_theme',
						'type' => 'select',
						'label' => 'CodeMirror Theme',
						'showIf' => 'enable_codemirror=1',
						'options' => array_combine($themes, $themes),
					],
				],
			],
		]);
	}
}
