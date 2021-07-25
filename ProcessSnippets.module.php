<?php namespace ProcessWire;

/**
 * Snippets Manager
 *
 * This module provides centralized management for snippets.
 *
 * @copyright 2021 Teppo Koivula
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License, version 2
 */
class ProcessSnippets extends Process implements ConfigurableModule {

    /**
     * List of all snippets
     *
     * @var array
     */
    protected $snippets = [];

    /**
     * Render items for admin ajax navigation
     *
     * @param array $options
     * @return string rendered JSON string
     */
    public function executeNavJSON(array $options = array()) {
        $options['items'] = $this->getSnippets();
        $options['itemLabel'] = 'label';
        $options['add'] = 'edit/';
        return parent::___executeNavJSON($options); 
    }

    /**
     * Snippets dashboard; display a list of available snippets.
     *
     * @return string
     */
    public function execute() {

        $out = "";

        // inject settings, translations etc.
        $this->config->js($this->className(), [
            'processPage' => $this->page->url,
        ]);

        // fetch snippets from database
        if (!empty($this->getSnippets())) {
            $table = $this->modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->addClass('snippets');
            $table->headerRow([
                $this->_("Snippet"),
                $this->_("Created"),
                $this->_("Status"),
            ]);
            $label_enabled = $this->_('Enabled');
            $label_disabled = $this->_('Disabled');
            foreach ($this->snippets as $snippet) {
                $table->row([
                    '<div class="snippets__item">'
                    . '<a href="./edit/?id=' . $snippet->id . '" class="snippets__link snippets__link--' . ($snippet->enabled ? 'enabled' : 'disabled') . '"">' . htmlentities($snippet->label) . '</a>'
                    . ($snippet->summary ? '<p class="snippets__summary">' . $snippet->summary . '</p>' : '')
                    . '</div>',
                    $snippet->created,
                    [
                        '<input type="checkbox" class="snippets__toggle" id="snippet-toggle-' . $snippet->id . '" value="' . $snippet->id . '"' . ($snippet->enabled ? ' checked' : '') . '>'
                        . '<label for="snippet-toggle-' . $snippet->id . '"><span class="enabled">' . $label_enabled . '</span><span class="disabled">' . $label_disabled . '</span></label>',
                        'snippets__toggle-container',
                    ],
                ]);
            }
            $out .= $table->render();
        } else {
            $out .= "<p>" . $this->_("You don't have any snippets yet.") . "</p>";
        }

        // new snippet button
        $button = $this->modules->get('InputfieldButton');
        $button->value = $this->_('Add new snippet');
        if (!count($this->snippets)) {
            $button->value = $this->_('Add your first snippet');
        }
        $button->icon = 'plus-circle';
        $button->addClass('head_button_clone');
        $out .= '<a href="./edit/">' . $button->render() . '</a>';

        return '<form class="InputfieldForm">' . $out . '</form>';
    }

    /**
     * Add or edit snippet
     *
     * @return string
     */
    public function executeEdit() {

        $this->headline($this->_('Add new snippet'));
        $snippet = new WireData();

        // attempt to fetch existing snippet
        $snippet_id = (int) $this->input->get->id;
        if ($snippet_id) {
            $stmt = $this->database->prepare('SELECT * FROM ' . Snippets::TABLE_SNIPPETS . ' WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', $snippet_id, \PDO::PARAM_INT);
            if ($stmt->execute()) {
                $snippet = $stmt->fetch(\PDO::FETCH_OBJ);
                if ($snippet === false) {
                    throw new Wire404Exception(sprintf($this->_("Snippet ID %d not recognized"), $snippet_id));
                }
                $this->headline($this->_('Edit snippet'));
            } else {
                $this->session->message(sprintf($this->_("Error occurred while fetching snippet (%d)"), $snippet_id));
                $this->session->redirect('../', false);
            }
        }

        // build the edit form
        $form = new InputfieldForm();
        $form->action = $snippet->id ? "?id={$snippet->id}" : "";
        $snippet_fields = array(
            'label' => array(
                'type' => 'PageTitle',
                'required' => true,
                'label' => $this->_('Label'),
                'description' => $this->_('Labels identify snippets in the management section.'),
                'value' => $snippet->label ?: '',
            ),
            'summary' => array(
                'type' => 'textarea',
                'required' => false,
                'label' => $this->_('Summary'),
                'description' => $this->_('Summary describes the snippet in more detail: what does it do and/or why is it needed?'),
                'value' => $snippet->summary ?: '',
            ),
            'snippet' => array(
                'type' => 'textarea',
                'id' => 'textarea-snippet',
                'rows' => 11,
                'required' => true,
                'label' => $this->_('Snippet'),
                'description' => $this->_('This is the actual snippet that will be embedded into the markup of rendered page.'),
                'notes' => $this->_('You can use values from current page by wrapping them with double curly brackets: `{{page.name}}`.'),
                'value' => $snippet->snippet ?: '',
            ),
            'element' => array(
                'type' => 'radios',
                'required' => true,
                'label' => $this->_('Element'),
                'columnWidth' => 33,
                'options' => array(
                    '/\<head.*?\>/i' => '<head>',
                    '/\<\/head\>/i' => '</head>',
                    '/\<body.*?\>/i' => '<body>',
                    '/\<\/body\>/i' => '</body>',
                    'other' => $this->_('other ...'),
                ),
                'value' => $snippet->element ?: '/\<\/head\>/i',
            ),
            'position' => array(
                'type' => 'radios',
                'required' => true,
                'label' => $this->_('Position'),
                'description' => $this->_('Position compared to the element. Snippet can be placed before or after the element, or it can replace the original element entirely.'),
                'columnWidth' => 33,
                'options' => array(
                    'before' => $this->_('Before'),
                    'after' => $this->_('After'),
                    'replace' => $this->_('Replace'),
                ),
                'value' => $snippet->position ?: 'before',
            ),
            'apply_to' => array(
                'type' => 'radios',
                'required' => true,
                'label' => $this->_('Apply to'),
                'columnWidth' => 33,
                'options' => array(
                    'all' => $this->_('All pages'),
                    'admin' => $this->_('All admin pages'),
                    'non_admin' => $this->_('All non-admin pages'),
                    'page_list' => $this->_('Pages selected from a list'),
                    'selector' => $this->_('Pages matching a selector'),
                ),
                'value' => $snippet->apply_to ?: 'non_admin',
            ),
            'element_regex' => array(
                'type' => 'text',
                'requiredIf' => 'element=other',
                'label' => $this->_('Regular expression'),
                'description' => $this->_('Regular expression matching element in the markup'),
                'showIf' => 'element=other',
                'notes' => $this->_('Use [PCRE](http://www.php.net/manual/en/pcre.pattern.php) syntax including delimiters and modifiers.'),
                'pattern' => '(^[^\w\s\\\]|_).*\1([imsxADSUXJu]*)$',
                'value' => $snippet->element_regex ?: '/\<\/head\>/i',
            ),
            'apply_to_page_list' => array(
                'id' => 'apply_to_page_list',
                'type' => 'PageListSelectMultiple',
                'label' => $this->_('Select pages'),
                'showIf' => 'apply_to=page_list',
                'description' => $this->_('Select applicable pages'),
                'value' => $snippet->apply_to_page_list ?: '',
            ),
            'apply_to_selector' => array(
                'type' => 'selector',
                'label' => $this->_('Selector'),
                'showIf' => 'apply_to=selector',
                'description' => $this->_('Selector for matching pages'),
                'value' => $snippet->apply_to_selector ?: '',
            ),
            'enabled' => array(
                'type' => 'checkbox',
                'checked' => $snippet->enabled ? 'checked' : '',
                'label' => $this->_('Enabled'),
                'label2' => $this->_('This snippet is enabled'),
                'description' => $this->_('Snippets need to be enabled in order to have an effect.'),
            ),
            'remove' => array(
                'type' => $snippet->id ? 'checkbox' : 'hidden',
                'label' => $this->_('Remove'),
                'label2' => $this->_('Remove this snippet'),
                'description' => $this->_('Check this option and save to remove the snippet. Please note that removed snippets are not restorable!'),
                'icon' => 'times-circle',
                'collapsed' => Inputfield::collapsedYes,
            ),
            'submit' => array(
                'type' => 'submit',
                'class' => 'ui-button head_button_clone',
                'icon' => 'save',
                'value' => $this->_('Save'),
            ),
            'cancel' => array(
                'type' => 'button',
                'href' => '../',
                'class' => 'ui-button ui-button-float-right ui-priority-secondary align-right',
                'icon' => 'remove',
                'value' => $this->_('Cancel'),
            ),
        );
        $form->add($snippet_fields);

        // initialize CodeMirror
        if ($this->enable_codemirror) {
            $snippet_editor = $form->get('snippet');
            $snippet_editor->attr('data-codemirror', 1);
            $this->config->scripts->add($this->config->urls->{$this->className()} . 'codemirror/lib/codemirror.js');
            $this->config->scripts->add($this->config->urls->{$this->className()} . 'codemirror/mode/xml/xml.js');
            $this->config->scripts->add($this->config->urls->{$this->className()} . 'codemirror/mode/javascript/javascript.js');
            $this->config->scripts->add($this->config->urls->{$this->className()} . 'codemirror/mode/css/css.js');
            $this->config->scripts->add($this->config->urls->{$this->className()} . 'codemirror/mode/htmlmixed/htmlmixed.js');
            $this->config->styles->add($this->config->urls->{$this->className()} . 'codemirror/lib/codemirror.css');
            if ($this->codemirror_theme) {
                $snippet_editor->attr('data-codemirror-theme', substr($this->codemirror_theme, 0, -4));
                $this->config->styles->add($this->config->urls->{$this->className()} . 'codemirror/theme/' . $this->codemirror_theme);
            }
        }

        // if form submission received ...
        if (count($this->input->post)) {

            // remove the snippet?
            if ($snippet->id && $this->input->post->remove) {
                $stmt = $this->database->prepare("DELETE FROM " . Snippets::TABLE_SNIPPETS . " WHERE id = :id LIMIT 1");
                $stmt->bindValue(':id', $snippet->id, \PDO::PARAM_INT);
                if ($stmt->execute()) {
                    $this->session->message(sprintf($this->_('Snippet removed'), $snippet->id));
                    $this->session->redirect("../", false);
                } else {
                    throw new WireException(sprintf($this->_("Removing snippet failed, please try again later."), $snippet->id));
                }
            }

            // process the edit form
            $form->processInput($this->input->post);
            if (!count($form->getErrors())) {
                $changes = array();
                $keys = array_slice(array_keys($snippet_fields), 0, -3);
                foreach ($keys as $key) {
                    $value = $key == "enabled" ? ($this->input->post->{$key} ? 1 : 0) : $this->input->post->{$key};
                    if (is_array($value)) $value = implode(",", $value);
                    if ($snippet->{$key} != $value || !$snippet->id && $key == "enabled") $changes[] = $key;
                    $snippet->{$key} = $value;
                }
                if (count($changes)) {
                    $stmt = null;
                    if ($snippet->id) {
                        // edit existing snippet
                        $keys[] = 'id';
                        $keys[] = 'modified';
                        $keys[] = 'modified_users_id';
                        $snippet->modified = date('Y-m-d H:i:s');
                        $snippet->modified_users_id = $this->user->id;
                        foreach ($keys as $key) {
                            $stmt_keys_values[] = "{$key} = :{$key}";
                        }
                        $stmt_keys_values = implode(", ", $stmt_keys_values);
                        $stmt = $this->database->prepare("UPDATE " . Snippets::TABLE_SNIPPETS . " SET {$stmt_keys_values} WHERE id = :id LIMIT 1");
                        $stmt->bindValue(":id", $snippet->id, \PDO::PARAM_INT);
                    } else {
                        // add new snippet
                        $keys[] = 'created_users_id';
                        $snippet->created_users_id = $this->user->id;
                        $stmt_keys = implode(", ", $keys);
                        $stmt_values = ":" . str_replace(", ", ", :", $stmt_keys);
                        $stmt = $this->database->prepare("INSERT INTO " . Snippets::TABLE_SNIPPETS . " ({$stmt_keys}) VALUES ({$stmt_values})");
                    }
                    $int_keys = array(
                        'id',
                        'created_users_id',
                        'modified_users_id',
                        'enabled',
                    );
                    foreach ($keys as $key) {
                        $value = $snippet->{$key} ?: ($key == "enabled" ? 0 : null);
                        if (is_array($value)) $value = implode(",", $value);
                        $stmt->bindValue(":{$key}", $value, in_array($key, $int_keys) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
                    }
                    $stmt->execute();
                    if ($snippet->id) {
                        $this->message(sprintf($this->_("Snippet saved (changes: %s)"), implode(", ", $changes)));
                    } else {
                        $snippet->id = $this->database->lastInsertId();
                        $this->session->message($this->_("New snippet added"));
                        $this->session->redirect("./?id={$snippet->id}", false);
                    }
                } else {
                    $this->message($this->_("Snipped saved (no changes)"));
                }
            }
        }

        return $form->render();
    }

    /**
     * Enable or disable snippet
     *
     * @return int 0 if snippet was disabled, 1 if snippet was enabled
     */
    public function executeToggle() {

        // this only applies to POST requests
        if ($_SERVER['REQUEST_METHOD'] !== "POST") return;

        // get and check snippet
        $snippet = null;
        $snippet_id = (int) $this->input->post->id;
        if ($snippet_id) {
            $stmt = $this->database->prepare("SELECT enabled FROM " . Snippets::TABLE_SNIPPETS . " WHERE id = :id LIMIT 1");
            $stmt->bindValue(':id', $snippet_id, \PDO::PARAM_INT);
            $stmt->execute();
            $snippet = $stmt->fetch(\PDO::FETCH_OBJ);
        }
        if (!$snippet) {
            throw new WireException(sprintf($this->_("Snippet doesn't exist: %d"), $snippet_id));
        }

        // toggle snippet status
        $enabled = $this->input->post->enabled ?: ($snippet->enabled ? 0 : 1);
        if ($enabled == 0 || $enabled == 1) {
            $stmt = $this->database->prepare("UPDATE " . Snippets::TABLE_SNIPPETS . " SET enabled = :enabled WHERE id = :id LIMIT 1");
            $stmt->bindValue(':enabled', $enabled, \PDO::PARAM_INT);
            $stmt->bindValue(':id', $snippet_id, \PDO::PARAM_INT);
            $stmt->execute();
        } else {
            throw new WireException($this->_("Unrecognized status received"));
        }

        return $enabled;
    }

    /**
     * Fetch all snippets from database
     *
     * @return array
     */
    protected function getSnippets(): array {
        if (empty($this->snippets)) {
            $query = $this->database->query("SELECT s.id, s.label, s.summary, s.enabled, s.created, u1.name AS 'created_user', s.modified, u2.name AS 'modified_user' FROM " . Snippets::TABLE_SNIPPETS . " s LEFT JOIN pages u1 ON u1.templates_id = 3 AND u1.id = s.created_users_id LEFT JOIN pages u2 ON u2.templates_id = 3 AND u2.id = s.modified_users_id ORDER BY id ASC");
            while ($snippet = $query->fetch(\PDO::FETCH_OBJ)) {
                $this->snippets[] = $snippet;
            }
        }
        return $this->snippets;
    }

}
