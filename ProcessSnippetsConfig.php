<?php namespace ProcessWire;

class ProcessSnippetsConfig extends ModuleConfig {
    public function __construct() {
        $themes = array_map('basename', glob(__DIR__ . "/codemirror/theme/*.css"));
        $this->add(array(
            array(
                'name' => 'snippet_editor',
                'type' => 'fieldset',
                'icon' => 'code',
                'label' => 'Snippet Editor',
                'children' => array(
                    array(
                        'name' => 'enable_codemirror',
                        'type' => 'checkbox',
                        'label' => 'Enable CodeMirror',
                        'value' => true,
                    ),
                    array(
                        'name' => 'codemirror_theme',
                        'type' => 'select',
                        'label' => 'CodeMirror Theme',
                        'showIf' => 'enable_codemirror=1',
                        'options' => array_combine($themes, $themes),
                    ),
                ),
            ),
        ));
    }
}
